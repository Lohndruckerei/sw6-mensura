/**
 * Steuerung ohne DOM: hält den Zustand, nimmt Aktionen entgegen und holt Preise entprellt von der Store-API.
 * Das Shopware-JS-Plugin (configurator.plugin.js) übersetzt nur DOM-Ereignisse in Aktionen und zeichnet neu.
 */

import { apply, CONFIG_ACTIONS, createState, toConfiguration, configKey } from './state.js';
import { debounce } from './util.js';

export class MensuraController {
    constructor({ data, client, onChange = () => {}, debounceMs = 250, initial = null, layout = null, step = null, layoutForced = false }) {
        this.client = client;
        this.onChange = onChange;
        this.state = createState(data, { initial, layout, step, layoutForced });
        this.seq = 0;
        this.inflight = null;
        this.abort = null;
        this.lastRequestKey = null;
        this.scheduled = debounce(() => this.calculate(), debounceMs);
    }

    dispatch(action, { render = true } = {}) {
        const before = this.state;
        this.state = apply(this.state, action);
        const layoutNeedsMatrix = action.type === 'layout' && before.layout !== this.state.layout && this.state.layout === 'guided';
        if (CONFIG_ACTIONS.has(action.type) || layoutNeedsMatrix) this.scheduled();
        if (render) this.onChange(this.state, action);
        return this.state;
    }

    /** sofort rechnen (z. B. beim Start), ohne Entprellung */
    calculateNow() {
        this.scheduled.cancel();
        return this.calculate();
    }

    async calculate() {
        const configuration = toConfiguration(this.state);
        const matrix = this.state.layout === 'guided';
        const key = configKey(configuration) + (matrix ? '|m' : '');
        if (key === this.lastRequestKey && this.state.result && !this.state.netError) return this.state;
        this.lastRequestKey = key;
        const seq = ++this.seq;
        if (this.abort) this.abort.abort();
        this.abort = typeof AbortController !== 'undefined' ? new AbortController() : null;
        this.state = apply(this.state, { type: 'pending' });
        this.onChange(this.state, { type: 'pending' });
        const run = (async () => {
            try {
                const result = await this.client.calculate(configuration, { matrix, signal: this.abort?.signal });
                if (seq !== this.seq) return;
                this.state = apply(this.state, { type: 'result', result });
                this.onChange(this.state, { type: 'result' });
            } catch (e) {
                if (seq !== this.seq || e?.name === 'AbortError') return;
                this.lastRequestKey = null;
                this.state = apply(this.state, { type: 'netError' });
                this.onChange(this.state, { type: 'netError' });
            }
        })();
        this.inflight = run;
        await run;
        return this.state;
    }

    /** für Tests: wartet, bis keine Berechnung mehr ansteht */
    async idle() {
        this.scheduled.flush();
        while (this.inflight) {
            const p = this.inflight;
            await p;
            if (p === this.inflight) this.inflight = null;
        }
        return this.state;
    }

    async addToCart() {
        const configuration = toConfiguration(this.state);
        const res = await this.client.addToCart(configuration);
        if (!res.ok && res.result) {
            this.state = apply(this.state, { type: 'result', result: res.result });
            this.onChange(this.state, { type: 'result' });
        }
        return res;
    }
}
