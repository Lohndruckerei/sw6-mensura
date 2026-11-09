import { formatNumber } from '../../service/margin';

const { Criteria } = Shopware.Data;

/**
 * Kalkulationen (KONZEPT 3.1): Rechenvorlagen je Artikelart mit Materialien, Mindestpreis und VK-Regel.
 */
export default {
    template: `
<lm-entity-list
    entity="ld_mensura_calculation"
    area="calculation"
    :title="$tc('ld-mensura.areas.calculation.title')"
    icon="regular-euro"
    snippet="ld-mensura.calculation.list"
    detail-route="ld.mensura.calculationDetail"
    create-route="ld.mensura.calculationCreate"
    default-sort="name"
    :name-of="nameOf"
    :status-of="statusOf"
    :columns="columns"
    :filters="filters"
    :associations="['materials']"
    :clone-overwrites="cloneOverwrites"
>
    <template #column-unit="{ item }">
        {{ $tc('ld-mensura.calculation.units.' + item.unit) }}
    </template>
    <template #column-materials="{ item }">
        <lm-chip v-for="material in item.materials" :key="material.id" color="c2" icon="regular-layer-group" :label="material.translated?.name || material.technicalName" />
        <span v-if="!item.materials || item.materials.length === 0" class="lm-muted">–</span>
    </template>
    <template #column-minimumPriceSale="{ item }">
        <span class="lm-num">{{ item.minimumPriceSale === null ? '–' : fmt(item.minimumPriceSale) + ' ' + $tc('ld-mensura.price.unitCurrency') }}</span>
    </template>
    <template #column-saleRule="{ item }">
        {{ $tc('ld-mensura.saleRule.' + item.saleRule) }} {{ item.saleRule === 'free' ? '' : fmt(item.saleRuleValue, 1) }}
    </template>
    <template #column-active="{ item }">
        <lm-status v-bind="statusOf(item)" />
    </template>
    <template #tile="{ item }">
        <div><lm-chip v-for="material in item.materials" :key="material.id" color="c2" icon="regular-layer-group" :label="material.translated?.name || material.technicalName" /></div>
        <div class="lm-tile__meta">
            <span>{{ $tc('ld-mensura.calculation.units.' + item.unit) }}</span>
            <span v-if="item.minimumPriceSale !== null">{{ $tc('ld-mensura.calculation.list.minimumShort', { price: fmt(item.minimumPriceSale) }) }}</span>
        </div>
    </template>
</lm-entity-list>`,

    computed: {
        filters() {
            return [
                { name: 'all', label: this.$tc('ld-mensura.filter.all'), icon: 'regular-euro', variant: 'all' },
                { name: 'active', label: this.$tc('ld-mensura.filter.active'), icon: 'regular-checkmark-s', variant: 'ok', filters: () => [Criteria.equals('active', true)] },
                { name: 'noMaterial', label: this.$tc('ld-mensura.calculation.list.filterNoMaterial'), icon: 'regular-exclamation-triangle', variant: 'warn', filters: () => [Criteria.equals('materials.id', null)] },
                { name: 'inactive', label: this.$tc('ld-mensura.filter.inactive'), icon: 'regular-ban', variant: 'off', filters: () => [Criteria.equals('active', false)] },
            ];
        },
    },

    methods: {
        nameOf(item) {
            return item.name || item.technicalName;
        },

        statusOf(item) {
            if (!item.active) {
                return { state: 'off', label: this.$tc('lm-design.states.off') };
            }
            if (!item.materials || item.materials.length === 0) {
                return { state: 'warn', label: this.$tc('ld-mensura.calculation.list.filterNoMaterial') };
            }

            return { state: 'ok', label: this.$tc('lm-design.states.ok') };
        },

        cloneOverwrites(item) {
            return { name: `${item.name} (${this.$tc('ld-mensura.general.copySuffix')})`, active: false };
        },

        columns(design) {
            return [
                { property: 'technicalName', label: 'ld-mensura.calculation.list.columnName', routerLink: 'ld.mensura.calculationDetail', primary: true, allowResize: true },
                ...(design === 'a' ? [{ property: 'name', label: 'ld-mensura.material.list.columnLabel', allowResize: true }] : []),
                { property: 'unit', label: 'ld-mensura.calculation.list.columnUnit', allowResize: true },
                { property: 'materials', label: 'ld-mensura.areas.material.title', sortable: false, allowResize: true },
                { property: 'minimumPriceSale', label: 'ld-mensura.calculation.list.columnMinimum', align: 'right', allowResize: true },
                { property: 'saleRule', label: 'ld-mensura.calculation.list.columnSaleRule', allowResize: true },
                { property: 'active', label: 'ld-mensura.general.columnStatus', allowResize: true },
            ];
        },

        fmt(value, digits = 2) {
            return formatNumber(value, digits);
        },
    },
};
