/**
 * Staffeln EK und VK getrennt (PRODUKTBEISPIELE Lücke 1), Format wie die Engine (Service\Calculation\Tiers):
 * { purchase: { basis, steps: [{ from, price } | { from, discount }] }, sale: { … } }.
 * Das alte Format (Liste mit purchase/sale, Basis aus `legacyBasis`) wird beim Laden umgewandelt.
 *
 * Beispiel PVC-Banner VK: Basis „Gesamtfläche“, ab 0 m² 5,55 €, ab 10 m² 5,25 €, ab 25 m² 5,05 €.
 * v-model:value
 */
export const BASES = ['none', 'quantity', 'total_area', 'piece_area', 'total_price'];

export function normalizeTiers(value, legacyBasis) {
    const empty = () => ({ basis: 'none', steps: [] });
    if (!value) {
        return { purchase: empty(), sale: empty() };
    }
    if (Array.isArray(value)) {
        const side = (key) => ({
            basis: legacyBasis || 'none',
            steps: value.map((step) => (step.discount !== undefined && step.discount !== null
                ? { from: step.from, discount: step.discount }
                : { from: step.from, price: step[key] ?? step.price ?? null })),
        });

        return { purchase: side('purchase'), sale: side('sale') };
    }

    return {
        purchase: { basis: value.purchase?.basis ?? 'none', steps: [...(value.purchase?.steps ?? [])] },
        sale: { basis: value.sale?.basis ?? 'none', steps: [...(value.sale?.steps ?? [])] },
    };
}

export default {
    template: `
<div class="lm-tiers">
    <div v-for="side in sides" :key="side" class="lm-tiers__side">
        <h4 class="lm-tiers__title">{{ $tc('ld-mensura.tiers.' + side) }}</h4>
        <sw-single-select
            :value="tiers[side].basis"
            :options="basisOptions"
            :label="$tc('ld-mensura.tiers.basis')"
            :help-text="$tc('ld-mensura.tiers.helpBasis')"
            :disabled="disabled || undefined"
            @update:value="setBasis(side, $event)"
        />
        <template v-if="tiers[side].basis !== 'none'">
            <div v-for="(step, index) in tiers[side].steps" :key="index" class="lm-tiers__row">
                <mt-number-field
                    :model-value="step.from"
                    :label="index === 0 ? $tc('ld-mensura.tiers.from') : undefined"
                    :help-text="index === 0 ? $tc('ld-mensura.rowHelp.tierFrom') : undefined"
                    :digits="2"
                    :disabled="disabled || undefined"
                    @update:model-value="setStep(side, index, 'from', $event)"
                />
                <sw-single-select
                    :value="step.discount !== undefined ? 'discount' : 'price'"
                    :options="modeOptions"
                    :label="index === 0 ? $tc('ld-mensura.tiers.mode') : undefined"
                    :help-text="index === 0 ? $tc('ld-mensura.rowHelp.tierMode') : undefined"
                    :disabled="disabled || undefined"
                    @update:value="setMode(side, index, $event)"
                />
                <mt-number-field
                    :model-value="step.discount !== undefined ? step.discount : step.price"
                    :label="index === 0 ? $tc('ld-mensura.tiers.value') : undefined"
                    :help-text="index === 0 ? $tc('ld-mensura.rowHelp.tierValue') : undefined"
                    :digits="4"
                    :disabled="disabled || undefined"
                    @update:model-value="setStep(side, index, step.discount !== undefined ? 'discount' : 'price', $event)"
                />
                <mt-button
                    size="small"
                    variant="secondary"
                    square
                    :aria-label="$tc('ld-mensura.tiers.remove')"
                    :disabled="disabled || undefined"
                    @click="removeStep(side, index)"
                >
                    <mt-icon name="regular-trash" size="14px" />
                </mt-button>
            </div>
            <mt-button size="small" variant="secondary" :disabled="disabled || undefined" @click="addStep(side)">
                <mt-icon name="regular-plus-circle" size="14px" />
                {{ $tc('ld-mensura.tiers.add') }}
            </mt-button>
            <p v-if="jumps[side]" class="lm-tiers__warn">
                <mt-icon name="regular-exclamation-triangle" size="14px" />
                {{ $tc('ld-mensura.tiers.warnOrder') }}
            </p>
        </template>
    </div>
</div>`,

    emits: ['update:value'],

    props: {
        value: { type: [Object, Array], required: false, default: null },
        legacyBasis: { type: String, required: false, default: null },
        showPurchase: { type: Boolean, required: false, default: true },
        disabled: { type: Boolean, required: false, default: false },
    },

    computed: {
        tiers() {
            return normalizeTiers(this.value, this.legacyBasis);
        },

        sides() {
            return this.showPurchase ? ['sale', 'purchase'] : ['sale'];
        },

        basisOptions() {
            return BASES.map((basis) => ({ value: basis, label: this.$tc(`ld-mensura.tiers.bases.${basis}`) }));
        },

        modeOptions() {
            return [
                { value: 'price', label: this.$tc('ld-mensura.tiers.modePrice') },
                { value: 'discount', label: this.$tc('ld-mensura.tiers.modeDiscount') },
            ];
        },

        /** Warnung „Staffelsprung“: Schwellen nicht aufsteigend oder höhere Schwelle mit höherem Preis. */
        jumps() {
            const check = (steps) => steps.some((step, i) => i > 0 && (Number(step.from) <= Number(steps[i - 1].from)
                || (step.price !== undefined && steps[i - 1].price !== undefined && Number(step.price) > Number(steps[i - 1].price))));

            return { sale: check(this.tiers.sale.steps), purchase: check(this.tiers.purchase.steps) };
        },
    },

    methods: {
        emit(tiers) {
            // leer erst, wenn beide Seiten keine Grundlage haben (Grundlage ohne Stufen bleibt stehen, bis Stufen folgen)
            const isEmpty = (side) => side.basis === 'none';
            this.$emit('update:value', isEmpty(tiers.sale) && isEmpty(tiers.purchase) ? null : tiers);
        },

        clone() {
            return JSON.parse(JSON.stringify(this.tiers));
        },

        setBasis(side, basis) {
            const tiers = this.clone();
            tiers[side].basis = basis;
            if (basis === 'none') {
                tiers[side].steps = [];
            } else if (tiers[side].steps.length === 0) {
                // erste Stufe gleich anlegen: ab 0 gilt der eingetragene Preis
                tiers[side].steps.push({ from: 0, price: null });
            }
            this.emit(tiers);
        },

        setStep(side, index, field, value) {
            const tiers = this.clone();
            tiers[side].steps[index][field] = value;
            this.emit(tiers);
        },

        setMode(side, index, mode) {
            const tiers = this.clone();
            const step = tiers[side].steps[index];
            const amount = step.discount ?? step.price ?? null;
            tiers[side].steps[index] = mode === 'discount' ? { from: step.from, discount: amount } : { from: step.from, price: amount };
            this.emit(tiers);
        },

        addStep(side) {
            const tiers = this.clone();
            const last = tiers[side].steps[tiers[side].steps.length - 1];
            tiers[side].steps.push({ from: last ? Number(last.from) + 1 : 0, price: null });
            this.emit(tiers);
        },

        removeStep(side, index) {
            const tiers = this.clone();
            tiers[side].steps.splice(index, 1);
            this.emit(tiers);
        },
    },
};
