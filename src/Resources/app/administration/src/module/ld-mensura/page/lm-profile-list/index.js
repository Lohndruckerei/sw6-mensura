import template from './lm-profile-list.html.twig';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * Produktprofile (KONZEPT 3.6) im Überblick: welche Produkte mit welchen Materialien konfigurierbar sind.
 * Bearbeitet wird im Reiter „Mensura“ des Produkts; hier: öffnen, auf ein anderes Produkt kopieren, löschen,
 * neues Profil für ein Produkt beginnen. Listen-Varianten A/B/C wie DESIGN.md 7.7.
 */
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('listing'), Mixin.getByName('notification'), Mixin.getByName('lm-design')],

    data() {
        return {
            items: null,
            isLoading: true,
            total: 0,
            filter: 'all',
            counts: {},
            tileView: true,
            deleteItem: null,
            copyItem: null,
            copyTargetId: null,
            showCreate: false,
            createProductId: null,
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('ld_mensura_product_profile');
        },

        filters() {
            return [
                { name: 'all', label: this.$tc('ld-mensura.filter.all'), icon: 'regular-products', variant: 'all' },
                { name: 'active', label: this.$tc('ld-mensura.filter.active'), icon: 'regular-checkmark-s', variant: 'ok', filters: () => [Criteria.equals('active', true)] },
                { name: 'special', label: this.$tc('ld-mensura.profile.types.special'), icon: 'regular-star', variant: 'all', filters: () => [Criteria.equals('type', 'special')] },
                { name: 'noMaterial', label: this.$tc('ld-mensura.calculation.list.filterNoMaterial'), icon: 'regular-exclamation-triangle', variant: 'warn', filters: () => [Criteria.equals('materials.id', null)] },
                { name: 'inactive', label: this.$tc('ld-mensura.filter.inactive'), icon: 'regular-ban', variant: 'off', filters: () => [Criteria.equals('active', false)] },
            ];
        },

        criteria() {
            const criteria = new Criteria(this.page, this.limit);
            if (this.term) {
                criteria.addFilter(Criteria.contains('product.name', this.term));
            }
            criteria.addAssociation('product');
            criteria.addAssociation('materials.calculation');
            criteria.addAssociation('materials.material');
            criteria.addSorting(Criteria.sort('product.name', 'ASC'));
            (this.lmDesign === 'a' ? [] : this.filtersFor(this.filter)).forEach((filter) => criteria.addFilter(filter));

            return criteria;
        },

        columns() {
            return [
                { property: 'product', label: 'ld-mensura.profile.list.columnProduct', primary: true, sortable: false, allowResize: true },
                { property: 'type', label: 'ld-mensura.profile.labelType', sortable: false, allowResize: true },
                { property: 'materials', label: 'ld-mensura.areas.material.title', sortable: false, allowResize: true },
                { property: 'layout', label: 'ld-mensura.profile.labelLayout', sortable: false, allowResize: true },
                { property: 'active', label: 'ld-mensura.general.columnStatus', sortable: false, allowResize: true },
            ];
        },

        filterItems() {
            return this.filters.map((item) => ({ name: item.name, label: item.label, count: this.counts[item.name] ?? null }));
        },

        kpiItems() {
            return this.filters.map((item) => ({ ...item, count: this.counts[item.name] ?? '–' }));
        },

        currentFilterLabel() {
            return this.filters.find((item) => item.name === this.filter)?.label ?? '';
        },

        showTiles() {
            return this.lmDesign === 'c' && this.tileView;
        },

        withoutProfileCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('ldMensura.id', null));
            criteria.addFilter(Criteria.equals('parentId', null));

            return criteria;
        },

        canEdit() {
            return this.acl.can('ld_mensura.editor') && this.acl.can('product.editor');
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
                const result = await this.repository.search(this.criteria);
                this.items = result;
                this.total = result.total;
            } finally {
                this.isLoading = false;
            }
            if (this.lmDesign !== 'a') {
                this.loadCounts();
            }
        },

        filtersFor(name) {
            return this.filters.find((item) => item.name === name)?.filters?.() ?? [];
        },

        async loadCounts() {
            const entries = await Promise.all(this.filters.map(async (item) => {
                const criteria = new Criteria(1, 1);
                criteria.setTotalCountMode(1);
                (item.filters?.() ?? []).forEach((filter) => criteria.addFilter(filter));
                try {
                    return [item.name, (await this.repository.searchIds(criteria)).total];
                } catch {
                    return [item.name, null];
                }
            }));
            this.counts = Object.fromEntries(entries);
        },

        setFilter(name) {
            this.filter = this.filter === name && name !== 'all' ? 'all' : name;
            this.page = 1;
            this.getList();
        },

        productName(item) {
            return item.product?.translated?.name || item.product?.name || item.product?.productNumber || '–';
        },

        materialLabels(item) {
            return [...(item.materials ?? [])]
                .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
                .map((row) => row.material
                    ? (row.material.translated?.name || row.material.technicalName)
                    : this.$tc('ld-mensura.profile.list.allOf', { name: row.calculation?.name ?? '–' }));
        },

        statusOf(item) {
            if (!item.active) {
                return { state: 'off', label: this.$tc('lm-design.states.off') };
            }
            if (!item.materials || item.materials.length === 0) {
                return { state: 'warn', label: this.$tc('ld-mensura.calculation.list.filterNoMaterial') };
            }

            return { state: 'ok', label: this.$tc('lm-design.states.ok') };
        },

        layoutLabel(item) {
            return this.$tc(`ld-mensura.profile.layouts.${item.layout || 'default'}`);
        },

        openProduct(item) {
            this.$router.push({ name: 'sw.product.detail.ldMensura', params: { id: item.productId } });
        },

        startCreate() {
            const productId = this.createProductId;
            this.showCreate = false;
            this.createProductId = null;
            if (productId) {
                this.$router.push({ name: 'sw.product.detail.ldMensura', params: { id: productId } });
            }
        },

        async confirmCopy() {
            const source = this.copyItem;
            const targetId = this.copyTargetId;
            this.copyItem = null;
            this.copyTargetId = null;
            if (!source || !targetId) {
                return;
            }
            try {
                await this.repository.clone(source.id, {
                    overwrites: { productId: targetId, active: false, templateProfileId: source.id },
                }, Shopware.Context.api);
                this.createNotificationSuccess({ message: this.$tc('ld-mensura.profile.list.copied') });
                this.$router.push({ name: 'sw.product.detail.ldMensura', params: { id: targetId } });
            } catch {
                this.createNotificationError({ message: this.$tc('ld-mensura.general.duplicateError') });
            }
        },

        async confirmDelete() {
            const item = this.deleteItem;
            this.deleteItem = null;
            try {
                await this.repository.delete(item.id, Shopware.Context.api);
                this.createNotificationSuccess({ message: this.$tc('ld-mensura.general.deleted') });
                this.getList();
            } catch {
                this.createNotificationError({ message: this.$tc('ld-mensura.profile.remove.error') });
            }
        },
    },
};
