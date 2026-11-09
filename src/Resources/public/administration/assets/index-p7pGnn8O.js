import{e as l,s as d}from"./save-error-3onBsN12.js";const{Mixin:n}=Shopware,{Criteria:o}=Shopware.Data,c={template:`
<lm-detail-page
    :title="title"
    icon="regular-list"
    v-model:save-success="isSaveSuccessful"
    :ready="set !== null"
    :is-loading="isLoading"
    :can-edit="canEdit"
    @save="onSave"
    @cancel="onCancel"
>
    <template #header-meta>
        <span>{{ set.technicalName }}</span>
    </template>
    <template #header-chips>
        <lm-chip v-for="row in sortedRows" :key="row.id" color="c3" icon="regular-sliders-v" :label="groupName(row)" />
    </template>
    <template #header-stats>
        <span class="lm-header__stat"><b>{{ sortedRows.length }}</b>{{ $tc('ld-mensura.optionSet.detail.statGroups') }}</span>
        <span class="lm-header__stat" :class="{ 'is--warn': usageCount === 0 }"><b>{{ usageCount }}</b>{{ $tc('ld-mensura.optionSet.detail.statUsage') }}</span>
    </template>

    <lm-info-card name="option-set" :title="$tc('ld-mensura.optionSet.list.info.title')">
        {{ $tc('ld-mensura.optionSet.list.info.text') }}
    </lm-info-card>

    <mt-card position-identifier="lm-set-base" :title="$tc('ld-mensura.optionSet.detail.cardBase')">
        <sw-container columns="repeat(auto-fit, minmax(250px, 1fr))" gap="0 30px">
            <mt-text-field v-model="set.name" :label="$tc('ld-mensura.optionSet.detail.labelName')" :help-text="$tc('ld-mensura.optionSet.detail.helpName')" :disabled="!canEdit || undefined" required />
            <mt-text-field v-model="set.technicalName" :label="$tc('ld-mensura.general.labelTechnicalName')" :help-text="$tc('ld-mensura.optionSet.detail.helpTechnicalName')" :disabled="!canEdit || undefined" required />
        </sw-container>
        <mt-textarea v-model="set.description" :label="$tc('ld-mensura.optionSet.detail.labelDescription')" :help-text="$tc('ld-mensura.optionSet.detail.helpDescription')" :disabled="!canEdit || undefined" />
    </mt-card>

    <mt-card position-identifier="lm-set-groups" :title="$tc('ld-mensura.optionSet.detail.cardGroups')">
        <p class="lm-muted lm-card-intro">{{ $tc('ld-mensura.optionSet.detail.introGroups') }}</p>
        <div v-for="(row, index) in sortedRows" :key="row.id" class="lm-set-row">
            <span class="lm-set-row__pos">{{ index + 1 }}</span>
            <span class="lm-set-row__name">
                <b>{{ groupName(row) }}</b>
                <small class="lm-muted">{{ row.optionGroup?.technicalName }}</small>
            </span>
            <sw-single-select
                :value="requiredValue(row)"
                :options="requiredOptions"
                :help-text="index === 0 ? $tc('ld-mensura.rowHelp.setRequired') : undefined"
                :disabled="!canEdit || undefined"
                @update:value="setRequired(row, $event)"
            />
            <mt-button size="small" variant="secondary" square :aria-label="$tc('ld-mensura.optionSet.detail.moveUp')" :disabled="!canEdit || index === 0 || undefined" @click="move(index, -1)">
                <mt-icon name="regular-chevron-up-s" size="14px" />
            </mt-button>
            <mt-button size="small" variant="secondary" square :aria-label="$tc('ld-mensura.optionSet.detail.moveDown')" :disabled="!canEdit || index === sortedRows.length - 1 || undefined" @click="move(index, 1)">
                <mt-icon name="regular-chevron-down-s" size="14px" />
            </mt-button>
            <mt-button size="small" variant="secondary" square :aria-label="$tc('ld-mensura.components.remove')" :disabled="!canEdit || undefined" @click="removeRow(row)">
                <mt-icon name="regular-trash" size="14px" />
            </mt-button>
        </div>
        <p v-if="sortedRows.length === 0" class="lm-muted">{{ $tc('ld-mensura.optionSet.detail.emptyGroups') }}</p>
        <sw-entity-single-select
            :value="null"
            entity="ld_mensura_option_group"
            label-property="name"
            :criteria="addCriteria"
            :label="$tc('ld-mensura.optionSet.detail.labelAddGroup')"
            :help-text="$tc('ld-mensura.optionSet.detail.helpAddGroup')"
            :disabled="!canEdit || undefined"
            @update:value="addGroup"
        />
    </mt-card>

    <mt-card v-if="setId" position-identifier="lm-set-usage" :title="$tc('ld-mensura.optionSet.detail.cardUsage')">
        <p class="lm-muted lm-card-intro">{{ $tc('ld-mensura.optionSet.detail.introUsage') }}</p>
        <ul class="lm-usage">
            <li v-for="item in set.calculations" :key="item.id">
                <lm-chip color="c1" icon="regular-euro" :label="$tc('ld-mensura.optionSet.detail.usageStart')" /> {{ item.name }}
            </li>
            <li v-for="item in set.materials" :key="item.id">
                <lm-chip color="c2" icon="regular-layer-group" :label="$tc('ld-mensura.optionSet.detail.usageMaterial')" />
                <router-link :to="{ name: 'ld.mensura.materialDetail', params: { id: item.id } }">{{ item.translated?.name || item.technicalName }}</router-link>
            </li>
            <li v-for="item in set.optionValues" :key="item.id">
                <lm-chip color="c3" icon="regular-sliders-v" :label="$tc('ld-mensura.optionSet.detail.usageValue')" />
                <router-link :to="{ name: 'ld.mensura.optionDetail', params: { id: item.optionGroupId } }">{{ item.translated?.name || item.technicalName }}</router-link>
            </li>
        </ul>
        <p v-if="usageCount === 0" class="lm-muted">{{ $tc('ld-mensura.optionSet.usage.none') }}</p>
    </mt-card>
</lm-detail-page>`,inject:["repositoryFactory","acl"],mixins:[n.getByName("notification"),n.getByName("discard-detail-page-changes")("set")],shortcuts:{"SYSTEMKEY+S":"onSave",ESCAPE:"onCancel"},props:{setId:{type:String,required:!1,default:null}},data(){return{set:null,isLoading:!1,isSaveSuccessful:!1}},metaInfo(){return{title:this.$createTitle(this.title)}},computed:{repository(){return this.repositoryFactory.create("ld_mensura_option_set")},rowRepository(){return this.repositoryFactory.create("ld_mensura_option_set_group")},title(){var e;return this.setId&&this.set===null?this.$tc("ld-mensura.areas.optionSet.title"):((e=this.set)==null?void 0:e.name)||this.$tc("ld-mensura.optionSet.detail.newTitle")},canEdit(){return this.acl.can(this.setId?"ld_mensura.editor":"ld_mensura.creator")},sortedRows(){var e;return[...((e=this.set)==null?void 0:e.groups)??[]].sort((t,i)=>(t.position??0)-(i.position??0))},usageCount(){var e,t,i,a,s,r;return(((t=(e=this.set)==null?void 0:e.calculations)==null?void 0:t.length)??0)+(((a=(i=this.set)==null?void 0:i.materials)==null?void 0:a.length)??0)+(((r=(s=this.set)==null?void 0:s.optionValues)==null?void 0:r.length)??0)},addCriteria(){const e=new o(1,25),t=this.sortedRows.map(i=>i.optionGroupId);return t.length&&e.addFilter(o.not("AND",[o.equalsAny("id",t)])),e.addFilter(o.equals("type","standard")),e},requiredOptions(){return[{value:"inherit",label:this.$tc("ld-mensura.optionSet.detail.requiredInherit")},{value:"yes",label:this.$tc("ld-mensura.optionSet.detail.requiredYes")},{value:"no",label:this.$tc("ld-mensura.optionSet.detail.requiredNo")}]}},watch:{setId(){this.createdComponent()}},created(){this.createdComponent()},methods:{createdComponent(){if(this.setId){this.load();return}this.set=this.repository.create()},async load(){this.isLoading=!0;try{const e=new o(1,1);e.addAssociation("groups.optionGroup"),e.addAssociation("calculations"),e.addAssociation("materials"),e.addAssociation("optionValues"),this.set=await this.repository.get(this.setId,Shopware.Context.api,e)}catch{this.createNotificationError({message:this.$tc("global.notification.notificationLoadingDataErrorMessage")})}finally{this.isLoading=!1}},groupName(e){var t,i,a,s;return((i=(t=e.optionGroup)==null?void 0:t.translated)==null?void 0:i.name)||((a=e.optionGroup)==null?void 0:a.name)||((s=e.optionGroup)==null?void 0:s.technicalName)||""},requiredValue(e){return e.required===null||e.required===void 0?"inherit":e.required?"yes":"no"},setRequired(e,t){e.required=t==="inherit"?null:t==="yes"},renumber(e){e.forEach((t,i)=>{t.position=(i+1)*10})},move(e,t){const i=[...this.sortedRows],[a]=i.splice(e,1);i.splice(e+t,0,a),this.renumber(i)},async addGroup(e){if(!e)return;const t=this.rowRepository.create();t.optionSetId=this.set.id,t.optionGroupId=e,t.position=(this.sortedRows.length+1)*10,t.optionGroup=await this.repositoryFactory.create("ld_mensura_option_group").get(e),this.set.groups.add(t)},removeRow(e){this.set.groups.remove(e.id),this.renumber(this.sortedRows)},async onSave(){if(this.canEdit){this.isLoading=!0,l(this.set);try{if(await this.repository.save(this.set,Shopware.Context.api),this.isSaveSuccessful=!0,this.sortedRows.length===0&&this.createNotificationWarning({message:this.$tc("ld-mensura.optionSet.warnings.noGroups")}),!this.setId){this.$router.push({name:"ld.mensura.optionSetDetail",params:{id:this.set.id}});return}await this.load()}catch(e){this.createNotificationError({message:d(this,e)})}finally{this.isLoading=!1}}},onCancel(){this.$router.push({name:"ld.mensura.optionSet"})}}};export{c as default};
