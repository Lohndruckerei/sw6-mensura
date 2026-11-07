const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * Gemeinsame Listenseite der Mensura-Bereiche (DESIGN.md 7.7):
 * A = nur Liste, B = Filterchips, C = Kennzahlen als Filter + Kacheln/Liste; Erklärkarte, Kopieren, Löschen, leere Zustände.
 *
 * Spalten-Inhalte über Slots `column-<property>` (werden an sw-entity-listing durchgereicht), Kachel-Inhalt über `tile`.
 * Texte unter `<snippet>`: placeholderSearch, buttonAdd, emptyTitle, emptySubline, confirmDelete, deleteRestricted,
 * info.title, info.text.
 */
export default {
    template: `
<lm-page :title="title" :active="area" :amount="isLoading ? null : total">
    <template #search-bar>
        <sw-search-bar :initial-search-type="entity" :placeholder="$tc(snippet + '.placeholderSearch')" :initial-search="term" @search="onSearch" />
    </template>

    <template #actions>
        <mt-button
            v-tooltip.bottom="{ message: $tc('sw-privileges.tooltip.warning'), disabled: acl.can('ld_mensura.creator'), showOnDisabledElements: true }"
            :disabled="!acl.can('ld_mensura.creator') || undefined"
            variant="primary"
            size="default"
            @click="$router.push({ name: createRoute })"
        >
            {{ $tc(snippet + '.buttonAdd') }}
        </mt-button>
    </template>

    <div class="lm-list">
        <lm-info-card :name="area" :title="$tc(snippet + '.info.title')">
            {{ $tc(snippet + '.info.text') }}
        </lm-info-card>

        <div v-if="lmDesign === 'c'" class="lm-kpis">
            <button
                v-for="kpi in kpiItems"
                :key="kpi.name"
                type="button"
                class="lm-kpi"
                :class="['lm-kpi--' + kpi.variant, { 'is--active': filter === kpi.name && kpi.name !== 'all' }]"
                @click="setFilter(kpi.name)"
            >
                <mt-icon :name="kpi.icon" size="20px" />
                <span><b>{{ kpi.count }}</b><small>{{ kpi.label }}</small></span>
            </button>
        </div>

        <div v-if="lmDesign === 'b'" class="lm-filters" role="group">
            <button
                v-for="item in filterItems"
                :key="item.name"
                type="button"
                class="lm-filter"
                :class="{ 'is--active': filter === item.name }"
                :aria-pressed="filter === item.name ? 'true' : 'false'"
                @click="setFilter(item.name)"
            >
                {{ item.label }}
                <b v-if="item.count !== null">{{ item.count }}</b>
            </button>
        </div>

        <div v-if="lmDesign === 'c'" class="lm-toolbar">
            <span class="lm-muted">{{ currentFilterLabel }}</span>
            <div class="lm-seg">
                <button v-tooltip="$tc('ld-mensura.general.viewTiles')" type="button" :class="{ 'is--active': tileView }" :aria-label="$tc('ld-mensura.general.viewTiles')" :aria-pressed="tileView ? 'true' : 'false'" @click="tileView = true">
                    <mt-icon name="regular-view-grid" size="15px" />
                </button>
                <button v-tooltip="$tc('ld-mensura.general.viewList')" type="button" :class="{ 'is--active': !tileView }" :aria-label="$tc('ld-mensura.general.viewList')" :aria-pressed="!tileView ? 'true' : 'false'" @click="tileView = false">
                    <mt-icon name="regular-list" size="15px" />
                </button>
            </div>
        </div>

        <template v-if="showTiles && total > 0">
            <sw-skeleton v-if="isLoading" />
            <div v-else class="lm-tiles">
                <router-link
                    v-for="item in items"
                    :key="item.id"
                    class="lm-tile"
                    :class="{ 'is--inactive': item.active === false }"
                    :to="{ name: detailRoute, params: { id: item.id } }"
                >
                    <div class="lm-tile__top">
                        <span class="lm-sicon" :class="{ 'is--off': item.active === false }"><mt-icon :name="icon" size="18px" /></span>
                        <span><b>{{ nameOf(item) }}</b><small>{{ item.technicalName }}</small></span>
                        <lm-status v-if="statusOf" class="lm-tile__status" v-bind="statusOf(item)" />
                    </div>
                    <slot name="tile" :item="item" />
                </router-link>
            </div>
            <sw-pagination :page="page" :limit="limit" :total="total" :total-visible="7" @page-change="onPageChange" />
        </template>

        <sw-entity-listing
            v-if="!showTiles"
            :detail-route="detailRoute"
            :identifier="'lm-' + area + '-list'"
            :is-loading="isLoading"
            :columns="columnList"
            :repository="repository"
            :items="items"
            :criteria-limit="limit"
            :sort-by="currentSortBy"
            :sort-direction="sortDirection"
            :disable-data-fetching="true"
            :allow-inline-edit="false"
            :allow-edit="acl.can('ld_mensura.editor') || undefined"
            :allow-delete="acl.can('ld_mensura.deleter') || undefined"
            :show-selections="acl.can('ld_mensura.deleter') || undefined"
            @update-records="updateTotal"
            @page-change="onPageChange"
            @column-sort="onSortColumn"
        >
            <template v-if="lmDesign !== 'a'" #column-technicalName="{ item }">
                <div class="lm-namecell">
                    <span class="lm-sicon" :class="{ 'is--off': item.active === false }"><mt-icon :name="icon" size="16px" /></span>
                    <span>
                        <router-link :to="{ name: detailRoute, params: { id: item.id } }">{{ nameOf(item) }}</router-link>
                        <small>{{ item.technicalName }}</small>
                    </span>
                </div>
            </template>
            <template v-for="name in columnSlots" :key="name" #[name]="scope">
                <slot :name="name" v-bind="scope" />
            </template>
            <template #actions="{ item }">
                <sw-context-menu-item :disabled="!acl.can('ld_mensura.editor') || undefined" @click="$router.push({ name: detailRoute, params: { id: item.id } })">
                    {{ $tc('global.default.edit') }}
                </sw-context-menu-item>
                <sw-context-menu-item v-if="cloneable" :disabled="!acl.can('ld_mensura.creator') || undefined" @click="onDuplicate(item)">
                    {{ $tc('ld-mensura.general.duplicate') }}
                </sw-context-menu-item>
                <sw-context-menu-divider />
                <sw-context-menu-item variant="danger" :disabled="!acl.can('ld_mensura.deleter') || undefined" @click="deleteItem = item">
                    {{ $tc('global.default.delete') }}
                </sw-context-menu-item>
            </template>
        </sw-entity-listing>

        <sw-empty-state v-if="!isLoading && !total && term" :title="$tc('ld-mensura.general.noResultsTitle')" :subline="$tc('ld-mensura.general.noResultsSubline', { term }, 0)" icon="regular-search" :absolute="false" />
        <sw-empty-state v-else-if="!isLoading && !total && filter !== 'all'" :title="$tc('ld-mensura.general.emptyFilterTitle')" :subline="$tc('ld-mensura.general.emptyFilterSubline')" icon="regular-filter" :absolute="false" />
        <sw-empty-state v-else-if="!isLoading && !total" :title="$tc(snippet + '.emptyTitle')" :subline="$tc(snippet + '.emptySubline')" :icon="icon" :absolute="false">
            <template #actions>
                <mt-button variant="primary" :disabled="!acl.can('ld_mensura.creator') || undefined" @click="$router.push({ name: createRoute })">
                    {{ $tc(snippet + '.buttonAdd') }}
                </mt-button>
            </template>
        </sw-empty-state>

        <sw-modal v-if="deleteItem" :title="$tc('global.default.warning')" variant="small" @modal-close="deleteItem = null">
            <p>{{ $tc(snippet + '.confirmDelete', { name: nameOf(deleteItem) }, 0) }}</p>
            <template #modal-footer>
                <mt-button size="small" variant="secondary" @click="deleteItem = null">{{ $tc('global.default.cancel') }}</mt-button>
                <mt-button size="small" variant="critical" @click="confirmDelete">{{ $tc('global.default.delete') }}</mt-button>
            </template>
        </sw-modal>
    </div>
</lm-page>`,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('listing'), Mixin.getByName('notification'), Mixin.getByName('lm-design')],

    props: {
        entity: { type: String, required: true },
        area: { type: String, required: true },
        title: { type: String, required: true },
        icon: { type: String, required: true },
        snippet: { type: String, required: true },
        detailRoute: { type: String, required: true },
        createRoute: { type: String, required: true },
        /** (design) => Spaltenliste für sw-entity-listing; erste Spalte technicalName */
        columns: { type: Function, required: true },
        /** [{ name, label, icon, variant, filters: () => [Criteria…] }] – erster Eintrag „all“ */
        filters: { type: Array, required: true },
        associations: { type: Array, required: false, default: () => [] },
        defaultSort: { type: String, required: false, default: 'technicalName' },
        nameOf: { type: Function, required: false, default: (item) => item.translated?.name || item.name || item.technicalName },
        statusOf: { type: Function, required: false, default: null },
        cloneable: { type: Boolean, required: false, default: true },
        /** (item) => overwrites beim Kopieren */
        cloneOverwrites: { type: Function, required: false, default: null },
    },

    data() {
        return {
            items: null,
            isLoading: true,
            sortBy: this.defaultSort,
            sortDirection: 'ASC',
            total: 0,
            filter: 'all',
            counts: {},
            tileView: true,
            deleteItem: null,
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create(this.entity);
        },

        columnList() {
            return this.columns(this.lmDesign);
        },

        columnSlots() {
            return Object.keys(this.$slots).filter((name) => name.startsWith('column-'));
        },

        criteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            this.associations.forEach((association) => criteria.addAssociation(association));
            this.filtersFor(this.lmDesign === 'a' ? 'all' : this.filter).forEach((filter) => criteria.addFilter(filter));

            return criteria;
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
                criteria.setTerm(this.term);
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

        updateTotal({ total }) {
            this.total = total;
        },

        async onDuplicate(item) {
            try {
                const suffix = Date.now().toString(36).slice(-4);
                const overwrites = { technicalName: `${item.technicalName}-kopie-${suffix}`.slice(0, 64), ...(this.cloneOverwrites ? this.cloneOverwrites(item) : {}) };
                const result = await this.repository.clone(item.id, { overwrites }, Shopware.Context.api);
                this.createNotificationSuccess({ message: this.$tc('ld-mensura.general.duplicated') });
                this.$router.push({ name: this.detailRoute, params: { id: result.id } });
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
                this.createNotificationError({ message: this.$tc(`${this.snippet}.deleteRestricted`) });
            }
        },
    },
};
