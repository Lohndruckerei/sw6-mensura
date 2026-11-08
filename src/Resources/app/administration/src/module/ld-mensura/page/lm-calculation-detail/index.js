import template from './lm-calculation-detail.html.twig';
import { formatNumber, marginInfo, ruleOf } from '../../service/margin';
import { ensureTechnicalName } from '../../service/technical-name';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/** Vorlagen für „Neue Kalkulation“ (KONZEPT 3a Punkt 3) – füllen nur Felder vor, Preise trägt man selbst ein. */
const TEMPLATES = [
    {
        name: 'board', icon: 'regular-layer-group',
        values: {
            unit: 'area', formatMode: 'free', widthMin: 100, widthMax: 1500, heightMin: 100, heightMax: 3000, allowRotation: true,
            motifMode: 'multiply', tierScope: 'total', bestPriceMode: 'hint', areaScope: 'total', minArea: null, areaRounding: null,
            priceComponents: [{ type: 'per_piece', purchase: null, sale: 0.5 }], minimumPriceSale: 12.5, tierBasis: 'none', tiers: null,
            quantitySteps: [1, 2, 5, 10, 25, 50, 100],
        },
    },
    {
        name: 'banner', icon: 'regular-crop',
        values: {
            unit: 'area', formatMode: 'free', widthMin: 100, widthMax: 5000, heightMin: 100, heightMax: 50000, allowRotation: true,
            motifMode: 'multiply', tierScope: 'total', bestPriceMode: 'hint', areaScope: 'total', minArea: null, areaRounding: null,
            priceComponents: null, minimumPriceSale: 7.0, tierBasis: 'none',
            tiers: { sale: { basis: 'total_area', steps: [{ from: 10, discount: 5 }, { from: 25, discount: 9 }, { from: 50, discount: 15 }] }, purchase: { basis: 'none', steps: [] } },
            quantitySteps: [1, 2, 5, 10, 25, 50],
        },
    },
    {
        name: 'label', icon: 'regular-products',
        values: {
            unit: 'area', formatMode: 'free', widthMin: 10, widthMax: 500, heightMin: 10, heightMax: 500, allowRotation: true,
            motifMode: 'split', tierScope: 'total', bestPriceMode: 'apply', areaScope: 'total', minArea: null, areaRounding: null,
            priceComponents: null, minimumPriceSale: 11.45, tierBasis: 'none',
            tiers: { sale: { basis: 'total_area', steps: [{ from: 1, discount: 40 }, { from: 10, discount: 80 }] }, purchase: { basis: 'none', steps: [] } },
            quantitySteps: [250, 500, 1000, 2500, 5000],
        },
    },
    {
        name: 'flyer', icon: 'regular-file-text',
        values: {
            unit: 'piece', formatMode: 'fixed', widthMin: 50, widthMax: 420, heightMin: 50, heightMax: 594, allowRotation: true,
            motifMode: 'multiply', tierScope: 'total', bestPriceMode: 'hint', areaScope: 'total', minArea: null, areaRounding: null,
            priceComponents: null, minimumPriceSale: null, tierBasis: 'none', tiers: null,
            quantitySteps: [100, 250, 500, 1000, 2500, 5000],
        },
    },
];

/**
 * Kalkulation (KONZEPT 3.1, 3.3, 3.5): Grunddaten, Format und Fläche, Preise und VK-Regel, Materialien, Optionspreise je
 * Wert (+ je Material), Kundengruppen, Preistabellen, Testrechner. Warnungen beim Speichern (KONZEPT 3a Punkt 5).
 */
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('discard-detail-page-changes')('calculation')],

    shortcuts: { 'SYSTEMKEY+S': 'onSave', ESCAPE: 'onCancel' },

    props: {
        calculationId: { type: String, required: false, default: null },
    },

    data() {
        return {
            calculation: null,
            groups: null,
            isLoading: false,
            isSaveSuccessful: false,
            activeTab: 'base',
            editRow: null,
            chosenTemplate: null,
            testKey: 0,
            units: ['area', 'piece', 'length', 'weight', 'volume'],
            formatModes: ['free', 'fixed', 'both', 'none'],
            motifModes: ['multiply', 'split'],
            tierScopes: ['total', 'per_motif'],
            bestPriceModes: ['none', 'hint', 'apply'],
            areaScopes: ['total', 'piece'],
            saleRules: ['margin', 'markup', 'factor', 'free'],
            templates: TEMPLATES,
        };
    },

    metaInfo() {
        return { title: this.$createTitle(this.title) };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('ld_mensura_calculation');
        },

        title() {
            if (this.calculationId && this.calculation === null) {
                return this.$tc('ld-mensura.areas.calculation.title');
            }

            return this.calculation?.name || this.$tc('ld-mensura.calculation.detail.newTitle');
        },

        canEdit() {
            return this.acl.can(this.calculationId ? 'ld_mensura.editor' : 'ld_mensura.creator');
        },

        showPurchase() {
            return this.acl.can('ld_mensura.purchase');
        },

        canEditCustomerGroups() {
            return this.acl.can('ld_mensura.customer_groups');
        },

        rule() {
            return ruleOf(this.calculation);
        },

        status() {
            if (!this.calculation) {
                return null;
            }
            if (!this.calculation.active) {
                return { state: 'off', label: this.$tc('lm-design.states.off') };
            }
            if (!this.calculation.materials || this.calculation.materials.length === 0) {
                return { state: 'warn', label: this.$tc('ld-mensura.calculation.list.filterNoMaterial') };
            }

            return { state: 'ok', label: this.$tc('lm-design.states.ok') };
        },

        materialList() {
            return [...(this.calculation?.materials ?? [])];
        },

        sortedGroups() {
            return [...(this.groups ?? [])].sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
        },

        offeredCount() {
            return [...(this.calculation?.optionValues ?? [])].filter((row) => row.materialId === null && row.active !== false).length;
        },

        missingDefaults() {
            return this.sortedGroups.filter((group) => {
                const rows = this.sortedValues(group).map((value) => this.generalRow(value)).filter(Boolean);
                return group.required && rows.length > 0 && !rows.some((row) => row.isDefault);
            });
        },

        tabs() {
            const noMaterial = !this.calculation?.materials || this.calculation.materials.length === 0;

            return [
                { name: 'base', label: this.$tc('ld-mensura.calculation.detail.tabBase'), icon: 'regular-file-text', group: 'calculation' },
                { name: 'format', label: this.$tc('ld-mensura.calculation.detail.tabFormat'), icon: 'regular-crop', group: 'calculation' },
                { name: 'prices', label: this.$tc('ld-mensura.calculation.detail.tabPrices'), icon: 'regular-euro', group: 'calculation' },
                { name: 'materials', label: this.$tc('ld-mensura.areas.material.title'), icon: 'regular-layer-group', group: 'assortment', count: this.calculation?.materials?.length ?? 0, warn: noMaterial, warnText: this.$tc('ld-mensura.calculation.list.filterNoMaterial') },
                { name: 'options', label: this.$tc('ld-mensura.areas.option.title'), icon: 'regular-sliders-v', group: 'assortment', count: this.offeredCount, warn: this.missingDefaults.length > 0, warnText: this.$tc('ld-mensura.calculation.warnings.noDefault', { groups: this.missingDefaults.map((group) => group.translated?.name).join(', ') }) },
                // Kundengruppen-Aufschläge nur mit Recht ld_mensura.customer_groups (KONZEPT 3b)
                ...(this.canEditCustomerGroups ? [{ name: 'customerGroups', label: this.$tc('ld-mensura.calculation.detail.tabCustomerGroups'), icon: 'regular-users', group: 'assortment', count: this.calculation?.customerGroups?.length ?? 0 }] : []),
                { name: 'priceTables', label: this.$tc('ld-mensura.calculation.detail.tabPriceTables'), icon: 'regular-list', group: 'assortment', count: this.calculation?.priceTables?.length ?? 0 },
                { name: 'test', label: this.$tc('ld-mensura.calculation.detail.tabTest'), icon: 'regular-play', group: 'check' },
            ];
        },

        tabGroups() {
            return {
                calculation: this.$tc('ld-mensura.calculation.detail.groupCalculation'),
                assortment: this.$tc('ld-mensura.calculation.detail.groupAssortment'),
                check: this.$tc('ld-mensura.calculation.detail.groupCheck'),
            };
        },
    },

    watch: {
        calculationId() {
            this.createdComponent();
        },

        activeTab(tab) {
            if (tab === 'test') {
                this.testKey += 1;
            }
        },
    },

    created() {
        this.createdComponent();
        this.loadGroups();
    },

    methods: {
        createdComponent() {
            this.activeTab = 'base';
            this.chosenTemplate = null;
            if (this.calculationId) {
                this.load();
                return;
            }
            this.calculation = this.repository.create();
            Object.assign(this.calculation, {
                active: true, saleRule: 'margin', saleRuleValue: 25, marginMin: 20, marginMax: 30,
                ...TEMPLATES[0].values,
            });
        },

        async load() {
            this.isLoading = true;
            try {
                const criteria = new Criteria(1, 1);
                criteria.addAssociation('materials');
                criteria.addAssociation('optionValues');
                criteria.addAssociation('customerGroups');
                criteria.addAssociation('priceTables.rows');
                criteria.getAssociation('optionValues').setLimit(500);
                this.calculation = await this.repository.get(this.calculationId, Shopware.Context.api, criteria);
            } catch {
                this.createNotificationError({ message: this.$tc('global.notification.notificationLoadingDataErrorMessage') });
            } finally {
                this.isLoading = false;
            }
        },

        async loadGroups() {
            const criteria = new Criteria(1, 500);
            criteria.addAssociation('values');
            criteria.getAssociation('values').setLimit(500);
            this.groups = [...(await this.repositoryFactory.create('ld_mensura_option_group').search(criteria))];
        },

        options(key, list, base = 'ld-mensura.calculation.') {
            return list.map((value) => ({ value, label: this.$tc(`${base}${key}.${value}`) }));
        },

        applyTemplate(template) {
            this.chosenTemplate = template.name;
            Object.assign(this.calculation, JSON.parse(JSON.stringify(template.values)));
        },

        setQuantitySteps(list) {
            const steps = [...new Set(list.map((entry) => parseInt(entry, 10)).filter((entry) => entry > 0))].sort((a, b) => a - b);
            this.calculation.quantitySteps = steps.length ? steps : null;
        },

        sortedValues(group) {
            return [...(group.values ?? [])].sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
        },

        generalRow(value) {
            return [...(this.calculation?.optionValues ?? [])].find((row) => row.optionValueId === value.id && row.materialId === null) ?? null;
        },

        materialRows(value) {
            return [...(this.calculation?.optionValues ?? [])].filter((row) => row.optionValueId === value.id && row.materialId !== null);
        },

        isOffered(value) {
            const row = this.generalRow(value);
            return (row !== null && row.active !== false) || this.materialRows(value).length > 0;
        },

        createRow(value, materialId = null) {
            const row = this.repositoryFactory.create('ld_mensura_calculation_option_value').create();
            Object.assign(row, {
                calculationId: this.calculation.id,
                optionValueId: value.id,
                materialId,
                active: true,
                isDefault: false,
                position: value.position ?? 0,
                priceComponents: null,
                tiers: null,
                conditions: null,
            });
            this.calculation.optionValues.add(row);

            return row;
        },

        toggleOffered(value, offered) {
            const row = this.generalRow(value);
            if (offered && !row) {
                const group = this.groups.find((entry) => entry.id === value.optionGroupId);
                const created = this.createRow(value);
                // erster Wert einer Gruppe wird Standardwert
                created.isDefault = !this.sortedValues(group).some((entry) => this.generalRow(entry)?.isDefault && entry.id !== value.id);
            } else if (offered && row) {
                row.active = true;
            } else if (!offered) {
                [row, ...this.materialRows(value)].filter(Boolean).forEach((entry) => this.calculation.optionValues.remove(entry.id));
            }
        },

        setDefault(group, value) {
            this.sortedValues(group).forEach((entry) => {
                const row = this.generalRow(entry);
                if (row) {
                    row.isDefault = entry.id === value.id;
                }
            });
        },

        /** Vorauswahl je Gruppe (Kalkulation `preselect` {groupId: false}; fehlt = vorauswählen). */
        preselects(group) {
            return this.calculation?.preselect?.[group.id] !== false;
        },

        setPreselect(group, on) {
            const preselect = { ...(this.calculation.preselect ?? {}) };
            if (on) {
                delete preselect[group.id];
            } else {
                preselect[group.id] = false;
            }
            this.calculation.preselect = Object.keys(preselect).length ? preselect : null;
        },

        addMaterialRow(value) {
            const used = this.materialRows(value).map((row) => row.materialId);
            const material = this.materialList.find((entry) => !used.includes(entry.id));
            if (!material) {
                this.createNotificationInfo({ message: this.$tc('ld-mensura.calculation.options.allMaterialsUsed') });
                return;
            }
            const row = this.createRow(value, material.id);
            row.priceComponents = this.generalRow(value)?.priceComponents ? JSON.parse(JSON.stringify(this.generalRow(value).priceComponents)) : null;
            this.openRow(row, value);
        },

        openRow(row, value) {
            this.editRow = { row, name: value.translated?.name || value.technicalName };
        },

        materialName(id) {
            const material = this.materialList.find((entry) => entry.id === id);
            return material ? material.translated?.name || material.technicalName : id;
        },

        materialMargin(material) {
            return marginInfo(material.pricePurchase, material.priceSale, this.rule);
        },

        /** Kurzfassung der Preisanteile wie im Shop („+ 9,00 €/m²“), EK in Klammern mit Recht. */
        priceSummary(row) {
            if (!row) {
                return '';
            }
            const components = Array.isArray(row.priceComponents) ? row.priceComponents : [];
            if (!components.length) {
                return this.$tc('ld-mensura.calculation.options.noSurcharge');
            }

            return components.map((component) => {
                const unit = this.$tc(`ld-mensura.components.types.${component.type}`);
                const sale = component.sale === null || component.sale === undefined ? '–' : formatNumber(component.sale, 2);
                const purchase = this.showPurchase && component.purchase !== null && component.purchase !== undefined ? ` (EK ${formatNumber(component.purchase, 2)})` : '';

                return `${sale} ${unit}${purchase}`;
            }).join(' + ');
        },

        addCustomerGroupRule() {
            const rule = this.repositoryFactory.create('ld_mensura_customer_group_rule').create();
            Object.assign(rule, { calculationId: this.calculation.id, customerGroupId: null, surchargePercent: 10 });
            this.calculation.customerGroups.add(rule);
        },

        warnings() {
            const warnings = [];
            if (!this.calculation.materials || this.calculation.materials.length === 0) {
                warnings.push(this.$tc('ld-mensura.calculation.warnings.noMaterial'));
            }
            if (this.missingDefaults.length) {
                warnings.push(this.$tc('ld-mensura.calculation.warnings.noDefault', { groups: this.missingDefaults.map((group) => group.translated?.name).join(', ') }));
            }
            if (this.showPurchase) {
                const rows = [...(this.calculation.optionValues ?? [])];
                const below = rows.some((row) => (row.priceComponents ?? []).some((component) => component.sale !== null && component.purchase !== null
                    && component.purchase > 0 && Number(component.sale) <= Number(component.purchase)));
                if (below || (this.calculation.minimumPriceSale !== null && this.calculation.minimumPricePurchase !== null
                    && Number(this.calculation.minimumPriceSale) <= Number(this.calculation.minimumPricePurchase))) {
                    warnings.push(this.$tc('ld-mensura.calculation.warnings.saleBelowPurchase'));
                }
            }
            const materialWithoutPrice = this.materialList.filter((material) => material.priceSale === null || material.priceSale === undefined);
            if (materialWithoutPrice.length && this.calculation.unit === 'area' && !(this.calculation.priceTables?.length)) {
                warnings.push(this.$tc('ld-mensura.calculation.warnings.materialNoPrice', { materials: materialWithoutPrice.map((material) => material.translated?.name).join(', ') }));
            }
            if ([...(this.calculation.priceTables ?? [])].some((table) => !table.rows || table.rows.length === 0)) {
                warnings.push(this.$tc('ld-mensura.calculation.warnings.tableNoRows'));
            }

            return warnings;
        },

        async onSave() {
            if (!this.canEdit) {
                return;
            }
            this.isLoading = true;
            ensureTechnicalName(this.calculation);
            try {
                await this.repository.save(this.calculation, Shopware.Context.api);
                this.isSaveSuccessful = true;
                this.warnings().forEach((message) => this.createNotificationWarning({ message }));
                if (!this.calculationId) {
                    this.$router.push({ name: 'ld.mensura.calculationDetail', params: { id: this.calculation.id } });
                    return;
                }
                await this.load();
                this.testKey += 1;
            } catch (exception) {
                const detail = JSON.stringify(exception?.response?.data?.errors ?? []);
                this.createNotificationError({
                    message: detail.includes('technical_name')
                        ? this.$tc('ld-mensura.general.errorDuplicateTechnicalName')
                        : this.$tc('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        onCancel() {
            this.$router.push({ name: 'ld.mensura.calculation' });
        },

        fmt(value, digits = 2) {
            return formatNumber(value, digits);
        },
    },
};
