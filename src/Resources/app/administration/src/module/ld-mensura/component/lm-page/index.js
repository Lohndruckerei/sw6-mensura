import { NAV_ITEMS } from '../../constants';

/**
 * Gemeinsamer Seitenrahmen aller Mensura-Oberflächen:
 * Smart-Bar mit Titel, Design-Schalter und Aktionen; Bereichsnavigation je Design
 * (A: keine – Shopware-Menü genügt, B: Reiter oben, C: Seitennavigation links).
 *
 * Slots: default (Inhalt), actions (weitere Smart-Bar-Buttons)
 */
export default {
    template: `
<sw-page class="lm-page" :class="lmDesignClass">
    <template v-if="$slots['search-bar']" #search-bar>
        <slot name="search-bar" />
    </template>

    <template #smart-bar-header>
        <h2>
            {{ title }}
            <span v-if="amount !== null" class="sw-page__smart-bar-amount">({{ amount }})</span>
        </h2>
    </template>

    <template #smart-bar-actions>
        <lm-design-switch />
        <slot name="actions" />
    </template>

    <template #content>
        <div class="lm-page__content" :class="lmDesignClass">
            <lm-tabs
                v-if="lmDesign === 'b'"
                :items="navItems"
                :active="active"
                :aria-label="$tc('ld-mensura.general.mainMenuItem')"
                @select="go"
            />

            <div v-if="lmDesign === 'c'" class="lm-split">
                <lm-tabs
                    vertical
                    :items="navItems"
                    :groups="navGroups"
                    :active="active"
                    :aria-label="$tc('ld-mensura.general.mainMenuItem')"
                    @select="go"
                />
                <div class="lm-page__main">
                    <slot />
                </div>
            </div>

            <slot v-else />
        </div>
    </template>
</sw-page>`,

    mixins: [Shopware.Mixin.getByName('lm-design')],

    props: {
        title: {
            type: String,
            required: true,
        },

        active: {
            type: String,
            required: true,
        },

        amount: {
            type: Number,
            required: false,
            default: null,
        },
    },

    computed: {
        navItems() {
            return NAV_ITEMS.map((item) => ({
                name: item.name,
                label: this.$tc(`ld-mensura.areas.${item.name}.title`),
                icon: item.icon,
                group: item.group,
            }));
        },

        navGroups() {
            return {
                start: this.$tc('ld-mensura.groups.start'),
                setup: this.$tc('ld-mensura.groups.setup'),
            };
        },
    },

    methods: {
        go(name) {
            const item = NAV_ITEMS.find((entry) => entry.name === name);
            if (item && name !== this.active) {
                this.$router.push({ name: item.route });
            }
        },
    },
};
