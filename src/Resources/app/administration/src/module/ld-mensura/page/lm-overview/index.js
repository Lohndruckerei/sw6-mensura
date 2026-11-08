import template from './lm-overview.html.twig';
import { AREAS } from '../../constants';
import { formatNumber, marginInfo } from '../../service/margin';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const DAYS = 30;

/**
 * Startseite von Mensura (KONZEPT 3a, DESIGN.md 7.5/7.7/7.9): Kopfkarte mit Gesamtstatus, Erklärkarte mit dem
 * Ablauf Material → Kalkulation → Optionen → Produktprofil → Shop, Kennzahlen (Bestand und Bestellungen der
 * letzten 30 Tage), „Zu erledigen“ mit direkten Links und Bereichskacheln mit echten Zahlen.
 * Einkaufswerte (EK, Deckungsbeitrag) nur mit Recht ld_mensura.purchase.
 */
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('lm-design')],

    data() {
        return {
            isLoading: true,
            counts: {},
            orders: null,
        };
    },

    computed: {
        canSeePurchase() {
            return this.acl.can('ld_mensura.purchase');
        },

        areas() {
            return AREAS.map((area) => ({ ...area, count: this.counts[area.name] ?? null }));
        },

        todos() {
            const items = [
                { key: 'profilesNoMaterial', route: 'ld.mensura.profile', icon: 'regular-products' },
                { key: 'calculationsNoMaterial', route: 'ld.mensura.calculation', icon: 'regular-euro' },
                { key: 'materialsNoPrice', route: 'ld.mensura.material', icon: 'regular-layer-group' },
                { key: 'groupsNoValues', route: 'ld.mensura.option', icon: 'regular-sliders-v' },
                { key: 'profilesInactive', route: 'ld.mensura.profile', icon: 'regular-ban', state: 'off' },
            ];

            return items
                .map((item) => ({ ...item, state: item.state ?? 'warn', count: this.counts[item.key] ?? 0 }))
                .filter((item) => item.count > 0);
        },

        warnCount() {
            return this.todos.filter((item) => item.state === 'warn').reduce((sum, item) => sum + item.count, 0);
        },

        headerStatus() {
            if (this.isLoading) {
                return null;
            }
            if ((this.counts.profilesActive ?? 0) === 0) {
                return { state: 'off', label: this.$tc('ld-mensura.overview.statusNoProfile') };
            }
            if (this.warnCount > 0) {
                return { state: 'warn', label: this.$tc('ld-mensura.overview.statusTodos', { count: this.warnCount }, this.warnCount) };
            }

            return { state: 'ok', label: this.$tc('ld-mensura.overview.statusReady') };
        },

        flow() {
            return [
                { key: 'material', count: this.counts.material },
                { key: 'calculation', count: this.counts.calculation },
                { key: 'option', count: this.counts.option },
                { key: 'profile', count: this.counts.profilesActive },
            ];
        },

        margin() {
            if (!this.orders || !this.canSeePurchase || !this.orders.sale) {
                return null;
            }

            return marginInfo(this.orders.purchase, this.orders.sale);
        },
    },

    created() {
        this.load();
    },

    methods: {
        async count(entity, filters = []) {
            const criteria = new Criteria(1, 1);
            criteria.setTotalCountMode(1);
            filters.forEach((filter) => criteria.addFilter(filter));
            try {
                return (await this.repositoryFactory.create(entity).searchIds(criteria)).total;
            } catch {
                return null;
            }
        },

        async load() {
            this.isLoading = true;
            const entries = {
                profile: ['ld_mensura_product_profile'],
                profilesActive: ['ld_mensura_product_profile', [Criteria.equals('active', true)]],
                profilesInactive: ['ld_mensura_product_profile', [Criteria.equals('active', false)]],
                profilesNoMaterial: ['ld_mensura_product_profile', [Criteria.equals('materials.id', null)]],
                calculation: ['ld_mensura_calculation'],
                calculationsNoMaterial: ['ld_mensura_calculation', [Criteria.equals('active', true), Criteria.equals('materials.id', null)]],
                material: ['ld_mensura_material'],
                materialsNoPrice: ['ld_mensura_material', [Criteria.equals('active', true), Criteria.equals('priceSale', null)]],
                option: ['ld_mensura_option_group'],
                groupsNoValues: ['ld_mensura_option_group', [Criteria.equals('values.id', null)]],
                optionSet: ['ld_mensura_option_set'],
            };
            const results = await Promise.all(Object.entries(entries).map(async ([key, [entity, filters]]) => [key, await this.count(entity, filters)]));
            this.counts = Object.fromEntries(results);
            await this.loadOrders();
            this.isLoading = false;
        },

        async loadOrders() {
            const since = new Date(Date.now() - DAYS * 24 * 3600 * 1000).toISOString();
            const criteria = new Criteria(1, 1);
            criteria.setTotalCountMode(1);
            criteria.addFilter(Criteria.range('orderLineItem.order.orderDateTime', { gte: since }));
            criteria.addAggregation(Criteria.sum('sale', 'saleTotal'));
            if (this.canSeePurchase) {
                criteria.addAggregation(Criteria.sum('purchase', 'purchaseTotal'));
            }
            try {
                const result = await this.repositoryFactory.create('ld_mensura_order_line_item').search(criteria);
                this.orders = {
                    positions: result.total,
                    sale: result.aggregations?.sale?.sum ?? 0,
                    purchase: result.aggregations?.purchase?.sum ?? null,
                };
            } catch {
                this.orders = null;
            }
        },

        money(value) {
            return value === null || value === undefined ? '–' : `${formatNumber(value, 2)} €`;
        },

        num(value, digits = 1) {
            return formatNumber(value, digits);
        },
    },
};
