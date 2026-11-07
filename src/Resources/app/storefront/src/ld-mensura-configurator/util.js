/**
 * Kleine Helfer ohne DOM: Escaping, Übersetzung, Zahlen-/Datumsformat.
 * Wird im Browser (Shopware-Storefront) und in Node (tests/Frontend) genutzt.
 */

export function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

/**
 * Übersetzer aus einem flachen Objekt {schlüssel: text}. Platzhalter wie in Shopware-Snippets: %name%.
 * Fehlt ein Schlüssel, erscheint der Schlüssel selbst (fällt in Tests sofort auf).
 */
export function createTranslator(texts) {
    const t = (key, params = {}) => {
        let text = texts && Object.prototype.hasOwnProperty.call(texts, key) ? texts[key] : key;
        for (const [name, value] of Object.entries(params)) {
            text = text.split(`%${name}%`).join(String(value));
        }
        return text;
    };
    t.has = (key) => Boolean(texts && Object.prototype.hasOwnProperty.call(texts, key));
    /** wie t(), aber Snippet-Text wird escaped; Parameter müssen bereits sicheres HTML sein */
    t.html = (key, htmlParams = {}) => {
        let text = esc(texts && Object.prototype.hasOwnProperty.call(texts, key) ? texts[key] : key);
        for (const [name, value] of Object.entries(htmlParams)) text = text.split(`%${name}%`).join(String(value));
        return text;
    };
    return t;
}

/** Snippet-Datei (verschachtelt) → flaches Objekt ab einem Präfix, z. B. "ldMensura.configurator". */
export function flattenSnippets(tree, prefix) {
    let node = tree;
    for (const part of prefix.split('.')) node = node?.[part];
    const out = {};
    const walk = (obj, path) => {
        for (const [k, v] of Object.entries(obj || {})) {
            if (v && typeof v === 'object') walk(v, `${path}${k}.`);
            else out[`${path}${k}`] = v;
        }
    };
    walk(node, '');
    return out;
}

export function createFormatter(locale = 'de-DE', currency = 'EUR') {
    const cache = {};
    const nf = (min, max) => {
        const key = `${min}-${max}`;
        cache[key] ??= new Intl.NumberFormat(locale, { minimumFractionDigits: min, maximumFractionDigits: max });
        return cache[key];
    };
    const symbol = (() => {
        try {
            const part = new Intl.NumberFormat(locale, { style: 'currency', currency }).formatToParts(1).find((p) => p.type === 'currency');
            return part ? part.value : currency;
        } catch (e) {
            return currency;
        }
    })();
    return {
        locale,
        currency,
        /** Geldbetrag mit 2 Nachkommastellen, z. B. „353,00 €“ */
        money: (v) => `${nf(2, 2).format(v)} ${symbol}`,
        /** Geldbetrag mit bis zu 4 Nachkommastellen (je Stück bei großen Mengen) */
        money4: (v) => `${nf(2, 4).format(v)} ${symbol}`,
        int: (v) => nf(0, 0).format(v),
        num: (v, max = 3) => nf(0, max).format(v),
        /** ISO-Datum YYYY-MM-DD → „Fr., 14.11.“ (ohne Zeitzonen-Verschiebung) */
        date: (iso) => {
            if (!iso) return '';
            const [y, m, d] = iso.split('-').map(Number);
            return new Intl.DateTimeFormat(locale, { weekday: 'short', day: '2-digit', month: '2-digit', timeZone: 'UTC' }).format(new Date(Date.UTC(y, m - 1, d)));
        },
    };
}

/** Annahmeschluss „HH:MM“ gegenüber jetzt: {passed, minutesLeft} */
export function cutoffStatus(cutoff, now = new Date()) {
    if (!cutoff || !/^\d{2}:\d{2}$/.test(cutoff)) return null;
    const [h, m] = cutoff.split(':').map(Number);
    const minutesNow = now.getHours() * 60 + now.getMinutes();
    const left = h * 60 + m - minutesNow;
    return { passed: left <= 0, minutesLeft: Math.max(0, left), time: cutoff };
}

export function debounce(fn, ms) {
    let timer = null;
    const wrapped = (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => { timer = null; fn(...args); }, ms);
    };
    wrapped.flush = (...args) => { clearTimeout(timer); timer = null; fn(...args); };
    wrapped.cancel = () => { clearTimeout(timer); timer = null; };
    return wrapped;
}
