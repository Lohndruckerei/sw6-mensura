/**
 * Lieferantencodes (INTEGRATION.md Abschnitt 3): [{ supplierId, code }], z. B. Comdatek „29.1“ für Alu 3 mm.
 * Mit LieferLotse Auswahl des Lieferanten, sonst ID als Text (ohne Fremdschlüssel). v-model:value
 */
export default {
    template: `
<div class="lm-codes">
    <h4 class="lm-tiers__title">{{ $tc('ld-mensura.supplierCodes.title') }}</h4>
    <p class="lm-muted">{{ $tc('ld-mensura.supplierCodes.help') }}</p>
    <div v-for="(row, index) in rows" :key="index" class="lm-codes__row">
        <sw-entity-single-select
            v-if="hasLieferlotse"
            :value="row.supplierId"
            entity="ld_supplier"
            label-property="name"
            :label="index === 0 ? $tc('ld-mensura.general.labelSupplier') : undefined"
            :disabled="disabled || undefined"
            @update:value="update(index, 'supplierId', $event)"
        />
        <mt-text-field
            v-else
            :model-value="row.supplierId"
            :label="index === 0 ? $tc('ld-mensura.general.labelSupplierId') : undefined"
            :disabled="disabled || undefined"
            @update:model-value="update(index, 'supplierId', $event)"
        />
        <mt-text-field
            :model-value="row.code"
            :label="index === 0 ? $tc('ld-mensura.supplierCodes.code') : undefined"
            :placeholder="$tc('ld-mensura.supplierCodes.placeholder')"
            :disabled="disabled || undefined"
            @update:model-value="update(index, 'code', $event)"
        />
        <mt-button size="small" variant="secondary" square :aria-label="$tc('ld-mensura.components.remove')" :disabled="disabled || undefined" @click="remove(index)">
            <mt-icon name="regular-trash" size="14px" />
        </mt-button>
    </div>
    <mt-button size="small" variant="secondary" :disabled="disabled || undefined" @click="add">
        <mt-icon name="regular-plus-circle" size="14px" />
        {{ $tc('ld-mensura.supplierCodes.add') }}
    </mt-button>
</div>`,

    emits: ['update:value'],

    props: {
        value: { type: Array, required: false, default: null },
        hasLieferlotse: { type: Boolean, required: false, default: false },
        disabled: { type: Boolean, required: false, default: false },
    },

    computed: {
        rows() {
            return Array.isArray(this.value) ? this.value : [];
        },
    },

    methods: {
        emit(rows) {
            this.$emit('update:value', rows.length ? rows : null);
        },

        update(index, field, value) {
            const rows = this.rows.map((row) => ({ ...row }));
            rows[index][field] = value;
            this.emit(rows);
        },

        add() {
            this.emit([...this.rows.map((row) => ({ ...row })), { supplierId: null, code: '' }]);
        },

        remove(index) {
            this.emit(this.rows.filter((_, i) => i !== index));
        },
    },
};
