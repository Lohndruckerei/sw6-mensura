/**
 * Darstellung beider Varianten als HTML-Zeichenkette (ohne DOM, daher in Node testbar).
 * Bedienelemente tragen data-lm-* Attribute; das JS-Plugin übersetzt Ereignisse daran in Aktionen.
 *
 * Kompakt (Vorbild Comdatek): eine Seite, Abschnitte 1 Material · 2 Endformat · 3 Auflage · 4 Druck & Weiterverarbeitung.
 * Geführt (Vorbild flyeralarm): Reiter je Schritt, Kacheln, Preismatrix Menge × Lieferzeit.
 * Rechts in beiden: Preis (nur aus der Store-API), „Ihr Produkt“, Vorschau mit Schnittmarken.
 */

import {
    allowedValues, canOrder, currentErrors, currentHints, currentStep, deliveryGroup, findValue, formatGroup, getGroup,
    guidedSteps, isFresh, materialById, optionGroups,
} from './state.js';
import { cutoffStatus, esc } from './util.js';

/* ---------- kleine Bausteine ---------- */

function help(ctx, text, id) {
    if (!text) return '';
    return `<span class="lm-help" tabindex="0" role="button" aria-label="${esc(ctx.t('help.label'))}: ${esc(text)}" aria-describedby="${id}">?<span class="lm-help__tip" role="tooltip" id="${id}">${esc(text)}</span></span>`;
}

const badges = (list) => (list && list.length ? `<span class="lm-badges">${list.map((b) => `<span class="lm-badge badge rounded-pill text-bg-light border">${esc(b)}</span>`).join('')}</span>` : '');

function fieldId(ctx, name) {
    return `${ctx.uid}-${name}`;
}

function errorText(ctx, state, err) {
    if (err.message && !err.local) return err.message;
    const material = materialById(state.data, state.config.materialId);
    const map = {
        'format.width_min': 'error.widthMin', 'format.width_max': 'error.widthMax',
        'format.height_min': 'error.heightMin', 'format.height_max': 'error.heightMax',
        'quantity.motifs_max': 'error.motifsMax', 'quantity.max': 'error.quantityMax',
    };
    const key = map[err.code];
    if (key) return ctx.t(key, { material: material?.name || '', limit: ctx.fmt.int(err.limit) });
    return err.message || err.code;
}

function fixButton(ctx, err) {
    if (!err.fix || typeof err.fix !== 'object') return '';
    const entries = Object.entries(err.fix);
    if (!entries.length) return '';
    const [path, value] = entries[0];
    let label;
    if (entries.length === 1 && path.startsWith('format.')) label = ctx.t('fix.format', { value: ctx.fmt.int(value) });
    else if (entries.length === 1 && (path === 'motifs' || path === 'perMotif')) label = ctx.t('fix.quantity', { value: ctx.fmt.int(value) });
    else label = ctx.t('fix.generic');
    return `<button type="button" class="lm-fix btn btn-sm btn-outline-primary" data-lm-action="fix" data-lm-fix="${esc(JSON.stringify(err.fix))}">${esc(label)}</button>`;
}

function errorsFor(state, field) {
    return currentErrors(state).filter((e) => e.field === field || (field === 'format' && (e.field || '').startsWith('format')));
}

function messageList(ctx, state, list, cls) {
    if (!list.length) return '';
    return `<ul class="lm-msgs ${cls} ${cls === 'lm-msgs--error' ? 'invalid-feedback d-block' : 'form-text'}" role="${cls === 'lm-msgs--error' ? 'alert' : 'status'}">${list.map((e) => `<li data-lm-code="${esc(e.code)}"><span>${esc(errorText(ctx, state, e))}</span> ${fixButton(ctx, e)}</li>`).join('')}</ul>`;
}

function valueLabel(v) {
    return v.priceHint ? `${v.name} · ${v.priceHint}` : v.name;
}

/* ---------- Hinweis-Box beim Materialwechsel (KONZEPT 3.7) ---------- */

export function renderNotice(ctx, state) {
    if (!state.changes.length) return '';
    const { data } = state;
    const material = materialById(data, state.config.materialId);
    const prev = state.changeContext?.previous ? materialById(data, state.changeContext.previous) : null;
    const mName = material?.calculation?.name || material?.name || '';
    const gName = (g) => getGroup(data, g)?.name || g;
    const vName = (g, v) => findValue(getGroup(data, g), v)?.name || v;
    const items = state.changes.map((c) => {
        if (c.backend) return esc(c.message);
        switch (c.code) {
            case 'option.removed': return ctx.t.html('notice.removed', { group: `<b>${esc(gName(c.group))}</b>`, material: esc(mName), value: esc(vName(c.group, c.from)) });
            case 'option.reset': return ctx.t.html('notice.reset', { group: `<b>${esc(gName(c.group))}</b>`, material: esc(mName), from: esc(vName(c.group, c.from)), to: esc(vName(c.group, c.to)) });
            case 'option.added': return ctx.t.html('notice.added', { group: `<b>${esc(gName(c.group))}</b>`, material: esc(mName) });
            case 'option.restored': return ctx.t.html('notice.restored', { group: `<b>${esc(gName(c.group))}</b>`, value: esc(vName(c.group, c.to)) });
            case 'format.check': return `<b>${esc(ctx.t('notice.formatTitle'))}</b> ${esc(errorText(ctx, state, c.error))} ${fixButton(ctx, c.error)}`;
            default: return esc(c.message || c.code);
        }
    });
    const keep = prev ? `<span class="lm-notice__keep">${esc(ctx.t('notice.keep'))}</span>` : '';
    return `<div class="lm-notice alert alert-warning" role="status" data-lm-notice>
        <div class="lm-notice__head"><b>${esc(ctx.t('notice.title'))}</b><button type="button" class="lm-notice__close btn-close" data-lm-action="dismissNotice" aria-label="${esc(ctx.t('notice.close'))}"></button></div>
        <ul>${items.map((i) => `<li>${i}</li>`).join('')}</ul>${keep}</div>`;
}

/* ---------- Format- und Mengenfelder (beide Varianten) ---------- */

function formatFields(ctx, state) {
    const { data, config } = state;
    const material = materialById(data, config.materialId);
    const l = material?.limits || {};
    const errs = errorsFor(state, 'format');
    const bad = (dim) => errs.some((e) => e.field === `format.${dim}`);
    const fg = formatGroup(data, state);
    const fgSelect = fg ? `<div class="lm-field"><label class="form-label" for="${fieldId(ctx, 'fmt')}">${esc(fg.name)} ${help(ctx, fg.helpText || ctx.t('help.formatGroup'), fieldId(ctx, 'fmt-h'))}</label>
        <select class="form-select" id="${fieldId(ctx, 'fmt')}" data-lm-field="formatValue" data-lm-group="${esc(fg.technicalName)}">${allowedValues(fg, material).map((v) => `<option value="${esc(v.technicalName)}" ${config.options[fg.technicalName] === v.technicalName ? 'selected' : ''}>${esc(valueLabel(v))}</option>`).join('')}</select></div>` : '';
    const range = (min, max) => `${ctx.fmt.int(min ?? 1)}–${max != null ? ctx.fmt.int(max) : '∞'}`;
    const possible = ctx.t.html('format.possible', { material: esc(material?.name || ''), width: range(l.widthMin, l.widthMax), height: range(l.heightMin, l.heightMax) });
    const rotate = l.allowRotation !== false ? `<button type="button" class="lm-rotate btn btn-outline-secondary" data-lm-action="rotate" title="${esc(ctx.t('field.rotateHelp'))}" aria-label="${esc(ctx.t('field.rotate'))}">⇄</button>` : '';
    return `${fgSelect}
      <div class="lm-row">
        <div class="lm-field ${bad('width') ? 'is-bad' : ''}"><label class="form-label" for="${fieldId(ctx, 'width')}">${esc(ctx.t('field.width'))} ${help(ctx, ctx.t('help.width'), fieldId(ctx, 'width-h'))}</label>
          <input id="${fieldId(ctx, 'width')}" type="number" inputmode="numeric" min="1" step="1" value="${config.format.width}" data-lm-field="width" class="form-control${bad('width') ? ' is-invalid' : ''}" ${bad('width') ? 'aria-invalid="true"' : ''}></div>
        <span class="lm-times" aria-hidden="true">×</span>
        <div class="lm-field ${bad('height') ? 'is-bad' : ''}"><label class="form-label" for="${fieldId(ctx, 'height')}">${esc(ctx.t('field.height'))} ${help(ctx, ctx.t('help.height'), fieldId(ctx, 'height-h'))}</label>
          <input id="${fieldId(ctx, 'height')}" type="number" inputmode="numeric" min="1" step="1" value="${config.format.height}" data-lm-field="height" class="form-control${bad('height') ? ' is-invalid' : ''}" ${bad('height') ? 'aria-invalid="true"' : ''}></div>
        ${rotate}
      </div>
      ${errs.length ? messageList(ctx, state, errs, 'lm-msgs--error') : `<div class="lm-limit">${possible}</div>`}`;
}

function quantityFields(ctx, state) {
    const { data, config } = state;
    const material = materialById(data, config.materialId);
    const split = material?.calculation?.motifMode === 'split';
    const total = split ? config.perMotif : config.motifs * config.perMotif;
    const errs = currentErrors(state).filter((e) => e.field === 'motifs' || e.field === 'perMotif' || (e.code || '').startsWith('quantity.'));
    const sum = split ? ctx.t.html('quantity.totalSplit', { count: ctx.fmt.int(total), motifs: ctx.fmt.int(config.motifs) }) : ctx.t.html('quantity.total', { count: `<b>${ctx.fmt.int(total)}</b>` });
    return `<div class="lm-row">
        <div class="lm-field"><label class="form-label" for="${fieldId(ctx, 'motifs')}">${esc(ctx.t('field.motifs'))} ${help(ctx, ctx.t(split ? 'help.motifsSplit' : 'help.motifs'), fieldId(ctx, 'motifs-h'))}</label>
          <input id="${fieldId(ctx, 'motifs')}" type="number" inputmode="numeric" min="1" step="1" value="${config.motifs}" data-lm-field="motifs" class="form-control"></div>
        <span class="lm-times" aria-hidden="true">${split ? '' : '×'}</span>
        <div class="lm-field"><label class="form-label" for="${fieldId(ctx, 'perMotif')}">${esc(ctx.t(split ? 'field.totalQuantity' : 'field.perMotif'))} ${help(ctx, ctx.t(split ? 'help.totalQuantity' : 'help.perMotif'), fieldId(ctx, 'perMotif-h'))}</label>
          <input id="${fieldId(ctx, 'perMotif')}" type="number" inputmode="numeric" min="1" step="1" value="${config.perMotif}" data-lm-field="perMotif" class="form-control"></div>
        <span class="lm-limit lm-sum">${sum}</span>
      </div>${messageList(ctx, state, errs, 'lm-msgs--error')}`;
}

/**
 * Fläche Breite × Höhe × Stück – reine Anzeige, damit der Kunde früh sieht, worauf flächenbezogene Optionen rechnen.
 * Maßgeblich (auch abgerechnete Fläche, Mindestfläche) ist das Backend: ist das Ergebnis frisch, steht dessen price.area hier.
 */
function areaInfo(ctx, state) {
    const { data, config } = state;
    const material = materialById(data, config.materialId);
    const qty = material?.calculation?.motifMode === 'split' ? config.perMotif : config.motifs * config.perMotif;
    const piece = (config.format.width * config.format.height) / 1e6;
    const backendArea = isFresh(state) ? state.result?.price?.area : null;
    const total = backendArea ?? piece * qty;
    return `<div class="lm-area" data-lm-area="${total}">${ctx.t.html('area.summary', { piece: esc(ctx.fmt.num(piece)), count: esc(ctx.fmt.int(qty)), total: `<b>${esc(ctx.fmt.num(total))}</b>` })} ${help(ctx, ctx.t('help.area'), fieldId(ctx, 'area-h'))}</div>`;
}

/** Format und Stückzahl gehören zusammen (ergibt die Gesamtfläche) */
function formatQuantityFields(ctx, state) {
    return `${formatFields(ctx, state)}<div class="lm-g-group lm-qty">${quantityFields(ctx, state)}</div>${areaInfo(ctx, state)}`;
}

function deliveryTiles(ctx, state) {
    const dg = deliveryGroup(state.data);
    if (!dg) return '';
    const material = materialById(state.data, state.config.materialId);
    return `<div class="lm-tiles">${allowedValues(dg, material).map((v) => {
        const info = deliveryInfo(state, v.technicalName);
        const text = [v.deliveryDays != null ? ctx.t('delivery.days', { days: v.deliveryDays }) : null, info?.date ? ctx.fmt.date(info.date) : null].filter(Boolean).join(' · ');
        const extra = `${v.priceHint ? `<span class="lm-tile__price">${esc(v.priceHint)}</span>` : ''}${info && info.available === false ? `<small>${esc(info.reason || ctx.t('delivery.unavailable'))}</small>` : ''}`;
        return tile({ action: 'delivery', attrs: `data-lm-value="${esc(v.technicalName)}"`, pressed: state.config.deliveryValue === v.technicalName, title: v.name, text, extra, disabled: info?.available === false });
    }).join('')}</div>`;
}

function referenceField(ctx, state) {
    return `<div class="lm-field lm-field--wide"><label class="form-label" for="${fieldId(ctx, 'reference')}">${esc(ctx.t('field.reference'))} ${help(ctx, ctx.t('help.reference'), fieldId(ctx, 'reference-h'))}</label>
      <input id="${fieldId(ctx, 'reference')}" type="text" maxlength="255" value="${esc(state.config.reference || '')}" data-lm-field="reference" class="form-control" autocomplete="off"></div>`;
}

function deliveryInfo(state, value) {
    const d = isFresh(state) || state.result ? (state.result?.delivery || []).find((x) => x.value === value) : null;
    return d || null;
}

function deliveryOptionText(ctx, state, v) {
    const info = deliveryInfo(state, v.technicalName);
    const parts = [v.name];
    if (v.deliveryDays != null) parts.push(ctx.t('delivery.days', { days: v.deliveryDays }));
    if (info?.date) parts.push(ctx.fmt.date(info.date));
    if (v.priceHint) parts.push(v.priceHint);
    let text = parts.join(' · ');
    if (info && info.available === false) text += ` (${info.reason || ctx.t('delivery.unavailable')})`;
    return text;
}

function deliverySelect(ctx, state) {
    const dg = deliveryGroup(state.data);
    if (!dg) return '';
    const material = materialById(state.data, state.config.materialId);
    const cutoff = state.data.orderCutoff ? ctx.t('delivery.cutoff', { time: state.data.orderCutoff }) : '';
    return `<div class="lm-field"><label class="form-label" for="${fieldId(ctx, 'delivery')}">${esc(dg.name)} ${help(ctx, dg.helpText || cutoff || ctx.t('help.delivery'), fieldId(ctx, 'delivery-h'))}</label>
      <select class="form-select" id="${fieldId(ctx, 'delivery')}" data-lm-field="delivery">${allowedValues(dg, material).map((v) => {
        const info = deliveryInfo(state, v.technicalName);
        return `<option value="${esc(v.technicalName)}" ${state.config.deliveryValue === v.technicalName ? 'selected' : ''} ${info && info.available === false ? 'disabled' : ''}>${esc(deliveryOptionText(ctx, state, v))}</option>`;
    }).join('')}</select></div>`;
}

/* ---------- Variante „kompakt“ ---------- */

export function renderCompact(ctx, state) {
    const { data, config } = state;
    const material = materialById(data, config.materialId);
    // Materialien nach Kalkulation gruppiert (Plattenart), wie bei Comdatek
    const byCalc = new Map();
    for (const m of data.materials) {
        const k = m.calculation.technicalName;
        if (!byCalc.has(k)) byCalc.set(k, { name: m.calculation.name, items: [] });
        byCalc.get(k).items.push(m);
    }
    const matOptions = [...byCalc.values()].map((c) => `<optgroup label="${esc(c.name)}">${c.items.map((m) => `<option value="${esc(m.id)}" ${m.id === config.materialId ? 'selected' : ''}>${esc(m.name)}</option>`).join('')}</optgroup>`).join('');
    const groups = optionGroups(data, state);
    const options = groups.map((g) => {
        const id = fieldId(ctx, `g-${g.technicalName}`);
        const values = allowedValues(g, material);
        const empty = !g.required ? `<option value="" ${!config.options[g.technicalName] ? 'selected' : ''}>${esc(ctx.t('option.none'))}</option>` : '';
        const ctrl = g.mode === 'fixed'
            ? `<div class="lm-fixed" id="${id}">${esc(findValue(g, config.options[g.technicalName])?.name || '')}</div>`
            : `<select class="form-select" id="${id}" data-lm-field="option" data-lm-group="${esc(g.technicalName)}">${empty}${values.map((v) => `<option value="${esc(v.technicalName)}" ${config.options[g.technicalName] === v.technicalName ? 'selected' : ''}>${esc(valueLabel(v))}</option>`).join('')}</select>`;
        const errs = errorsFor(state, `options.${g.technicalName}`);
        return `<div class="lm-field ${errs.length ? 'is-bad' : ''}" data-lm-group-field="${esc(g.technicalName)}"><label class="form-label" for="${id}">${esc(g.name)} ${help(ctx, g.helpText || ctx.t('help.option'), `${id}-h`)}</label>${ctrl}${messageList(ctx, state, errs, 'lm-msgs--error')}</div>`;
    }).join('');
    const sec = (no, title, body, key) => `<section class="lm-sec" data-lm-section="${key}" aria-labelledby="${fieldId(ctx, `sec-${key}`)}"><span class="lm-sec__no" aria-hidden="true">${no}</span><h3 class="lm-sec__title" id="${fieldId(ctx, `sec-${key}`)}">${esc(title)}</h3><div class="lm-sec__body">${body}</div></section>`;
    return `<div class="lm-compact">
      ${sec(1, ctx.t('section.material'), `<div class="lm-field"><label class="form-label" for="${fieldId(ctx, 'material')}">${esc(ctx.t('field.material'))} ${help(ctx, ctx.t('help.material'), fieldId(ctx, 'material-h'))}</label>
          <select class="form-select" id="${fieldId(ctx, 'material')}" data-lm-field="material">${matOptions}</select></div>
        ${material?.description ? `<p class="lm-muted">${esc(material.description)} ${badges(material.badges)}</p>` : badges(material?.badges)}`, 'material')}
      ${sec(2, ctx.t('section.format'), formatQuantityFields(ctx, state), 'format')}
      ${sec(3, ctx.t('section.options'), `<div class="lm-opts">${options}${deliverySelect(ctx, state)}</div>${referenceField(ctx, state)}`, 'options')}
    </div>`;
}

/* ---------- Variante „geführt“ ---------- */

const PRESETS = [[210, 297, 'DIN A4'], [297, 420, 'DIN A3'], [420, 594, 'DIN A2'], [594, 841, 'DIN A1'], [600, 400, null], [1000, 500, null]];

function matrixFactor(state) {
    const p = state.result?.price;
    return state.gross && p && p.totalGross && p.total ? p.totalGross / p.total : 1;
}

function renderMatrix(ctx, state) {
    const { data, config } = state;
    const dg = deliveryGroup(data);
    const material = materialById(data, config.materialId);
    const deliveries = dg ? allowedValues(dg, material) : [{ technicalName: config.deliveryValue || '', name: ctx.t('delivery.title') }];
    const steps = data.quantitySteps && data.quantitySteps.length ? data.quantitySteps : [1, 2, 5, 10, 25];
    const matrix = state.result?.matrix || [];
    const factor = matrixFactor(state);
    const blocked = currentErrors(state).some((e) => (e.field || '').startsWith('format'));
    const head = deliveries.map((d) => {
        const info = deliveryInfo(state, d.technicalName);
        const sub = [d.deliveryDays != null ? ctx.t('delivery.daysShort', { days: d.deliveryDays }) : null, info?.date ? ctx.fmt.date(info.date) : null, d.priceHint].filter(Boolean).join(' · ');
        return `<th scope="col">${esc(d.name)}${sub ? `<small>${esc(sub)}</small>` : ''}</th>`;
    }).join('');
    const rows = steps.map((q) => `<tr><th scope="row">${ctx.fmt.int(q)} ${esc(ctx.t(config.motifs > 1 ? 'guided.piecesPerMotif' : 'guided.pieces'))}</th>${deliveries.map((d) => {
        const info = deliveryInfo(state, d.technicalName);
        if (blocked || (info && info.available === false)) return `<td><span class="lm-na" title="${esc(blocked ? ctx.t('price.blocked') : info.reason || ctx.t('delivery.unavailable'))}">–</span></td>`;
        const cell = matrix.find((m) => m.quantity === q && m.delivery === d.technicalName);
        const on = config.perMotif === q && (config.deliveryValue || '') === d.technicalName;
        const label = cell && cell.total != null ? ctx.fmt.money(cell.total * factor) : '–';
        const title = cell && cell.total != null ? '' : ` title="${esc(ctx.t('guided.matrixOnRequest'))}"`;
        return `<td><button type="button" class="btn btn-sm w-100 ${on ? 'btn-primary' : 'btn-link text-body text-decoration-none'}" data-lm-action="matrix" data-lm-quantity="${q}" data-lm-delivery="${esc(d.technicalName)}" aria-pressed="${on}"${title}>${esc(label)}</button></td>`;
    }).join('')}</tr>`).join('');
    return `<div class="lm-matrix-wrap"><table class="lm-matrix table table-hover align-middle"><caption class="lm-sr">${esc(ctx.t('guided.matrixTitle'))}</caption><thead><tr><th scope="col">${esc(ctx.t('guided.matrixQty'))}</th>${head}</tr></thead><tbody>${rows}</tbody></table></div>`;
}

function tile({ action, attrs, pressed, title, text, extra = '', image = null, disabled = false }) {
    return `<button type="button" class="lm-tile btn btn-outline-secondary text-start${pressed ? ' active' : ''}" data-lm-action="${action}" ${attrs} aria-pressed="${pressed}" ${disabled ? 'disabled' : ''}>
      ${image ? `<img class="lm-tile__img" src="${esc(image)}" alt="" loading="lazy">` : ''}<b>${esc(title)}</b>${text ? `<small>${esc(text)}</small>` : ''}${extra}</button>`;
}

export function renderGuided(ctx, state) {
    const { data, config } = state;
    const material = materialById(data, config.materialId);
    const steps = guidedSteps(data, state);
    const step = currentStep(data, state);
    const idx = Math.max(0, steps.findIndex((s) => s.key === step.key));
    const stepLabel = (s) => s.label || ctx.t(`steps.${s.kind}`);
    const nav = `<nav class="lm-steps" aria-label="${esc(ctx.t('guided.stepsLabel'))}"><ol class="nav nav-tabs">${steps.map((s, i) => `<li class="nav-item"><button type="button" data-lm-action="step" data-lm-value="${esc(s.key)}" class="nav-link${i === idx ? ' active' : ''}${i < idx ? ' is-done' : ''}" ${i === idx ? 'aria-current="step"' : ''}><span class="lm-steps__n" aria-hidden="true">${i < idx ? '✓' : i + 1}</span>${esc(stepLabel(s))}</button></li>`).join('')}</ol></nav>`;
    let body = '';
    if (step.kind === 'material') {
        const byCalc = new Map();
        for (const m of data.materials) {
            const k = m.calculation.technicalName;
            if (!byCalc.has(k)) byCalc.set(k, { name: m.calculation.name, items: [] });
            byCalc.get(k).items.push(m);
        }
        body = `<h3 class="lm-g-title">${esc(ctx.t('guided.materialTitle'))} ${help(ctx, ctx.t('help.material'), fieldId(ctx, 'gm-h'))}</h3>` + [...byCalc.values()].map((c) => `<div class="lm-g-group"><h4>${esc(c.name)}</h4><div class="lm-tiles">${c.items.map((m) => tile({
            action: 'material', attrs: `data-lm-value="${esc(m.id)}"`, pressed: m.id === config.materialId, title: m.name, text: m.description, image: m.image,
            extra: `${badges(m.badges)}${m.fromPricePerSqm != null ? `<span class="lm-tile__price">${esc(ctx.t('guided.fromPrice', { price: ctx.fmt.money(m.fromPricePerSqm) }))}</span>` : ''}`,
        })).join('')}</div></div>`).join('');
    } else if (step.kind === 'format') {
        const l = material?.limits || {};
        const fits = ([w, h]) => (l.widthMin == null || w >= l.widthMin) && (l.widthMax == null || w <= l.widthMax) && (l.heightMin == null || h >= l.heightMin) && (l.heightMax == null || h <= l.heightMax);
        const fg = formatGroup(data, state);
        const presets = fg ? '' : `<div class="lm-g-group"><h4>${esc(ctx.t('guided.presets'))}</h4><div class="lm-tiles lm-tiles--small">${PRESETS.filter(fits).map(([w, h, n]) => tile({
            action: 'formatValue', attrs: `data-lm-width="${w}" data-lm-height="${h}"`, pressed: config.format.width === w && config.format.height === h,
            title: n || `${ctx.fmt.int(w)} × ${ctx.fmt.int(h)} mm`, text: n ? `${ctx.fmt.int(w)} × ${ctx.fmt.int(h)} mm` : '',
        })).join('')}</div></div>`;
        body = `<h3 class="lm-g-title">${esc(ctx.t('guided.formatTitle', { material: material?.name || '' }))}</h3><div class="lm-g-group">${formatQuantityFields(ctx, state)}</div>${presets}`;
    } else if (step.kind === 'group') {
        const g = getGroup(data, step.group);
        const values = allowedValues(g, material);
        const none = !g.required ? tile({ action: 'option', attrs: `data-lm-group="${esc(g.technicalName)}" data-lm-value=""`, pressed: !config.options[g.technicalName], title: ctx.t('option.none'), text: '' }) : '';
        body = `<h3 class="lm-g-title">${esc(g.name)} ${help(ctx, g.helpText || ctx.t('help.option'), fieldId(ctx, `gg-${g.technicalName}-h`))}</h3>
          ${g.helpText ? `<p class="lm-muted">${esc(g.helpText)}</p>` : ''}
          <div class="lm-tiles">${none}${values.map((v) => tile({
            action: 'option', attrs: `data-lm-group="${esc(g.technicalName)}" data-lm-value="${esc(v.technicalName)}"`, pressed: config.options[g.technicalName] === v.technicalName,
            title: v.name, text: v.description, image: v.image, disabled: g.mode === 'fixed' && config.options[g.technicalName] !== v.technicalName,
            extra: `${badges(v.badges)}${v.priceHint ? `<span class="lm-tile__price">${esc(v.priceHint)}</span>` : ''}`,
        })).join('')}</div>${messageList(ctx, state, errorsFor(state, `options.${g.technicalName}`), 'lm-msgs--error')}`;
    } else {
        // Lieferzeit: Kacheln mit Termin; Staffelpreise (Menge × Lieferzeit) nur noch zum Vergleichen, Klick übernimmt beides
        body = `<h3 class="lm-g-title">${esc(ctx.t('guided.deliveryTitle'))} ${help(ctx, deliveryGroup(data)?.helpText || ctx.t('help.delivery'), fieldId(ctx, 'gd-h'))}</h3>
          ${deliveryTiles(ctx, state)}
          <details class="lm-more"><summary>${esc(ctx.t('guided.matrixCompare'))}</summary><div class="lm-g-group">
            <p class="lm-muted">${esc(ctx.t('guided.matrixHelp', { mode: ctx.t(state.gross ? 'price.grossShort' : 'price.netShort') }))}</p>
            ${renderMatrix(ctx, state)}</div></details>
          ${referenceField(ctx, state)}`;
    }
    const last = idx === steps.length - 1;
    const formatBlocked = step.kind === 'format' && errorsFor(state, 'format').length > 0;
    const navBtns = `<div class="lm-g-nav">
        <button type="button" class="btn btn-outline-secondary" data-lm-action="back" ${idx === 0 ? 'disabled' : ''}>${esc(ctx.t('guided.back'))}</button>
        ${last
        ? `<button type="button" class="btn btn-primary btn-buy" data-lm-action="cart" ${canOrder(state) ? '' : 'disabled'}>${esc(ctx.t('cart.add'))}</button>`
        : `<button type="button" class="btn btn-primary" data-lm-action="next" ${formatBlocked ? 'disabled' : ''}>${esc(ctx.t('guided.next'))}</button>`}
      </div>`;
    return `<div class="lm-guided">${nav}<div class="lm-g-body" data-lm-step="${esc(step.key)}">${body}</div>${navBtns}</div>`;
}

/* ---------- rechte Spalte: Preis, Ihr Produkt, Vorschau ---------- */

export function renderPrice(ctx, state) {
    const fresh = isFresh(state);
    const result = state.result;
    const price = result?.price;
    const errs = currentErrors(state);
    const hints = currentHints(state);
    const hasGross = Boolean(price && price.totalGross != null);
    const factor = state.gross && hasGross ? price.totalGross / price.total : 1;
    const toggle = hasGross ? `<span class="lm-toggle btn-group btn-group-sm" role="group" aria-label="${esc(ctx.t('price.toggle'))}">
        <button type="button" class="btn btn-outline-secondary${state.gross ? '' : ' active'}" data-lm-action="gross" data-lm-value="0" aria-pressed="${!state.gross}">${esc(ctx.t('price.net'))}</button>
        <button type="button" class="btn btn-outline-secondary${state.gross ? ' active' : ''}" data-lm-action="gross" data-lm-value="1" aria-pressed="${state.gross}">${esc(ctx.t('price.gross'))}</button></span>` : '';
    let body;
    if (state.netError) {
        body = `<div class="lm-blocked text-danger" role="alert">${esc(ctx.t('price.netError'))} <button type="button" class="lm-fix btn btn-sm btn-outline-primary" data-lm-action="retry">${esc(ctx.t('price.retry'))}</button></div>`;
    } else if (errs.length) {
        body = `<div class="lm-blocked text-danger" role="alert">${esc(ctx.t('price.blocked'))}</div>${messageList(ctx, state, errs, 'lm-msgs--error')}`;
    } else if (price && result.valid) {
        const minimum = price.minimumApplied ? hints.find((h) => h.code === 'price.minimum_applied') : null;
        const other = hints.filter((h) => h.code !== 'price.minimum_applied' && !/^option\./.test(h.code));
        body = `<div class="lm-total ${state.pending || !fresh ? 'is-stale' : ''}" data-lm-total="${price.total}"><span class="lm-total__value">${ctx.fmt.money(price.total * factor)}</span> <small>${esc(ctx.t(state.gross ? 'price.grossLabel' : 'price.netLabel'))}</small></div>
          <dl class="lm-kv">
            <dt>${esc(ctx.t('price.perPiece'))}</dt><dd>${ctx.fmt.money4(price.perPiece * factor)}</dd>
            <dt>${esc(ctx.t('price.perSqm'))}</dt><dd>${ctx.fmt.money(price.perSqm * factor)}</dd>
            <dt>${esc(ctx.t('price.quantity'))}</dt><dd>${ctx.fmt.int(price.quantity)}</dd>
            <dt>${esc(ctx.t('price.area'))}</dt><dd>${ctx.fmt.num(price.area)} m²</dd>
          </dl>
          ${minimum ? `<p class="lm-minimum alert alert-info py-2 px-3 mb-0 small" data-lm-code="price.minimum_applied">${esc(minimum.message)}</p>` : ''}
          ${other.length ? `<ul class="lm-msgs lm-msgs--hint">${other.map((h) => `<li data-lm-code="${esc(h.code)}">${esc(h.message)} ${fixButton(ctx, h)}</li>`).join('')}</ul>` : ''}`;
    } else if (state.pending || !result) {
        body = `<div class="lm-pending" aria-busy="true">${esc(ctx.t('price.pending'))}</div>`;
    } else {
        const info = hints.filter((h) => !/^option\./.test(h.code));
        body = `<div class="lm-none">${esc(ctx.t('price.none'))}</div>${info.length ? `<ul class="lm-msgs lm-msgs--hint">${info.map((h) => `<li data-lm-code="${esc(h.code)}">${esc(h.message)}</li>`).join('')}</ul>` : ''}`;
    }
    const dg = deliveryGroup(state.data);
    const dv = dg ? findValue(dg, state.config.deliveryValue) : null;
    const dInfo = deliveryInfo(state, state.config.deliveryValue);
    const cut = cutoffStatus(state.data.orderCutoff, ctx.now());
    let cutText = '';
    if (cut) cutText = cut.passed ? ctx.t('delivery.cutoffPassed', { time: cut.time }) : `${ctx.t('delivery.cutoff', { time: cut.time })} · ${ctx.t('delivery.cutoffLeft', { hours: Math.floor(cut.minutesLeft / 60), minutes: cut.minutesLeft % 60 })}`;
    const delivery = dv ? `<div class="lm-delivery"><span><b>${esc(ctx.t('delivery.title'))} ${esc(dv.name)}:</b> ${dInfo?.date ? `<time datetime="${esc(dInfo.date)}">${esc(ctx.fmt.date(dInfo.date))}</time>` : esc(dv.deliveryDays != null ? ctx.t('delivery.days', { days: dv.deliveryDays }) : '')}</span>${cutText ? `<span class="lm-muted">${esc(cutText)}</span>` : ''}</div>` : '';
    const cart = state.layout === 'compact' ? `<button type="button" class="btn btn-primary btn-buy w-100" data-lm-action="cart" ${canOrder(state) ? '' : 'disabled'}>${esc(ctx.t('cart.add'))}</button>` : '';
    return `<div class="lm-card card card-body lm-price" aria-live="polite"><div class="lm-price__head"><h3>${esc(ctx.t('price.title'))}</h3>${toggle}</div>${body}${delivery}${cart}
      <button type="button" class="lm-link btn btn-link p-0" data-lm-action="share">${esc(ctx.t('share.copy'))}</button>${ctx.fixtureMode ? `<p class="lm-fixture small text-body-secondary">${esc(ctx.t('fixtureMode'))}</p>` : ''}</div>`;
}

export function renderSummary(ctx, state) {
    const { data, config } = state;
    const material = materialById(data, config.materialId);
    const guided = state.layout === 'guided';
    const link = (step, focus) => (guided
        ? ` <button type="button" class="lm-link btn btn-link p-0" data-lm-action="step" data-lm-value="${esc(step)}">${esc(ctx.t('summary.change'))}</button>`
        : ` <button type="button" class="lm-link btn btn-link p-0" data-lm-action="focus" data-lm-value="${esc(fieldId(ctx, focus))}">${esc(ctx.t('summary.change'))}</button>`);
    const split = material?.calculation?.motifMode === 'split';
    const qty = split ? `${ctx.fmt.int(config.perMotif)} (${ctx.fmt.int(config.motifs)} ${ctx.t('field.motifs')})` : `${ctx.fmt.int(config.motifs)} × ${ctx.fmt.int(config.perMotif)} = ${ctx.fmt.int(config.motifs * config.perMotif)}`;
    const dg = deliveryGroup(data);
    const dv = dg ? findValue(dg, config.deliveryValue) : null;
    const rows = [
        [ctx.t('summary.material'), material?.name || '', link('material', 'material')],
        [ctx.t('summary.format'), `${ctx.fmt.int(config.format.width)} × ${ctx.fmt.int(config.format.height)} mm`, link('format', 'width')],
        ...optionGroups(data, state).map((g) => [g.name, findValue(g, config.options[g.technicalName])?.name || ctx.t('option.none'), link(`group:${g.technicalName}`, `g-${g.technicalName}`)]),
        [ctx.t('summary.quantity'), `${qty} ${ctx.t('summary.pieces')}`, link('format', 'motifs')],
    ];
    if (dv) rows.push([ctx.t('summary.delivery'), dv.name, link('delivery', 'delivery')]);
    if (config.reference) rows.push([ctx.t('field.reference'), config.reference, link('delivery', 'reference')]);
    return `<div class="lm-card card card-body lm-summary"><h3>${esc(ctx.t('summary.title'))}</h3><dl>${rows.map(([k, v, l]) => `<dt>${esc(k)}</dt><dd>${esc(v)}${l}</dd>`).join('')}</dl></div>`;
}

/** Vorschau des Endformats mit Schnittmarken; Bohrungen/Kontur nur als Andeutung (reine Anzeige). */
export function renderPreview(ctx, state) {
    const { config } = state;
    const { width, height } = config.format;
    const bad = currentErrors(state).some((e) => (e.field || '').startsWith('format'));
    const maxW = 250; const maxH = 160;
    const s = Math.min(maxW / width, maxH / height);
    const w = Math.max(16, width * s); const h = Math.max(16, height * s);
    const x = (320 - w) / 2; const y = 20 + (maxH - h) / 2;
    const opts = Object.entries(config.options || {});
    const contour = opts.some(([, v]) => /kontur|contour|freiform/i.test(v));
    const holes = opts.some(([g, v]) => /bohr|loch|hole|oese|öse/i.test(g) && !/^(nein|no|none|keine|ohne)$/i.test(v));
    const r = contour ? Math.min(w, h) * 0.18 : 1.5;
    const crop = (cx, cy, dx, dy) => `<path d="M${cx + dx * 4} ${cy} h${dx * 12} M${cx} ${cy + dy * 4} v${dy * 12}" class="lm-pv__crop"/>`;
    const inset = Math.min(8, w / 6, h / 6);
    const holeMarks = holes ? [[x + inset, y + inset], [x + w - inset, y + inset], [x + inset, y + h - inset], [x + w - inset, y + h - inset]].map(([hx, hy]) => `<circle cx="${hx}" cy="${hy}" r="2.6" class="lm-pv__hole"/>`).join('') : '';
    const label = `${ctx.fmt.int(width)} × ${ctx.fmt.int(height)} mm${bad ? ` – ${ctx.t('preview.outside')}` : ''}`;
    return `<div class="lm-card card card-body lm-preview"><h3>${esc(ctx.t('preview.title'))}</h3>
      <svg viewBox="0 0 320 ${maxH + 64}" role="img" aria-label="${esc(ctx.t('preview.label', { width: ctx.fmt.int(width), height: ctx.fmt.int(height) }))}" class="lm-pv ${bad ? 'is-bad' : ''}">
        ${crop(x, y, -1, -1)}${crop(x + w, y, 1, -1)}${crop(x, y + h, -1, 1)}${crop(x + w, y + h, 1, 1)}
        <rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${r}" class="lm-pv__sheet"/>
        <rect x="${x + w * 0.12}" y="${y + h * 0.3}" width="${w * 0.5}" height="${Math.max(3, h * 0.12)}" class="lm-pv__art1"/>
        <rect x="${x + w * 0.12}" y="${y + h * 0.52}" width="${w * 0.72}" height="${Math.max(2, h * 0.06)}" class="lm-pv__art2"/>
        ${holeMarks}
        <text x="160" y="${y + h + 26}" text-anchor="middle" class="lm-pv__text">${esc(label)}</text>
      </svg><p class="lm-muted lm-small">${esc(ctx.t('preview.caption'))}</p></div>`;
}

/* ---------- Gesamtansicht ---------- */

export function renderConfigurator(ctx, state) {
    const { data } = state;
    const switcher = data.allowLayoutSwitch ? `<div class="lm-switch btn-group" role="group" aria-label="${esc(ctx.t('layout.label'))}">
        <button type="button" class="btn btn-outline-primary${state.layout === 'compact' ? ' active' : ''}" data-lm-action="layout" data-lm-value="compact" aria-pressed="${state.layout === 'compact'}">${esc(ctx.t('layout.compact'))}</button>
        <button type="button" class="btn btn-outline-primary${state.layout === 'guided' ? ' active' : ''}" data-lm-action="layout" data-lm-value="guided" aria-pressed="${state.layout === 'guided'}">${esc(ctx.t('layout.guided'))}</button></div>` : '';
    const view = state.layout === 'guided' ? renderGuided(ctx, state) : renderCompact(ctx, state);
    return `<div class="lm-cfg lm-cfg--${state.layout}" data-lm-layout="${state.layout}">
      <div class="lm-cfg__head"><h2 class="lm-cfg__title">${esc(ctx.t('title'))}</h2>${switcher}</div>
      <div class="lm-cfg__grid">
        <div class="lm-cfg__main">${renderNotice(ctx, state)}<div class="lm-card card lm-view">${view}</div></div>
        <aside class="lm-cfg__aside">${renderPrice(ctx, state)}${renderSummary(ctx, state)}${renderPreview(ctx, state)}</aside>
      </div>
    </div>`;
}
