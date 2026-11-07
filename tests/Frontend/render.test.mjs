// Rendering beider Varianten und Ablauf Steuerung → Fixture-Client → Darstellung (ohne Shop, ohne DOM).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { join } from 'node:path';
import { loadCases, loadConfigurators, loadTestData, makeCtx, NOW, SRC, textOf } from './helpers.mjs';

const S = await import(join(SRC, 'state.js'));
const R = await import(join(SRC, 'render.js'));
const { FixtureClient, StoreApiClient, addWorkdays } = await import(join(SRC, 'api-client.js'));
const { MensuraController } = await import(join(SRC, 'controller.js'));

const configurators = loadConfigurators().map((c) => c.data);
const alu = configurators[0];
const cases = loadCases();
const byName = Object.fromEntries(cases.map((c) => [c.name, c]));
const ACRYL = '0194e1a0000070008000000000000b02';
const ctx = await makeCtx('de-DE');
const ctxEn = await makeCtx('en-GB');

const client = () => new FixtureClient({ configurators, cases, now: () => NOW });
async function controller(opts = {}) {
    const c = new MensuraController({ data: alu, client: opts.client || client(), debounceMs: 0, ...opts });
    await c.calculateNow();
    return c;
}

test('Fixture-Client: jeder Beispielfall liefert genau sein Sollergebnis', async () => {
    const fc = client();
    for (const c of cases) {
        const res = await fc.calculate(c.request);
        const { delivery, ...rest } = res;
        assert.deepEqual(rest, c.expected, c.name);
        assert.ok(Array.isArray(delivery));
    }
});

test('Fixture-Client: unbekannte Konfiguration → normalisiert, kein Preis (Frontend rechnet nie)', async () => {
    const res = await client().calculate({ ...byName['alu-mindestpreis'].request, perMotif: 3 });
    assert.equal(res.price, undefined);
    assert.ok(res.hints.some((h) => h.code === 'fixture.no_price'));
    const bad = await client().calculate({ ...byName['alu-mindestpreis'].request, materialId: ACRYL, format: { width: 1200, height: 2500 } });
    assert.equal(bad.valid, false);
    assert.equal(bad.errors[0].code, 'format.height_max');
    assert.deepEqual(bad.errors[0].fix, { 'format.height': 2000 });
});

test('Liefertermine (Fixture-Client): Werktage ab Samstag, 07.11.2026', () => {
    assert.equal(addWorkdays(NOW, 5), '2026-11-13');
    assert.equal(addWorkdays(NOW, 3), '2026-11-11');
});

test('kompakt: Startzustand zeigt Mindestpreis 12,50 €, nummerierte Abschnitte, Hilfe an jedem Feld', async () => {
    const c = await controller();
    assert.equal(c.state.result.price.total, 12.5);
    const html = R.renderConfigurator(ctx, c.state);
    const text = textOf(html);
    for (const s of ['Material', 'Format & Auflage', 'Druck & Weiterverarbeitung']) assert.ok(text.includes(s), s);
    assert.equal((html.match(/class="lm-sec__no"/g) || []).length, 3, 'drei Abschnitte, Stückzahl beim Format');
    assert.ok(text.includes('Fläche: 0,24 m² je Stück × 1 Stück = 0,24 m² gesamt'), 'Gesamtfläche beim Format');
    assert.match(text, /12,50 €/);
    assert.ok(text.includes('Mindestpreis 12,50 € je Position'));
    assert.ok(text.includes('netto zzgl. MwSt.'));
    // jedes Feld (Label) hat ein Hilfe-Symbol
    const labels = html.match(/<label /g).length;
    const helps = (html.match(/class="lm-help"/g) || []).length;
    assert.ok(helps >= labels, `Hilfe ${helps} < Felder ${labels}`);
    // Materialliste gruppiert nach Kalkulation, Optionen mit Preis-Hinweis
    assert.match(html, /<optgroup label="Aluverbund">/);
    assert.match(html, /4\/4 beidseitig bedruckt · \+ 9,00 €\/m²/);
    // Kein „In den Warenkorb“ ohne gültigen Preis? Hier gültig → aktiv
    assert.match(html, /data-lm-action="cart" >/);
    assert.ok(!/data-lm-action="cart" disabled/.test(html));
});

test('kompakt: Weg zum Comdatek-Fall 353,00 € (je Stück 35,30 €, je m² 70,60 €)', async () => {
    const c = await controller();
    c.dispatch({ type: 'format', width: 1000, height: 500 });
    c.dispatch({ type: 'perMotif', value: 10 });
    c.dispatch({ type: 'option', group: 'farbigkeit', value: '4-4' });
    c.dispatch({ type: 'option', group: 'veredelung', value: 'ag-2' });
    c.dispatch({ type: 'option', group: 'bohrung', value: 'ja' });
    c.dispatch({ type: 'option', group: 'schnitt', value: 'kontur' });
    await c.idle();
    assert.equal(c.state.result.price.total, 353);
    const text = textOf(R.renderPrice(ctx, c.state));
    assert.ok(text.includes('353,00 €'));
    assert.ok(text.includes('35,30 €'));
    assert.ok(text.includes('70,60 €'));
    assert.ok(text.includes('5 m²'));
    assert.ok(textOf(R.renderCompact(ctx, c.state)).includes('= 5 m² gesamt'), 'Fläche vom Backend');
    // nur die letzte Anfrage zählt, entprellt: höchstens Start + 1 weitere Berechnung
    assert.ok(c.client.calls.length <= 2, `Aufrufe: ${c.client.calls.length}`);
});

test('Materialwechsel mit zu großer Höhe: Hinweis-Box, markiertes Feld, fix-Schaltfläche, kein Warenkorb', async () => {
    const c = await controller({ initial: { ...byName['alu-mindestpreis'].request, format: { width: 1200, height: 2500 }, options: { farbigkeit: '4-4', veredelung: 'lam-matt', bohrung: 'ja', schnitt: 'recht' } } });
    c.dispatch({ type: 'material', id: ACRYL });
    await c.idle();
    const html = R.renderConfigurator(ctx, c.state);
    const text = textOf(html);
    assert.ok(text.includes('Ihre Auswahl wurde angepasst'));
    assert.ok(text.includes('Veredelung entfällt bei Acrylglas (war: Laminat matt).'));
    assert.ok(text.includes('„4/4 beidseitig bedruckt“ gibt es bei Acrylglas nicht – jetzt „4/0 einseitig bedruckt“'));
    assert.ok(text.includes('Format und Menge bleiben erhalten.'));
    assert.ok(text.includes('Auf 2.000 mm setzen'));
    assert.match(html, /data-lm-fix="\{&quot;format.height&quot;:2000\}"/);
    assert.match(html, /aria-invalid="true"/);
    assert.match(html, /data-lm-action="cart" disabled/);
    assert.ok(text.includes('Bitte zuerst die markierten Angaben anpassen'));
    assert.match(html, /lm-pv is-bad/);
    // fix übernehmen → neue Berechnung, Fehler weg
    c.dispatch({ type: 'fix', fix: { 'format.height': 2000 } });
    await c.idle();
    assert.deepEqual(S.currentErrors(c.state), []);
    assert.equal(c.state.config.format.height, 2000);
});

test('Sollergebnis „Acryl zu hoch“ (Backend-Texte) wird nach code dargestellt', () => {
    const c = byName['acryl-hoehe-zu-gross'];
    let s = S.createState(alu, { initial: c.request });
    s = S.apply(s, { type: 'result', result: structuredClone(c.expected) });
    const html = R.renderConfigurator(ctx, s);
    assert.match(html, /data-lm-code="format.height_max"/);
    assert.ok(textOf(html).includes('Acrylglas 5 mm: Höhe höchstens 2.000 mm'));
    assert.ok(textOf(html).includes('Veredelung entfällt bei Acrylglas'));
    assert.ok(textOf(html).includes('Auf 2.000 mm setzen'));
});

test('Großformat 1.500 × 3.000 mm = 131,00 €', async () => {
    const c = await controller({ initial: byName['alu-grossformat'].request });
    assert.equal(c.state.result.price.total, 131);
    assert.ok(textOf(R.renderPrice(ctx, c.state)).includes('131,00 €'));
});

test('geführt: Reiter, Kacheln mit Badges/ab-Preis, automatisch weiter, Ihr Produkt mit Ändern-Links', async () => {
    const c = await controller({ layout: 'guided' });
    let html = R.renderConfigurator(ctx, c.state);
    assert.match(html, /aria-current="step"/);
    assert.ok(textOf(html).includes('Welches Material soll es sein?'));
    assert.match(html, /class="lm-tile btn btn-outline-secondary text-start active" data-lm-action="material"/);
    assert.match(html, /class="nav nav-tabs"/, 'Reiter im Theme-Stil (Bootstrap nav-tabs)');
    assert.ok(textOf(html).includes('Bestseller'));
    assert.ok(textOf(html).includes('ab 29,00 €/m²'));
    assert.match(html, /data-lm-action="step" data-lm-value="group:veredelung">ändern</);
    const labels = [...html.matchAll(/<span class="lm-steps__n"[^>]*>[^<]*<\/span>([^<]+)<\/button>/g)].map((m) => m[1].replace(/&amp;/g, '&'));
    assert.deepEqual(labels, ['Material', 'Format & Stückzahl', 'Farbigkeit', 'Veredelung', 'Lochbohrungen', 'Schneiden', 'Lieferzeit']);
    // Format-Schritt: Schnellwahl nur innerhalb der Grenzen
    c.dispatch({ type: 'step', key: 'format' });
    html = R.renderConfigurator(ctx, c.state);
    assert.ok(textOf(html).includes('Wie groß soll Aluverbund 3 mm sein – und wie viele?'));
    assert.ok(textOf(html).includes('DIN A4'));
    assert.match(html, /data-lm-field="perMotif"/, 'Stückzahl im Format-Schritt');
    assert.ok(textOf(html).includes('= 0,24 m² gesamt'));
    // Optionsschritt: Kacheln mit Preis-Hinweis
    c.dispatch({ type: 'step', key: 'group:veredelung' });
    html = R.renderConfigurator(ctx, c.state);
    assert.ok(textOf(html).includes('Anti-Graffiti beidseitig'));
    assert.ok(textOf(html).includes('+ 29,00 €/m²'));
});

test('geführt: Preismatrix Menge × Lieferzeit mit Datum; Klick wählt beides', async () => {
    const c = await controller({ layout: 'guided', step: 'delivery' });
    assert.ok(c.client.calls.at(-1).matrix, 'geführt fragt die Matrix an');
    let html = R.renderConfigurator(ctx, c.state);
    assert.match(html, /<table class="lm-matrix table table-hover align-middle">/);
    assert.ok(textOf(html).includes('Wann brauchen Sie die Ware?'));
    assert.match(html, /data-lm-action="delivery" data-lm-value="express" aria-pressed="false"/, 'Lieferzeit als Kachel');
    assert.ok(textOf(html).includes('Standard'));
    assert.ok(textOf(html).includes('Fr., 13.11.'), 'Liefertermin Standard');
    assert.ok(textOf(html).includes('Mi., 11.11.'), 'Liefertermin Express');
    assert.match(html, /data-lm-quantity="1" data-lm-delivery="standard" aria-pressed="true">12,50\s€</);
    assert.equal((html.match(/data-lm-action="matrix"/g) || []).length, alu.quantitySteps.length * 2);
    c.dispatch({ type: 'matrix', quantity: 10, delivery: 'express' });
    await c.idle();
    html = R.renderConfigurator(ctx, c.state);
    assert.match(html, /data-lm-quantity="10" data-lm-delivery="express" aria-pressed="true"/);
    assert.equal(c.state.config.perMotif, 10);
    assert.equal(c.state.config.deliveryValue, 'express');
    assert.match(html, /data-lm-action="cart"/, 'letzter Schritt bietet den Warenkorb');
});

test('Umschalten kompakt ↔ geführt ohne Verlust der Auswahl (auch im Rendering)', async () => {
    const c = await controller({ initial: byName['alu-comdatek-353'].request });
    c.dispatch({ type: 'layout', value: 'guided' });
    await c.idle();
    const text = textOf(R.renderSummary(ctx, c.state));
    assert.ok(text.includes('1.000 × 500 mm'));
    assert.ok(text.includes('Anti-Graffiti beidseitig'));
    assert.ok(text.includes('1 × 10 = 10 Stk'));
    assert.equal(c.state.result.price.total, 353);
});

test('Annahmeschluss und Liefertermin im Preisblock', async () => {
    const c = await controller();
    const text = textOf(R.renderPrice(ctx, c.state));
    assert.ok(text.includes('Lieferung Standard: Fr., 13.11.'));
    assert.ok(text.includes('bei Bestellung und Druckdaten bis 11:30 Uhr · noch 2 Std. 30 Min.'));
    const late = { ...ctx, now: () => new Date(2026, 10, 9, 12, 0) };
    assert.ok(textOf(R.renderPrice(late, c.state)).includes('Annahmeschluss 11:30 Uhr ist für heute vorbei'));
});

test('brutto umschaltbar, wenn das Backend totalGross liefert; sonst kein Schalter', async () => {
    const c = await controller();
    assert.ok(!R.renderPrice(ctx, c.state).includes('data-lm-action="gross"'));
    const r = structuredClone(c.state.result);
    r.price.totalGross = 14.88;
    let s = S.apply(c.state, { type: 'result', result: r });
    s = S.apply(s, { type: 'gross', value: true });
    const text = textOf(R.renderPrice(ctx, s));
    assert.ok(text.includes('14,88 €'));
    assert.ok(text.includes('inkl. MwSt.'));
});

test('abgerechnete Fläche (billedArea/minimumArea) im Live-Preis und beim Format, nur wenn größer als die echte Fläche', async () => {
    const c = await controller();
    const r = structuredClone(c.state.result);
    r.price.billedArea = 1;
    r.price.minimumArea = 1;
    const s = S.apply(c.state, { type: 'result', result: r });
    const price = textOf(R.renderPrice(ctx, s));
    assert.ok(price.includes('abgerechnete Fläche 1 m² (Mindestfläche 1 m²)'), price);
    assert.ok(textOf(R.renderCompact(ctx, s)).includes('= 0,24 m² gesamt (abgerechnet 1 m²)'));
    // ohne Vertragsfeld bzw. gleich der Fläche: keine Zeile
    assert.ok(!textOf(R.renderPrice(ctx, c.state)).includes('abgerechnete Fläche'));
});

test('Anzeige-Schalter: Aufpreise, Fläche, je m²/je Stück, ab-Preis abschaltbar – Gesamtpreis bleibt', async () => {
    const data = structuredClone(alu);
    data.display = { showPriceHints: false, showArea: false, showPerSqm: false, showPerPiece: false, showFromPrice: false };
    const c = new MensuraController({ data, client: client(), debounceMs: 0 });
    await c.calculateNow();
    const html = R.renderConfigurator(ctx, c.state);
    const text = textOf(html);
    assert.ok(text.includes('12,50 €'), 'Gesamtpreis sichtbar');
    for (const hidden of ['+ 9,00 €/m²', 'je m²', 'je Stück', 'Fläche gesamt', 'Fläche: ']) assert.ok(!text.includes(hidden), `sichtbar: ${hidden}`);
    const guided = textOf(R.renderGuided(ctx, S.apply(c.state, { type: 'step', key: 'material' })));
    assert.ok(!guided.includes('ab 29,00 €/m²'));
    // je Gruppe: nur Veredelung ohne Aufpreis
    const perGroup = structuredClone(alu);
    perGroup.groups.find((g) => g.technicalName === 'veredelung').showPriceHint = false;
    const s = S.createState(perGroup);
    const compact = textOf(R.renderCompact(ctx, s));
    assert.ok(!compact.includes('+ 29,00 €/m²'), 'Veredelung ohne Aufpreis');
    assert.ok(compact.includes('+ 9,00 €/m²'), 'Farbigkeit weiter mit Aufpreis');
});

test('Netzfehler: Hinweis mit „Erneut versuchen“, kein Warenkorb', async () => {
    const failing = { calculate: async () => { throw new Error('offline'); }, addToCart: async () => ({ ok: false }) };
    const c = await controller({ client: failing });
    assert.equal(c.state.netError, true);
    const html = R.renderPrice(ctx, c.state);
    assert.match(html, /data-lm-action="retry"/);
    assert.match(html, /data-lm-action="cart" disabled/);
});

test('veraltete Antworten werden verworfen (nur die letzte Anfrage zählt)', async () => {
    const slow = client();
    const orig = slow.calculate.bind(slow);
    let first = true;
    slow.calculate = async (cfg, o) => {
        if (first) { first = false; await new Promise((r) => setTimeout(r, 30)); }
        return orig(cfg, o);
    };
    const c = new MensuraController({ data: alu, client: slow, debounceMs: 0 });
    const p1 = c.calculateNow();
    c.dispatch({ type: 'format', width: 1500, height: 3000 });
    await c.idle();
    await p1;
    assert.equal(c.state.result.price.total, 131);
});

test('Warenkorb: gültig → ok; ungültig → Ergebnis mit Fehlern übernommen', async () => {
    const c = await controller();
    assert.equal((await c.addToCart()).ok, true);
    c.dispatch({ type: 'material', id: ACRYL });
    c.dispatch({ type: 'format', height: 2500 });
    const res = await c.addToCart();
    assert.equal(res.ok, false);
    assert.equal(S.currentErrors(c.state)[0].code, 'format.height_max');
});

test('Store-API-Client: Pfade, Header, Kontext-Token', async () => {
    const seen = [];
    const fetchImpl = async (url, init) => {
        seen.push({ url, init });
        return { ok: true, status: 200, headers: { get: (h) => (h === 'sw-context-token' ? 'neu' : null) }, json: async () => ({ apiVersion: 1 }) };
    };
    const api = new StoreApiClient({ baseUrl: '/shop/store-api/', accessKey: 'SWSC123', contextToken: 'alt', fetchImpl });
    await api.configurator(alu.productId);
    await api.calculate(byName['alu-mindestpreis'].request, { matrix: true });
    assert.equal(seen[0].url, `/shop/store-api/ld-mensura/product/${alu.productId}/configurator`);
    assert.equal(seen[0].init.method, 'GET');
    assert.equal(seen[0].init.headers['sw-access-key'], 'SWSC123');
    assert.equal(seen[0].init.headers['sw-context-token'], 'alt');
    assert.equal(seen[1].url, '/shop/store-api/ld-mensura/calculate');
    assert.deepEqual(JSON.parse(seen[1].init.body), { configuration: byName['alu-mindestpreis'].request, matrix: true });
    assert.equal(seen[1].init.headers['sw-context-token'], 'neu', 'neues Token aus der Antwort wird weiterverwendet');
});

test('englische Texte (en-GB) ohne fehlende Schlüssel', async () => {
    const c = await controller({ layout: 'guided' });
    for (const layout of ['compact', 'guided']) {
        const s = S.apply(c.state, { type: 'layout', value: layout });
        for (const step of S.guidedSteps(alu, s)) {
            const html = R.renderConfigurator(ctxEn, S.apply(s, { type: 'step', key: step.key }));
            assert.ok(!/\b(help|field|section|price|guided|summary|notice|delivery|preview|cart|share|layout|steps)\.[a-zA-Z]+\b/.test(textOf(html)), `fehlender Schlüssel in ${layout}/${step.key}: ${textOf(html).match(/\b(help|field|section|price|guided|summary|notice|delivery|preview|cart|share|layout|steps)\.[a-zA-Z]+\b/)?.[0]}`);
        }
    }
    assert.ok(textOf(R.renderConfigurator(ctxEn, c.state)).includes('Which material would you like?'));
});

test('Folge-Set-Daten: Festformat-Auswahl, split-Menge, optionale Gruppe „keine“', async () => {
    const data = loadTestData('follow-set');
    const fc = new FixtureClient({ configurators: [data], cases: [], now: () => NOW });
    const c = new MensuraController({ data, client: fc, debounceMs: 0, layout: 'compact' });
    await c.calculateNow();
    c.dispatch({ type: 'option', group: 'rand', value: 'saum' });
    c.dispatch({ type: 'step', key: 'group:hohlsaum' });
    await c.idle();
    const html = R.renderGuided(ctx, c.state);
    assert.ok(textOf(html).includes('Hohlsaum'));
    assert.match(html, /data-lm-group="hohlsaum" data-lm-value="" aria-pressed="true"/, '„keine“ vorgewählt, nichts Kostenpflichtiges');
    const fmt = R.renderGuided(ctx, S.apply(c.state, { type: 'step', key: 'format' }));
    assert.match(fmt, /data-lm-field="formatValue" data-lm-group="format"/);
    assert.ok(!fmt.includes('data-lm-action="rotate"'), 'Drehen gesperrt');
    const qty = R.renderGuided(ctx, S.apply(c.state, { type: 'step', key: 'format' }));
    assert.ok(textOf(qty).includes('Gesamtmenge'));
});
