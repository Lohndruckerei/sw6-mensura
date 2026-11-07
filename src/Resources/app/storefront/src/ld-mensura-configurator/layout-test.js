/**
 * Welche Darstellung (kompakt/geführt) ein Besucher sieht – inkl. Testbetrieb (KONZEPT 3c, Vertrag layoutTest.share).
 *
 * Reihenfolge: geteilter Link (#lm?…&v=) → eigene Wahl des Besuchers → Testzuteilung → Standard laut Konfigurator-Daten.
 * Die Testzuteilung wird einmalig zufällig gezogen und gemerkt: „test“ = die andere als die Standard-Darstellung des
 * Produkts, „control“ = die Standard-Darstellung. Gemerkt wird die Gruppe, nicht die Darstellung, weil der Standard je
 * Produktprofil verschieden sein kann. Keine Messung hier – die Gruppe steht nur als data-Attribut am Konfigurator,
 * damit eine spätere Messung (nur mit Einwilligung) sie lesen kann.
 */

/** eigene Wahl über den Umschalter (bestehender Schlüssel) */
export const CHOICE_KEY = 'ldMensuraLayout';
/** Testzuteilung, getrennt von der eigenen Wahl */
export const TEST_KEY = 'ldMensuraLayoutTest';

const LAYOUTS = ['compact', 'guided'];

/** localStorage-Hülle; ohne Speicher (privater Modus, gesperrt) einfach ohne Merken */
export function browserStorage() {
    return {
        get(key) { try { return window.localStorage.getItem(key); } catch (e) { return null; } },
        set(key, value) { try { window.localStorage.setItem(key, value); } catch (e) { /* ohne Merken */ } },
    };
}

export function testShare(data) {
    const share = Number(data?.layoutTest?.share ?? 0);
    return Number.isFinite(share) ? Math.min(100, Math.max(0, share)) : 0;
}

export const otherLayout = (layout) => (layout === 'guided' ? 'compact' : 'guided');

/**
 * @returns {{ layout: string, source: 'link'|'choice'|'test'|'default', group: 'test'|'control'|null, forced: boolean }}
 *   forced = Darstellung auch ohne allowLayoutSwitch verwenden (Testzuteilung)
 */
export function resolveLayout(data, { linkLayout = null, storage = browserStorage(), random = Math.random } = {}) {
    const standard = LAYOUTS.includes(data.layout) ? data.layout : 'compact';
    const share = testShare(data);
    const allowed = (layout) => LAYOUTS.includes(layout) && (data.allowLayoutSwitch || layout === standard);

    // Gruppe bestimmen (auch wenn Link oder eigene Wahl gewinnen, damit eine spätere Messung die Zuteilung kennt)
    let group = null;
    if (share > 0) {
        const stored = storage.get(TEST_KEY);
        if (stored === 'test' || stored === 'control') {
            group = stored;
        } else if (!LAYOUTS.includes(storage.get(CHOICE_KEY))) {
            // nur neue Besucher ohne eigene Wahl werden zugeteilt
            group = random() * 100 < share ? 'test' : 'control';
            storage.set(TEST_KEY, group);
        }
    }

    const choice = storage.get(CHOICE_KEY);
    const assigned = group === 'test' ? otherLayout(standard) : standard;
    // der eigene Hash (v=) spiegelt nach dem Neuladen nur die Zuteilung wider → weiter „test“, nicht „link“
    if (group && !allowed(choice) && linkLayout === assigned) return { layout: assigned, source: 'test', group, forced: true };
    if (allowed(linkLayout)) return { layout: linkLayout, source: 'link', group, forced: false };
    if (allowed(choice)) return { layout: choice, source: 'choice', group, forced: false };
    if (group) return { layout: assigned, source: 'test', group, forced: true };
    return { layout: standard, source: 'default', group: null, forced: false };
}

/** Umschalter: eigene Wahl merken (gewinnt künftig vor der Testzuteilung) */
export function rememberChoice(layout, storage = browserStorage()) {
    if (LAYOUTS.includes(layout)) storage.set(CHOICE_KEY, layout);
}
