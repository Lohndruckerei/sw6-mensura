/* GENERIERT aus ld-admin-design 1.0.0 (1d3d326) – nicht hier ändern, sondern in ~/projekte/sw6-entwicklung-lohndruckerei/ld-admin-design/vorlage und ./sync.sh ausführen. */
/**
 * Reiter mit Icon, Zähler und Warnhinweis.
 * - horizontal (Design B): bricht auf schmalen Bildschirmen um statt überzulaufen
 * - vertikal (Design C): Seitennavigation mit Gruppen, wie in den Shopware-Einstellungen
 * Design A nutzt stattdessen `sw-tabs`.
 *
 * items: [{ name, label, icon, count?, warn?, warnText?, group? }]
 */
export default {
    template: `
<nav
    class="lm-tabs"
    :class="{ 'lm-tabs--vertical': vertical }"
    :aria-label="ariaLabel"
>
    <template v-for="group in groupedItems" :key="group.name">
        <div
            v-if="vertical && group.label"
            class="lm-tabs__group"
        >
            {{ group.label }}
        </div>
        <button
            v-for="item in group.items"
            :key="item.name"
            type="button"
            class="lm-tabs__item"
            :class="{ 'is--active': item.name === active, 'is--warn': item.warn }"
            :aria-current="item.name === active ? 'page' : undefined"
            @click="$emit('select', item.name)"
        >
            <mt-icon
                class="lm-tabs__icon"
                :name="item.icon"
                size="16px"
            />
            <span class="lm-tabs__label">{{ item.label }}</span>
            <span
                v-if="item.count !== undefined && item.count !== null"
                class="lm-tabs__count"
            >
                {{ item.count }}
            </span>
            <mt-icon
                v-if="item.warn"
                v-tooltip="item.warnText || ''"
                class="lm-tabs__warn"
                name="regular-exclamation-triangle"
                size="12px"
            />
        </button>
    </template>
</nav>`,

    emits: ['select'],

    props: {
        items: {
            type: Array,
            required: true,
        },

        active: {
            type: String,
            required: true,
        },

        vertical: {
            type: Boolean,
            required: false,
            default: false,
        },

        groups: {
            type: Object,
            required: false,
            default: () => ({}),
        },

        ariaLabel: {
            type: String,
            required: false,
            default: null,
        },
    },

    computed: {
        groupedItems() {
            if (!this.vertical) {
                return [{ name: 'all', label: null, items: this.items }];
            }

            const groups = [];
            this.items.forEach((item) => {
                const name = item.group ?? 'default';
                let group = groups.find((entry) => entry.name === name);
                if (!group) {
                    group = { name, label: this.groups[name] ?? null, items: [] };
                    groups.push(group);
                }
                group.items.push(item);
            });

            return groups;
        },
    },
};
