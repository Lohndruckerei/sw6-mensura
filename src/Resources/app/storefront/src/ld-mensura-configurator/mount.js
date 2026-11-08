/**
 * Bindet den Konfigurator an ein DOM-Element: lädt die Daten, zeichnet bei jeder Änderung neu und übersetzt
 * Klicks/Eingaben in Aktionen. Wird vom Shopware-JS-Plugin und von der statischen Testseite genutzt.
 */

import { MensuraController } from './controller.js';
import { FixtureClient, loadFixturesFromUrl, StoreApiClient, StorefrontClient } from './api-client.js';
import { renderConfigurator } from './render.js';
import { decodeHash, encodeHash, nextStepKey, guidedSteps, normalizeStep } from './state.js';
import { createFormatter, createTranslator } from './util.js';
import { browserStorage, rememberChoice, resolveLayout } from './layout-test.js';

let counter = 0;

export async function createClient(options) {
    if (options.fixtures) {
        const index = options.fixtureIndex || await (await fetch(`${options.fixtures}/index.json`)).json();
        const loaded = await loadFixturesFromUrl(options.fixtures, index);
        return new FixtureClient({ ...loaded, delayMs: options.fixtureDelayMs ?? 120 });
    }
    const storeApi = options.accessKey ? new StoreApiClient({ baseUrl: options.storeApiUrl || '/store-api', accessKey: options.accessKey, contextToken: options.contextToken }) : null;
    // im Shop: Storefront-Routen im Sitzungskontext (Shopware 6.7 gibt den Kontext-Token nicht heraus)
    if (options.storefrontUrl != null) return new StorefrontClient({ baseUrl: options.storefrontUrl, fallback: storeApi });
    return storeApi;
}

export async function mountConfigurator(el, options = {}) {
    const t = createTranslator(options.texts || {});
    const fmt = createFormatter(options.locale || document.documentElement.lang || 'de-DE', options.currency || 'EUR');
    const ctx = { t, fmt, uid: `lm${++counter}`, now: () => new Date(), fixtureMode: Boolean(options.fixtures) };

    el.innerHTML = `<div class="lm-loading" aria-busy="true">${t('loading')}</div>`;
    let client; let data;
    try {
        client = options.client || await createClient(options);
        data = options.data || await client.configurator(options.productId);
    } catch (e) {
        el.innerHTML = `<div class="lm-load-error" role="alert">${t('loadError')}</div>`;
        return null;
    }

    const app = document.createElement('div');
    const toast = document.createElement('div');
    toast.className = 'lm-toast';
    toast.setAttribute('role', 'status');
    toast.hidden = true;
    el.innerHTML = '';
    el.append(app, toast);

    const fromHash = decodeHash(data, window.location.hash);
    // Link → eigene Wahl → Testzuteilung (layoutTest.share) → Standard; Gruppe als data-Attribut für eine spätere Messung
    const storage = options.storage || browserStorage();
    const chosen = resolveLayout(data, { linkLayout: fromHash?.layout || null, storage, random: options.random || Math.random });
    el.setAttribute('data-lm-layout-source', chosen.source);
    if (chosen.group) {
        el.setAttribute('data-lm-test-group', chosen.group);
        el.setAttribute('data-lm-test-layout', chosen.group === 'test' ? (data.layout === 'guided' ? 'compact' : 'guided') : data.layout);
    }
    let frame = null;

    const controller = new MensuraController({
        data,
        client,
        debounceMs: options.debounceMs ?? 250,
        initial: fromHash?.config || null,
        layout: chosen.layout,
        layoutForced: chosen.forced,
        step: fromHash?.step || null,
        onChange: () => {
            // gebündelt neu zeichnen; requestAnimationFrame pausiert in verdeckten Tabs, daher Zeitgeber als Rückfall
            if (frame) return;
            const run = () => {
                if (!frame) return;
                cancelAnimationFrame(frame.raf);
                clearTimeout(frame.timer);
                frame = null;
                draw();
            };
            frame = { raf: requestAnimationFrame(run), timer: setTimeout(run, 100) };
        },
    });

    function draw() {
        const active = document.activeElement;
        const focusId = active && app.contains(active) ? active.id || null : null;
        const focusAction = !focusId && active && app.contains(active) ? active.getAttribute('data-lm-action') + '|' + (active.getAttribute('data-lm-value') || active.getAttribute('data-lm-quantity') || '') : null;
        let selection = null;
        try { selection = focusId && active.selectionStart != null ? [active.selectionStart, active.selectionEnd] : null; } catch (e) { selection = null; }
        app.innerHTML = renderConfigurator(ctx, controller.state);
        if (focusId) {
            const again = document.getElementById(focusId);
            if (again) {
                again.focus({ preventScroll: true });
                try { if (selection) again.setSelectionRange(...selection); } catch (e) { /* number-Felder kennen keine Auswahl */ }
            }
        } else if (focusAction) {
            const [action, value] = focusAction.split('|');
            const again = [...app.querySelectorAll(`[data-lm-action="${action}"]`)].find((b) => (b.getAttribute('data-lm-value') || b.getAttribute('data-lm-quantity') || '') === value);
            if (again) again.focus({ preventScroll: true });
        }
        const hash = encodeHash(controller.state);
        if (window.location.hash !== hash && options.updateHash !== false) {
            try { window.history.replaceState(null, '', hash); } catch (e) { /* z. B. file:// */ }
        }
    }

    function showToast(text) {
        toast.textContent = text;
        toast.hidden = false;
        clearTimeout(showToast.timer);
        showToast.timer = setTimeout(() => { toast.hidden = true; }, 2800);
    }

    function goNext() {
        controller.dispatch({ type: 'step', key: nextStepKey(data, controller.state) });
        el.scrollIntoView?.({ block: 'nearest', behavior: 'smooth' });
    }

    async function addToCart() {
        try {
            const res = await controller.addToCart();
            if (!res.ok) { showToast(t('cart.invalid')); return; }
            showToast(t('cart.added'));
            el.dispatchEvent(new CustomEvent('ldMensura:cartAdded', { bubbles: true, detail: res }));
            if (typeof options.onCartAdded === 'function') options.onCartAdded(res);
        } catch (e) {
            showToast(t('cart.error'));
        }
    }

    app.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-lm-action]');
        if (!btn || !app.contains(btn) || btn.disabled) return;
        const action = btn.getAttribute('data-lm-action');
        const value = btn.getAttribute('data-lm-value');
        const s = controller.state;
        switch (action) {
            case 'layout':
                controller.dispatch({ type: 'layout', value });
                rememberChoice(controller.state.layout, storage);
                el.setAttribute('data-lm-layout-source', 'choice');
                break;
            case 'material':
                controller.dispatch({ type: 'material', id: value });
                if (s.layout === 'guided') goNext();
                break;
            case 'option':
                controller.dispatch({ type: 'option', group: btn.getAttribute('data-lm-group'), value });
                if (s.layout === 'guided') goNext();
                break;
            case 'formatValue':
                controller.dispatch({ type: 'formatValue', width: Number(btn.getAttribute('data-lm-width')), height: Number(btn.getAttribute('data-lm-height')) });
                break;
            case 'delivery':
                controller.dispatch({ type: 'delivery', value });
                break;
            case 'matrix':
                controller.dispatch({ type: 'matrix', quantity: Number(btn.getAttribute('data-lm-quantity')), delivery: btn.getAttribute('data-lm-delivery') });
                break;
            case 'fix':
                controller.dispatch({ type: 'fix', fix: JSON.parse(btn.getAttribute('data-lm-fix') || '{}') });
                break;
            case 'rotate': controller.dispatch({ type: 'rotate' }); break;
            case 'step': controller.dispatch({ type: 'step', key: value }); break;
            case 'next': goNext(); break;
            case 'back': {
                const steps = guidedSteps(data, s);
                const i = steps.findIndex((x) => x.key === normalizeStep(s.step));
                controller.dispatch({ type: 'step', key: steps[Math.max(0, i - 1)].key });
                break;
            }
            case 'gross': controller.dispatch({ type: 'gross', value: value === '1' }); break;
            case 'dismissNotice': controller.dispatch({ type: 'dismissNotice' }); break;
            case 'retry': controller.lastRequestKey = null; controller.calculateNow(); break;
            case 'focus': document.getElementById(value)?.focus(); break;
            case 'cart': addToCart(); break;
            case 'share': {
                const url = window.location.href.split('#')[0] + encodeHash(controller.state);
                const done = () => showToast(t('share.copied'));
                if (navigator.clipboard?.writeText) navigator.clipboard.writeText(url).then(done, () => showToast(url));
                else showToast(url);
                break;
            }
            default:
        }
    });

    const fieldAction = (field, value, target) => {
        switch (field) {
            case 'material': return { type: 'material', id: value };
            case 'width': return { type: 'format', width: value };
            case 'height': return { type: 'format', height: value };
            case 'motifs': return { type: 'motifs', value };
            case 'perMotif': return { type: 'perMotif', value };
            case 'reference': return { type: 'reference', value };
            case 'delivery': return { type: 'delivery', value };
            case 'option': return { type: 'option', group: target.getAttribute('data-lm-group'), value };
            case 'formatValue': return { type: 'formatValue', group: target.getAttribute('data-lm-group'), value };
            default: return null;
        }
    };

    app.addEventListener('change', (event) => {
        const target = event.target.closest('[data-lm-field]');
        if (!target) return;
        const action = fieldAction(target.getAttribute('data-lm-field'), target.value, target);
        if (action) controller.dispatch(action);
    });

    // Tippen in Zahlen-/Textfeldern: Zustand sofort, Preis entprellt, neu zeichnen erst mit dem Ergebnis (Fokus bleibt)
    app.addEventListener('input', (event) => {
        const target = event.target.closest('input[data-lm-field]');
        if (!target) return;
        if (target.type === 'number' && (target.value === '' || Number(target.value) < 1)) return;
        const action = fieldAction(target.getAttribute('data-lm-field'), target.value, target);
        if (action) controller.dispatch(action, { render: false });
    });

    window.addEventListener('hashchange', () => {
        const parsed = decodeHash(data, window.location.hash);
        if (parsed && window.location.hash !== encodeHash(controller.state)) controller.dispatch({ type: 'restore', config: parsed.config, layout: parsed.layout, step: parsed.step });
    });

    draw();
    controller.calculateNow();
    return { controller, ctx, el };
}
