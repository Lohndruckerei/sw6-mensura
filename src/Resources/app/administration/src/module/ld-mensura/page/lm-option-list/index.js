const { Criteria } = Shopware.Data;

const TYPE_ICONS = { standard: 'regular-sliders-v', delivery: 'regular-truck', format: 'regular-crop' };
const TYPE_COLORS = { standard: 'c1', delivery: 'c3', format: 'c2' };

/**
 * Optionsgruppen (Bibliothek): Farbigkeit, Veredelung, Lieferzeit, Festformate … mit Anzahl der Werte und Typ.
 */
export default {
    template: `
<lm-entity-list
    entity="ld_mensura_option_group"
    area="option"
    :title="$tc('ld-mensura.areas.option.title')"
    icon="regular-sliders-v"
    snippet="ld-mensura.option.list"
    detail-route="ld.mensura.optionDetail"
    create-route="ld.mensura.optionCreate"
    default-sort="position"
    :columns="columns"
    :filters="filters"
    :associations="['values', 'setGroups.optionSet']"
>
    <template #column-type="{ item }">
        <lm-chip :color="typeColor(item.type)" :icon="typeIcon(item.type)" :label="$tc('ld-mensura.option.types.' + item.type)" />
    </template>
    <template #column-values="{ item }">
        <span class="lm-num">{{ item.values ? item.values.length : 0 }}</span>
        <span class="lm-muted lm-option-list__preview">{{ preview(item) }}</span>
    </template>
    <template #column-sets="{ item }">
        <lm-chip v-for="row in item.setGroups" :key="row.id" color="c4" icon="regular-list" :label="row.optionSet?.name || ''" />
        <span v-if="!item.setGroups || item.setGroups.length === 0" class="lm-muted">–</span>
    </template>
    <template #column-required="{ item }">
        {{ item.required ? $tc('ld-mensura.general.yes') : $tc('ld-mensura.general.no') }}
    </template>
    <template #tile="{ item }">
        <div><lm-chip :color="typeColor(item.type)" :icon="typeIcon(item.type)" :label="$tc('ld-mensura.option.types.' + item.type)" /></div>
        <div class="lm-tile__meta"><span>{{ $tc('ld-mensura.option.list.valueCount', { count: item.values ? item.values.length : 0 }, item.values ? item.values.length : 0) }}</span><span>{{ preview(item) }}</span></div>
    </template>
</lm-entity-list>`,

    computed: {
        filters() {
            const byType = (type) => () => [Criteria.equals('type', type)];

            return [
                { name: 'all', label: this.$tc('ld-mensura.filter.all'), icon: 'regular-sliders-v', variant: 'all' },
                { name: 'standard', label: this.$tc('ld-mensura.option.types.standard'), icon: 'regular-sliders-v', variant: 'ok', filters: byType('standard') },
                { name: 'delivery', label: this.$tc('ld-mensura.option.types.delivery'), icon: 'regular-truck', variant: 'ok', filters: byType('delivery') },
                { name: 'format', label: this.$tc('ld-mensura.option.types.format'), icon: 'regular-crop', variant: 'ok', filters: byType('format') },
            ];
        },
    },

    methods: {
        columns(design) {
            const columns = [{ property: 'technicalName', label: 'ld-mensura.option.list.columnName', routerLink: 'ld.mensura.optionDetail', primary: true, allowResize: true }];
            if (design === 'a') {
                columns.push({ property: 'name', label: 'ld-mensura.material.list.columnLabel', allowResize: true });
            }

            return [
                ...columns,
                { property: 'type', label: 'ld-mensura.option.list.columnType', allowResize: true },
                { property: 'values', label: 'ld-mensura.option.list.columnValues', sortable: false, allowResize: true },
                { property: 'sets', label: 'ld-mensura.option.list.columnSets', sortable: false, allowResize: true },
                { property: 'required', label: 'ld-mensura.option.list.columnRequired', allowResize: true },
            ];
        },

        typeIcon(type) {
            return TYPE_ICONS[type] ?? 'regular-sliders-v';
        },

        typeColor(type) {
            return TYPE_COLORS[type] ?? 'c1';
        },

        preview(item) {
            const names = [...(item.values ?? [])]
                .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
                .map((value) => value.translated?.name || value.name || value.technicalName);

            return names.slice(0, 4).join(', ') + (names.length > 4 ? ' …' : '');
        },
    },
};
