const{Mixin:l}=Shopware,{Criteria:s}=Shopware.Data,o=["standard","delivery","format"],n=["position","supplier"],r=["select","tiles","radio","checkbox","number","multi"],d={template:`
<lm-detail-page
    :title="title"
    icon="regular-sliders-v"
    :status="null"
    :tabs="tabs"
    :groups="tabGroups"
    v-model:active="activeTab"
    v-model:save-success="isSaveSuccessful"
    :ready="group !== null"
    :is-loading="isLoading"
    :can-edit="canEdit"
    @save="onSave"
    @cancel="$router.push({ name: 'ld.mensura.option' })"
>
    <template #header-meta>
        <span>{{ group.technicalName }}</span>
        <span>{{ $tc('ld-mensura.option.types.' + group.type) }}</span>
    </template>
    <template #header-chips>
        <lm-chip v-for="row in group.setGroups" :key="row.id" color="c4" icon="regular-list" :label="row.optionSet?.name || ''" />
    </template>
    <template #header-stats>
        <span class="lm-header__stat"><b>{{ group.values ? group.values.length : 0 }}</b>{{ $tc('ld-mensura.option.detail.tabValues') }}</span>
        <span class="lm-header__stat"><b>{{ group.setGroups ? group.setGroups.length : 0 }}</b>{{ $tc('ld-mensura.areas.optionSet.title') }}</span>
    </template>

    <template #default="{ active }">
        <template v-if="active === 'base'">
            <mt-card position-identifier="lm-option-base" :title="$tc('ld-mensura.option.detail.cardBase')">
                <sw-container columns="repeat(auto-fit, minmax(250px, 1fr))" gap="0 30px">
                    <mt-text-field v-model="group.name" :label="$tc('ld-mensura.option.detail.labelName')" :help-text="$tc('ld-mensura.option.detail.helpName')" :disabled="!canEdit || undefined" required />
                    <mt-text-field v-model="group.technicalName" :label="$tc('ld-mensura.general.labelTechnicalName')" :help-text="$tc('ld-mensura.option.detail.helpTechnicalName')" :disabled="!canEdit || undefined" required />
                    <sw-single-select v-model:value="group.type" :options="typeOptions" :label="$tc('ld-mensura.option.detail.labelType')" :help-text="$tc('ld-mensura.option.detail.helpType')" :disabled="!canEdit || undefined" />
                    <sw-single-select v-model:value="group.displayType" :options="displayOptions" :label="$tc('ld-mensura.option.detail.labelDisplayType')" :help-text="$tc('ld-mensura.option.detail.helpDisplayType')" :disabled="!canEdit || undefined" />
                    <sw-single-select v-model:value="group.scope" :options="scopeOptions" :label="$tc('ld-mensura.option.detail.labelScope')" :help-text="$tc('ld-mensura.option.detail.helpScope')" :disabled="!canEdit || undefined" />
                    <mt-number-field v-model="group.position" number-type="int" :label="$tc('ld-mensura.general.labelPosition')" :help-text="$tc('ld-mensura.option.detail.helpPosition')" :disabled="!canEdit || undefined" />
                </sw-container>
                <mt-textarea v-model="group.helpText" :label="$tc('ld-mensura.option.detail.labelHelpText')" :help-text="$tc('ld-mensura.option.detail.helpHelpText')" :disabled="!canEdit || undefined" />
                <mt-textarea v-model="group.description" :label="$tc('ld-mensura.option.detail.labelDescription')" :help-text="$tc('ld-mensura.option.detail.helpDescription')" :disabled="!canEdit || undefined" />
                <mt-switch v-model="group.required" :label="$tc('ld-mensura.option.detail.labelRequired')" :help-text="$tc('ld-mensura.option.detail.helpRequired')" :disabled="!canEdit || undefined" bordered />
                <mt-switch v-model="group.showPriceHint" :label="$tc('ld-mensura.option.detail.labelShowPriceHint')" :help-text="$tc('ld-mensura.option.detail.helpShowPriceHint')" :disabled="!canEdit || undefined" bordered />
            </mt-card>
        </template>

        <template v-if="active === 'values'">
            <mt-card position-identifier="lm-option-values" :title="$tc('ld-mensura.option.detail.cardValues')">
                <p class="lm-muted lm-card-intro">{{ $tc('ld-mensura.option.detail.introValues') }}</p>
                <sw-data-grid
                    v-if="sortedValues.length"
                    :data-source="sortedValues"
                    :columns="valueColumns"
                    :show-selection="false"
                    :show-actions="true"
                    :allow-inline-edit="false"
                    plain-appearance
                >
                    <template #column-name="{ item }">
                        <a href="#" @click.prevent="editValue = item">{{ item.translated?.name || item.name || item.technicalName }}</a>
                    </template>
                    <template #column-detail="{ item }">
                        <span class="lm-muted">{{ valueDetail(item) }}</span>
                    </template>
                    <template #column-optionSet="{ item }">
                        <span>{{ setName(item.optionSetId) }}</span>
                    </template>
                    <template #actions="{ item }">
                        <sw-context-menu-item @click="editValue = item">{{ $tc('global.default.edit') }}</sw-context-menu-item>
                        <sw-context-menu-item variant="danger" :disabled="!canEdit || undefined" @click="removeValue(item)">{{ $tc('global.default.delete') }}</sw-context-menu-item>
                    </template>
                </sw-data-grid>
                <sw-empty-state v-else :title="$tc('ld-mensura.option.detail.emptyValuesTitle')" :subline="$tc('ld-mensura.option.detail.emptyValuesSubline')" icon="regular-sliders-v" :absolute="false" />
                <mt-button size="small" variant="secondary" :disabled="!canEdit || undefined" @click="addValue">
                    <mt-icon name="regular-plus-circle" size="14px" />
                    {{ $tc('ld-mensura.option.detail.buttonAddValue') }}
                </mt-button>
            </mt-card>
        </template>

        <lm-value-modal v-if="editValue" :value="editValue" :group-type="group.type" :disabled="!canEdit" @close="editValue = null" />
    </template>
</lm-detail-page>`,inject:["repositoryFactory","acl"],mixins:[l.getByName("notification"),l.getByName("discard-detail-page-changes")("group")],shortcuts:{"SYSTEMKEY+S":"onSave",ESCAPE:"onCancel"},props:{groupId:{type:String,required:!1,default:null}},data(){return{group:null,isLoading:!1,isSaveSuccessful:!1,activeTab:"base",editValue:null,sets:[]}},metaInfo(){return{title:this.$createTitle(this.title)}},computed:{repository(){return this.repositoryFactory.create("ld_mensura_option_group")},valueRepository(){return this.repositoryFactory.create("ld_mensura_option_value")},title(){var e,a,t;return this.groupId&&this.group===null?this.$tc("ld-mensura.areas.option.title"):((a=(e=this.group)==null?void 0:e.translated)==null?void 0:a.name)||((t=this.group)==null?void 0:t.name)||this.$tc("ld-mensura.option.detail.newTitle")},canEdit(){return this.acl.can(this.groupId?"ld_mensura.editor":"ld_mensura.creator")},tabs(){var e,a,t,i;return[{name:"base",label:this.$tc("ld-mensura.option.detail.tabBase"),icon:"regular-file-text",group:"group"},{name:"values",label:this.$tc("ld-mensura.option.detail.tabValues"),icon:"regular-list",group:"group",count:((a=(e=this.group)==null?void 0:e.values)==null?void 0:a.length)??0,warn:(((i=(t=this.group)==null?void 0:t.values)==null?void 0:i.length)??0)===0,warnText:this.$tc("ld-mensura.option.detail.emptyValuesTitle")}]},tabGroups(){return{group:this.$tc("ld-mensura.option.detail.cardBase")}},typeOptions(){return o.map(e=>({value:e,label:this.$tc(`ld-mensura.option.types.${e}`)}))},scopeOptions(){return n.map(e=>({value:e,label:this.$tc(`ld-mensura.option.scopes.${e}`)}))},displayOptions(){return r.map(e=>({value:e,label:this.$tc(`ld-mensura.option.displayTypes.${e}`)}))},sortedValues(){var e;return[...((e=this.group)==null?void 0:e.values)??[]].sort((a,t)=>(a.position??0)-(t.position??0))},valueColumns(){return[{property:"name",label:this.$tc("ld-mensura.option.value.labelName"),primary:!0},{property:"technicalName",label:this.$tc("ld-mensura.general.labelTechnicalName")},{property:"detail",label:this.$tc("ld-mensura.option.detail.columnDetail")},{property:"optionSet",label:this.$tc("ld-mensura.material.detail.labelOptionSet")},{property:"position",label:this.$tc("ld-mensura.general.labelPosition"),align:"right"}]}},watch:{groupId(){this.createdComponent()}},created(){this.createdComponent(),this.loadSets()},methods:{createdComponent(){if(this.activeTab="base",this.groupId){this.load();return}this.group=this.repository.create(),Object.assign(this.group,{type:"standard",scope:"position",displayType:"select",required:!0,showPriceHint:!0,position:0})},async load(){this.isLoading=!0;try{const e=new s(1,1);e.addAssociation("values"),e.addAssociation("setGroups.optionSet"),this.group=await this.repository.get(this.groupId,Shopware.Context.api,e)}catch{this.createNotificationError({message:this.$tc("global.notification.notificationLoadingDataErrorMessage")})}finally{this.isLoading=!1}},async loadSets(){const e=await this.repositoryFactory.create("ld_mensura_option_set").search(new s(1,500));this.sets=[...e]},setName(e){var a;return((a=this.sets.find(t=>t.id===e))==null?void 0:a.name)??"–"},valueDetail(e){return this.group.type==="delivery"?e.deliveryDays!==null&&e.deliveryDays!==void 0?this.$tc("ld-mensura.option.detail.days",{days:e.deliveryDays},e.deliveryDays):"–":this.group.type==="format"?e.width&&e.height?`${e.width} × ${e.height} mm`:"–":e.code||""},addValue(){const e=this.valueRepository.create(),a=Math.max(0,...this.sortedValues.map(t=>t.position??0));Object.assign(e,{optionGroupId:this.group.id,position:a+1,widthAdd:0,heightAdd:0,widthFactor:1,heightFactor:1}),this.group.values.add(e),this.editValue=e},removeValue(e){this.group.values.remove(e.id)},warnings(){var a;const e=[];return(((a=this.group.values)==null?void 0:a.length)??0)===0&&e.push(this.$tc("ld-mensura.option.warnings.noValues")),this.group.type==="delivery"&&[...this.group.values??[]].some(t=>t.deliveryDays===null||t.deliveryDays===void 0)&&e.push(this.$tc("ld-mensura.option.warnings.noDays")),this.group.type==="format"&&[...this.group.values??[]].some(t=>!t.width||!t.height)&&e.push(this.$tc("ld-mensura.option.warnings.noFormat")),e},async onSave(){var e,a;if(this.canEdit){this.isLoading=!0;try{if(await this.repository.save(this.group,Shopware.Context.api),this.isSaveSuccessful=!0,this.warnings().forEach(t=>this.createNotificationWarning({message:t})),!this.groupId){this.$router.push({name:"ld.mensura.optionDetail",params:{id:this.group.id}});return}await this.load()}catch(t){const i=JSON.stringify(((a=(e=t==null?void 0:t.response)==null?void 0:e.data)==null?void 0:a.errors)??[]);this.createNotificationError({message:i.includes("technical_name")?this.$tc("ld-mensura.general.errorDuplicateTechnicalName"):this.$tc("global.notification.notificationSaveErrorMessageRequiredFieldsInvalid")})}finally{this.isLoading=!1}}},onCancel(){this.$router.push({name:"ld.mensura.option"})}}};export{d as default};
