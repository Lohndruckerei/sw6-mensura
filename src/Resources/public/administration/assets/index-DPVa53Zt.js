const{Mixin:l}=Shopware,{Criteria:s}=Shopware.Data,r={template:`
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
</lm-page>`,inject:["repositoryFactory","acl"],mixins:[l.getByName("listing"),l.getByName("notification"),l.getByName("lm-design")],props:{entity:{type:String,required:!0},area:{type:String,required:!0},title:{type:String,required:!0},icon:{type:String,required:!0},snippet:{type:String,required:!0},detailRoute:{type:String,required:!0},createRoute:{type:String,required:!0},columns:{type:Function,required:!0},filters:{type:Array,required:!0},associations:{type:Array,required:!1,default:()=>[]},defaultSort:{type:String,required:!1,default:"technicalName"},nameOf:{type:Function,required:!1,default:e=>{var t;return((t=e.translated)==null?void 0:t.name)||e.name||e.technicalName}},statusOf:{type:Function,required:!1,default:null},cloneable:{type:Boolean,required:!1,default:!0},cloneOverwrites:{type:Function,required:!1,default:null}},data(){return{items:null,isLoading:!0,sortBy:this.defaultSort,sortDirection:"ASC",total:0,filter:"all",counts:{},tileView:!0,deleteItem:null}},computed:{repository(){return this.repositoryFactory.create(this.entity)},columnList(){return this.columns(this.lmDesign)},columnSlots(){return Object.keys(this.$slots).filter(e=>e.startsWith("column-"))},criteria(){const e=new s(this.page,this.limit);return e.setTerm(this.term),e.addSorting(s.sort(this.sortBy,this.sortDirection,this.naturalSorting)),this.associations.forEach(t=>e.addAssociation(t)),this.filtersFor(this.lmDesign==="a"?"all":this.filter).forEach(t=>e.addFilter(t)),e},filterItems(){return this.filters.map(e=>({name:e.name,label:e.label,count:this.counts[e.name]??null}))},kpiItems(){return this.filters.map(e=>({...e,count:this.counts[e.name]??"–"}))},currentFilterLabel(){var e;return((e=this.filters.find(t=>t.name===this.filter))==null?void 0:e.label)??""},showTiles(){return this.lmDesign==="c"&&this.tileView}},watch:{lmDesign(){this.filter="all",this.getList()}},methods:{async getList(){this.isLoading=!0;try{const e=await this.repository.search(this.criteria);this.items=e,this.total=e.total}finally{this.isLoading=!1}this.lmDesign!=="a"&&this.loadCounts()},filtersFor(e){var t,i;return((i=(t=this.filters.find(a=>a.name===e))==null?void 0:t.filters)==null?void 0:i.call(t))??[]},async loadCounts(){const e=await Promise.all(this.filters.map(async t=>{var a;const i=new s(1,1);i.setTerm(this.term),i.setTotalCountMode(1),(((a=t.filters)==null?void 0:a.call(t))??[]).forEach(n=>i.addFilter(n));try{return[t.name,(await this.repository.searchIds(i)).total]}catch{return[t.name,null]}}));this.counts=Object.fromEntries(e)},setFilter(e){this.filter=this.filter===e&&e!=="all"?"all":e,this.page=1,this.getList()},updateTotal({total:e}){this.total=e},async onDuplicate(e){try{const t=Date.now().toString(36).slice(-4),i={technicalName:`${e.technicalName}-kopie-${t}`.slice(0,64),...this.cloneOverwrites?this.cloneOverwrites(e):{}},a=await this.repository.clone(e.id,{overwrites:i},Shopware.Context.api);this.createNotificationSuccess({message:this.$tc("ld-mensura.general.duplicated")}),this.$router.push({name:this.detailRoute,params:{id:a.id}})}catch{this.createNotificationError({message:this.$tc("ld-mensura.general.duplicateError")})}},async confirmDelete(){const e=this.deleteItem;this.deleteItem=null;try{await this.repository.delete(e.id,Shopware.Context.api),this.createNotificationSuccess({message:this.$tc("ld-mensura.general.deleted")}),this.getList()}catch{this.createNotificationError({message:this.$tc(`${this.snippet}.deleteRestricted`)})}}}};export{r as default};
