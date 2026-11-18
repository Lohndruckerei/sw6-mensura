/* GENERIERT aus ld-admin-design 1.1.0 (3d22274) – nicht hier ändern, sondern in ~/projekte/sw6-entwicklung-lohndruckerei/ld-admin-design/vorlage und ./sync.sh ausführen. */
/**
 * Plugin-Markierung (Testmodus) – gemeinsam für alle Lohndruckerei-Plugins.
 *
 * Eingeschaltet (Alt+Umschalt+M oder Schalter „Markieren“ neben „Design“) bekommt jeder Bereich eines
 * Plugins einen Rahmen mit Namensschild in der Plugin-Farbe: eigene Seiten, Karten und Erweiterungen in
 * Shopware-Seiten (Wurzelelement mit Präfix-Klasse), Menüpunkte und Reiter (Link enthält den Modulnamen).
 * Felder mit Beschriftung, aber ohne Hilfetext, werden rot gestrichelt; die Legende zählt sie und springt
 * zum nächsten. Gespeichert je Browser (localStorage), Standard aus.
 *
 * Jedes Plugin bringt diese Datei mit; der erste geladene Stand richtet die Markierung ein
 * (window.ldAdminMarker), alle weiteren melden sich nur an.
 */
import { MARKER_COLORS, MARKER_FALLBACK_COLOR, MARKER_ROUTES } from './constants';

const STORAGE_KEY = 'ld-admin-marker';
const ROOT_ATTR = 'data-ld-marker';
const FIELD = '.mt-field, .mt-base-field,.sw-field, .mt-textarea, .mt-select, .sw-select, .mt-switch, .sw-text-editor, .mt-text-editor';
const HELP = '.mt-help-text, .sw-help-text';
const LINKS = '.sw-admin-menu a[href], .sw-tabs a[href], a.sw-tabs-item[href]';
const SCAN_MS = 1000;

const DEFAULT_TEXTS = {
    title: 'Plugin-Markierung',
    missing: 'Hilfetext fehlt',
    missingCount: 'Felder ohne Hilfetext',
    next: 'Nächstes',
    off: 'Ausschalten',
    shortcut: 'Alt+Umschalt+M',
};

function createManager() {
    const plugins = [];
    const listeners = new Set();
    let marked = new Map();
    let missing = [];
    let nextIndex = 0;
    let timer = null;
    let legend = null;

    const read = () => {
        try {
            return window.localStorage.getItem(STORAGE_KEY) === '1';
        } catch {
            return false;
        }
    };

    const write = (value) => {
        try {
            if (value) {
                window.localStorage.setItem(STORAGE_KEY, '1');
            } else {
                window.localStorage.removeItem(STORAGE_KEY);
            }
        } catch {
            // Ohne Speicher gilt die Wahl nur bis zum Neuladen.
        }
    };

    const text = (key) => {
        const prefix = plugins[0]?.prefix;
        try {
            const snippet = `${prefix}-design.marker.${key}`;
            const value = Shopware.Snippet.t(snippet);
            if (value && value !== snippet) {
                return value;
            }
        } catch {
            // Snippets noch nicht geladen
        }

        return DEFAULT_TEXTS[key];
    };

    const ownerOf = (element) => element.closest('[data-ld-marker-plugin]');

    function mark(next, element, plugin) {
        next.set(element, plugin);
        if (marked.get(element) === plugin) {
            return;
        }
        element.setAttribute('data-ld-marker-plugin', plugin.label);
        element.style.setProperty('--ld-marker-color', plugin.color);
        if (window.getComputedStyle(element).position === 'static') {
            element.setAttribute('data-ld-marker-relative', '');
        }
    }

    function unmark(element) {
        element.removeAttribute('data-ld-marker-plugin');
        element.removeAttribute('data-ld-marker-relative');
        element.style.removeProperty('--ld-marker-color');
    }

    function scan() {
        const next = new Map();

        plugins.forEach((plugin) => {
            const selector = `[class^="${plugin.prefix}-"], [class*=" ${plugin.prefix}-"]`;
            document.querySelectorAll(selector).forEach((element) => {
                // Im Hauptmenü tragen Shopware-Einträge die Menü-ID als Klasse (z. B. `ld-ordo`) –
                // dort zählt nur der Link (unten), nicht die Klasse.
                if (element.parentElement?.closest(selector)
                    || element.closest('[data-ld-marker-legend], .sw-admin-menu')
                    || element.classList.contains(`${plugin.prefix}-design-switch`)) {
                    return;
                }
                mark(next, element, plugin);
            });

            document.querySelectorAll(LINKS).forEach((link) => {
                const href = (link.getAttribute('href') || '').toLowerCase();
                if (plugin.routes.some((route) => href.includes(route))) {
                    mark(next, link, plugin);
                }
            });
        });

        marked.forEach((plugin, element) => {
            if (!next.has(element)) {
                unmark(element);
            }
        });
        marked = next;

        const found = [];
        document.querySelectorAll(FIELD).forEach((field) => {
            const label = field.querySelector('label');
            const isMissing = !field.parentElement?.closest(FIELD)
                && ownerOf(field)
                && label
                && label.textContent.trim() !== ''
                && !field.querySelector(HELP);

            if (isMissing) {
                found.push(field);
                field.setAttribute('data-ld-marker-missing', '');
                label.setAttribute('data-ld-marker-missing-label', '');
            } else if (field.hasAttribute('data-ld-marker-missing')) {
                field.removeAttribute('data-ld-marker-missing');
                field.querySelector('[data-ld-marker-missing-label]')?.removeAttribute('data-ld-marker-missing-label');
            }
        });
        missing = found;
        renderLegend();
    }

    function clear() {
        marked.forEach((plugin, element) => unmark(element));
        marked = new Map();
        document.querySelectorAll('[data-ld-marker-missing], [data-ld-marker-missing-label]').forEach((element) => {
            element.removeAttribute('data-ld-marker-missing');
            element.removeAttribute('data-ld-marker-missing-label');
        });
        missing = [];
    }

    function showNext() {
        if (missing.length === 0) {
            return;
        }
        nextIndex %= missing.length;
        const field = missing[nextIndex];
        nextIndex += 1;
        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
        field.setAttribute('data-ld-marker-focus', '');
        window.setTimeout(() => field.removeAttribute('data-ld-marker-focus'), 1600);
    }

    function renderLegend() {
        if (!manager.active) {
            legend?.remove();
            legend = null;

            return;
        }

        if (!legend) {
            legend = document.createElement('div');
            legend.setAttribute('data-ld-marker-legend', '');
            legend.setAttribute('role', 'status');
            legend.addEventListener('click', (event) => {
                const action = event.target.closest('[data-action]')?.getAttribute('data-action');
                if (action === 'next') {
                    showNext();
                } else if (action === 'off') {
                    manager.toggle(false);
                }
            });
            document.body.appendChild(legend);
        }

        const counts = new Map();
        marked.forEach((plugin) => counts.set(plugin, (counts.get(plugin) ?? 0) + 1));
        const escape = (value) => String(value).replace(/[&<>"]/g, (c) => `&#${c.charCodeAt(0)};`);

        const html = `
<div data-ld-marker-legend-title>${escape(text('title'))}</div>
${[...plugins].sort((a, b) => a.label.localeCompare(b.label)).map((plugin) => `
<div data-ld-marker-legend-row${counts.has(plugin) ? '' : ' data-ld-marker-legend-idle'}>
    <span data-ld-marker-legend-dot style="background:${plugin.color}"></span>${escape(plugin.label)}
</div>`).join('')}
<div data-ld-marker-legend-row data-ld-marker-legend-missing>
    <span data-ld-marker-legend-dash></span>${escape(text('missingCount'))}: <b>${missing.length}</b>
    ${missing.length ? `<button type="button" data-action="next">${escape(text('next'))}</button>` : ''}
</div>
<button type="button" data-action="off" data-ld-marker-legend-off>${escape(text('off'))} · ${escape(text('shortcut'))}</button>`;

        // Nur bei Änderung neu zeichnen, damit Hover und Klicks nicht jede Sekunde verloren gehen.
        if (legend.ldHtml !== html) {
            legend.innerHTML = html;
            legend.ldHtml = html;
        }
    }

    function apply() {
        const html = document.documentElement;
        if (manager.active) {
            html.setAttribute(ROOT_ATTR, '');
            html.style.setProperty('--ld-marker-missing-text', JSON.stringify(` ⚠ ${text('missing')}`));
            scan();
            timer ??= window.setInterval(scan, SCAN_MS);
        } else {
            html.removeAttribute(ROOT_ATTR);
            window.clearInterval(timer);
            timer = null;
            clear();
            renderLegend();
        }
        listeners.forEach((listener) => listener(manager.active));
    }

    const manager = {
        version: 1,
        active: false,
        plugins,

        register(plugin) {
            if (!plugins.some((entry) => entry.prefix === plugin.prefix)) {
                plugins.push(plugin);
            }
            if (manager.active && document.body) {
                scan();
            }
        },

        toggle(force) {
            const value = typeof force === 'boolean' ? force : !manager.active;
            if (value === manager.active) {
                return;
            }
            manager.active = value;
            write(value);
            apply();
        },

        onChange(listener) {
            listeners.add(listener);

            return () => listeners.delete(listener);
        },
    };

    document.addEventListener('keydown', (event) => {
        if (event.altKey && event.shiftKey && !event.ctrlKey && !event.metaKey && event.code === 'KeyM') {
            event.preventDefault();
            manager.toggle();
        }
    });

    if (read()) {
        manager.active = true;
        const start = () => apply();
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start, { once: true });
        } else {
            window.setTimeout(start, 0);
        }
    }

    return manager;
}

export default function registerMarker() {
    window.ldAdminMarker ??= createManager();
    window.ldAdminMarker.register({
        prefix: 'lm',
        label: 'LdMensura'.replace(/^Ld/, ''),
        routes: MARKER_ROUTES.lm ?? ['ld-mensura'.replace('-', '/'), 'ld-mensura'],
        color: MARKER_COLORS.lm ?? MARKER_FALLBACK_COLOR,
    });
}
