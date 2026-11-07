import template from './lm-material-detail.html.twig';
import { marginInfo, ruleOf, formatNumber } from '../../service/margin';
import { ensureTechnicalName } from '../../service/technical-name';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

/**
 * Material anlegen und bearbeiten (KONZEPT 3.2): Grunddaten, Preise EK/VK mit Margenrechner, Preisanteile,
 * Staffeln, Format und Gewicht, Lieferant. Warnungen beim Speichern statt Fehlern im Shop (KONZEPT 3a Punkt 5).
 */
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('material'),
        Mixin.getByName('lm-design'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
    },

    props: {
        materialId: { type: String, required: false, default: null },
    },

    data() {
        return {
            material: null,
            isLoading: false,
            isSaveSuccessful: false,
            activeTab: 'base',
        };
    },

    metaInfo() {
        return { title: this.$createTitle(this.title) };
    },

    computed: {
        materialRepository() {
            return this.repositoryFactory.create('ld_mensura_material');
        },

        title() {
            if (this.materialId && this.material === null) {
                return this.$tc('ld-mensura.areas.material.title');
            }

            return this.material?.translated?.name || this.material?.name || this.$tc('ld-mensura.material.detail.newTitle');
        },

        canEdit() {
            return this.acl.can(this.materialId ? 'ld_mensura.editor' : 'ld_mensura.creator');
        },

        showPurchase() {
            return this.acl.can('ld_mensura.purchase');
        },

        hasLieferLotse() {
            return Shopware.EntityDefinition.has('ld_supplier');
        },

        /** VK-Regel der ersten zugeordneten Kalkulation (Material kann in mehreren stehen). */
        rule() {
            return ruleOf(this.material?.calculations?.first?.() ?? null);
        },

        ruleLabel() {
            const calculation = this.material?.calculations?.first?.();
            if (!calculation) {
                return this.$tc('ld-mensura.material.detail.ruleDefault');
            }

            return `${calculation.name}: ${this.$tc(`ld-mensura.saleRule.${this.rule.saleRule}`)} ${this.rule.saleRuleValue ?? ''}`;
        },

        margin() {
            return marginInfo(this.material?.pricePurchase, this.material?.priceSale, this.rule);
        },

        status() {
            if (!this.material?.active) {
                return { state: 'off', label: this.$tc('lm-design.states.off') };
            }
            if (this.material.priceSale === null || this.material.priceSale === undefined) {
                return { state: 'warn', label: this.$tc('ld-mensura.material.state.noPrice') };
            }

            return { state: 'ok', label: this.$tc('lm-design.states.ok') };
        },

        tabItems() {
            return [
                { name: 'base', label: this.$tc('ld-mensura.material.detail.tabBase'), icon: 'regular-file-text', group: 'material' },
                {
                    name: 'prices',
                    label: this.$tc('ld-mensura.material.detail.tabPrices'),
                    icon: 'regular-euro',
                    group: 'material',
                    warn: this.material?.priceSale === null || this.material?.priceSale === undefined,
                    warnText: this.$tc('ld-mensura.material.state.noPrice'),
                },
                { name: 'format', label: this.$tc('ld-mensura.material.detail.tabFormat'), icon: 'regular-crop', group: 'material' },
                { name: 'supplier', label: this.$tc('ld-mensura.material.detail.tabSupplier'), icon: 'regular-truck', group: 'connection' },
            ];
        },

        tabGroups() {
            return {
                material: this.$tc('ld-mensura.areas.material.title'),
                connection: this.$tc('ld-mensura.general.groupConnection'),
            };
        },

        tooltipSave() {
            if (this.canEdit) {
                return { message: `${this.$device.getSystemKey()} + S`, appearance: 'light' };
            }

            return { showDelay: 300, message: this.$tc('sw-privileges.tooltip.warning'), disabled: this.canEdit, showOnDisabledElements: true };
        },

        ...mapPropertyErrors('material', ['name', 'technicalName']),
    },

    watch: {
        materialId() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.activeTab = 'base';
            if (this.materialId) {
                this.load();
                return;
            }
            this.material = this.materialRepository.create();
            this.material.active = true;
            this.material.position = 0;
        },

        async load() {
            this.isLoading = true;
            try {
                const criteria = new Criteria(1, 1);
                criteria.addAssociation('calculations');
                this.material = await this.materialRepository.get(this.materialId, Shopware.Context.api, criteria);
            } catch {
                this.createNotificationError({ message: this.$tc('global.notification.notificationLoadingDataErrorMessage') });
            } finally {
                this.isLoading = false;
            }
        },

        /** Hinweise, die das Speichern nicht verhindern (KONZEPT 3a Punkt 5). */
        warnings() {
            const warnings = [];
            if (this.material.priceSale === null || this.material.priceSale === undefined) {
                warnings.push(this.$tc('ld-mensura.material.warnings.noPrice'));
            }
            if (this.showPurchase && this.margin?.state === 'err') {
                warnings.push(this.$tc('ld-mensura.material.warnings.saleBelowPurchase'));
            }
            if (!this.material.calculations || this.material.calculations.length === 0) {
                warnings.push(this.$tc('ld-mensura.material.warnings.noCalculation'));
            }

            return warnings;
        },

        async onSave() {
            if (!this.canEdit) {
                return;
            }
            this.isLoading = true;
            ensureTechnicalName(this.material);
            try {
                await this.materialRepository.save(this.material, Shopware.Context.api);
                this.isSaveSuccessful = true;
                this.warnings().forEach((message) => this.createNotificationWarning({ message }));
                if (!this.materialId) {
                    this.$router.push({ name: 'ld.mensura.materialDetail', params: { id: this.material.id } });
                    return;
                }
                await this.load();
            } catch (exception) {
                const detail = JSON.stringify(exception?.response?.data?.errors ?? []);
                this.createNotificationError({
                    message: detail.includes('uniq.ld_mensura_material.technical_name')
                        ? this.$tc('ld-mensura.general.errorDuplicateTechnicalName')
                        : this.$tc('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        onTabChange(item) {
            this.activeTab = typeof item === 'string' ? item : item?.name ?? 'base';
        },

        onCancel() {
            this.$router.push({ name: 'ld.mensura.material' });
        },

        fmt(value, digits = 2) {
            return formatNumber(value, digits);
        },
    },
};
