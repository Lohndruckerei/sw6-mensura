// Texte: jeder im JS benutzte Schlüssel existiert in de-DE/en-GB, und das Twig-Template reicht genau diese Schlüssel durch.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { join } from 'node:path';
import { loadSnippets, readSource, SRC } from './helpers.mjs';

const { flattenSnippets } = await import(join(SRC, 'util.js'));
const de = flattenSnippets(loadSnippets('de-DE'), 'ldMensura.configurator');
const en = flattenSnippets(loadSnippets('en-GB'), 'ldMensura.configurator');
const TWIG_ONLY = (k) => k.startsWith('buybox.');

function usedKeys() {
    const keys = new Set(['steps.material', 'steps.format', 'steps.delivery']); // dynamisch: steps.${kind}
    for (const file of ['render.js', 'mount.js']) {
        const src = readSource(`src/Resources/app/storefront/src/ld-mensura-configurator/${file}`);
        for (const m of src.matchAll(/\bt(?:\.html)?\(\s*'([a-zA-Z.]+)'/g)) keys.add(m[1]);
        for (const m of src.matchAll(/\?\s*'((?:help|field)\.[a-zA-Z]+)'\s*:\s*'((?:help|field)\.[a-zA-Z]+)'/g)) { keys.add(m[1]); keys.add(m[2]); }
        for (const m of src.matchAll(/'(error\.[a-zA-Z]+)'/g)) keys.add(m[1]);
        for (const m of src.matchAll(/'(price\.(?:gross|net)(?:Short|Label))'/g)) keys.add(m[1]);
    }
    return keys;
}

test('de-DE und en-GB haben dieselben Schlüssel, keiner ist leer', () => {
    assert.deepEqual(Object.keys(de).sort(), Object.keys(en).sort());
    for (const [k, v] of [...Object.entries(de), ...Object.entries(en)]) assert.ok(String(v).trim().length > 0, `leer: ${k}`);
});

test('jeder im JS benutzte Schlüssel ist übersetzt', () => {
    for (const k of usedKeys()) assert.ok(k in de, `fehlt in Snippets: ${k}`);
});

test('Twig reicht genau die Konfigurator-Texte durch (lmTextKeys = Snippets ohne buybox.*)', () => {
    const twig = readSource('src/Resources/views/storefront/component/ld-mensura/configurator.html.twig');
    const block = twig.match(/lmTextKeys = \[([\s\S]*?)\]/)[1];
    const twigKeys = [...block.matchAll(/'([a-zA-Z.]+)'/g)].map((m) => m[1]).sort();
    assert.deepEqual(twigKeys, Object.keys(de).filter((k) => !TWIG_ONLY(k)).sort());
    for (const k of Object.keys(de).filter(TWIG_ONLY)) assert.ok(readSource('src/Resources/views/storefront/component/buy-widget/buy-widget.html.twig').includes(`ldMensura.configurator.${k}`) || twig.includes(k), `ungenutzt: ${k}`);
});

test('jeder in Storefront-Templates übersetzte Mensura-Schlüssel existiert (de-DE und en-GB)', async () => {
    const { readdirSync, statSync } = await import('node:fs');
    const walk = (dir) => readdirSync(dir).flatMap((f) => (statSync(join(dir, f)).isDirectory() ? walk(join(dir, f)) : [join(dir, f)]));
    const { ROOT } = await import('./helpers.mjs');
    const flatDe = flattenSnippets(loadSnippets('de-DE'), 'ldMensura');
    const flatEn = flattenSnippets(loadSnippets('en-GB'), 'ldMensura');
    for (const file of walk(join(ROOT, 'src/Resources/views/storefront')).filter((f) => f.endsWith('.twig'))) {
        const src = readSource(file.slice(ROOT.length + 1));
        for (const m of src.matchAll(/'ldMensura\.([a-zA-Z.]+)'\|trans/g)) {
            assert.ok(m[1] in flatDe, `fehlt in de-DE: ldMensura.${m[1]}`);
            assert.ok(m[1] in flatEn, `fehlt in en-GB: ldMensura.${m[1]}`);
        }
    }
    // Warenkorb-Fehler des Backends (messageKey ld-mensura-configuration-invalid, Parameter name/reason)
    for (const loc of ['de-DE', 'en-GB']) {
        const text = loadSnippets(loc).checkout?.['ld-mensura-configuration-invalid'];
        assert.ok(text && text.includes('%name%') && text.includes('%reason%'), loc);
    }
});

test('Platzhalter stimmen in beiden Sprachen überein', () => {
    const ph = (s) => (String(s).match(/%[a-z]+%/gi) || []).sort().join(',');
    for (const k of Object.keys(de)) assert.equal(ph(de[k]), ph(en[k]), k);
});

test('Hilfetexte mit Beispiel (KONZEPT 3a)', () => {
    for (const [k, v] of Object.entries(de).filter(([key]) => key.startsWith('help.') && key !== 'help.label')) {
        assert.match(v, /z\. B\.|Beispiel/, `Hilfetext ohne Beispiel: ${k}`);
    }
});
