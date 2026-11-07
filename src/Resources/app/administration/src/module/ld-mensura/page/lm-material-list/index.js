import template from './lm-material-list.html.twig';
import { marginInfo, ruleOf, formatNumber } from '../../service/margin';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const FILTERS = ['all', 'active', 'noPrice', 'inactive'];

/**
 * Materialliste (DESIGN.md 7.7): A = nur Liste, B = Filterchips, C = Kennzahlen als Filter + Kacheln/Liste.
 */
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
        Mixin.getByName('lm-design'),
    ],

    data() {
        return {
            materials: null,
            isLoading: true,
            sortBy: 'position',
            sortDirection: 'ASC',
            total: 0,
            filter: 'all',
            counts: { all: null, active: null, noPrice: null, inactive: null },
            tileView: true,
            deleteItem: null,
        };
    },

    metaInfo() {
        return { title: this.$createTitle() };
    },

    computed: {
        materialRepository() {
            return this.repositoryFactory.create('ld_mensura_material');
        },

        showPurchase() {
            return this.acl.can('ld_mensura.purchase');
        },

        columns() {
            const columns = [
                { property: 'technicalName', label: 'ld-mensura.material.list.columnName', routerLink: 'ld.mensura.materialDetail', primary: true, allowResize: true },
            ];
            if (this.lmDesign === 'a') {
                columns.push({ property: 'name', label: 'ld-mensura.material.list.columnLabel', allowResize: true });
            }
            columns.push(
                { property: 'priceSale', label: 'ld-mensura.material.list.columnPrice', align: 'right', allowResize: true },
            );
            if (this.showPurchase) {
                columns.push({ property: 'margin', label: 'ld-mensura.material.list.columnMargin', sortable: false, allowResize: true });
            }
            columns.push(
                { property: 'calculations', label: 'ld-mensura.material.list.columnCalculations', sortable: false, allowResize: true },
                { property: 'active', label: 'ld-mensura.general.columnStatus', allowResize: true },
            );

            return columns;
        },

        criteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            criteria.addAssociation('calculations');
            this.filtersFor(this.lmDesign === 'a' ? 'all' : this.filter).forEach((filter) => criteria.addFilter(filter));

            return criteria;
        },

        filterItems() {
            return FILTERS.map((name) => ({ name, label: this.$tc(`ld-mensura.filter.${name}`), count: this.counts[name] }));
        },

        kpiItems() {
            const icons = { all: 'regular-layer-group', active: 'regular-checkmark-s', noPrice: 'regular-exclamation-triangle', inactive: 'regular-ban' };
            const variants = { all: 'all', active: 'ok', noPrice: 'warn', inactive: 'off' };

            return FILTERS.map((name) => ({
                name,
                icon: icons[name],
                variant: variants[name],
                label: this.$tc(`ld-mensura.filter.${name}`),
                count: this.counts[name] ?? '–',
            }));
        },

        showTiles() {
            return this.lmDesign === 'c' && this.tileView;
        },
    },

    watch: {
        lmDesign() {
            this.filter = 'all';
            this.getList();
        },
    },

    methods: {
        async getList() {
            this.isLoading = true;
            try {
                const result = await this.materialRepository.search(this.criteria);
                this.materials = result;
                this.total = result.total;
            } finally {
                this.isLoading = false;
            }
            if (this.lmDesign !== 'a') {
                this.loadCounts();
            }
        },

        filtersFor(name) {
            switch (name) {
                case 'active':
                    return [Criteria.equals('active', true)];
                case 'inactive':
                    return [Criteria.equals('active', false)];
                case 'noPrice':
                    return [Criteria.equals('priceSale', null)];
                default:
                    return [];
            }
        },

        async loadCounts() {
            const entries = await Promise.all(FILTERS.map(async (name) => {
                const criteria = new Criteria(1, 1);
                criteria.setTerm(this.term);
                criteria.setTotalCountMode(1);
                this.filtersFor(name).forEach((filter) => criteria.addFilter(filter));
                try {
                    return [name, (await this.materialRepository.searchIds(criteria)).total];
                } catch {
                    return [name, null];
                }
            }));
            this.counts = Object.fromEntries(entries);
        },

        setFilter(name) {
            this.filter = this.filter === name && name !== 'all' ? 'all' : name;
            this.page = 1;
            this.getList();
        },

        updateTotal({ total }) {
            this.total = total;
        },

        status(item) {
            if (!item.active) {
                return { state: 'off', label: this.$tc('lm-design.states.off') };
            }
            if (item.priceSale === null || item.priceSale === undefined) {
                return { state: 'warn', label: this.$tc('ld-mensura.material.state.noPrice') };
            }

            return { state: 'ok', label: this.$tc('lm-design.states.ok') };
        },

        marginOf(item) {
            return marginInfo(item.pricePurchase, item.priceSale, ruleOf(item.calculations?.first?.() ?? null));
        },

        priceLabel(item) {
            return item.priceSale === null || item.priceSale === undefined ? '–' : `${formatNumber(item.priceSale)} €/m²`;
        },

        fmt(value, digits) {
            return formatNumber(value, digits);
        },

        /** Kopieren (KONZEPT 3a Punkt 6): gleiche Daten, neuer technischer Name, inaktiv bis zur Prüfung. */
        async onDuplicate(item) {
            try {
                const suffix = Date.now().toString(36).slice(-4);
                const result = await this.materialRepository.clone(item.id, {
                    overwrites: { technicalName: `${item.technicalName}-kopie-${suffix}`.slice(0, 64), active: false },
                }, Shopware.Context.api);
                this.createNotificationSuccess({ message: this.$tc('ld-mensura.general.duplicated') });
                this.$router.push({ name: 'ld.mensura.materialDetail', params: { id: result.id } });
            } catch {
                this.createNotificationError({ message: this.$tc('ld-mensura.general.duplicateError') });
            }
        },

        onDelete(item) {
            this.deleteItem = item;
        },

        async confirmDelete() {
            const item = this.deleteItem;
            this.deleteItem = null;
            try {
                await this.materialRepository.delete(item.id, Shopware.Context.api);
                this.createNotificationSuccess({ message: this.$tc('ld-mensura.general.deleted') });
                this.getList();
            } catch {
                // RestrictDelete: Material wird noch in Produktprofilen verwendet
                this.createNotificationError({ message: this.$tc('ld-mensura.material.list.deleteRestricted') });
            }
        },
    },
};
