// Zustandslogik gegen die Vertrags-Fixtures (KONZEPT 3.7: Materialwechsel, Optionssets, Grenzen) – ohne Shop.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { join } from 'node:path';
import { loadCases, loadConfigurators, loadTestData, SRC } from './helpers.mjs';

const S = await import(join(SRC, 'state.js'));
const configurators = loadConfigurators().map((c) => c.data);
const alu = loadConfigurators().find((c) => c.name === 'aluverbund').data;
const dataFor = (productId) => configurators.find((d) => d.productId === productId);
const cases = Object.fromEntries(loadCases().map((c) => [c.name, c]));
const ACRYL = '0194e1a0000070008000000000000b02';
const ALU = '0194e1a0000070008000000000000b01';

function checkConfigurationShape(cfg) {
    // configuration.schema.json: Pflichtfelder, Typen, keine Zusatzfelder
    const allowed = ['schema', 'productId', 'materialId', 'format', 'motifs', 'perMotif', 'options', 'deliveryValue', 'reference', 'artwork'];
    for (const k of ['schema', 'productId', 'materialId', 'format', 'motifs', 'perMotif', 'options']) assert.ok(k in cfg, `Pflichtfeld ${k}`);
    for (const k of Object.keys(cfg)) assert.ok(allowed.includes(k), `unerlaubtes Feld ${k}`);
    assert.equal(cfg.schema, 1);
    assert.match(cfg.productId, /^[0-9a-f]{32}$/);
    assert.match(cfg.materialId, /^[0-9a-f]{32}$/);
    assert.deepEqual(Object.keys(cfg.format).filter((k) => !['width', 'height', 'formatValueId'].includes(k)), []);
    for (const k of ['motifs', 'perMotif']) assert.ok(Number.isInteger(cfg[k]) && cfg[k] >= 1);
    assert.ok(Number.isInteger(cfg.format.width) && cfg.format.width >= 1);
    for (const v of Object.values(cfg.options)) assert.ok(typeof v === 'string' && v.length > 0);
}

test('jede Konfigurator-Fixture: Startzustand ergibt eine gültige Konfiguration', () => {
    for (const { name, data } of loadConfigurators()) {
        const cfg = S.toConfiguration(S.createState(data));
        checkConfigurationShape(cfg);
        assert.equal(cfg.productId, data.productId, name);
    }
});

test('Aluverbund-Vorbelegung = Beispielfall Mindestpreis (600 × 400, 1 Stk)', () => {
    const cfg = S.toConfiguration(S.createState(alu));
    assert.equal(S.configKey(cfg), S.configKey(cases['alu-mindestpreis'].request));
});

test('jeder Beispielfall: lokale Normalisierung trifft die normalisierte Konfiguration des Backends', () => {
    for (const c of Object.values(cases)) {
        const data = dataFor(c.request.productId);
        assert.ok(data, `kein Konfigurator für ${c.request.productId} (${c.name})`);
        const state = S.createState(data, { initial: c.request });
        assert.equal(S.configKey(S.toConfiguration(state)), S.configKey(c.expected.configuration), c.name);
        if (c.expected.activeGroups) assert.deepEqual(S.activeGroupNames(data, state.config), c.expected.activeGroups, c.name);
    }
});

test('Materialwechsel Alu → Acryl: Format/Menge bleiben, Änderungen werden gemeldet, Rückweg stellt frühere Wahl her', () => {
    let s = S.createState(alu, { initial: cases['alu-comdatek-353'].request });
    s = S.apply(s, { type: 'material', id: ACRYL });
    assert.deepEqual(s.config.format, { width: 1000, height: 500 });
    assert.equal(s.config.perMotif, 10);
    assert.equal(s.config.motifs, 1);
    const codes = s.changes.map((c) => `${c.code}:${c.group || ''}`).sort();
    assert.deepEqual(codes, ['option.added:kanten', 'option.removed:veredelung', 'option.reset:farbigkeit']);
    assert.equal(s.config.options.farbigkeit, '4-0');
    assert.equal(s.config.options.bohrung, 'ja', 'gemeinsame Gruppe behält ihren Wert');
    assert.equal(s.config.options.schnitt, 'kontur');
    assert.equal(s.config.options.kanten, 'nein', 'neue Gruppe: Standard, nichts Kostenpflichtiges');
    assert.equal(s.config.options.veredelung, undefined);
    assert.deepEqual(Object.keys(S.toConfiguration(s).options).sort(), ['bohrung', 'farbigkeit', 'kanten', 'schnitt']);

    s = S.apply(s, { type: 'material', id: ALU });
    assert.equal(s.config.options.veredelung, 'ag-2', 'Rückkehr stellt Anti-Graffiti wieder her');
    assert.ok(s.changes.some((c) => c.code === 'option.restored' && c.group === 'veredelung'));
    assert.equal(s.config.options.farbigkeit, '4-0', 'erlaubter Wert bleibt, kein stilles Zurückspringen');
});

test('Grenzen des neuen Materials: Wert wird nicht still geändert, Fehler mit fix, fix übernimmt 2.000 mm', () => {
    let s = S.createState(alu, { initial: { ...cases['alu-mindestpreis'].request, format: { width: 1200, height: 2500 } } });
    assert.deepEqual(S.localErrors(alu, s.config), []);
    s = S.apply(s, { type: 'material', id: ACRYL });
    assert.equal(s.config.format.height, 2500, 'nicht still verändert');
    const err = S.currentErrors(s);
    assert.equal(err.length, 1);
    assert.equal(err[0].code, 'format.height_max');
    assert.deepEqual(err[0].fix, { 'format.height': 2000 });
    assert.ok(s.changes.some((c) => c.code === 'format.check'));
    assert.equal(S.canOrder(s), false);
    s = S.apply(s, { type: 'fix', fix: err[0].fix });
    assert.equal(s.config.format.height, 2000);
    assert.deepEqual(S.currentErrors(s), []);
    assert.ok(!s.changes.some((c) => c.code === 'format.check'), 'Format-Hinweis verschwindet nach dem Übernehmen');
});

test('Ergebnis des Backends: nur frisch zählt; normalisierte Optionen werden übernommen, Format nie', () => {
    let s = S.createState(alu);
    const r = structuredClone(cases['alu-mindestpreis'].expected);
    s = S.apply(s, { type: 'result', result: r });
    assert.ok(S.isFresh(s));
    assert.ok(S.canOrder(s));
    s = S.apply(s, { type: 'perMotif', value: 2 });
    assert.ok(!S.isFresh(s), 'nach Änderung ist der alte Preis nicht mehr gültig');
    assert.equal(S.canOrder(s), false);
    // Backend ersetzt eine Option → wird übernommen und als Hinweis gezeigt
    const acryl = structuredClone(cases['acryl-hoehe-zu-gross'].expected);
    let t = S.createState(alu, { initial: cases['acryl-hoehe-zu-gross'].request });
    t = S.apply(t, { type: 'result', result: acryl });
    assert.ok(S.isFresh(t));
    assert.equal(t.config.format.height, 2500);
    assert.equal(S.currentErrors(t)[0].message, 'Acrylglas 5 mm: Höhe höchstens 2.000 mm');
    assert.ok(t.changes.some((c) => c.backend && c.code === 'option.removed'));
});

test('Preismatrix-Klick wählt Menge und Lieferzeit; Lieferzeit nur aus der Gruppe', () => {
    let s = S.createState(alu);
    s = S.apply(s, { type: 'matrix', quantity: 25, delivery: 'express' });
    assert.equal(s.config.perMotif, 25);
    assert.equal(s.config.deliveryValue, 'express');
    assert.equal(S.apply(s, { type: 'delivery', value: 'gibts-nicht' }), s);
});

test('Darstellung umschalten behält die Auswahl', () => {
    let s = S.createState(alu, { initial: cases['alu-comdatek-353'].request });
    const before = S.configKey(S.toConfiguration(s));
    s = S.apply(s, { type: 'layout', value: 'guided' });
    assert.equal(s.layout, 'guided');
    assert.equal(S.configKey(S.toConfiguration(s)), before);
    s = S.apply(s, { type: 'layout', value: 'compact' });
    assert.equal(S.configKey(S.toConfiguration(s)), before);
});

test('Umschalten gesperrt, wenn allowLayoutSwitch=false', () => {
    const data = loadTestData('follow-set');
    let s = S.createState(data, { layout: 'compact' });
    assert.equal(s.layout, 'guided');
    s = S.apply(s, { type: 'layout', value: 'compact' });
    assert.equal(s.layout, 'guided');
});

test('URL-Hash: Konfiguration teilen und wiederherstellen', () => {
    let s = S.createState(alu, { initial: cases['alu-comdatek-353'].request });
    s = S.apply(s, { type: 'layout', value: 'guided' });
    s = S.apply(s, { type: 'step', key: 'quantity' });
    const hash = S.encodeHash(s);
    assert.match(hash, /^#lm\?m=alu-3mm&w=1000&h=500&mo=1&pm=10/);
    const parsed = S.decodeHash(alu, hash);
    const back = S.createState(alu, { initial: parsed.config, layout: parsed.layout, step: parsed.step });
    assert.equal(S.configKey(S.toConfiguration(back)), S.configKey(S.toConfiguration(s)));
    assert.equal(back.layout, 'guided');
    assert.equal(back.step, 'delivery', 'alter Hash-Schritt quantity wird zu delivery');
    assert.equal(S.decodeHash(alu, '#reviews'), null, 'fremde Anker bleiben unberührt');
    const junk = S.decodeHash(alu, '#lm?m=unbekannt&w=abc&o.farbigkeit=xx');
    const junkState = S.createState(alu, { initial: junk.config });
    assert.equal(junkState.config.materialId, ALU);
    assert.equal(junkState.config.format.width, 600);
    assert.equal(junkState.config.options.farbigkeit, '4-0');
});

test('Geführte Schritte folgen den aktiven Gruppen (Optionssets)', () => {
    const keys = (s) => S.guidedSteps(alu, s).map((x) => x.key);
    let s = S.createState(alu);
    assert.deepEqual(keys(s), ['material', 'format', 'group:farbigkeit', 'group:veredelung', 'group:bohrung', 'group:schnitt', 'delivery']);
    s = S.apply(s, { type: 'material', id: ACRYL });
    assert.deepEqual(keys(s), ['material', 'format', 'group:farbigkeit', 'group:kanten', 'group:bohrung', 'group:schnitt', 'delivery']);
    assert.equal(S.nextStepKey(alu, { ...s, step: 'material' }), 'format');
    assert.equal(S.nextStepKey(alu, { ...s, step: 'quantity' }), 'delivery', 'alter Schlüssel quantity = Lieferzeit-Schritt');
    assert.equal(S.nextStepKey(alu, { ...s, step: 'format' }), 'group:farbigkeit', 'nach Format & Stückzahl kommen die Optionen');
});

test('Folge-Sets von Werten, Start-Set, ausgeblendete und optionale Gruppen, Festformat', () => {
    const data = loadTestData('follow-set');
    let s = S.createState(data);
    assert.deepEqual(S.activeGroupNames(data, s.config), ['format', 'qualitaet', 'rand']);
    assert.deepEqual(S.optionGroups(data, s).map((g) => g.technicalName), ['rand'], 'hidden und Format nicht bei den Optionen');
    assert.equal(S.formatGroup(data, s).technicalName, 'format');
    assert.equal(S.toConfiguration(s).options.qualitaet, 'standard', 'hidden-Wert geht mit');
    s = S.apply(s, { type: 'option', group: 'rand', value: 'saum' });
    assert.deepEqual(S.activeGroupNames(data, s.config), ['format', 'qualitaet', 'rand', 'oesen', 'hohlsaum']);
    assert.equal(s.config.options.oesen, 'keine');
    assert.equal(s.config.options.hohlsaum, undefined, 'optionale kostenpflichtige Gruppe nicht vorausgewählt');
    s = S.apply(s, { type: 'option', group: 'oesen', value: '50cm' });
    s = S.apply(s, { type: 'option', group: 'rand', value: 'schnitt' });
    assert.equal(s.config.options.oesen, undefined);
    s = S.apply(s, { type: 'option', group: 'rand', value: 'saum' });
    assert.equal(s.config.options.oesen, '50cm', 'frühere Wahl im Folge-Set wiederhergestellt');
    // Festformat ↔ freie Eingabe
    s = S.apply(s, { type: 'formatValue', group: 'format', value: '2000x1000' });
    assert.deepEqual(s.config.format, { width: 2000, height: 1000 });
    assert.equal(S.toConfiguration(s).format.formatValueId, '0194e1a00000700080000000000f0f02', 'Festformat mit id → formatValueId');
    s = S.apply(s, { type: 'format', width: 1234 });
    assert.equal(s.config.options.format, 'frei');
    assert.equal(S.toConfiguration(s).format.formatValueId, undefined, 'freies Format ohne formatValueId');
    s = S.apply(s, { type: 'format', width: 1000, height: 500 });
    assert.equal(s.config.options.format, '1000x500');
    // Drehen gesperrt (allowRotation=false), Mengengrenzen mit fix
    assert.equal(S.apply(s, { type: 'rotate' }), s);
    s = S.apply(s, { type: 'motifs', value: 9 });
    const motErr = S.currentErrors(s).find((e) => e.code === 'quantity.motifs_max');
    assert.deepEqual(motErr.fix, { motifs: 5 });
});

test('Vorbelegung je Material (günstigster Wert, materials[].defaults) gilt beim Materialwechsel vor defaults.options', () => {
    const data = structuredClone(alu);
    // Annahme für den Test: bei Acryl ist „ja“ (Kanten poliert) nicht günstiger – Backend nennt „nein“; bei Alu sei Laminat matt am günstigsten
    data.materials.find((m) => m.id === ACRYL).defaults = { kanten: 'nein', farbigkeit: '0-0' };
    data.materials.find((m) => m.id === ALU).defaults = { veredelung: 'lam-matt' };
    let s = S.createState(data, { initial: cases['alu-comdatek-353'].request });
    s = S.apply(s, { type: 'material', id: ACRYL });
    assert.equal(s.config.options.farbigkeit, '0-0', '4-4 bei Acryl nicht erlaubt → günstigster Wert des Materials');
    assert.equal(s.config.options.kanten, 'nein', 'neue Gruppe → Vorbelegung des Materials');
    // ungültiger Eintrag in materials[].defaults wird ignoriert
    data.materials.find((m) => m.id === ACRYL).defaults = { farbigkeit: '4-4' };
    assert.equal(S.defaultValue(data, 'farbigkeit', data.materials.find((m) => m.id === ACRYL)), '4-0', 'nicht erlaubter Wert → allgemeine Vorbelegung');
    // Startzustand: gesetzte Werte bleiben, fehlende bekommen die Vorbelegung des Materials
    const fresh = S.createState(data, { initial: { ...cases['alu-mindestpreis'].request, options: {} } });
    assert.equal(fresh.config.options.veredelung, 'lam-matt');
    // Link ohne Angabe einer Gruppe: ebenfalls Vorbelegung des Materials
    const parsed = S.decodeHash(data, '#lm?m=alu-3mm&w=600&h=400&mo=1&pm=1&o.farbigkeit=4-4');
    const fromLink = S.createState(data, { initial: parsed.config });
    assert.equal(fromLink.config.options.veredelung, 'lam-matt');
    assert.equal(fromLink.config.options.farbigkeit, '4-4', 'Angabe im Link bleibt');
});

test('Standardwerte: optionale Gruppen ohne Vorbelegung bleiben leer; ungültige Eingaben werden zu ganzen Zahlen ≥ 1', () => {
    const data = loadTestData('follow-set');
    const material = data.materials[0];
    assert.equal(S.defaultValue(data, 'hohlsaum', material), null);
    let s = S.createState(alu);
    s = S.apply(s, { type: 'format', width: '-5', height: 'abc' });
    assert.deepEqual(s.config.format, { width: 1, height: 1 });
    s = S.apply(s, { type: 'perMotif', value: '12.6' });
    assert.equal(s.config.perMotif, 13);
});
