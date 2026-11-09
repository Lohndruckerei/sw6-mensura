const { Criteria } = Shopware.Data;

/**
 * Optionssets (KONZEPT 3.7): Gruppen in Reihenfolge = ein Konfigurationsschritt. Zeigt, wo das Set verwendet wird.
 */
export default {
    template: `
<lm-entity-list
    entity="ld_mensura_option_set"
    area="optionSet"
    :title="$tc('ld-mensura.areas.optionSet.title')"
    icon="regular-list"
    snippet="ld-mensura.optionSet.list"
    detail-route="ld.mensura.optionSetDetail"
    create-route="ld.mensura.optionSetCreate"
    default-sort="name"
    :name-of="nameOf"
    :columns="columns"
    :filters="filters"
    :associations="['groups.optionGroup', 'calculations', 'materials', 'optionValues']"
>
    <template #column-groups="{ item }">
        <lm-chip v-for="row in sortedGroups(item)" :key="row.id" color="c3" icon="regular-sliders-v" :label="row.optionGroup?.translated?.name || row.optionGroup?.technicalName || ''" />
    </template>
    <template #column-usage="{ item }">
        <span class="lm-muted">{{ usage(item) }}</span>
    </template>
    <template #tile="{ item }">
        <div><lm-chip v-for="row in sortedGroups(item)" :key="row.id" color="c3" icon="regular-sliders-v" :label="row.optionGroup?.translated?.name || row.optionGroup?.technicalName || ''" /></div>
        <div class="lm-tile__meta"><span>{{ usage(item) }}</span></div>
    </template>
</lm-entity-list>`,

    computed: {
        filters() {
            const unused = () => [
                Criteria.equals('calculations.id', null),
                Criteria.equals('materials.id', null),
                Criteria.equals('optionValues.id', null),
            ];

            return [
                { name: 'all', label: this.$tc('ld-mensura.filter.all'), icon: 'regular-list', variant: 'all' },
                { name: 'unused', label: this.$tc('ld-mensura.optionSet.list.filterUnused'), icon: 'regular-exclamation-triangle', variant: 'warn', filters: unused },
            ];
        },
    },

    methods: {
        nameOf(item) {
            return item.name || item.technicalName;
        },

        columns(design) {
            return [
                { property: 'technicalName', label: 'ld-mensura.optionSet.list.columnName', routerLink: 'ld.mensura.optionSetDetail', primary: true, allowResize: true },
                ...(design === 'a' ? [{ property: 'name', label: 'ld-mensura.material.list.columnLabel', allowResize: true }] : []),
                { property: 'groups', label: 'ld-mensura.optionSet.list.columnGroups', sortable: false, allowResize: true },
                { property: 'usage', label: 'ld-mensura.optionSet.list.columnUsage', sortable: false, allowResize: true },
            ];
        },

        sortedGroups(item) {
            return [...(item.groups ?? [])].sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
        },

        usage(item) {
            const parts = [];
            const add = (collection, key) => {
                const count = collection?.length ?? 0;
                if (count) {
                    parts.push(this.$tc(`ld-mensura.optionSet.usage.${key}`, { count }, count));
                }
            };
            add(item.calculations, 'calculations');
            add(item.materials, 'materials');
            add(item.optionValues, 'values');

            return parts.length ? parts.join(' · ') : this.$tc('ld-mensura.optionSet.usage.none');
        },
    },
};
