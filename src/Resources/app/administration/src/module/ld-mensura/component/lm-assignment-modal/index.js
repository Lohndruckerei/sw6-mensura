/**
 * Optionswert in einer Kalkulation (KONZEPT 3.3): Preis als Preisanteile EK/VK, eigene Staffel, Bedingungen
 * (setzt voraus / schließt aus) und optional nur für ein Material („Abweichung je Material“).
 */
export default {
    template: `
<sw-modal :title="title" variant="large" @modal-close="$emit('close')">
    <sw-container columns="repeat(auto-fit, minmax(220px, 1fr))" gap="0 30px">
        <sw-single-select
            :value="assignment.materialId || '__all'"
            :options="materialOptions"
            :label="$tc('ld-mensura.calculation.assignment.labelMaterial')"
            :help-text="$tc('ld-mensura.calculation.assignment.helpMaterial')"
            :disabled="disabled || undefined"
            @update:value="assignment.materialId = $event === '__all' ? null : $event"
        />
        <mt-number-field v-model="assignment.position" number-type="int" :label="$tc('ld-mensura.general.labelPosition')" :help-text="$tc('ld-mensura.calculation.assignment.helpPosition')" :disabled="disabled || undefined" />
    </sw-container>
    <mt-switch v-model="assignment.active" :label="$tc('ld-mensura.calculation.assignment.labelActive')" :help-text="$tc('ld-mensura.calculation.assignment.helpActive')" :disabled="disabled || undefined" bordered />
    <mt-switch v-model="assignment.isDefault" :label="$tc('ld-mensura.calculation.assignment.labelDefault')" :help-text="$tc('ld-mensura.calculation.assignment.helpDefault')" :disabled="disabled || undefined" bordered />

    <h4 class="lm-tiers__title">{{ $tc('ld-mensura.calculation.assignment.headingPrice') }}</h4>
    <lm-price-components v-model:value="assignment.priceComponents" :show-purchase="showPurchase" :disabled="disabled" :empty-text="$tc('ld-mensura.calculation.assignment.noSurcharge')" />

    <h4 class="lm-tiers__title">{{ $tc('ld-mensura.calculation.assignment.headingTiers') }}</h4>
    <p class="lm-muted lm-card-intro">{{ $tc('ld-mensura.calculation.assignment.introTiers') }}</p>
    <lm-tier-editor v-model:value="assignment.tiers" :legacy-basis="legacyBasis" :show-purchase="showPurchase" :disabled="disabled" />

    <h4 class="lm-tiers__title">{{ $tc('ld-mensura.calculation.assignment.headingConditions') }}</h4>
    <sw-entity-multi-id-select
        :ids="conditions.requires || []"
        :repository="valueRepository"
        label-property="name"
        :label="$tc('ld-mensura.calculation.assignment.labelRequires')"
        :help-text="$tc('ld-mensura.calculation.assignment.helpRequires')"
        :disabled="disabled || undefined"
        @update:ids="setCondition('requires', $event)"
    />
    <sw-entity-multi-id-select
        :ids="conditions.excludes || []"
        :repository="valueRepository"
        label-property="name"
        :label="$tc('ld-mensura.calculation.assignment.labelExcludes')"
        :help-text="$tc('ld-mensura.calculation.assignment.helpExcludes')"
        :disabled="disabled || undefined"
        @update:ids="setCondition('excludes', $event)"
    />

    <template #modal-footer>
        <mt-button size="small" variant="secondary" @click="$emit('close')">{{ $tc('ld-mensura.option.value.buttonDone') }}</mt-button>
    </template>
</sw-modal>`,

    inject: ['repositoryFactory'],

    emits: ['close'],

    props: {
        assignment: { type: Object, required: true },
        valueName: { type: String, required: true },
        materials: { type: Array, required: false, default: () => [] },
        legacyBasis: { type: String, required: false, default: null },
        showPurchase: { type: Boolean, required: false, default: true },
        disabled: { type: Boolean, required: false, default: false },
    },

    computed: {
        valueRepository() {
            return this.repositoryFactory.create('ld_mensura_option_value');
        },

        title() {
            return this.$tc('ld-mensura.calculation.assignment.title', { value: this.valueName });
        },

        materialOptions() {
            return [
                { value: '__all', label: this.$tc('ld-mensura.calculation.assignment.allMaterials') },
                ...this.materials.map((material) => ({ value: material.id, label: material.translated?.name || material.technicalName })),
            ];
        },

        conditions() {
            return this.assignment.conditions ?? {};
        },
    },

    methods: {
        setCondition(key, ids) {
            const next = { ...this.conditions, [key]: ids };
            if (!ids.length) {
                delete next[key];
            }
            this.assignment.conditions = Object.keys(next).length ? next : null;
        },
    },
};
