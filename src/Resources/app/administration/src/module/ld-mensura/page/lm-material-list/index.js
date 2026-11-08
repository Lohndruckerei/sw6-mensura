import { marginInfo, ruleOf, formatNumber } from '../../service/margin';

const { Criteria } = Shopware.Data;

/**
 * Materialien (KONZEPT 3.2) mit VK je m², Marge (nur mit Recht ld_mensura.purchase) und Kalkulationen.
 */
export default {
    template: `
<lm-entity-list
    entity="ld_mensura_material"
    area="material"
    :title="$tc('ld-mensura.areas.material.title')"
    icon="regular-layer-group"
    snippet="ld-mensura.material.list"
    info-snippet="ld-mensura.material.info"
    detail-route="ld.mensura.materialDetail"
    create-route="ld.mensura.materialCreate"
    default-sort="position"
    :status-of="statusOf"
    :columns="columns"
    :filters="filters"
    :associations="['calculations']"
    :clone-overwrites="cloneOverwrites"
>
    <template #column-priceSale="{ item }">
        <span class="lm-num">{{ priceLabel(item) }}</span>
    </template>
    <template #column-margin="{ item }">
        <lm-status v-if="marginOf(item)" :state="marginOf(item).state" :label="fmt(marginOf(item).margin, 1) + ' %'" />
        <span v-else class="lm-muted">–</span>
    </template>
    <template #column-calculations="{ item }">
        <lm-chip v-for="calculation in item.calculations" :key="calculation.id" color="c1" icon="regular-euro" :label="calculation.name" />
        <span v-if="!item.calculations || item.calculations.length === 0" class="lm-muted">–</span>
    </template>
    <template #column-active="{ item }">
        <lm-status v-bind="statusOf(item)" />
    </template>
    <template #tile="{ item }">
        <div><lm-chip v-for="calculation in item.calculations" :key="calculation.id" color="c1" icon="regular-euro" :label="calculation.name" /></div>
        <div class="lm-tile__meta">
            <span>{{ priceLabel(item) }}</span>
            <span v-if="item.weightPerSqm">{{ fmt(item.weightPerSqm, 2) }} kg/m²</span>
        </div>
    </template>
</lm-entity-list>`,

    inject: ['acl'],

    metaInfo() {
        return { title: this.$createTitle() };
    },

    computed: {
        showPurchase() {
            return this.acl.can('ld_mensura.purchase');
        },

        filters() {
            return [
                { name: 'all', label: this.$tc('ld-mensura.filter.all'), icon: 'regular-layer-group', variant: 'all' },
                { name: 'active', label: this.$tc('ld-mensura.filter.active'), icon: 'regular-checkmark-s', variant: 'ok', filters: () => [Criteria.equals('active', true)] },
                { name: 'noPrice', label: this.$tc('ld-mensura.filter.noPrice'), icon: 'regular-exclamation-triangle', variant: 'warn', filters: () => [Criteria.equals('priceSale', null)] },
                { name: 'inactive', label: this.$tc('ld-mensura.filter.inactive'), icon: 'regular-ban', variant: 'off', filters: () => [Criteria.equals('active', false)] },
            ];
        },
    },

    methods: {
        columns(design) {
            return [
                { property: 'technicalName', label: 'ld-mensura.material.list.columnName', routerLink: 'ld.mensura.materialDetail', primary: true, allowResize: true },
                ...(design === 'a' ? [{ property: 'name', label: 'ld-mensura.material.list.columnLabel', allowResize: true }] : []),
                { property: 'priceSale', label: 'ld-mensura.material.list.columnPrice', align: 'right', allowResize: true },
                ...(this.showPurchase ? [{ property: 'margin', label: 'ld-mensura.material.list.columnMargin', sortable: false, allowResize: true }] : []),
                { property: 'calculations', label: 'ld-mensura.material.list.columnCalculations', sortable: false, allowResize: true },
                { property: 'active', label: 'ld-mensura.general.columnStatus', allowResize: true },
            ];
        },

        statusOf(item) {
            if (!item.active) {
                return { state: 'off', label: this.$tc('lm-design.states.off') };
            }
            if (item.priceSale === null || item.priceSale === undefined) {
                return { state: 'warn', label: this.$tc('ld-mensura.material.state.noPrice') };
            }

            return { state: 'ok', label: this.$tc('lm-design.states.ok') };
        },

        /** Kopieren (KONZEPT 3a Punkt 6): gleiche Daten, neuer technischer Name, inaktiv bis zur Prüfung. */
        cloneOverwrites() {
            return { active: false };
        },

        marginOf(item) {
            return marginInfo(item.pricePurchase, item.priceSale, ruleOf(item.calculations?.first?.() ?? null));
        },

        priceLabel(item) {
            return item.priceSale === null || item.priceSale === undefined ? '–' : `${formatNumber(item.priceSale)} €/m²`;
        },

        fmt(value, digits) {
            return formatNumber(value, digits);
        },
    },
};
