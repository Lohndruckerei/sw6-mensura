import { formatNumber, marginInfo } from '../../service/margin';

/**
 * Testrechner einer Kalkulation (KONZEPT 3a Punkt 4, Abschnitt 5): rechnet mit derselben Engine wie Shop und
 * Warenkorb (Admin-API /_action/ld-mensura/test-calculate) – gespeicherter Stand der Kalkulation – und zeigt den
 * Rechenweg Zeile für Zeile mit VK, EK und Deckungsbeitrag (EK nur mit Recht, serverseitig geprüft).
 */
export default {
    template: `
<div class="lm-testcalc">
    <mt-card position-identifier="lm-testcalc-input" :title="$tc('ld-mensura.calculation.test.cardInput')">
        <p class="lm-muted lm-card-intro">{{ $tc('ld-mensura.calculation.test.intro') }}</p>
        <sw-container columns="repeat(auto-fit, minmax(180px, 1fr))" gap="0 24px">
            <sw-single-select v-model:value="input.materialId" :options="materialOptions" :label="$tc('ld-mensura.areas.material.title')" />
            <mt-number-field v-model="input.width" number-type="int" :label="$tc('ld-mensura.calculation.test.width')" />
            <mt-number-field v-model="input.height" number-type="int" :label="$tc('ld-mensura.calculation.test.height')" />
            <mt-number-field v-model="input.motifs" number-type="int" :min="1" :label="$tc('ld-mensura.calculation.test.motifs')" />
            <mt-number-field v-model="input.perMotif" number-type="int" :min="1" :label="$tc('ld-mensura.calculation.test.perMotif')" />
            <sw-entity-single-select v-model:value="input.customerGroupId" entity="customer_group" label-property="name" :label="$tc('ld-mensura.calculation.test.customerGroup')" :help-text="$tc('ld-mensura.calculation.test.helpCustomerGroup')" />
        </sw-container>
        <sw-container columns="repeat(auto-fit, minmax(180px, 1fr))" gap="0 24px">
            <sw-single-select
                v-for="group in groupChoices"
                :key="group.technicalName"
                :value="input.options[group.technicalName] ?? null"
                :options="group.options"
                :label="group.label"
                @update:value="setOption(group, $event)"
            />
        </sw-container>
        <mt-button variant="primary" :is-loading="isLoading" @click="calculate">
            {{ $tc('ld-mensura.calculation.test.buttonCalculate') }}
        </mt-button>
        <span class="lm-muted lm-testcalc__note">{{ $tc('ld-mensura.calculation.test.noteSaved') }}</span>
    </mt-card>

    <mt-card v-if="response" position-identifier="lm-testcalc-result" :title="$tc('ld-mensura.calculation.test.cardResult')">
        <div v-if="!result.valid" class="lm-testcalc__errors">
            <mt-banner v-for="error in result.errors" :key="error.code" variant="critical" :title="error.code">{{ error.message }}</mt-banner>
        </div>
        <template v-else>
            <div class="lm-kpis lm-testcalc__kpis">
                <div class="lm-kpi lm-kpi--all"><mt-icon name="regular-euro" size="20px" /><span><b>{{ money(result.price.total) }}</b><small>{{ $tc('ld-mensura.calculation.test.total') }}</small></span></div>
                <div class="lm-kpi lm-kpi--all"><mt-icon name="regular-products" size="20px" /><span><b>{{ money(result.price.perPiece) }}</b><small>{{ $tc('ld-mensura.calculation.test.perPiece') }}</small></span></div>
                <div class="lm-kpi lm-kpi--all"><mt-icon name="regular-crop" size="20px" /><span><b>{{ money(result.price.perSqm) }}</b><small>{{ $tc('ld-mensura.calculation.test.perSqm') }}</small></span></div>
                <div v-if="response.purchase" class="lm-kpi" :class="'lm-kpi--' + (margin ? margin.state : 'all')"><mt-icon name="regular-chart-line" size="20px" /><span><b>{{ money(response.contributionMargin) }}</b><small>{{ $tc('ld-mensura.calculation.test.contribution', { margin: margin ? num(margin.margin, 1) : '–' }) }}</small></span></div>
            </div>
            <mt-banner v-for="hint in result.hints" :key="hint.code" variant="info">{{ hint.message }}</mt-banner>

            <table class="lm-breakdown">
                <thead>
                    <tr>
                        <th>{{ $tc('ld-mensura.calculation.test.line') }}</th>
                        <th class="lm-num">{{ $tc('ld-mensura.price.sale') }}</th>
                        <th v-if="response.purchase" class="lm-num">{{ $tc('ld-mensura.price.purchase') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.key">
                        <td>{{ row.label }}</td>
                        <td class="lm-num">{{ row.sale }}</td>
                        <td v-if="response.purchase" class="lm-num">{{ row.purchase }}</td>
                    </tr>
                </tbody>
            </table>
            <p class="lm-muted">
                {{ $tc('ld-mensura.calculation.test.areaLine', { piece: num(response.areas?.piece, 4), total: num(response.areas?.total, 4), billed: num(response.areas?.billed, 4) }) }}
                <template v-if="response.weight"> · {{ $tc('ld-mensura.calculation.test.weightLine', { total: num(response.weight.total, 2) }) }}</template>
            </p>
        </template>
    </mt-card>
</div>`,

    inject: ['repositoryFactory'],

    mixins: [Shopware.Mixin.getByName('notification')],

    props: {
        calculation: { type: Object, required: true },
        groups: { type: Array, required: true },
    },

    data() {
        const first = this.calculation.materials?.first?.() ?? null;

        return {
            isLoading: false,
            response: null,
            input: {
                materialId: first?.id ?? null,
                width: 1000,
                height: 500,
                motifs: 1,
                perMotif: 1,
                customerGroupId: null,
                options: {},
                deliveryValue: null,
            },
        };
    },

    computed: {
        httpClient() {
            return Shopware.Application.getContainer('init').httpClient;
        },

        materialOptions() {
            return [...(this.calculation.materials ?? [])].map((material) => ({ value: material.id, label: material.translated?.name || material.technicalName }));
        },

        /** Gruppen mit in dieser Kalkulation angebotenen Werten (für das gewählte Material). */
        groupChoices() {
            const assigned = [...(this.calculation.optionValues ?? [])].filter((row) => row.active !== false
                && (row.materialId === null || row.materialId === this.input.materialId));

            return [...this.groups]
                .sort((a, b) => (a.position ?? 0) - (b.position ?? 0)
                    || (a.translated?.name || a.technicalName).localeCompare(b.translated?.name || b.technicalName))
                .map((group) => {
                    const options = [...(group.values ?? [])]
                        .filter((value) => assigned.some((row) => row.optionValueId === value.id))
                        .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
                        .map((value) => ({ value: value.technicalName, label: value.translated?.name || value.technicalName }));

                    return { technicalName: group.technicalName, type: group.type, label: group.translated?.name || group.technicalName, options };
                })
                .filter((group) => group.options.length > 0 && group.type !== 'format');
        },

        result() {
            return this.response?.result ?? {};
        },

        margin() {
            if (!this.response?.purchase) {
                return null;
            }

            return marginInfo(this.response.purchase.total, this.response.sale.total);
        },

        rows() {
            const sale = this.response?.sale ?? {};
            const purchase = this.response?.purchase ?? {};
            const line = (side, key) => (side.lines ?? []).find((entry) => entry.key === key);
            const value = (side, key, digits = 2, suffix = ' €') => {
                const entry = line(side, key);
                return entry && entry.amount !== undefined ? `${this.num(entry.amount, digits)}${suffix}` : '–';
            };
            const rows = [
                { key: 'material', label: this.$tc('ld-mensura.calculation.test.lineMaterial'), sale: value(sale, 'material', 4, ' €/m²'), purchase: value(purchase, 'material', 4, ' €/m²') },
            ];
            const saleComponents = line(sale, 'components')?.items ?? [];
            const purchaseComponents = line(purchase, 'components')?.items ?? [];
            saleComponents.forEach((component, index) => {
                const other = purchaseComponents.find((entry) => entry.source === component.source && entry.type === component.type);
                rows.push({
                    key: `component-${index}`,
                    label: `${this.sourceLabel(component.source)} · ${this.$tc(`ld-mensura.components.types.${component.type}`)}`,
                    sale: this.num(component.amount, 4),
                    purchase: other ? this.num(other.amount, 4) : '–',
                });
            });
            rows.push(
                { key: 'piece', label: this.$tc('ld-mensura.calculation.test.linePiece'), sale: value(sale, 'piece', 4), purchase: value(purchase, 'piece', 4) },
                { key: 'quantity', label: this.$tc('ld-mensura.calculation.test.lineQuantity'), sale: this.num(sale.quantity, 0), purchase: this.num(purchase.quantity ?? null, 0) },
                { key: 'setup', label: this.$tc('ld-mensura.calculation.test.lineSetup'), sale: value(sale, 'setup'), purchase: value(purchase, 'setup') },
                { key: 'tier', label: this.$tc('ld-mensura.calculation.test.lineTier'), sale: value(sale, 'tierDiscountPercent', 1, ' %'), purchase: value(purchase, 'tierDiscountPercent', 1, ' %') },
                { key: 'percent', label: this.$tc('ld-mensura.calculation.test.linePercent'), sale: value(sale, 'percent', 1, ' %'), purchase: value(purchase, 'percent', 1, ' %') },
                { key: 'subtotal', label: this.$tc('ld-mensura.calculation.test.lineSubtotal'), sale: value(sale, 'subtotal'), purchase: value(purchase, 'subtotal') },
                { key: 'minimum', label: this.$tc('ld-mensura.calculation.test.lineMinimum'), sale: value(sale, 'minimum'), purchase: value(purchase, 'minimum') },
                { key: 'group', label: this.$tc('ld-mensura.calculation.test.lineCustomerGroup'), sale: value(sale, 'customerGroupPercent', 1, ' %'), purchase: '–' },
                { key: 'total', label: this.$tc('ld-mensura.calculation.test.total'), sale: this.money(sale.total), purchase: this.money(purchase.total ?? null) },
            );

            return rows;
        },
    },

    methods: {
        setOption(group, value) {
            if (group.type === 'delivery') {
                this.input.deliveryValue = value;
                return;
            }
            this.input.options = { ...this.input.options, [group.technicalName]: value };
        },

        async calculate() {
            this.isLoading = true;
            try {
                const { data } = await this.httpClient.post('/_action/ld-mensura/test-calculate', {
                    calculationId: this.calculation.id,
                    customerGroupId: this.input.customerGroupId,
                    language: Shopware.Store.get('session').currentLocale ?? 'de-DE',
                    configuration: {
                        materialId: this.input.materialId,
                        format: { width: this.input.width, height: this.input.height },
                        motifs: this.input.motifs,
                        perMotif: this.input.perMotif,
                        options: this.input.options,
                        deliveryValue: this.input.deliveryValue,
                    },
                }, { headers: Shopware.Service('loginService').getBasicHeaders?.() ?? { Authorization: `Bearer ${Shopware.Context.api.authToken.access}` } });
                this.response = data;
                // normalisierte Optionen übernehmen (z. B. Standardwerte)
                this.input.options = { ...data.result.configuration.options };
                this.input.deliveryValue = data.result.configuration.deliveryValue ?? this.input.deliveryValue;
            } catch {
                this.createNotificationError({ message: this.$tc('ld-mensura.calculation.test.error') });
            } finally {
                this.isLoading = false;
            }
        },

        sourceLabel(source) {
            if (source.startsWith('option:')) {
                return source.slice(7);
            }

            return this.$tc(`ld-mensura.calculation.test.sources.${source}`);
        },

        num(value, digits = 2) {
            return formatNumber(value, digits);
        },

        money(value) {
            return value === null || value === undefined ? '–' : `${formatNumber(value, 2)} €`;
        },
    },
};
