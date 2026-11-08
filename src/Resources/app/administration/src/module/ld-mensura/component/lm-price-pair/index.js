import { marginInfo, suggestSale, formatNumber, DEFAULT_RULE } from '../../service/margin';

/**
 * EK und VK eines Preises nebeneinander, darunter Marge % und Aufschlag % in Farbe (KONZEPT 3.5):
 * grün im Zielkorridor, gelb darunter, rot bei VK ≤ EK. „VK übernehmen“ setzt den Vorschlag der VK-Regel –
 * gespeichert wird immer, was im VK-Feld steht.
 *
 * EK-Feld und Marge nur mit `showPurchase` (Recht ld_mensura.purchase, KONZEPT 3b).
 *
 * v-model:purchase, v-model:sale
 */
export default {
    template: `
<div class="lm-price-pair">
    <div class="lm-price-pair__fields" :class="{ 'is--single': !showPurchase }">
        <mt-number-field
            v-if="showPurchase"
            :model-value="purchase"
            :label="label + ' · ' + $tc('ld-mensura.price.purchase')"
            :help-text="helpPurchase || $tc('ld-mensura.price.helpPurchase')"
            :digits="digits"
            :disabled="disabled || undefined"
            :placeholder="unit"
            @update:model-value="$emit('update:purchase', $event)"
        />
        <mt-number-field
            :model-value="sale"
            :label="label + ' · ' + $tc('ld-mensura.price.sale')"
            :help-text="helpSale || $tc('ld-mensura.price.helpSale')"
            :digits="digits"
            :disabled="disabled || undefined"
            :placeholder="unit"
            @update:model-value="$emit('update:sale', $event)"
        />
    </div>
    <div v-if="showPurchase && (info || suggestion !== null)" class="lm-price-pair__margin">
        <template v-if="info">
            <lm-status :state="info.state" :label="statusLabel" />
            <span class="lm-muted">{{ $tc('ld-mensura.price.marginLine', { margin: fmt(info.margin, 1), markup: fmt(info.markup, 1) }) }}</span>
        </template>
        <mt-button
            v-if="suggestion !== null && suggestion !== Number(sale)"
            size="small"
            variant="secondary"
            :disabled="disabled || undefined"
            @click="$emit('update:sale', suggestion)"
        >
            {{ $tc('ld-mensura.price.applySuggestion', { price: fmt(suggestion, digits) }) }}
        </mt-button>
    </div>
</div>`,

    emits: ['update:purchase', 'update:sale'],

    props: {
        purchase: { type: Number, required: false, default: null },
        sale: { type: Number, required: false, default: null },
        label: { type: String, required: true },
        unit: { type: String, required: false, default: '' },
        helpPurchase: { type: String, required: false, default: '' },
        helpSale: { type: String, required: false, default: '' },
        rule: { type: Object, required: false, default: () => DEFAULT_RULE },
        showPurchase: { type: Boolean, required: false, default: true },
        disabled: { type: Boolean, required: false, default: false },
        digits: { type: Number, required: false, default: 2 },
    },

    computed: {
        info() {
            return marginInfo(this.purchase, this.sale, this.rule);
        },

        suggestion() {
            return suggestSale(this.purchase, this.rule);
        },

        statusLabel() {
            if (!this.info) {
                return '';
            }
            const keys = { ok: 'marginOk', warn: 'marginLow', err: 'marginNegative' };

            return this.$tc(`ld-mensura.price.${keys[this.info.state]}`);
        },
    },

    methods: {
        fmt(value, digits) {
            return formatNumber(value, digits);
        },
    },
};
