/**
 * Zugriff auf die Mensura-Store-API (docs/contract/store-api.yaml) – und ein Fixture-Client, der dieselbe
 * Schnittstelle gegen docs/contract/fixtures bedient, solange das Backend die Store-API noch nicht liefert
 * (statische Testseite, Node-Tests). Beide Clients haben dieselben Methoden:
 *   configurator(productId) → Konfigurator-Daten
 *   calculate(configuration, {matrix, signal}) → Ergebnis (calculation-result.schema.json)
 *   addToCart(configuration) → {ok, result?}
 */

import { activeGroupNames, allowedValues, configKey, defaultValue, deliveryGroup, findValue, getGroup, localErrors, materialById } from './state.js';

export class StoreApiClient {
    constructor({ baseUrl = '/store-api', accessKey, contextToken = null, fetchImpl = null } = {}) {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.accessKey = accessKey;
        this.contextToken = contextToken;
        this.fetch = fetchImpl || ((...args) => globalThis.fetch(...args));
    }

    headers() {
        const h = { Accept: 'application/json', 'Content-Type': 'application/json', 'sw-access-key': this.accessKey };
        if (this.contextToken) h['sw-context-token'] = this.contextToken;
        return h;
    }

    async request(method, path, body, signal) {
        const res = await this.fetch(`${this.baseUrl}${path}`, { method, headers: this.headers(), body: body ? JSON.stringify(body) : undefined, signal, credentials: 'same-origin' });
        const token = res.headers?.get?.('sw-context-token');
        if (token) this.contextToken = token;
        let json = null;
        try { json = await res.json(); } catch (e) { json = null; }
        return { status: res.status, ok: res.ok, json };
    }

    async configurator(productId) {
        const r = await this.request('GET', `/ld-mensura/product/${encodeURIComponent(productId)}/configurator`);
        if (!r.ok) throw new Error(`configurator ${r.status}`);
        return r.json;
    }

    async calculate(configuration, { matrix = false, signal } = {}) {
        const r = await this.request('POST', '/ld-mensura/calculate', { configuration, matrix }, signal);
        if (!r.ok || !r.json) throw new Error(`calculate ${r.status}`);
        return r.json;
    }

    async addToCart(configuration) {
        const r = await this.request('POST', '/ld-mensura/cart/add', { configuration });
        if (r.status === 400 && r.json) return { ok: false, result: r.json };
        if (!r.ok) throw new Error(`cart ${r.status}`);
        return { ok: true, cart: r.json };
    }
}

/** n Werktage (Mo–Fr) nach `from` als YYYY-MM-DD – nur für den Fixture-Client (Liefertermine rechnet sonst das Backend). */
export function addWorkdays(from, days) {
    const d = new Date(Date.UTC(from.getFullYear(), from.getMonth(), from.getDate()));
    let n = 0;
    while (n < days) {
        d.setUTCDate(d.getUTCDate() + 1);
        const wd = d.getUTCDay();
        if (wd !== 0 && wd !== 6) n++;
    }
    return d.toISOString().slice(0, 10);
}

/**
 * Fixture-Client: beantwortet Anfragen aus den Beispielfällen des Vertrags.
 * - Anfrage gleicht einem Beispielfall (`*.request.json`) → dessen Sollergebnis, unverändert.
 * - sonst: Normalisierung wie vom Backend beschrieben (Gruppen, Grenzen mit `fix`), aber OHNE Preis
 *   (Hinweis `fixture.no_price`) – das Frontend rechnet nie selbst.
 * - Liefertermine und Preismatrix: Termine aus `deliveryDays`, Matrixpreise nur dort, wo ein Beispielfall passt.
 */
export class FixtureClient {
    constructor({ configurators = [], cases = [], now = () => new Date(), delayMs = 0 } = {}) {
        this.configurators = configurators;
        this.cases = cases.map((c) => ({ ...c, key: configKey(c.request) }));
        this.now = now;
        this.delayMs = delayMs;
        this.calls = [];
        this.cart = [];
    }

    async wait() {
        if (this.delayMs) await new Promise((r) => setTimeout(r, this.delayMs));
    }

    async configurator(productId) {
        await this.wait();
        const data = this.configurators.find((c) => c.productId === productId) || (productId ? null : this.configurators[0]);
        if (!data) throw new Error('configurator 404');
        return structuredClone(data);
    }

    findCase(configuration) {
        const key = configKey(configuration);
        return this.cases.find((c) => c.key === key) || null;
    }

    async calculate(configuration, { matrix = false } = {}) {
        this.calls.push({ configuration: structuredClone(configuration), matrix });
        await this.wait();
        const result = this.respond(configuration);
        if (matrix) result.matrix = this.matrix(result.configuration);
        return result;
    }

    respond(configuration) {
        const hit = this.findCase(configuration);
        const data = this.configurators.find((c) => c.productId === configuration.productId);
        if (hit) {
            const result = structuredClone(hit.expected);
            if (data && !result.delivery) result.delivery = this.delivery(data, result.configuration);
            return result;
        }
        if (!data) {
            return { apiVersion: 1, valid: false, errors: [{ code: 'product.not_found', message: 'Produkt unbekannt', field: null }], hints: [], configuration };
        }
        return this.normalize(data, configuration);
    }

    normalize(data, configuration) {
        const material = materialById(data, configuration.materialId) || materialById(data, data.defaults.materialId);
        const hints = [];
        const config = { ...structuredClone(configuration), materialId: material.id, options: {} };
        const active = activeGroupNames(data, { ...configuration, materialId: material.id });
        for (const name of active) {
            const group = getGroup(data, name);
            const wanted = configuration.options?.[name];
            const allowed = allowedValues(group, material);
            if (wanted && allowed.some((v) => v.technicalName === wanted)) { config.options[name] = wanted; continue; }
            const def = defaultValue(data, name, material);
            if (def) config.options[name] = def;
            if (wanted) {
                hints.push({ code: 'option.reset', message: `${group.name} „${findValue(group, wanted)?.name || wanted}“ gibt es bei ${material.calculation.name} nicht – jetzt „${findValue(group, def)?.name || def}“`, field: `options.${name}` });
            }
        }
        for (const name of Object.keys(configuration.options || {})) {
            if (!active.includes(name)) hints.push({ code: 'option.removed', message: `${getGroup(data, name)?.name || name} entfällt bei ${material.calculation.name}`, field: `options.${name}` });
        }
        const dg = deliveryGroup(data);
        if (dg && !findValue(dg, config.deliveryValue)) config.deliveryValue = defaultValue(data, dg.technicalName, material) || dg.values[0]?.technicalName;
        const errors = localErrors(data, config).map((e) => {
            const dim = e.field === 'format.width' ? 'Breite' : e.field === 'format.height' ? 'Höhe' : 'Menge';
            const word = e.code.endsWith('_min') ? 'mindestens' : 'höchstens';
            return { code: e.code, message: `${material.name}: ${dim} ${word} ${e.limit.toLocaleString('de-DE')}${e.field.startsWith('format') ? ' mm' : ''}`, field: e.field, fix: e.fix };
        });
        const result = { apiVersion: 1, valid: errors.length === 0, errors, hints, configuration: config, activeGroups: active };
        if (result.valid) {
            // gültig, aber kein Beispielfall: Preis gibt es nur vom Backend
            hints.push({ code: 'fixture.no_price', message: 'Beispielmodus: Preis nur für die Beispielfälle des Vertrags', field: null });
            result.valid = false;
            result.errors = [];
        }
        result.delivery = this.delivery(data, config);
        return result;
    }

    delivery(data, config) {
        const dg = deliveryGroup(data);
        if (!dg) return [];
        const material = materialById(data, config.materialId);
        return dg.values.map((v) => ({
            value: v.technicalName,
            available: dg.values.length > 0 && allowedValues(dg, material).includes(v),
            date: v.deliveryDays != null ? addWorkdays(this.now(), v.deliveryDays) : null,
            reason: null,
        }));
    }

    matrix(config) {
        const data = this.configurators.find((c) => c.productId === config.productId);
        if (!data) return [];
        const dg = deliveryGroup(data);
        const deliveries = dg ? dg.values.map((v) => v.technicalName) : [config.deliveryValue];
        const rows = [];
        for (const q of data.quantitySteps || []) {
            for (const d of deliveries) {
                const probe = { ...config, perMotif: q };
                if (d) probe.deliveryValue = d;
                const hit = this.findCase(probe);
                rows.push({ quantity: q, delivery: d, total: hit?.expected?.price?.total ?? null });
            }
        }
        return rows;
    }

    async addToCart(configuration) {
        await this.wait();
        const result = this.respond(configuration);
        if (!result.valid) return { ok: false, result };
        this.cart.push(structuredClone(configuration));
        return { ok: true, cart: { lineItems: this.cart.length } };
    }
}

/** Fixtures im Browser laden (statische Testseite): Index-Datei listet die Beispielfälle. */
export async function loadFixturesFromUrl(baseUrl, index) {
    const get = async (path) => (await fetch(`${baseUrl}/${path}`)).json();
    const configurators = await Promise.all(index.configurators.map((n) => get(`configurator/${n}.json`)));
    const cases = await Promise.all(index.cases.map(async (n) => ({ name: n, request: await get(`calculate/${n}.request.json`), expected: await get(`calculate/${n}.expected.json`) })));
    return { configurators, cases };
}
