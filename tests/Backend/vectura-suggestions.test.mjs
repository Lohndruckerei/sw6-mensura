// Vorschläge aus Vectura (KONZEPT 3d.7, INTEGRATION.md 7.2): Zuordnung über Schlüssel, Übernahme als Entwurf,
// Schrittgrenze, Laden ohne Vectura. Die Admin-Datei ist ein ES-Modul ohne Imports und wird hier direkt geladen.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const dir = new URL('../../src/Resources/app/administration/src/module/ld-mensura/service/', import.meta.url);
const source = readFileSync(new URL('vectura-suggestions.js', dir), 'utf8');
const lib = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const example = JSON.parse(readFileSync(new URL('vectura-suggestions.example.json', dir), 'utf8'));

test('Zuordnung: Material, Kalkulation, Optionswert – nur über Schlüssel', () => {
    const ids = (keys) => lib.matchSuggestions(example.suggestions, keys).map((s) => s.id);
    assert.deepEqual(ids({ materialKey: 'acryl-5mm' }), ['acryl-bulky-1200-2449', 'acryl-base']);
    assert.deepEqual(ids({ materialKey: 'alu-3mm' }), ['alu-base']);
    assert.deepEqual(ids({ valueKey: 'express' }), ['express-24h']);
    // Kalkulation nur für Vorschläge ohne Materialliste
    assert.deepEqual(ids({ calculationKey: 'aluverbund' }), []);
    assert.deepEqual(ids({ materialKey: 'unbekannt' }), []);
    assert.deepEqual(lib.matchSuggestions(null, { materialKey: 'acryl-5mm' }), []);
});

test('Übernahme: Entwurf mit Form, Rampe, Mindestbetrag und Herkunft', () => {
    const express = example.suggestions.find((s) => s.id === 'express-24h');
    const draft = lib.proposalToDraft(express, example, '2026-11-09');
    assert.equal(draft.costType, 'express');
    assert.equal(draft.form, 'per_sqm');
    assert.equal(draft.value, 4.18);
    assert.equal(draft.useMinimum, true);
    assert.equal(draft.minimumAmount, 25);
    assert.equal(draft.minimumScope, 'order');
    assert.equal(draft.useRamp, false);
    assert.equal(draft.validFrom, '2026-11-09');
    assert.equal(draft.source, 'vectura');
    assert.equal(draft.sourceReference.suggestionId, 'express-24h');
    assert.deepEqual(draft.sourceReference.period, { from: '2026-08-01', to: '2026-10-31' });

    const bulky = lib.proposalToDraft(example.suggestions[0], example, '2026-11-09');
    assert.equal(bulky.useRamp, true);
    assert.equal(bulky.rampFrom, 1000);
    assert.equal(bulky.rampTo, 1400);
});

test('Schrittgrenze ±1 €/m² bzw. ±2 Prozentpunkte gegen die gültige Regel', () => {
    const bulky = example.suggestions[0]; // 18,50 €/m²
    assert.equal(lib.stepExceeded(bulky, { form: 'per_sqm', value: 17.77 }), false);
    assert.equal(lib.stepExceeded(bulky, { form: 'per_sqm', value: 17.0 }), true);
    assert.equal(lib.stepExceeded(bulky, null), false);
    assert.equal(lib.stepExceeded(bulky, { form: 'percent', value: 1 }), false);
});

test('nächster Montag und Zeitraum 3 Monate', () => {
    assert.equal(lib.nextMonday(new Date(2026, 10, 8)), '2026-11-09');  // Sonntag → Montag
    assert.equal(lib.nextMonday(new Date(2026, 10, 9)), '2026-11-16');  // Montag → nächster Montag
    assert.deepEqual(lib.defaultPeriod(new Date(2026, 10, 8)), { from: '2026-08-08', to: '2026-11-08' });
});

test('Laden: ohne Vectura kein Aufruf, 404 = fehlt, 403 = keine Berechtigung', async () => {
    let calls = 0;
    const client = (impl) => ({ get: async (...args) => { calls++; return impl(...args); } });
    const fail = (status) => () => { const error = new Error('x'); error.response = { status }; throw error; };

    assert.deepEqual(await lib.loadSuggestions(client(() => ({})), {}, false, {}), { status: 'missing', data: null });
    assert.equal(calls, 0);
    assert.equal((await lib.loadSuggestions(client(fail(404)), {}, true, {})).status, 'missing');
    assert.equal((await lib.loadSuggestions(client(fail(403)), {}, true, {})).status, 'forbidden');
    assert.equal((await lib.loadSuggestions(client(fail(500)), {}, true, {})).status, 'error');
    const ok = await lib.loadSuggestions(client((path, options) => ({ data: { path, params: options.params } })), {}, true, { from: 'a', to: 'b' });
    assert.equal(ok.status, 'ok');
    assert.equal(ok.data.path, '/_action/ld-vectura/logistics/suggestions');
    assert.deepEqual(ok.data.params, { from: 'a', to: 'b' });
});
