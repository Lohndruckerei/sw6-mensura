import { DESIGNS } from '../../constants';

/**
 * Schalter „Design A | B | C“. Speichert die Wahl je Benutzer.
 */
export default {
    template: `
<div class="lm-design-switch" role="group" :aria-label="$tc('lm-design.switchLabel')">
    <span class="lm-design-switch__label">{{ $tc('lm-design.switchLabel') }}</span>
    <button
        v-for="design in designs"
        :key="design.key"
        v-tooltip.bottom="{ message: design.title, appearance: 'light' }"
        type="button"
        class="lm-design-switch__option"
        :class="{ 'is--active': design.key === current }"
        :aria-pressed="design.key === current ? 'true' : 'false'"
        @click="select(design.key)"
    >
        {{ design.key.toUpperCase() }}
    </button>
</div>`,

    computed: {
        store() {
            return Shopware.Store.get('lmDesign');
        },

        current() {
            return this.store.design;
        },

        designs() {
            return DESIGNS.map((key) => {
                const isDefault = key === this.store.defaultDesign;

                return {
                    key,
                    title: this.$tc(`lm-design.designs.${key}`) + (isDefault ? ` (${this.$tc('lm-design.default')})` : ''),
                };
            });
        },
    },

    created() {
        this.store.ensureLoaded();
    },

    methods: {
        async select(design) {
            if (design === this.current) {
                return;
            }

            try {
                await this.store.setDesign(design);
            } catch {
                // Anzeige ist schon umgeschaltet; nur das Speichern für den Benutzer ist fehlgeschlagen.
            }
        },
    },
};
