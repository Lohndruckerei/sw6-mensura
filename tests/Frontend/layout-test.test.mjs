// Testbetrieb der Darstellungen (KONZEPT 3c, Vertrag layoutTest.share): Zuteilung deterministisch mit eingespeistem Zufall.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { join } from 'node:path';
import { loadConfigurators, SRC } from './helpers.mjs';

const L = await import(join(SRC, 'layout-test.js'));
const S = await import(join(SRC, 'state.js'));
const alu = loadConfigurators().find((c) => c.name === 'aluverbund').data; // Standard compact, allowLayoutSwitch true

const memory = (init = {}) => {
    const m = new Map(Object.entries(init));
    return { get: (k) => (m.has(k) ? m.get(k) : null), set: (k, v) => m.set(k, v), map: m };
};
const withShare = (data, share, extra = {}) => ({ ...structuredClone(data), layoutTest: { share }, ...extra });
const never = () => { throw new Error('Zufall darf nicht gezogen werden'); };

test('ohne layoutTest bzw. share 0: Standard-Darstellung, nichts gezogen, nichts gemerkt', () => {
    for (const data of [alu, withShare(alu, 0)]) {
        const st = memory();
        assert.deepEqual(L.resolveLayout(data, { storage: st, random: never }), { layout: 'compact', source: 'default', group: null, forced: false });
        assert.equal(st.map.size, 0);
    }
});

test('neuer Besucher, share 30: Zufall < 30 % → andere Darstellung (test), sonst Standard (control); Zuteilung gemerkt', () => {
    const st = memory();
    const r = L.resolveLayout(withShare(alu, 30), { storage: st, random: () => 0.29 });
    assert.deepEqual(r, { layout: 'guided', source: 'test', group: 'test', forced: true });
    assert.equal(st.get(L.TEST_KEY), 'test');
    assert.equal(st.get(L.CHOICE_KEY), null, 'Zuteilung ist keine eigene Wahl');
    // zweiter Besuch: kein neuer Zufall, gleiche Gruppe
    assert.deepEqual(L.resolveLayout(withShare(alu, 30), { storage: st, random: never }).layout, 'guided');

    const st2 = memory();
    const c = L.resolveLayout(withShare(alu, 30), { storage: st2, random: () => 0.3 });
    assert.deepEqual(c, { layout: 'compact', source: 'test', group: 'control', forced: true });
    assert.equal(st2.get(L.TEST_KEY), 'control');
});

test('Gruppe statt Darstellung gemerkt: „test“ ist je Produkt die andere als dessen Standard', () => {
    const st = memory({ [L.TEST_KEY]: 'test' });
    assert.equal(L.resolveLayout(withShare(alu, 20), { storage: st, random: never }).layout, 'guided');
    assert.equal(L.resolveLayout(withShare(alu, 20, { layout: 'guided' }), { storage: st, random: never }).layout, 'compact');
});

test('eigene Wahl gewinnt und verhindert die Zuteilung; Link gewinnt vor allem', () => {
    const st = memory({ [L.CHOICE_KEY]: 'guided' });
    const r = L.resolveLayout(withShare(alu, 100), { storage: st, random: never });
    assert.deepEqual(r, { layout: 'guided', source: 'choice', group: null, forced: false });
    assert.equal(st.get(L.TEST_KEY), null, 'wer selbst gewählt hat, wird nicht zugeteilt');
    const link = L.resolveLayout(withShare(alu, 100), { linkLayout: 'compact', storage: memory(), random: () => 0 });
    assert.equal(link.layout, 'compact');
    assert.equal(link.source, 'link');
    assert.equal(link.group, 'test', 'Zuteilung trotzdem gezogen und gemerkt (für spätere Messung)');
});

test('Neuladen mit eigenem Hash (v=guided aus der Zuteilung) bleibt Quelle „test“', () => {
    const st = memory({ [L.TEST_KEY]: 'test' });
    assert.deepEqual(L.resolveLayout(withShare(alu, 30), { linkLayout: 'guided', storage: st, random: never }), { layout: 'guided', source: 'test', group: 'test', forced: true });
    // fremder Link mit anderer Darstellung gewinnt weiterhin
    assert.equal(L.resolveLayout(withShare(alu, 30), { linkLayout: 'compact', storage: st, random: never }).source, 'link');
});

test('Umschalten nach Zuteilung: eigene Wahl gewinnt künftig, Gruppe bleibt bekannt', () => {
    const st = memory();
    L.resolveLayout(withShare(alu, 100), { storage: st, random: () => 0 });
    L.rememberChoice('compact', st);
    const r = L.resolveLayout(withShare(alu, 100), { storage: st, random: never });
    assert.deepEqual(r, { layout: 'compact', source: 'choice', group: 'test', forced: false });
});

test('allowLayoutSwitch false: eigene Wahl ≠ Standard zählt nicht, Testzuteilung gilt trotzdem (forced)', () => {
    const data = withShare(alu, 50, { allowLayoutSwitch: false });
    const r = L.resolveLayout(data, { storage: memory({ [L.CHOICE_KEY]: 'guided', [L.TEST_KEY]: 'test' }), random: never });
    assert.equal(r.source, 'test');
    assert.equal(r.layout, 'guided');
    const s = S.createState(data, { layout: r.layout, layoutForced: r.forced });
    assert.equal(s.layout, 'guided', 'createState übernimmt die Zuteilung');
    assert.equal(S.createState(data, { layout: 'guided' }).layout, 'compact', 'ohne forced bleibt der Standard');
});

test('Test beendet (share 0) trotz gemerkter Zuteilung: Standard; Anteil wird auf 0–100 begrenzt; ohne Speicher kein Fehler', () => {
    assert.equal(L.resolveLayout(withShare(alu, 0), { storage: memory({ [L.TEST_KEY]: 'test' }), random: never }).source, 'default');
    assert.equal(L.testShare({ layoutTest: { share: 250 } }), 100);
    assert.equal(L.testShare({ layoutTest: { share: -5 } }), 0);
    const broken = { get: () => null, set: () => {} };
    assert.equal(L.resolveLayout(withShare(alu, 100), { storage: broken, random: () => 0.5 }).layout, 'guided');
});
