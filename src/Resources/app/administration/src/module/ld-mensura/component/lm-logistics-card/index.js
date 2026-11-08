import { formatMoney, formatNumber } from '../../service/margin';
import {
    defaultPeriod, loadSuggestions, matchSuggestions, nextMonday, proposalToDraft, stepExceeded,
} from '../../service/vectura-suggestions';

/** Beispieldaten statt Vectura (nur Entwicklung): im Browser localStorage „ld-mensura.vectura-demo“ = „1“ setzen. */
const DEMO_KEY = 'ld-mensura.vectura-demo';

const { Criteria } = Shopware.Data;

const COST_TYPES = ['base', 'quantity', 'bulky', 'express', 'multi_source', 'other'];
const FORMS = ['per_sqm', 'per_piece', 'per_position', 'percent'];
const COST_COLORS = { base: 'c1', bulky: 'c2', quantity: 'c3', express: 'c4' };
const DEFAULT_SAMPLES = [
    { width: 1000, height: 500, perMotif: 1 },
    { width: 1500, height: 1000, perMotif: 1 },
    { width: 2400, height: 1200, perMotif: 2 },
];

/**
 * Karte „Logistikanteil“ (KONZEPT 3d.6): unsichtbarer Aufschlag im VK für Versand- und Servicekosten der Lieferanten,
 * an Kalkulation, Material oder Optionswert. Speichert eigenständig in `ld_mensura_logistics_rule` (eigenes Recht,
 * nicht Teil des Seiten-Speicherns). Änderungen an einer gültigen Regel legen eine neue Zeile mit „gültig ab“ an,
 * damit der Verlauf für Vectura erhalten bleibt. Vorschau rechnet serverseitig mit derselben Engine wie der Shop.
 *
 * Nur einbinden mit `acl.can('ld_mensura.logistics')`; pflegen nur mit `ld_mensura.logistics_edit`.
 *
 * Props: target {calculationId} | {materialId} | {optionValueId}; calculationId = Kalkulation für die Vorschau;
 * sample = feste Teile der Beispielkonfiguration (z. B. {materialId} oder {deliveryValue: 'express'});
 * optionInfo = {groupType, showPriceHint} bei Optionswerten (für Warnungen).
 */
export default {
    template: `
<mt-card class="lm-logistics" position-identifier="lm-logistics" :title="$tc('ld-mensura.logistics.cardTitle')">
    <template #headerRight>
        <mt-button v-if="canEdit" size="small" variant="secondary" @click="openCreate">
            <mt-icon name="regular-plus-circle" size="14px" />
            {{ $tc('ld-mensura.logistics.buttonAdd') }}
        </mt-button>
    </template>

    <lm-info-card name="logistics" :title="$tc('ld-mensura.logistics.infoTitle')">
        {{ $tc('ld-mensura.logistics.infoText') }}
    </lm-info-card>

    <sw-loader v-if="isLoading" size="32px" />

    <table v-else-if="sortedRules.length" class="lm-breakdown lm-logistics__rules">
        <thead>
            <tr>
                <th>{{ $tc('ld-mensura.logistics.labelCostType') }}</th>
                <th>{{ $tc('ld-mensura.logistics.labelForm') }}</th>
                <th class="lm-num">{{ $tc('ld-mensura.logistics.labelValue') }}</th>
                <th>{{ $tc('ld-mensura.logistics.columnRule') }}</th>
                <th>{{ $tc('ld-mensura.logistics.labelValidFrom') }}</th>
                <th>{{ $tc('ld-mensura.logistics.columnStatus') }}</th>
                <th v-if="canEdit"></th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="rule in sortedRules" :key="rule.id" :class="{ 'is--inactive': statusOf(rule).state === 'off' }">
                <td><lm-chip :label="$tc('ld-mensura.logistics.costTypes.' + rule.costType)" :color="costColor(rule.costType)" icon="regular-truck" /></td>
                <td>{{ $tc('ld-mensura.logistics.forms.' + rule.form) }}</td>
                <td class="lm-num">{{ valueText(rule) }}</td>
                <td class="lm-muted">{{ ruleDetail(rule) }}</td>
                <td>{{ rule.validFrom ? date(rule.validFrom) : $tc('ld-mensura.logistics.immediately') }}</td>
                <td><lm-status :state="statusOf(rule).state" :label="statusOf(rule).label" /></td>
                <td v-if="canEdit" class="lm-num">
                    <sw-context-button>
                        <sw-context-menu-item @click="openEdit(rule)">{{ $tc('global.default.edit') }}</sw-context-menu-item>
                        <sw-context-menu-item variant="danger" :disabled="statusOf(rule).key === 'replaced' || undefined" @click="deleteRule = rule">{{ $tc('global.default.delete') }}</sw-context-menu-item>
                    </sw-context-button>
                </td>
            </tr>
        </tbody>
    </table>

    <sw-empty-state
        v-else
        :title="$tc('ld-mensura.logistics.emptyTitle')"
        :subline="$tc('ld-mensura.logistics.emptySubline')"
        icon="regular-truck"
        :absolute="false"
    >
        <template #actions>
            <mt-button v-if="canEdit" variant="primary" @click="openCreate">{{ $tc('ld-mensura.logistics.buttonAdd') }}</mt-button>
        </template>
    </sw-empty-state>
    <p v-if="!hasVectura && !suggestionDemo" class="lm-muted lm-logistics__vectura">{{ $tc('ld-mensura.logistics.noVectura') }}</p>

    <div v-if="showSuggestions" class="lm-logistics__suggestions">
        <h4>{{ $tc('ld-mensura.logistics.vectura.title') }}</h4>
        <mt-banner v-if="suggestionDemo" variant="attention">{{ $tc('ld-mensura.logistics.vectura.demo') }}</mt-banner>
        <mt-banner v-if="suggestionStatus === 'forbidden'" variant="info">{{ $tc('ld-mensura.logistics.vectura.forbidden') }}</mt-banner>
        <mt-banner v-else-if="suggestionStatus === 'error'" variant="attention">{{ $tc('ld-mensura.logistics.vectura.error') }}</mt-banner>
        <p v-else-if="matchedSuggestions.length === 0" class="lm-muted">{{ $tc('ld-mensura.logistics.vectura.none') }}</p>
        <div v-for="suggestion in matchedSuggestions" :key="suggestion.id" class="lm-logistics__suggestion">
            <div class="lm-logistics__suggestion-main">
                <lm-chip :label="$tc('ld-mensura.logistics.costTypes.' + suggestion.costType)" :color="costColor(suggestion.costType)" icon="regular-truck" />
                <b>{{ valueText(suggestion.proposal) }}</b>
                <span class="lm-muted">{{ ruleDetail(suggestion.proposal) }}</span>
            </div>
            <div class="lm-muted">
                {{ $tc('ld-mensura.logistics.vectura.coverage', {
                    before: num(suggestion.coverage, 2),
                    target: num(suggestion.targetCoverage, 2),
                    cases: suggestion.cases,
                    from: date(suggestionMeta.period?.from),
                    to: date(suggestionMeta.period?.to),
                }) }}
                <template v-if="currentFor(suggestion.costType)"> · {{ $tc('ld-mensura.logistics.vectura.current', { value: valueText(currentFor(suggestion.costType)) }) }}</template>
            </div>
            <ul v-if="suggestionWarnings(suggestion).length" class="lm-logistics__suggestion-warnings">
                <li v-for="code in suggestionWarnings(suggestion)" :key="code">
                    <mt-icon name="regular-exclamation-triangle" size="12px" />
                    {{ $tc('ld-mensura.logistics.vectura.warnings.' + code) }}
                </li>
            </ul>
            <div class="lm-logistics__suggestion-actions">
                <mt-button
                    v-if="canEdit && suggestion.stable !== false"
                    size="small"
                    variant="secondary"
                    @click="adoptSuggestion(suggestion)"
                >
                    {{ $tc('ld-mensura.logistics.vectura.adopt', { date: date(adoptDate) }) }}
                </mt-button>
                <span v-else-if="suggestion.stable === false" class="lm-muted">{{ $tc('ld-mensura.logistics.vectura.notStable') }}</span>
            </div>
        </div>
    </div>

    <div class="lm-logistics__preview">
        <h4>{{ $tc('ld-mensura.logistics.previewTitle') }}</h4>
        <p class="lm-muted">{{ $tc('ld-mensura.logistics.previewIntro') }}</p>
        <sw-entity-single-select
            v-if="needsCalculationChoice"
            v-model:value="previewCalculationId"
            entity="ld_mensura_calculation"
            label-property="name"
            :label="$tc('ld-mensura.logistics.labelPreviewCalculation')"
            :help-text="$tc('ld-mensura.logistics.helpPreviewCalculation')"
        />
        <mt-banner v-if="!previewCalculationId" variant="info">{{ $tc('ld-mensura.logistics.previewNoCalculation') }}</mt-banner>
        <template v-else>
            <table class="lm-breakdown">
                <thead>
                    <tr>
                        <th>{{ $tc('ld-mensura.logistics.columnFormat') }}</th>
                        <th class="lm-num">{{ $tc('ld-mensura.calculation.test.perMotif') }}</th>
                        <th class="lm-num">{{ $tc('ld-mensura.logistics.columnWithout') }}</th>
                        <th class="lm-num">{{ $tc('ld-mensura.logistics.columnLogistics') }}</th>
                        <th class="lm-num">{{ $tc('ld-mensura.logistics.columnTotal') }}</th>
                        <th class="lm-num">{{ $tc('ld-mensura.logistics.columnShare') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(sample, index) in samples" :key="index">
                        <td class="lm-logistics__format">
                            <mt-number-field v-model="sample.width" number-type="int" :min="1" size="small" @update:model-value="schedulePreview" />
                            <span>×</span>
                            <mt-number-field v-model="sample.height" number-type="int" :min="1" size="small" @update:model-value="schedulePreview" />
                        </td>
                        <td class="lm-num"><mt-number-field v-model="sample.perMotif" number-type="int" :min="1" size="small" @update:model-value="schedulePreview" /></td>
                        <template v-if="previewRows[index] && previewRows[index].valid">
                            <td class="lm-num">{{ money(previewRows[index].totalWithoutLogistics) }}</td>
                            <td class="lm-num">{{ money(previewRows[index].logistics) }}</td>
                            <td class="lm-num"><b>{{ money(previewRows[index].total) }}</b></td>
                            <td class="lm-num">
                                <lm-status
                                    :state="previewRows[index].warn ? 'warn' : 'ok'"
                                    :label="num(previewRows[index].sharePercent, 1) + ' %'"
                                />
                            </td>
                        </template>
                        <td v-else colspan="4" class="lm-muted">{{ previewRows[index] ? previewRows[index].errors.join(' · ') : '…' }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-if="previewWarn" class="lm-logistics__warn">
                <mt-icon name="regular-exclamation-triangle" size="14px" />
                {{ $tc('ld-mensura.logistics.warnShare', { percent: num(warnPercent, 0) }) }}
            </p>
        </template>
    </div>

    <sw-modal
        v-if="draft"
        :title="$tc(draft.id && !draft.isNewVersion ? 'ld-mensura.logistics.modalEdit' : 'ld-mensura.logistics.modalCreate')"
        variant="large"
        @modal-close="draft = null"
    >
        <mt-banner v-if="draft.isNewVersion" variant="info">{{ $tc('ld-mensura.logistics.newVersionHint') }}</mt-banner>
        <mt-banner v-if="draft.source === 'vectura'" variant="info">{{ $tc('ld-mensura.logistics.vectura.adoptHint') }}</mt-banner>
        <sw-container columns="repeat(auto-fit, minmax(220px, 1fr))" gap="0 24px">
            <sw-single-select v-model:value="draft.costType" :options="costTypeOptions" :label="$tc('ld-mensura.logistics.labelCostType')" :help-text="$tc('ld-mensura.logistics.helpCostType')" @update:value="schedulePreview" />
            <sw-single-select v-model:value="draft.form" :options="formOptions" :label="$tc('ld-mensura.logistics.labelForm')" :help-text="$tc('ld-mensura.logistics.helpForm')" @update:value="schedulePreview" />
            <mt-number-field v-model="draft.value" :digits="4" :label="$tc('ld-mensura.logistics.labelValue') + ' (' + unitOf(draft.form) + ')'" :help-text="$tc('ld-mensura.logistics.helpValue')" @update:model-value="schedulePreview" />
            <mt-datepicker v-model="draft.validFrom" date-type="date" :label="$tc('ld-mensura.logistics.labelValidFrom')" :help-text="$tc('ld-mensura.logistics.helpValidFrom')" :required="draft.isNewVersion || undefined" />
        </sw-container>

        <mt-switch v-model="draft.useRamp" :label="$tc('ld-mensura.logistics.labelRamp')" :help-text="$tc('ld-mensura.logistics.helpRamp')" bordered @update:model-value="schedulePreview" />
        <sw-container v-if="draft.useRamp" columns="repeat(auto-fit, minmax(180px, 1fr))" gap="0 24px">
            <mt-number-field v-model="draft.rampFrom" number-type="int" :label="$tc('ld-mensura.logistics.labelRampFrom')" :help-text="$tc('ld-mensura.logistics.helpRampFrom')" @update:model-value="schedulePreview" />
            <mt-number-field v-model="draft.rampTo" number-type="int" :label="$tc('ld-mensura.logistics.labelRampTo')" :help-text="$tc('ld-mensura.logistics.helpRampTo')" :error="rampError" @update:model-value="schedulePreview" />
        </sw-container>
        <svg v-if="draft.useRamp" class="lm-logistics__ramp" viewBox="0 0 300 60" preserveAspectRatio="none" role="img" :aria-label="$tc('ld-mensura.logistics.rampGraph')">
            <polyline :points="rampPoints" fill="none" stroke="currentColor" stroke-width="2" />
        </svg>

        <mt-switch v-model="draft.useThreshold" :label="$tc('ld-mensura.logistics.labelThreshold')" :help-text="$tc('ld-mensura.logistics.helpThreshold')" bordered @update:model-value="schedulePreview" />
        <sw-container v-if="draft.useThreshold" columns="repeat(auto-fit, minmax(180px, 1fr))" gap="0 24px">
            <sw-single-select v-model:value="draft.thresholdBasis" :options="thresholdOptions" :label="$tc('ld-mensura.logistics.labelThresholdBasis')" :help-text="$tc('ld-mensura.logistics.helpThresholdBasis')" @update:value="schedulePreview" />
            <mt-number-field v-model="draft.thresholdFrom" :digits="2" :label="$tc('ld-mensura.logistics.labelThresholdFrom')" :help-text="$tc('ld-mensura.logistics.helpThresholdFrom')" @update:model-value="schedulePreview" />
            <mt-number-field v-model="draft.thresholdTo" :digits="2" :label="$tc('ld-mensura.logistics.labelThresholdTo')" :help-text="$tc('ld-mensura.logistics.helpThresholdTo')" :error="thresholdError" @update:model-value="schedulePreview" />
        </sw-container>

        <mt-switch v-model="draft.useMinimum" :label="$tc('ld-mensura.logistics.labelMinimum')" :help-text="$tc('ld-mensura.logistics.helpMinimum')" bordered @update:model-value="schedulePreview" />
        <sw-container v-if="draft.useMinimum" columns="repeat(auto-fit, minmax(180px, 1fr))" gap="0 24px">
            <mt-number-field v-model="draft.minimumAmount" :digits="2" :label="$tc('ld-mensura.logistics.labelMinimumAmount')" :help-text="$tc('ld-mensura.logistics.helpMinimumAmount')" @update:model-value="schedulePreview" />
            <sw-single-select v-if="target.optionValueId" v-model:value="draft.minimumScope" :options="scopeOptions" :label="$tc('ld-mensura.logistics.labelMinimumScope')" :help-text="$tc('ld-mensura.logistics.helpMinimumScope')" @update:value="schedulePreview" />
        </sw-container>

        <sw-entity-single-select
            v-if="target.optionValueId"
            v-model:value="draft.calculationId"
            entity="ld_mensura_calculation"
            label-property="name"
            :label="$tc('ld-mensura.logistics.labelRestriction')"
            :help-text="$tc('ld-mensura.logistics.helpRestriction')"
        />
        <mt-textarea v-model="draft.note" :label="$tc('ld-mensura.logistics.labelNote')" :help-text="$tc('ld-mensura.logistics.helpNote')" />

        <mt-banner v-for="warning in draftWarnings" :key="warning" variant="attention">{{ warning }}</mt-banner>

        <template #modal-footer>
            <mt-button size="small" @click="draft = null">{{ $tc('global.default.cancel') }}</mt-button>
            <mt-button size="small" variant="primary" :is-loading="isSaving" :disabled="!draftValid || undefined" @click="saveDraft">{{ $tc('ld-mensura.logistics.buttonSave') }}</mt-button>
        </template>
    </sw-modal>

    <sw-modal v-if="deleteRule" :title="$tc('ld-mensura.logistics.deleteTitle')" variant="small" @modal-close="deleteRule = null">
        <p>{{ $tc('ld-mensura.logistics.deleteText') }}</p>
        <template #modal-footer>
            <mt-button size="small" @click="deleteRule = null">{{ $tc('global.default.cancel') }}</mt-button>
            <mt-button size="small" variant="critical" @click="confirmDelete">{{ $tc('global.default.delete') }}</mt-button>
        </template>
    </sw-modal>
</mt-card>`,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Shopware.Mixin.getByName('notification')],

    props: {
        target: { type: Object, required: true },
        calculationId: { type: String, required: false, default: null },
        sample: { type: Object, required: false, default: () => ({}) },
        optionInfo: { type: Object, required: false, default: null },
        // gemeinsame Schlüssel mit Vectura (keine Fremdschlüssel): calculationKey | materialKey | valueKey
        keys: { type: Object, required: false, default: () => ({}) },
    },

    data() {
        return {
            rules: [],
            isLoading: false,
            isSaving: false,
            draft: null,
            deleteRule: null,
            samples: DEFAULT_SAMPLES.map((sample) => ({ ...sample })),
            previewRows: [],
            warnPercent: 15,
            previewCalculationId: this.calculationId,
            previewTimer: null,
            suggestionStatus: 'missing',
            suggestionMeta: {},
            suggestionList: [],
            suggestionDemo: false,
            adoptDate: nextMonday(),
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('ld_mensura_logistics_rule');
        },

        canEdit() {
            return this.acl.can('ld_mensura.logistics_edit');
        },

        targetKey() {
            return JSON.stringify([this.target.calculationId ?? null, this.target.materialId ?? null, this.target.optionValueId ?? null]);
        },

        hasVectura() {
            return (Shopware.Context.app.config.bundles ?? {}).LdVectura !== undefined;
        },

        /** Vorschlagsbereich nur, wenn Vectura (oder die Beispieldaten) antwortet; ohne Vectura bleibt er unsichtbar. */
        showSuggestions() {
            return this.suggestionStatus !== 'missing';
        },

        matchedSuggestions() {
            return matchSuggestions(this.suggestionList, this.keys);
        },

        needsCalculationChoice() {
            return !this.target.calculationId;
        },

        today() {
            return new Date().toISOString().slice(0, 10);
        },

        sortedRules() {
            return [...this.rules].sort((a, b) => (a.costType.localeCompare(b.costType)) || (this.dateOf(b) || '').localeCompare(this.dateOf(a) || ''));
        },

        /** Gültige Regel je Kostenart (und Beschränkung auf eine Kalkulation) – wie die Engine. */
        currentIds() {
            const current = {};
            this.rules.forEach((rule) => {
                const valid = this.dateOf(rule);
                if (rule.active === false || (valid && valid > this.today)) {
                    return;
                }
                const key = `${rule.costType}|${rule.calculationId ?? ''}`;
                if (!current[key] || (valid || '') > (this.dateOf(current[key]) || '')) {
                    current[key] = rule;
                }
            });

            return new Set(Object.values(current).map((rule) => rule.id));
        },

        costTypeOptions() {
            return COST_TYPES.map((value) => ({ value, label: this.$tc(`ld-mensura.logistics.costTypes.${value}`) }));
        },

        formOptions() {
            return FORMS.map((value) => ({ value, label: this.$tc(`ld-mensura.logistics.forms.${value}`) }));
        },

        thresholdOptions() {
            return ['total_area', 'quantity'].map((value) => ({ value, label: this.$tc(`ld-mensura.logistics.thresholdBases.${value}`) }));
        },

        scopeOptions() {
            return ['position', 'order'].map((value) => ({ value, label: this.$tc(`ld-mensura.logistics.scopes.${value}`) }));
        },

        rampError() {
            if (!this.draft?.useRamp || this.draft.rampFrom === null || this.draft.rampTo === null) {
                return null;
            }

            return this.draft.rampTo < this.draft.rampFrom + 100 ? { detail: this.$tc('ld-mensura.logistics.errorRamp') } : null;
        },

        thresholdError() {
            if (!this.draft?.useThreshold || this.draft.thresholdFrom === null || this.draft.thresholdTo === null) {
                return null;
            }

            return this.draft.thresholdTo <= this.draft.thresholdFrom ? { detail: this.$tc('ld-mensura.logistics.errorThreshold') } : null;
        },

        draftValid() {
            const draft = this.draft;
            if (!draft || draft.value === null || draft.value === undefined || !draft.costType || !draft.form) {
                return false;
            }
            if (draft.useRamp && (draft.rampFrom === null || draft.rampTo === null || this.rampError)) {
                return false;
            }
            if (draft.useThreshold && (!draft.thresholdBasis || draft.thresholdFrom === null || draft.thresholdTo === null || this.thresholdError)) {
                return false;
            }
            if (draft.useMinimum && (draft.minimumAmount === null || draft.minimumAmount === undefined)) {
                return false;
            }

            return !(draft.isNewVersion && !draft.validFrom);
        },

        /** Hinweise, die das Speichern nicht verhindern (KONZEPT 3a Punkt 5, 3d.6). */
        draftWarnings() {
            const draft = this.draft;
            if (!draft) {
                return [];
            }
            const warnings = [];
            const nonLinear = draft.useRamp || draft.useThreshold || draft.useMinimum || draft.form === 'percent';
            if (this.target.optionValueId && nonLinear && this.optionInfo?.showPriceHint !== false) {
                warnings.push(this.$tc('ld-mensura.logistics.warnHint'));
            }
            if (draft.useMinimum && draft.minimumScope === 'order' && this.optionInfo?.groupType !== 'delivery') {
                warnings.push(this.$tc('ld-mensura.logistics.warnOrderScope'));
            }
            const current = this.rules.find((rule) => this.currentIds.has(rule.id) && rule.costType === draft.costType
                && (rule.calculationId ?? null) === (draft.calculationId ?? null) && rule.id !== draft.id && rule.form === draft.form);
            const previous = draft.isNewVersion ? draft.previousValue : current?.value;
            if (previous !== undefined && previous !== null && draft.value !== null) {
                const limit = draft.form === 'percent' ? 2 : (draft.form === 'per_sqm' ? 1 : null);
                if (limit !== null && Math.abs(draft.value - previous) > limit) {
                    warnings.push(this.$tc('ld-mensura.logistics.warnStep', { limit: this.num(limit, 0), unit: this.unitOf(draft.form) }));
                }
            }
            if (this.previewWarn) {
                warnings.push(this.$tc('ld-mensura.logistics.warnShare', { percent: this.num(this.warnPercent, 0) }));
            }

            return warnings;
        },

        previewWarn() {
            return this.previewRows.some((row) => row?.valid && row.warn);
        },

        rampPoints() {
            const from = Number(this.draft?.rampFrom ?? 0);
            const to = Number(this.draft?.rampTo ?? 0);
            const points = [];
            for (let length = 0; length <= 3000; length += 50) {
                const factor = to > from ? Math.min(1, Math.max(0, (length - from) / (to - from))) : 1;
                points.push(`${(length / 3000) * 300},${58 - factor * 54}`);
            }

            return points.join(' ');
        },
    },

    watch: {
        // als Text vergleichen: Seiten übergeben target als neues Objekt bei jedem Rendern
        targetKey(value, old) {
            if (value !== old) {
                this.load();
            }
        },

        calculationId(value) {
            this.previewCalculationId = value;
        },

        previewCalculationId() {
            this.schedulePreview();
        },
    },

    created() {
        this.load();
        this.loadVectura();
    },

    beforeUnmount() {
        clearTimeout(this.previewTimer);
    },

    methods: {
        criteria() {
            const criteria = new Criteria(1, 100);
            if (this.target.optionValueId) {
                criteria.addFilter(Criteria.equals('optionValueId', this.target.optionValueId));
            } else if (this.target.materialId) {
                criteria.addFilter(Criteria.equals('materialId', this.target.materialId));
            } else {
                criteria.addFilter(Criteria.equals('calculationId', this.target.calculationId));
                criteria.addFilter(Criteria.equals('materialId', null));
                criteria.addFilter(Criteria.equals('optionValueId', null));
            }

            return criteria;
        },

        async load() {
            if (!this.target.calculationId && !this.target.materialId && !this.target.optionValueId) {
                this.rules = [];
                return;
            }
            this.isLoading = true;
            try {
                this.rules = [...await this.repository.search(this.criteria(), Shopware.Context.api)];
            } catch {
                this.rules = [];
                this.createNotificationError({ message: this.$tc('ld-mensura.logistics.errorLoad') });
            } finally {
                this.isLoading = false;
            }
            this.schedulePreview();
        },

        /** Vorschläge aus Vectura – nur wenn installiert (lose Kopplung, INTEGRATION.md 7.2). */
        async loadVectura() {
            let demo = false;
            try {
                demo = !this.hasVectura && window.localStorage.getItem(DEMO_KEY) === '1';
            } catch {
                demo = false;
            }
            if (demo) {
                const example = await import('../../service/vectura-suggestions.example.json');
                this.applySuggestions({ status: 'ok', data: example.default ?? example }, true);
                return;
            }
            const result = await loadSuggestions(
                Shopware.Application.getContainer('init').httpClient,
                { Authorization: `Bearer ${Shopware.Context.api.authToken.access}` },
                this.hasVectura,
                defaultPeriod(),
            );
            this.applySuggestions(result, false);
        },

        applySuggestions(result, demo) {
            this.suggestionDemo = demo;
            this.suggestionStatus = result.status;
            this.suggestionMeta = { period: result.data?.period ?? null, generatedAt: result.data?.generatedAt ?? null };
            this.suggestionList = result.data?.suggestions ?? [];
        },

        /** gültige Regel einer Kostenart für dieses Ziel (ohne Beschränkung auf eine Kalkulation) */
        currentFor(costType) {
            return this.rules.find((rule) => this.currentIds.has(rule.id) && rule.costType === costType && !rule.calculationId) ?? null;
        },

        suggestionWarnings(suggestion) {
            const codes = [...(suggestion.warnings ?? [])];
            if (!codes.includes('step_limit_exceeded') && stepExceeded(suggestion, this.currentFor(suggestion.costType))) {
                codes.push('step_limit_exceeded');
            }

            return codes;
        },

        /** Öffnet den Dialog mit dem Vorschlag – übernommen wird erst beim Speichern, mit gültig-ab (Standard: nächster Montag). */
        adoptSuggestion(suggestion) {
            this.draft = {
                ...this.emptyDraft(),
                ...proposalToDraft(suggestion, this.suggestionMeta, this.adoptDate),
            };
            this.schedulePreview();
        },

        dateOf(rule) {
            return rule.validFrom ? String(rule.validFrom).slice(0, 10) : null;
        },

        statusOf(rule) {
            if (rule.active === false) {
                return { key: 'inactive', state: 'off', label: this.$tc('lm-design.states.off') };
            }
            const valid = this.dateOf(rule);
            if (valid && valid > this.today) {
                return { key: 'planned', state: 'warn', label: this.$tc('ld-mensura.logistics.states.planned') };
            }
            if (this.currentIds.has(rule.id)) {
                return { key: 'current', state: 'ok', label: this.$tc('ld-mensura.logistics.states.current') };
            }

            return { key: 'replaced', state: 'off', label: this.$tc('ld-mensura.logistics.states.replaced') };
        },

        costColor(costType) {
            return COST_COLORS[costType] ?? 'c4';
        },

        unitOf(form) {
            return { percent: '%', per_sqm: '€/m²', per_piece: '€/Stk', per_position: '€' }[form] ?? '€';
        },

        valueText(rule) {
            return `${this.num(rule.value, rule.form === 'percent' ? 2 : 2)} ${this.unitOf(rule.form)}`;
        },

        ruleDetail(rule) {
            const parts = [];
            if (rule.rampFrom !== null && rule.rampFrom !== undefined) {
                parts.push(this.$tc('ld-mensura.logistics.detailRamp', { from: this.num(rule.rampFrom, 0), to: this.num(rule.rampTo, 0) }));
            }
            if (rule.thresholdBasis) {
                parts.push(this.$tc(`ld-mensura.logistics.detailThreshold.${rule.thresholdBasis}`, { from: this.num(rule.thresholdFrom, 2), to: this.num(rule.thresholdTo, 2) }));
            }
            if (rule.minimumAmount !== null && rule.minimumAmount !== undefined) {
                parts.push(this.$tc(`ld-mensura.logistics.detailMinimum.${rule.minimumScope ?? 'position'}`, { amount: this.money(rule.minimumAmount) }));
            }
            if (rule.source === 'vectura') {
                parts.push(this.$tc('ld-mensura.logistics.fromVectura'));
            }

            return parts.join(' · ') || '–';
        },

        emptyDraft() {
            return {
                id: null,
                isNewVersion: false,
                costType: this.target.optionValueId ? 'express' : 'base',
                form: 'per_sqm',
                value: null,
                validFrom: null,
                useRamp: false,
                rampFrom: 1000,
                rampTo: 1400,
                useThreshold: false,
                thresholdBasis: 'total_area',
                thresholdFrom: null,
                thresholdTo: null,
                useMinimum: false,
                minimumAmount: null,
                minimumScope: this.optionInfo?.groupType === 'delivery' ? 'order' : 'position',
                calculationId: this.target.optionValueId ? null : (this.target.calculationId ?? null),
                note: '',
            };
        },

        openCreate() {
            this.draft = this.emptyDraft();
            this.schedulePreview();
        },

        /** Gültige (bereits wirksame) Regeln werden nicht überschrieben, sondern ab einem Datum ersetzt. */
        openEdit(rule) {
            const status = this.statusOf(rule).key;
            const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
            this.draft = {
                ...this.emptyDraft(),
                id: rule.id,
                isNewVersion: status === 'current' || status === 'replaced',
                previousValue: rule.value,
                costType: rule.costType,
                form: rule.form,
                value: rule.value,
                validFrom: status === 'current' || status === 'replaced' ? tomorrow : this.dateOf(rule),
                useRamp: rule.rampFrom !== null && rule.rampFrom !== undefined,
                rampFrom: rule.rampFrom ?? 1000,
                rampTo: rule.rampTo ?? 1400,
                useThreshold: !!rule.thresholdBasis,
                thresholdBasis: rule.thresholdBasis ?? 'total_area',
                thresholdFrom: rule.thresholdFrom,
                thresholdTo: rule.thresholdTo,
                useMinimum: rule.minimumAmount !== null && rule.minimumAmount !== undefined,
                minimumAmount: rule.minimumAmount,
                minimumScope: rule.minimumScope ?? 'position',
                calculationId: rule.calculationId ?? null,
                note: rule.note ?? '',
            };
            this.schedulePreview();
        },

        /** Gespeicherte Regel als einfache Daten (für die Vorschau-Route). */
        ruleData(rule) {
            const fields = ['id', 'calculationId', 'materialId', 'optionValueId', 'costType', 'form', 'value', 'rampFrom', 'rampTo',
                'thresholdBasis', 'thresholdFrom', 'thresholdTo', 'minimumAmount', 'minimumScope', 'validFrom', 'active'];

            return Object.fromEntries(fields.map((field) => [field, rule[field] ?? null]));
        },

        /** Entwurf als Regel-Daten (gleiches Format wie die Entity). */
        draftPayload() {
            const draft = this.draft;
            const optionTarget = !!this.target.optionValueId;

            return {
                calculationId: optionTarget ? (draft.calculationId || null) : (this.target.materialId ? null : this.target.calculationId),
                materialId: this.target.materialId ?? null,
                optionValueId: this.target.optionValueId ?? null,
                costType: draft.costType,
                form: draft.form,
                value: Number(draft.value),
                rampFrom: draft.useRamp ? draft.rampFrom : null,
                rampTo: draft.useRamp ? draft.rampTo : null,
                thresholdBasis: draft.useThreshold ? draft.thresholdBasis : null,
                thresholdFrom: draft.useThreshold ? draft.thresholdFrom : null,
                thresholdTo: draft.useThreshold ? draft.thresholdTo : null,
                minimumAmount: draft.useMinimum ? draft.minimumAmount : null,
                minimumScope: draft.useMinimum ? (optionTarget ? draft.minimumScope : 'position') : null,
                validFrom: draft.validFrom ? String(draft.validFrom).slice(0, 10) : null,
                active: true,
                note: draft.note || null,
            };
        },

        async saveDraft() {
            if (!this.draftValid) {
                return;
            }
            this.isSaving = true;
            const warnings = this.draftWarnings;
            try {
                const payload = this.draftPayload();
                const entity = this.draft.id && !this.draft.isNewVersion
                    ? this.rules.find((rule) => rule.id === this.draft.id)
                    : this.repository.create(Shopware.Context.api);
                if (entity.isNew?.()) {
                    Object.assign(entity, this.draft.source === 'vectura'
                        ? {
                            source: 'vectura',
                            sourceReference: {
                                ...this.draft.sourceReference,
                                adoptedAt: new Date().toISOString(),
                                adoptedBy: Shopware.Store.get('session').currentUser?.username ?? null,
                            },
                        }
                        : { source: 'manual' });
                }
                Object.assign(entity, payload);
                await this.repository.save(entity, Shopware.Context.api);
                this.draft = null;
                warnings.forEach((message) => this.createNotificationWarning({ message }));
                this.createNotificationSuccess({ message: this.$tc('ld-mensura.logistics.saved') });
                await this.load();
            } catch {
                this.createNotificationError({ message: this.$tc('ld-mensura.logistics.errorSave') });
            } finally {
                this.isSaving = false;
            }
        },

        async confirmDelete() {
            const rule = this.deleteRule;
            this.deleteRule = null;
            try {
                await this.repository.delete(rule.id, Shopware.Context.api);
                await this.load();
            } catch {
                this.createNotificationError({ message: this.$tc('ld-mensura.logistics.errorSave') });
            }
        },

        schedulePreview() {
            clearTimeout(this.previewTimer);
            this.previewTimer = setTimeout(() => this.runPreview(), 400);
        },

        async runPreview() {
            if (!this.previewCalculationId) {
                this.previewRows = [];
                return;
            }
            const draftRules = [];
            if (this.draft && this.draftValid) {
                const payload = this.draftPayload();
                const editsInPlace = this.draft.id && !this.draft.isNewVersion;
                // Vorschau „als gälte der Entwurf schon heute“: Regeln derselben Kostenart dieses Ziels ausblenden
                this.rules
                    .filter((rule) => rule.costType === payload.costType && (rule.calculationId ?? null) === (payload.calculationId ?? null)
                        && !(editsInPlace && rule.id === this.draft.id))
                    .forEach((rule) => draftRules.push({ ...this.ruleData(rule), active: false }));
                draftRules.push({ ...payload, id: editsInPlace ? this.draft.id : null, validFrom: null });
            }
            try {
                const { data } = await Shopware.Application.getContainer('init').httpClient.post('/_action/ld-mensura/logistics/preview', {
                    calculationId: this.previewCalculationId,
                    language: Shopware.Store.get('session').currentLocale ?? 'de-DE',
                    rules: draftRules,
                    samples: this.samples.map((sample) => ({
                        ...this.sample,
                        format: { width: sample.width, height: sample.height },
                        perMotif: sample.perMotif,
                    })),
                }, { headers: { Authorization: `Bearer ${Shopware.Context.api.authToken.access}` } });
                this.previewRows = data.samples ?? [];
                this.warnPercent = data.warnPercent ?? 15;
            } catch {
                this.previewRows = [];
            }
        },

        num(value, digits = 2) {
            return formatNumber(value, digits);
        },

        money(value) {
            return formatMoney(value);
        },

        date(value) {
            return value ? Shopware.Utils.format.date(value, { hour: undefined, minute: undefined }) : '–';
        },
    },
};
