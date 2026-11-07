// Gemeinsame Helfer der Frontend-Tests: Fixtures aus docs/contract laden, Übersetzer/Formatter wie im Shop.
import { readFileSync, readdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

export const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
export const SRC = join(ROOT, 'src/Resources/app/storefront/src/ld-mensura-configurator');
const F = join(ROOT, 'docs/contract/fixtures');

const json = (p) => JSON.parse(readFileSync(p, 'utf8'));

export function loadConfigurators() {
    return readdirSync(join(F, 'configurator')).filter((f) => f.endsWith('.json')).map((f) => ({ name: f.replace(/\.json$/, ''), data: json(join(F, 'configurator', f)) }));
}

export function loadCases() {
    const dir = join(F, 'calculate');
    return readdirSync(dir).filter((f) => f.endsWith('.request.json')).map((f) => {
        const name = f.replace(/\.request\.json$/, '');
        return { name, request: json(join(dir, f)), expected: json(join(dir, `${name}.expected.json`)) };
    });
}

export function loadSnippets(locale) {
    return json(join(ROOT, `src/Resources/snippet/storefront.${locale}.json`));
}

export function loadTestData(name) {
    return json(join(ROOT, 'tests/Frontend/data', `${name}.json`));
}

export const readSource = (rel) => readFileSync(join(ROOT, rel), 'utf8');

/** Samstag, 07.11.2026, 09:00 Uhr – feste Zeit für Liefertermine und Annahmeschluss */
export const NOW = new Date(2026, 10, 7, 9, 0, 0);

export async function makeCtx(locale = 'de-DE') {
    const { createFormatter, createTranslator, flattenSnippets } = await import(join(SRC, 'util.js'));
    const texts = flattenSnippets(loadSnippets(locale), 'ldMensura.configurator');
    return { t: createTranslator(texts), fmt: createFormatter(locale, 'EUR'), uid: 'lmT', now: () => NOW, fixtureMode: true };
}

/** Text ohne Tags und Entities (für Inhaltsprüfungen am gerenderten HTML); geschütztes Leerzeichen → Leerzeichen */
export const textOf = (html) => html
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/\s+/g, ' ');
