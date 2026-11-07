/**
 * Gemeinsames Zustandsmodell beider Konfigurator-Varianten (kompakt und geführt).
 *
 * Rein funktional und ohne DOM: `apply(state, action)` liefert einen neuen Zustand.
 * Preise werden hier NIE berechnet – sie kommen ausschließlich aus der Store-API (`calculate`).
 * Lokal geprüft werden nur Dinge, die für sofortige Rückmeldung nötig sind (Formatgrenzen, sichtbare Gruppen);
 * maßgeblich ist immer das Ergebnis des Backends (docs/contract, KONZEPT 3.7).
 */

const groupCache = new WeakMap();

export function groupMap(data) {
    if (!groupCache.has(data)) groupCache.set(data, new Map(data.groups.map((g) => [g.technicalName, g])));
    return groupCache.get(data);
}

export const getGroup = (data, name) => groupMap(data).get(name);
export const materialById = (data, id) => data.materials.find((m) => m.id === id) || null;
export const materialByTechnicalName = (data, name) => data.materials.find((m) => m.technicalName === name) || null;
export const deliveryGroup = (data) => data.groups.find((g) => g.type === 'delivery' && g.mode !== 'hidden') || null;

export function isAvailable(value, material) {
    const only = value.availableForMaterials;
    return !only || only.length === 0 || (material && only.includes(material.technicalName));
}

export function allowedValues(group, material) {
    return group ? group.values.filter((v) => isAvailable(v, material)) : [];
}

export function findValue(group, technicalName) {
    return group?.values.find((v) => v.technicalName === technicalName) || null;
}

/**
 * Standardwert einer Gruppe: Vorbelegung aus `defaults` (vom Backend), sonst der erste erlaubte Wert.
 * Nicht verpflichtende Gruppen ohne Vorbelegung bleiben leer (kostenpflichtige Optionen nie vorauswählen).
 */
export function defaultValue(data, groupName, material) {
    const group = getGroup(data, groupName);
    const allowed = allowedValues(group, material);
    const preset = data.defaults?.options?.[groupName];
    if (preset && allowed.some((v) => v.technicalName === preset)) return preset;
    if (!group?.required) return null;
    return allowed[0]?.technicalName ?? null;
}

/**
 * Aktive Gruppen in Reihenfolge: Start-Set → Folge-Set des Materials → Folge-Sets der gewählten Werte (rekursiv).
 * Enthält auch Gruppen mit mode=hidden (deren Wert gehört in die Konfiguration), nicht aber die Lieferzeit-Gruppe.
 */
export function activeGroupNames(data, config) {
    const material = materialById(data, config.materialId);
    const queue = [];
    if (data.startSet) queue.push(data.startSet);
    if (material?.optionSet) queue.push(material.optionSet);
    const seenSets = new Set();
    const names = [];
    while (queue.length) {
        const set = queue.shift();
        if (!set || seenSets.has(set)) continue;
        seenSets.add(set);
        for (const name of data.sets?.[set] || []) {
            const group = getGroup(data, name);
            if (!group || group.type === 'delivery' || names.includes(name)) continue;
            names.push(name);
            const chosen = config.options?.[name] ?? defaultValue(data, name, material);
            const follow = findValue(group, chosen)?.optionSet;
            if (follow) queue.push(follow);
        }
    }
    return names;
}

/** Sichtbare Gruppen (ohne mode=hidden). Format-Gruppen stehen im Abschnitt Endformat, nicht bei den Optionen. */
export function visibleGroups(data, state) {
    const fresh = isFresh(state) && Array.isArray(state.result?.activeGroups) ? state.result.activeGroups : activeGroupNames(data, state.config);
    return fresh.map((n) => getGroup(data, n)).filter((g) => g && g.mode !== 'hidden' && g.type !== 'delivery');
}

export const optionGroups = (data, state) => visibleGroups(data, state).filter((g) => g.type !== 'format');
export const formatGroup = (data, state) => visibleGroups(data, state).find((g) => g.type === 'format') || null;

/**
 * Optionen nach einem Wechsel (Material oder Wert mit Folge-Set) an die neuen aktiven Gruppen anpassen.
 * Regeln (KONZEPT 3.7): gemeinsame Gruppen behalten ihren Wert, solange erlaubt; wegfallende Gruppen werden gemerkt
 * und bei Rückkehr wiederhergestellt; sonst gilt der Standardwert. Liefert die Änderungen für die Hinweis-Box.
 */
export function syncOptions(data, prevConfig, nextConfig, memory = {}) {
    const material = materialById(data, nextConfig.materialId);
    const before = activeGroupNames(data, prevConfig);
    const options = { ...nextConfig.options };
    const mem = { ...memory };
    const changes = [];
    for (let round = 0; round < 8; round++) {
        const after = activeGroupNames(data, { ...nextConfig, options });
        let changed = false;
        for (const name of after) {
            const group = getGroup(data, name);
            const allowed = allowedValues(group, material);
            const current = options[name];
            if (current && allowed.some((v) => v.technicalName === current)) continue;
            if (!current && mem[name] && allowed.some((v) => v.technicalName === mem[name])) {
                options[name] = mem[name];
                delete mem[name];
                changes.push({ code: 'option.restored', group: name, to: options[name] });
                changed = true;
                continue;
            }
            const def = defaultValue(data, name, material);
            if (current) changes.push({ code: 'option.reset', group: name, from: current, to: def });
            else if (!before.includes(name) && group.mode !== 'hidden') changes.push({ code: 'option.added', group: name });
            if (def) options[name] = def;
            else delete options[name];
            if (current || def) changed = true;
        }
        for (const name of Object.keys(options)) {
            if (after.includes(name)) continue;
            const value = options[name];
            mem[name] = value;
            const prevMaterial = materialById(data, prevConfig.materialId);
            if (value && value !== defaultValue(data, name, prevMaterial)) changes.push({ code: 'option.removed', group: name, from: value });
            delete options[name];
            changed = true;
        }
        if (!changed) break;
    }
    return { options, memory: mem, changes };
}

/** Formatgrenzen und Mengengrenzen des Materials – sofortige Rückmeldung; das Backend prüft dasselbe. */
export function localErrors(data, config) {
    const material = materialById(data, config.materialId);
    const l = material?.limits || {};
    const errors = [];
    const check = (dim, value, min, max) => {
        if (min != null && value < min) errors.push({ code: `format.${dim}_min`, field: `format.${dim}`, fix: { [`format.${dim}`]: min }, limit: min, local: true });
        if (max != null && value > max) errors.push({ code: `format.${dim}_max`, field: `format.${dim}`, fix: { [`format.${dim}`]: max }, limit: max, local: true });
    };
    check('width', config.format.width, l.widthMin, l.widthMax);
    check('height', config.format.height, l.heightMin, l.heightMax);
    if (l.maxMotifs != null && config.motifs > l.maxMotifs) errors.push({ code: 'quantity.motifs_max', field: 'motifs', fix: { motifs: l.maxMotifs }, limit: l.maxMotifs, local: true });
    if (l.maxQuantity != null && config.motifs * config.perMotif > l.maxQuantity) {
        errors.push({ code: 'quantity.max', field: 'perMotif', fix: { perMotif: Math.max(1, Math.floor(l.maxQuantity / config.motifs)) }, limit: l.maxQuantity, local: true });
    }
    return errors;
}

/** Konfiguration, wie sie an die Store-API geht (configuration.schema.json). */
export function toConfiguration(state) {
    const { data, config } = state;
    const active = activeGroupNames(data, config);
    const options = {};
    for (const name of active) if (config.options[name]) options[name] = config.options[name];
    const out = {
        schema: 1,
        productId: data.productId,
        materialId: config.materialId,
        format: { width: config.format.width, height: config.format.height },
        motifs: config.motifs,
        perMotif: config.perMotif,
        options,
    };
    // Festformat: Die Engine erwartet format.formatValueId (Vertragsvorschlag: optionale id an Format-Werten).
    // Ohne id bleibt nur options[<Format-Gruppe>].
    for (const name of active) {
        const group = getGroup(data, name);
        if (group?.type !== 'format') continue;
        const value = findValue(group, config.options[name]);
        if (value?.id) out.format.formatValueId = value.id;
    }
    if (config.deliveryValue) out.deliveryValue = config.deliveryValue;
    if (config.reference) out.reference = config.reference;
    return out;
}

/** Stabiler Schlüssel einer Konfiguration (Objekt-Schlüssel sortiert) – erkennt, ob ein Ergebnis zur Auswahl passt. */
export function configKey(configuration) {
    const sort = (v) => (Array.isArray(v) ? v.map(sort) : v && typeof v === 'object' ? Object.fromEntries(Object.keys(v).sort().map((k) => [k, sort(v[k])])) : v);
    return JSON.stringify(sort(configuration));
}

export const isFresh = (state) => Boolean(state.result) && state.resultKey === configKey(toConfiguration(state));

/** Fehler zur aktuellen Auswahl: vom Backend, solange das Ergebnis frisch ist, sonst die lokale Grenzprüfung. */
export function currentErrors(state) {
    if (isFresh(state)) return state.result.errors || [];
    return localErrors(state.data, state.config);
}

export const currentHints = (state) => (isFresh(state) ? state.result.hints || [] : []);

export function canOrder(state) {
    return isFresh(state) && state.result.valid === true && Boolean(state.result.price) && !state.pending;
}

const clampInt = (v, min = 1) => {
    const n = Math.round(Number(v));
    return Number.isFinite(n) && n >= min ? n : min;
};

function initialConfig(data, partial) {
    const d = data.defaults;
    const materialId = partial?.materialId && materialById(data, partial.materialId) ? partial.materialId : d.materialId;
    return {
        materialId,
        format: { width: clampInt(partial?.format?.width ?? d.format.width), height: clampInt(partial?.format?.height ?? d.format.height) },
        motifs: clampInt(partial?.motifs ?? d.motifs),
        perMotif: clampInt(partial?.perMotif ?? d.perMotif),
        options: { ...d.options, ...(partial?.options || {}) },
        deliveryValue: partial?.deliveryValue ?? d.deliveryValue ?? null,
        reference: partial?.reference ?? '',
    };
}

/**
 * Neuer Zustand aus den Konfigurator-Daten (GET …/configurator), optional mit einer Teil-Konfiguration
 * (z. B. aus dem URL-Hash) und einer gewünschten Darstellung.
 */
export function createState(data, { initial = null, layout = null, step = null } = {}) {
    const config = initialConfig(data, initial);
    const synced = syncOptions(data, config, config, {});
    config.options = synced.options;
    const dg = deliveryGroup(data);
    if (dg && !findValue(dg, config.deliveryValue)) config.deliveryValue = defaultValue(data, dg.technicalName, materialById(data, config.materialId)) || dg.values[0]?.technicalName || null;
    if (!dg) config.deliveryValue = initial?.deliveryValue ?? data.defaults.deliveryValue ?? null;
    const wanted = layout === 'compact' || layout === 'guided' ? layout : null;
    return {
        data,
        layout: wanted && (data.allowLayoutSwitch || wanted === data.layout) ? wanted : data.layout,
        step: normalizeStep(step) || 'material',
        gross: false,
        config,
        memory: synced.memory,
        changes: [],
        changeContext: null,
        result: null,
        resultKey: null,
        pending: false,
        netError: false,
    };
}

/** Wert unter einem Pfad wie „format.height“ oder „perMotif“ setzen (für `fix`-Vorschläge des Backends). */
function setPath(config, path, value) {
    const next = { ...config, format: { ...config.format }, options: { ...config.options } };
    const parts = path.split('.');
    if (parts[0] === 'format' && parts.length === 2) next.format[parts[1]] = clampInt(value);
    else if (parts[0] === 'options' && parts.length === 2) next.options[parts[1]] = String(value);
    else if (parts[0] === 'motifs' || parts[0] === 'perMotif') next[parts[0]] = clampInt(value);
    else if (parts[0] === 'deliveryValue') next.deliveryValue = String(value);
    else if (parts[0] === 'materialId') next.materialId = String(value);
    return next;
}

function withFormatGroup(data, state, config) {
    // Bei freier Eingabe: Festformat-Gruppe auf einen passenden Wert bzw. „frei“ (Wert ohne Maße) stellen
    const group = formatGroup(data, { ...state, config, result: null });
    if (!group) return config;
    const match = group.values.find((v) => v.width === config.format.width && v.height === config.format.height)
        || group.values.find((v) => v.width == null && v.height == null);
    return match ? { ...config, options: { ...config.options, [group.technicalName]: match.technicalName } } : config;
}

/** Aktionen, die die Konfiguration ändern (→ neue Preisberechnung). */
export const CONFIG_ACTIONS = new Set(['material', 'format', 'rotate', 'motifs', 'perMotif', 'option', 'delivery', 'matrix', 'fix', 'restore', 'reference']);

export function apply(state, action) {
    const { data } = state;
    switch (action.type) {
        case 'material': {
            const material = materialById(data, action.id);
            if (!material || material.id === state.config.materialId) return state;
            const next = { ...state.config, materialId: material.id };
            const synced = syncOptions(data, state.config, next, state.memory);
            const changes = [...synced.changes];
            for (const err of localErrors(data, next)) if (err.code.startsWith('format.')) changes.push({ code: 'format.check', error: err });
            return {
                ...state,
                config: { ...next, options: synced.options },
                memory: synced.memory,
                changes,
                changeContext: { material: material.id, previous: state.config.materialId },
            };
        }
        case 'format': {
            const format = { ...state.config.format };
            if (action.width != null) format.width = clampInt(action.width);
            if (action.height != null) format.height = clampInt(action.height);
            return { ...state, config: withFormatGroup(data, state, { ...state.config, format }) };
        }
        case 'formatValue': {
            // Festformat aus einer Gruppe vom Typ „format“ oder einer Schnellwahl {width, height}
            const group = action.group ? getGroup(data, action.group) : null;
            const value = group ? findValue(group, action.value) : null;
            const width = value?.width ?? action.width;
            const height = value?.height ?? action.height;
            let config = { ...state.config };
            if (group && value) config.options = { ...config.options, [group.technicalName]: value.technicalName };
            if (width && height) config.format = { width: clampInt(width), height: clampInt(height) };
            else if (!group) return state;
            if (!value) config = withFormatGroup(data, state, config);
            return { ...state, config };
        }
        case 'rotate': {
            const material = materialById(data, state.config.materialId);
            if (material?.limits?.allowRotation === false) return state;
            const { width, height } = state.config.format;
            return { ...state, config: withFormatGroup(data, state, { ...state.config, format: { width: height, height: width } }) };
        }
        case 'motifs':
        case 'perMotif':
            return { ...state, config: { ...state.config, [action.type]: clampInt(action.value) } };
        case 'option': {
            const group = getGroup(data, action.group);
            if (!group) return state;
            const material = materialById(data, state.config.materialId);
            const value = action.value ? findValue(group, action.value) : null;
            if (action.value && (!value || !isAvailable(value, material))) return state;
            const options = { ...state.config.options };
            if (value) options[group.technicalName] = value.technicalName;
            else if (!group.required) delete options[group.technicalName];
            const next = { ...state.config, options };
            const synced = syncOptions(data, state.config, next, state.memory);
            const changes = synced.changes.filter((c) => c.group !== group.technicalName);
            return {
                ...state,
                config: { ...next, options: synced.options },
                memory: synced.memory,
                changes: changes.length ? changes : state.changes,
                changeContext: changes.length ? { group: group.technicalName } : state.changeContext,
            };
        }
        case 'delivery': {
            const dg = deliveryGroup(data);
            if (dg && !findValue(dg, action.value)) return state;
            return { ...state, config: { ...state.config, deliveryValue: action.value } };
        }
        case 'matrix': {
            // Klick in die Preismatrix wählt Menge (Stück je Motiv) und Lieferzeit zugleich
            const next = { ...state.config, perMotif: clampInt(action.quantity) };
            if (action.delivery) next.deliveryValue = action.delivery;
            return { ...state, config: next };
        }
        case 'fix': {
            let config = state.config;
            for (const [path, value] of Object.entries(action.fix || {})) config = setPath(config, path, value);
            const stillOpen = state.changes.filter((c) => !(c.code === 'format.check' && Object.keys(action.fix || {}).includes(c.error.field)));
            return { ...state, config: withFormatGroup(data, state, config), changes: stillOpen };
        }
        case 'reference':
            return { ...state, config: { ...state.config, reference: String(action.value || '').slice(0, 255) } };
        case 'restore': {
            // komplette Auswahl übernehmen (URL-Hash, Wiederbestellen); unbekannte Werte normalisiert das Backend
            const fresh = createState(data, { initial: action.config, layout: action.layout || state.layout, step: action.step || state.step });
            return { ...fresh, gross: state.gross };
        }
        case 'layout': {
            if (action.value !== 'compact' && action.value !== 'guided') return state;
            if (!data.allowLayoutSwitch && action.value !== data.layout) return state;
            return { ...state, layout: action.value };
        }
        case 'step':
            return { ...state, step: normalizeStep(action.key) };
        case 'gross':
            return { ...state, gross: Boolean(action.value) };
        case 'dismissNotice':
            return { ...state, changes: [], changeContext: null };
        case 'pending':
            return { ...state, pending: true, netError: false };
        case 'netError':
            return { ...state, pending: false, netError: true };
        case 'result': {
            const result = action.result;
            if (!result || result.apiVersion !== 1) return { ...state, pending: false, netError: true };
            // normalisierte Werte des Backends übernehmen (ersetzte Optionen, Lieferzeit) – Format und Menge nie still ändern
            const normalized = result.configuration || {};
            const config = {
                ...state.config,
                options: normalized.options ? { ...normalized.options } : state.config.options,
                deliveryValue: normalized.deliveryValue ?? state.config.deliveryValue,
            };
            // Hinweise des Backends zu entfallenen/ersetzten Optionen in die Hinweis-Box, wenn lokal nicht schon gemeldet
            const changes = [...state.changes];
            for (const hint of result.hints || []) {
                if (!/^option\./.test(hint.code)) continue;
                const group = (hint.field || '').replace(/^options\./, '');
                if (!changes.some((c) => c.group === group || c.message === hint.message)) changes.push({ code: hint.code, group, message: hint.message, backend: true });
            }
            const next = { ...state, config, changes, result, pending: false, netError: false };
            next.resultKey = configKey(toConfiguration(next));
            return next;
        }
        default:
            return state;
    }
}

/* ---------- Geführte Darstellung: Schritte ---------- */

/** früherer Schlüssel „quantity“ (Menge & Lieferzeit) = heute der Schritt „delivery“ (alte Links/Hashes) */
const normalizeStep = (key) => (key === 'quantity' ? 'delivery' : key);

/**
 * Schritte (Reiter): Material · Format & Stückzahl · je sichtbare Optionsgruppe ein Reiter · Lieferzeit.
 * Die Stückzahl gehört zum Format: daraus ergibt sich früh die Gesamtfläche (Breite × Höhe × Stück), auf der die
 * folgenden Optionen rechnen. steps[].key „quantity“ aus den Konfigurator-Daten steht für den Lieferzeit-Schritt.
 * Labels für Format und Lieferzeit kommen aus den Storefront-Snippets (Inhalt der Schritte ist hier festgelegt).
 */
export function guidedSteps(data, state) {
    const labels = Object.fromEntries((data.steps || []).map((s) => [s.key, s.label]));
    const order = (data.steps || []).map((s) => normalizeStep(s.key)).filter((k) => ['material', 'format', 'options', 'delivery'].includes(k));
    if (!order.includes('material')) order.unshift('material');
    if (!order.includes('format')) order.splice(order.indexOf('material') + 1, 0, 'format');
    if (!order.includes('delivery')) order.push('delivery');
    if (!order.includes('options')) order.splice(order.indexOf('delivery'), 0, 'options');
    const steps = [];
    for (const key of order) {
        if (key === 'material') steps.push({ key, kind: key, label: labels.material || null });
        else if (key === 'format' || key === 'delivery') steps.push({ key, kind: key, label: null });
        else for (const g of optionGroups(data, state)) steps.push({ key: `group:${g.technicalName}`, kind: 'group', group: g.technicalName, label: g.name });
    }
    return steps;
}

export function currentStep(data, state) {
    const steps = guidedSteps(data, state);
    return steps.find((s) => s.key === normalizeStep(state.step)) || steps[0];
}

export function nextStepKey(data, state) {
    const steps = guidedSteps(data, state);
    const i = steps.findIndex((s) => s.key === normalizeStep(state.step));
    return steps[Math.min(steps.length - 1, Math.max(0, i) + 1)].key;
}

/* ---------- URL-Hash: Konfiguration teilen und wiederherstellen ---------- */

const HASH_PREFIX = '#lm?';

export function encodeHash(state) {
    const { data, config } = state;
    const material = materialById(data, config.materialId);
    const p = new URLSearchParams();
    p.set('m', material ? material.technicalName : config.materialId);
    p.set('w', config.format.width);
    p.set('h', config.format.height);
    p.set('mo', config.motifs);
    p.set('pm', config.perMotif);
    for (const name of activeGroupNames(data, config)) if (config.options[name]) p.set(`o.${name}`, config.options[name]);
    if (config.deliveryValue) p.set('d', config.deliveryValue);
    if (state.layout !== data.layout) p.set('v', state.layout);
    if (state.layout === 'guided' && state.step && state.step !== 'material') p.set('s', state.step);
    return HASH_PREFIX + p.toString();
}

/** Liefert {config, layout, step} oder null, wenn der Hash nicht von Mensura stammt. */
export function decodeHash(data, hash) {
    if (!hash || !hash.startsWith(HASH_PREFIX)) return null;
    const p = new URLSearchParams(hash.slice(HASH_PREFIX.length));
    const material = materialByTechnicalName(data, p.get('m')) || materialById(data, p.get('m'));
    const int = (k) => (p.has(k) && /^\d+$/.test(p.get(k)) ? Number(p.get(k)) : undefined);
    const options = {};
    for (const [k, v] of p.entries()) if (k.startsWith('o.') && v) options[k.slice(2)] = v;
    const config = {
        materialId: material?.id,
        format: { width: int('w'), height: int('h') },
        motifs: int('mo'),
        perMotif: int('pm'),
        options: { ...data.defaults.options, ...options },
        deliveryValue: p.get('d') || undefined,
    };
    return { config, layout: p.get('v') || null, step: p.get('s') || null };
}
