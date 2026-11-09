import { formatNumber } from '../../service/margin';

/**
 * Preistabellen einer Kalkulation (Flyer, Broschüren): Schlüssel Material + Optionswerte, Zeilen Auflage → EK/VK.
 * Inline-Bearbeitung, CSV-Export (Datei) und CSV-Import (aus Excel einfügen: Auflage;EK;VK bzw. Auflage;VK).
 */
export default {
    template: `
<div class="lm-price-tables">
    <mt-card
        v-for="table in sortedTables"
        :key="table.id"
        :position-identifier="'lm-price-table-' + table.id"
        :title="table.name || $tc('ld-mensura.calculation.tables.untitled')"
    >
        <sw-container columns="repeat(auto-fit, minmax(220px, 1fr))" gap="0 30px">
            <mt-text-field v-model="table.name" :label="$tc('ld-mensura.calculation.tables.labelName')" :help-text="$tc('ld-mensura.calculation.tables.helpName')" :disabled="disabled || undefined" />
            <sw-single-select
                :value="table.materialId || '__all'"
                :options="materialOptions"
                :label="$tc('ld-mensura.calculation.assignment.labelMaterial')"
                :help-text="$tc('ld-mensura.calculation.tables.helpMaterial')"
                :disabled="disabled || undefined"
                @update:value="table.materialId = $event === '__all' ? null : $event"
            />
            <sw-single-select
                v-model:value="table.priceType"
                :options="typeOptions"
                :label="$tc('ld-mensura.calculation.tables.labelType')"
                :help-text="$tc('ld-mensura.calculation.tables.helpType')"
                :disabled="disabled || undefined"
            />
        </sw-container>
        <sw-entity-multi-id-select
            :ids="table.optionValueIds || []"
            :repository="valueRepository"
            label-property="name"
            :label="$tc('ld-mensura.calculation.tables.labelKey')"
            :help-text="$tc('ld-mensura.calculation.tables.helpKey')"
            :disabled="disabled || undefined"
            @update:ids="table.optionValueIds = $event"
        />
        <mt-switch v-model="table.active" :label="$tc('ld-mensura.general.labelActive')" :disabled="disabled || undefined" bordered />

        <div class="lm-table-rows">
            <div class="lm-table-rows__head" :class="{ 'is--single': !showPurchase }">
                <!-- Hilfetexte der Zeilen als Hilfe-Symbol im Spaltenkopf (DESIGN 7.9) -->
                <span>{{ $tc('ld-mensura.calculation.tables.quantity') }} <sw-help-text :text="$tc('ld-mensura.rowHelp.tableQuantity')" /></span>
                <span v-if="showPurchase">{{ $tc('ld-mensura.price.purchase') }} <sw-help-text :text="$tc('ld-mensura.rowHelp.purchase')" /></span>
                <span>{{ $tc('ld-mensura.price.sale') }} <sw-help-text :text="$tc('ld-mensura.rowHelp.sale')" /></span>
                <span />
            </div>
            <div v-for="row in sortedRows(table)" :key="row.id" class="lm-table-rows__row" :class="{ 'is--single': !showPurchase }">
                <mt-number-field v-model="row.quantity" number-type="int" :disabled="disabled || undefined" />
                <mt-number-field v-if="showPurchase" v-model="row.pricePurchase" :digits="4" :disabled="disabled || undefined" />
                <mt-number-field v-model="row.priceSale" :digits="4" :disabled="disabled || undefined" />
                <mt-button size="small" variant="secondary" square :aria-label="$tc('ld-mensura.components.remove')" :disabled="disabled || undefined" @click="table.rows.remove(row.id)">
                    <mt-icon name="regular-trash" size="14px" />
                </mt-button>
            </div>
            <p v-if="!table.rows || table.rows.length === 0" class="lm-muted">{{ $tc('ld-mensura.calculation.tables.emptyRows') }}</p>
        </div>

        <div class="lm-table-actions">
            <mt-button size="small" variant="secondary" :disabled="disabled || undefined" @click="addRow(table)">
                <mt-icon name="regular-plus-circle" size="14px" /> {{ $tc('ld-mensura.calculation.tables.addRow') }}
            </mt-button>
            <mt-button size="small" variant="secondary" @click="exportCsv(table)">
                <mt-icon name="regular-file-export" size="14px" /> {{ $tc('ld-mensura.calculation.tables.export') }}
            </mt-button>
            <mt-button size="small" variant="secondary" :disabled="disabled || undefined" @click="importTable = table">
                <mt-icon name="regular-upload" size="14px" /> {{ $tc('ld-mensura.calculation.tables.import') }}
            </mt-button>
            <mt-button size="small" variant="critical" :disabled="disabled || undefined" @click="tables.remove(table.id)">
                {{ $tc('ld-mensura.calculation.tables.remove') }}
            </mt-button>
        </div>
    </mt-card>

    <sw-empty-state v-if="sortedTables.length === 0" :title="$tc('ld-mensura.calculation.tables.emptyTitle')" :subline="$tc('ld-mensura.calculation.tables.emptySubline')" icon="regular-list" :absolute="false" />
    <mt-button variant="secondary" :disabled="disabled || undefined" @click="addTable">
        <mt-icon name="regular-plus-circle" size="14px" /> {{ $tc('ld-mensura.calculation.tables.add') }}
    </mt-button>

    <sw-modal v-if="importTable" :title="$tc('ld-mensura.calculation.tables.importTitle')" @modal-close="importTable = null">
        <p class="lm-muted lm-card-intro">{{ showPurchase ? $tc('ld-mensura.calculation.tables.importHelp') : $tc('ld-mensura.calculation.tables.importHelpSale') }}</p>
        <mt-textarea v-model="importText" :label="$tc('ld-mensura.calculation.tables.importLabel')" placeholder="100;8,00;11,00" />
        <p v-if="importError" class="lm-tiers__warn">{{ importError }}</p>
        <template #modal-footer>
            <mt-button size="small" variant="secondary" @click="importTable = null">{{ $tc('global.default.cancel') }}</mt-button>
            <mt-button size="small" variant="primary" @click="runImport">{{ $tc('ld-mensura.calculation.tables.importApply') }}</mt-button>
        </template>
    </sw-modal>
</div>`,

    inject: ['repositoryFactory'],

    props: {
        tables: { type: Object, required: true },
        calculationId: { type: String, required: true },
        materials: { type: Array, required: false, default: () => [] },
        showPurchase: { type: Boolean, required: false, default: true },
        disabled: { type: Boolean, required: false, default: false },
    },

    data() {
        return { importTable: null, importText: '', importError: '' };
    },

    computed: {
        tableRepository() {
            return this.repositoryFactory.create('ld_mensura_price_table');
        },

        rowRepository() {
            return this.repositoryFactory.create('ld_mensura_price_table_row');
        },

        valueRepository() {
            return this.repositoryFactory.create('ld_mensura_option_value');
        },

        sortedTables() {
            return [...this.tables].sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
        },

        materialOptions() {
            return [
                { value: '__all', label: this.$tc('ld-mensura.calculation.assignment.allMaterials') },
                ...this.materials.map((material) => ({ value: material.id, label: material.translated?.name || material.technicalName })),
            ];
        },

        typeOptions() {
            return ['piece', 'total'].map((value) => ({ value, label: this.$tc(`ld-mensura.calculation.tables.types.${value}`) }));
        },
    },

    methods: {
        sortedRows(table) {
            return [...(table.rows ?? [])].sort((a, b) => (a.quantity ?? 0) - (b.quantity ?? 0));
        },

        addTable() {
            const table = this.tableRepository.create();
            Object.assign(table, { calculationId: this.calculationId, priceType: 'piece', active: true, position: this.tables.length + 1, optionValueIds: [] });
            this.tables.add(table);
        },

        addRow(table, values = {}) {
            const row = this.rowRepository.create();
            const last = this.sortedRows(table).pop();
            Object.assign(row, { priceTableId: table.id, quantity: last ? last.quantity * 2 : 100, pricePurchase: null, priceSale: null }, values);
            table.rows.add(row);
        },

        exportCsv(table) {
            const fmt = (value) => (value === null || value === undefined ? '' : formatNumber(value, 4).replace(/\./g, ''));
            const header = this.showPurchase ? 'Auflage;EK;VK' : 'Auflage;VK';
            const lines = this.sortedRows(table).map((row) => (this.showPurchase
                ? `${row.quantity};${fmt(row.pricePurchase)};${fmt(row.priceSale)}`
                : `${row.quantity};${fmt(row.priceSale)}`));
            const blob = new Blob([`﻿${header}\n${lines.join('\n')}\n`], { type: 'text/csv;charset=utf-8' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `${(table.name || 'preistabelle').replace(/[^a-z0-9-_]+/gi, '_')}.csv`;
            link.click();
            URL.revokeObjectURL(link.href);
        },

        parseNumber(text) {
            const value = String(text ?? '').trim().replace(/\s|€/g, '');
            if (value === '') {
                return null;
            }
            const normalized = value.includes(',') ? value.replace(/\./g, '').replace(',', '.') : value;
            const number = Number(normalized);

            return Number.isFinite(number) ? number : NaN;
        },

        runImport() {
            const rows = [];
            for (const line of this.importText.split(/\r?\n/)) {
                const cells = line.split(/[;\t]/).map((cell) => cell.trim());
                if (cells.join('') === '' || Number.isNaN(this.parseNumber(cells[0])) || this.parseNumber(cells[0]) === null) {
                    continue; // Kopfzeile oder leere Zeile
                }
                const quantity = this.parseNumber(cells[0]);
                const purchase = this.showPurchase && cells.length >= 3 ? this.parseNumber(cells[1]) : null;
                const sale = this.parseNumber(cells[this.showPurchase && cells.length >= 3 ? 2 : 1]);
                if ([quantity, purchase, sale].some((value) => Number.isNaN(value))) {
                    this.importError = this.$tc('ld-mensura.calculation.tables.importErrorLine', { line }, 0);
                    return;
                }
                rows.push({ quantity: Math.round(quantity), pricePurchase: purchase, priceSale: sale });
            }
            if (!rows.length) {
                this.importError = this.$tc('ld-mensura.calculation.tables.importErrorEmpty');
                return;
            }
            const table = this.importTable;
            [...table.rows].forEach((row) => table.rows.remove(row.id));
            rows.forEach((values) => this.addRow(table, values));
            this.importTable = null;
            this.importText = '';
            this.importError = '';
        },
    },
};
