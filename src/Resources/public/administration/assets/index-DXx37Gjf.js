import{_ as u}from"./ld-mensura-BMXvwQzn.js";import{a as c,f as d}from"./margin-fIe6izj8.js";const m="/_action/ld-vectura/logistics/suggestions",h={per_sqm:1,percent:2};function p(t,e){return(Array.isArray(t)?t:[]).filter(i=>{const s=(i==null?void 0:i.cause)??{},a=Array.isArray(s.materialKeys)?s.materialKeys:[],l=s.deliveryKey??s.optionKey??null;return e.valueKey?l===e.valueKey&&(!s.calculationKey||!e.calculationKey||s.calculationKey===e.calculationKey):l?!1:e.materialKey?a.includes(e.materialKey):e.calculationKey?s.calculationKey===e.calculationKey&&a.length===0:!1})}function f(t=new Date){const e=new Date(Date.UTC(t.getFullYear(),t.getMonth(),t.getDate())),i=e.getUTCDay();return e.setUTCDate(e.getUTCDate()+((8-i)%7||7)),e.toISOString().slice(0,10)}function g(t,e){const i=(t==null?void 0:t.proposal)??{},s=h[i.form];return s===void 0||!e||e.form!==i.form?!1:Math.abs(Number(i.value)-Number(e.value))>s+1e-9}function v(t,e,i){const s=t.proposal??{},a=l=>l!=null;return{costType:t.costType??"other",form:s.form??"per_sqm",value:Number(s.value??0),validFrom:i,useRamp:a(s.rampFrom)&&a(s.rampTo),rampFrom:s.rampFrom??1e3,rampTo:s.rampTo??1400,useThreshold:a(s.thresholdBasis)&&a(s.thresholdFrom)&&a(s.thresholdTo),thresholdBasis:s.thresholdBasis??"total_area",thresholdFrom:s.thresholdFrom??null,thresholdTo:s.thresholdTo??null,useMinimum:a(s.minimumAmount),minimumAmount:s.minimumAmount??null,minimumScope:s.minimumScope??"position",source:"vectura",sourceReference:{suggestionId:t.id??null,period:(e==null?void 0:e.period)??null,generatedAt:(e==null?void 0:e.generatedAt)??null,costs:t.costs??null,revenue:t.revenue??null,coverage:t.coverage??null,targetCoverage:t.targetCoverage??null,cases:t.cases??null,warnings:t.warnings??[]}}}async function w(t,e,i,s){var a;if(!i)return{status:"missing",data:null};try{const{data:l}=await t.get(m,{headers:e,params:s});return{status:"ok",data:l}}catch(l){const o=(a=l==null?void 0:l.response)==null?void 0:a.status;return o===404?{status:"missing",data:null}:{status:o===403?"forbidden":"error",data:null}}}function b(t=new Date){const e=new Date(Date.UTC(t.getFullYear(),t.getMonth(),t.getDate())),i=new Date(e);return i.setUTCMonth(i.getUTCMonth()-3),{from:i.toISOString().slice(0,10),to:e.toISOString().slice(0,10)}}const y="ld-mensura.vectura-demo",{Criteria:n}=Shopware.Data,T=["base","quantity","bulky","express","multi_source","other"],$=["per_sqm","per_piece","per_position","percent"],S={base:"c1",bulky:"c2",quantity:"c3",express:"c4"},x=[{width:1e3,height:500,perMotif:1},{width:1500,height:1e3,perMotif:1},{width:2400,height:1200,perMotif:2}],_={template:`
<mt-card class="lm-logistics" position-identifier="lm-logistics" :title="$tc('ld-mensura.logistics.cardTitle')">
    <template #headerRight>
        <mt-button v-if="canEdit" size="small" variant="secondary" @click="openCreate">
            <mt-icon name="regular-plus-circle" size="14px" />
            {{ $tc('ld-mensura.logistics.buttonAdd') }}
        </mt-button>
    </template>

    <lm-info-card name="logistics" :title="$tc('ld-mensura.logistics.infoTitle')">
        {{ $tc('ld-mensura.logistics.infoText') }}
    </lm-info-card>

    <sw-loader v-if="isLoading" size="32px" />

    <table v-else-if="sortedRules.length" class="lm-breakdown lm-logistics__rules">
        <thead>
            <tr>
                <th>{{ $tc('ld-mensura.logistics.labelCostType') }}</th>
                <th>{{ $tc('ld-mensura.logistics.labelForm') }}</th>
                <th class="lm-num">{{ $tc('ld-mensura.logistics.labelValue') }}</th>
                <th>{{ $tc('ld-mensura.logistics.columnRule') }}</th>
                <th>{{ $tc('ld-mensura.logistics.labelValidFrom') }}</th>
                <th>{{ $tc('ld-mensura.logistics.columnStatus') }}</th>
                <th v-if="canEdit"></th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="rule in sortedRules" :key="rule.id" :class="{ 'is--inactive': statusOf(rule).state === 'off' }">
                <td><lm-chip :label="$tc('ld-mensura.logistics.costTypes.' + rule.costType)" :color="costColor(rule.costType)" icon="regular-truck" /></td>
                <td>{{ $tc('ld-mensura.logistics.forms.' + rule.form) }}</td>
                <td class="lm-num">{{ valueText(rule) }}</td>
                <td class="lm-muted">{{ ruleDetail(rule) }}</td>
                <td>{{ rule.validFrom ? date(rule.validFrom) : $tc('ld-mensura.logistics.immediately') }}</td>
                <td><lm-status :state="statusOf(rule).state" :label="statusOf(rule).label" /></td>
                <td v-if="canEdit" class="lm-num">
                    <sw-context-button>
                        <sw-context-menu-item @click="openEdit(rule)">{{ $tc('global.default.edit') }}</sw-context-menu-item>
                        <sw-context-menu-item variant="danger" :disabled="statusOf(rule).key === 'replaced' || undefined" @click="deleteRule = rule">{{ $tc('global.default.delete') }}</sw-context-menu-item>
                    </sw-context-button>
                </td>
            </tr>
        </tbody>
    </table>

    <sw-empty-state
        v-else
        :title="$tc('ld-mensura.logistics.emptyTitle')"
        :subline="$tc('ld-mensura.logistics.emptySubline')"
        icon="regular-truck"
        :absolute="false"
    >
        <template #actions>
            <mt-button v-if="canEdit" variant="primary" @click="openCreate">{{ $tc('ld-mensura.logistics.buttonAdd') }}</mt-button>
        </template>
    </sw-empty-state>
    <p v-if="!hasVectura && !suggestionDemo" class="lm-muted lm-logistics__vectura">{{ $tc('ld-mensura.logistics.noVectura') }}</p>

    <div v-if="showSuggestions" class="lm-logistics__suggestions">
        <h4>{{ $tc('ld-mensura.logistics.vectura.title') }}</h4>
        <mt-banner v-if="suggestionDemo" variant="attention">{{ $tc('ld-mensura.logistics.vectura.demo') }}</mt-banner>
        <mt-banner v-if="suggestionStatus === 'forbidden'" variant="info">{{ $tc('ld-mensura.logistics.vectura.forbidden') }}</mt-banner>
        <mt-banner v-else-if="suggestionStatus === 'error'" variant="attention">{{ $tc('ld-mensura.logistics.vectura.error') }}</mt-banner>
        <p v-else-if="matchedSuggestions.length === 0" class="lm-muted">{{ $tc('ld-mensura.logistics.vectura.none') }}</p>
        <div v-for="suggestion in matchedSuggestions" :key="suggestion.id" class="lm-logistics__suggestion">
            <div class="lm-logistics__suggestion-main">
                <lm-chip :label="$tc('ld-mensura.logistics.costTypes.' + suggestion.costType)" :color="costColor(suggestion.costType)" icon="regular-truck" />
                <b>{{ valueText(suggestion.proposal) }}</b>
                <span class="lm-muted">{{ ruleDetail(suggestion.proposal) }}</span>
            </div>
            <div class="lm-muted">
                {{ $tc('ld-mensura.logistics.vectura.coverage', {
                    before: num(suggestion.coverage, 2),
                    target: num(suggestion.targetCoverage, 2),
                    cases: suggestion.cases,
                    from: date(suggestionMeta.period?.from),
                    to: date(suggestionMeta.period?.to),
                }) }}
                <template v-if="currentFor(suggestion.costType)"> · {{ $tc('ld-mensura.logistics.vectura.current', { value: valueText(currentFor(suggestion.costType)) }) }}</template>
            </div>
            <ul v-if="suggestionWarnings(suggestion).length" class="lm-logistics__suggestion-warnings">
                <li v-for="code in suggestionWarnings(suggestion)" :key="code">
                    <mt-icon name="regular-exclamation-triangle" size="12px" />
                    {{ $tc('ld-mensura.logistics.vectura.warnings.' + code) }}
                </li>
            </ul>
            <div class="lm-logistics__suggestion-actions">
                <mt-button
                    v-if="canEdit && suggestion.stable !== false"
                    size="small"
                    variant="secondary"
                    @click="adoptSuggestion(suggestion)"
                >
                    {{ $tc('ld-mensura.logistics.vectura.adopt', { date: date(adoptDate) }) }}
                </mt-button>
                <span v-else-if="suggestion.stable === false" class="lm-muted">{{ $tc('ld-mensura.logistics.vectura.notStable') }}</span>
            </div>
        </div>
    </div>

    <div class="lm-logistics__preview">
        <h4>{{ $tc('ld-mensura.logistics.previewTitle') }}</h4>
        <p class="lm-muted">{{ $tc('ld-mensura.logistics.previewIntro') }}</p>
        <sw-entity-single-select
            v-if="needsCalculationChoice"
            v-model:value="previewCalculationId"
            entity="ld_mensura_calculation"
            label-property="name"
            :label="$tc('ld-mensura.logistics.labelPreviewCalculation')"
            :help-text="$tc('ld-mensura.logistics.helpPreviewCalculation')"
        />
        <mt-banner v-if="!previewCalculationId" variant="info">{{ $tc('ld-mensura.logistics.previewNoCalculation') }}</mt-banner>
        <template v-else>
            <table class="lm-breakdown">
                <thead>
                    <tr>
                        <th>{{ $tc('ld-mensura.logistics.columnFormat') }}</th>
                        <th class="lm-num">{{ $tc('ld-mensura.calculation.test.perMotif') }}</th>
                        <th class="lm-num">{{ $tc('ld-mensura.logistics.columnWithout') }}</th>
                        <th class="lm-num">{{ $tc('ld-mensura.logistics.columnLogistics') }}</th>
                        <th class="lm-num">{{ $tc('ld-mensura.logistics.columnTotal') }}</th>
                        <th class="lm-num">{{ $tc('ld-mensura.logistics.columnShare') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(sample, index) in samples" :key="index">
                        <td class="lm-logistics__format">
                            <mt-number-field v-model="sample.width" number-type="int" :min="1" size="small" @update:model-value="schedulePreview" />
                            <span>×</span>
                            <mt-number-field v-model="sample.height" number-type="int" :min="1" size="small" @update:model-value="schedulePreview" />
                        </td>
                        <td class="lm-num"><mt-number-field v-model="sample.perMotif" number-type="int" :min="1" size="small" @update:model-value="schedulePreview" /></td>
                        <template v-if="previewRows[index] && previewRows[index].valid">
                            <td class="lm-num">{{ money(previewRows[index].totalWithoutLogistics) }}</td>
                            <td class="lm-num">{{ money(previewRows[index].logistics) }}</td>
                            <td class="lm-num"><b>{{ money(previewRows[index].total) }}</b></td>
                            <td class="lm-num">
                                <lm-status
                                    :state="previewRows[index].warn ? 'warn' : 'ok'"
                                    :label="num(previewRows[index].sharePercent, 1) + ' %'"
                                />
                            </td>
                        </template>
                        <td v-else colspan="4" class="lm-muted">{{ previewRows[index] ? previewRows[index].errors.join(' · ') : '…' }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-if="previewWarn" class="lm-logistics__warn">
                <mt-icon name="regular-exclamation-triangle" size="14px" />
                {{ $tc('ld-mensura.logistics.warnShare', { percent: num(warnPercent, 0) }) }}
            </p>
        </template>
    </div>

    <sw-modal
        v-if="draft"
        :title="$tc(draft.id && !draft.isNewVersion ? 'ld-mensura.logistics.modalEdit' : 'ld-mensura.logistics.modalCreate')"
        variant="large"
        @modal-close="draft = null"
    >
        <mt-banner v-if="draft.isNewVersion" variant="info">{{ $tc('ld-mensura.logistics.newVersionHint') }}</mt-banner>
        <mt-banner v-if="draft.source === 'vectura'" variant="info">{{ $tc('ld-mensura.logistics.vectura.adoptHint') }}</mt-banner>
        <sw-container columns="repeat(auto-fit, minmax(220px, 1fr))" gap="0 24px">
            <sw-single-select v-model:value="draft.costType" :options="costTypeOptions" :label="$tc('ld-mensura.logistics.labelCostType')" :help-text="$tc('ld-mensura.logistics.helpCostType')" @update:value="schedulePreview" />
            <sw-single-select v-model:value="draft.form" :options="formOptions" :label="$tc('ld-mensura.logistics.labelForm')" :help-text="$tc('ld-mensura.logistics.helpForm')" @update:value="schedulePreview" />
            <mt-number-field v-model="draft.value" :digits="4" :label="$tc('ld-mensura.logistics.labelValue') + ' (' + unitOf(draft.form) + ')'" :help-text="$tc('ld-mensura.logistics.helpValue')" @update:model-value="schedulePreview" />
            <mt-datepicker v-model="draft.validFrom" date-type="date" :label="$tc('ld-mensura.logistics.labelValidFrom')" :help-text="$tc('ld-mensura.logistics.helpValidFrom')" :required="draft.isNewVersion || undefined" />
        </sw-container>

        <mt-switch v-model="draft.useRamp" :label="$tc('ld-mensura.logistics.labelRamp')" :help-text="$tc('ld-mensura.logistics.helpRamp')" bordered @update:model-value="schedulePreview" />
        <sw-container v-if="draft.useRamp" columns="repeat(auto-fit, minmax(180px, 1fr))" gap="0 24px">
            <mt-number-field v-model="draft.rampFrom" number-type="int" :label="$tc('ld-mensura.logistics.labelRampFrom')" :help-text="$tc('ld-mensura.logistics.helpRampFrom')" @update:model-value="schedulePreview" />
            <mt-number-field v-model="draft.rampTo" number-type="int" :label="$tc('ld-mensura.logistics.labelRampTo')" :help-text="$tc('ld-mensura.logistics.helpRampTo')" :error="rampError" @update:model-value="schedulePreview" />
        </sw-container>
        <svg v-if="draft.useRamp" class="lm-logistics__ramp" viewBox="0 0 300 60" preserveAspectRatio="none" role="img" :aria-label="$tc('ld-mensura.logistics.rampGraph')">
            <polyline :points="rampPoints" fill="none" stroke="currentColor" stroke-width="2" />
        </svg>

        <mt-switch v-model="draft.useThreshold" :label="$tc('ld-mensura.logistics.labelThreshold')" :help-text="$tc('ld-mensura.logistics.helpThreshold')" bordered @update:model-value="schedulePreview" />
        <sw-container v-if="draft.useThreshold" columns="repeat(auto-fit, minmax(180px, 1fr))" gap="0 24px">
            <sw-single-select v-model:value="draft.thresholdBasis" :options="thresholdOptions" :label="$tc('ld-mensura.logistics.labelThresholdBasis')" :help-text="$tc('ld-mensura.logistics.helpThresholdBasis')" @update:value="schedulePreview" />
            <mt-number-field v-model="draft.thresholdFrom" :digits="2" :label="$tc('ld-mensura.logistics.labelThresholdFrom')" :help-text="$tc('ld-mensura.logistics.helpThresholdFrom')" @update:model-value="schedulePreview" />
            <mt-number-field v-model="draft.thresholdTo" :digits="2" :label="$tc('ld-mensura.logistics.labelThresholdTo')" :help-text="$tc('ld-mensura.logistics.helpThresholdTo')" :error="thresholdError" @update:model-value="schedulePreview" />
        </sw-container>

        <mt-switch v-model="draft.useMinimum" :label="$tc('ld-mensura.logistics.labelMinimum')" :help-text="$tc('ld-mensura.logistics.helpMinimum')" bordered @update:model-value="schedulePreview" />
        <sw-container v-if="draft.useMinimum" columns="repeat(auto-fit, minmax(180px, 1fr))" gap="0 24px">
            <mt-number-field v-model="draft.minimumAmount" :digits="2" :label="$tc('ld-mensura.logistics.labelMinimumAmount')" :help-text="$tc('ld-mensura.logistics.helpMinimumAmount')" @update:model-value="schedulePreview" />
            <sw-single-select v-if="target.optionValueId" v-model:value="draft.minimumScope" :options="scopeOptions" :label="$tc('ld-mensura.logistics.labelMinimumScope')" :help-text="$tc('ld-mensura.logistics.helpMinimumScope')" @update:value="schedulePreview" />
        </sw-container>

        <sw-entity-single-select
            v-if="target.optionValueId"
            v-model:value="draft.calculationId"
            entity="ld_mensura_calculation"
            label-property="name"
            :label="$tc('ld-mensura.logistics.labelRestriction')"
            :help-text="$tc('ld-mensura.logistics.helpRestriction')"
        />
        <mt-textarea v-model="draft.note" :label="$tc('ld-mensura.logistics.labelNote')" :help-text="$tc('ld-mensura.logistics.helpNote')" />

        <mt-banner v-for="warning in draftWarnings" :key="warning" variant="attention">{{ warning }}</mt-banner>

        <template #modal-footer>
            <mt-button size="small" @click="draft = null">{{ $tc('global.default.cancel') }}</mt-button>
            <mt-button size="small" variant="primary" :is-loading="isSaving" :disabled="!draftValid || undefined" @click="saveDraft">{{ $tc('ld-mensura.logistics.buttonSave') }}</mt-button>
        </template>
    </sw-modal>

    <sw-modal v-if="deleteRule" :title="$tc('ld-mensura.logistics.deleteTitle')" variant="small" @modal-close="deleteRule = null">
        <p>{{ $tc('ld-mensura.logistics.deleteText') }}</p>
        <template #modal-footer>
            <mt-button size="small" @click="deleteRule = null">{{ $tc('global.default.cancel') }}</mt-button>
            <mt-button size="small" variant="critical" @click="confirmDelete">{{ $tc('global.default.delete') }}</mt-button>
        </template>
    </sw-modal>
</mt-card>`,inject:["repositoryFactory","acl"],mixins:[Shopware.Mixin.getByName("notification")],props:{target:{type:Object,required:!0},calculationId:{type:String,required:!1,default:null},sample:{type:Object,required:!1,default:()=>({})},optionInfo:{type:Object,required:!1,default:null},keys:{type:Object,required:!1,default:()=>({})}},data(){return{rules:[],isLoading:!1,isSaving:!1,draft:null,deleteRule:null,samples:x.map(t=>({...t})),previewRows:[],warnPercent:15,previewCalculationId:this.calculationId,previewTimer:null,suggestionStatus:"missing",suggestionMeta:{},suggestionList:[],suggestionDemo:!1,adoptDate:f()}},computed:{repository(){return this.repositoryFactory.create("ld_mensura_logistics_rule")},canEdit(){return this.acl.can("ld_mensura.logistics_edit")},targetKey(){return JSON.stringify([this.target.calculationId??null,this.target.materialId??null,this.target.optionValueId??null])},hasVectura(){return(Shopware.Context.app.config.bundles??{}).LdVectura!==void 0},showSuggestions(){return this.suggestionStatus!=="missing"},matchedSuggestions(){return p(this.suggestionList,this.keys)},needsCalculationChoice(){return!this.target.calculationId},today(){return new Date().toISOString().slice(0,10)},sortedRules(){return[...this.rules].sort((t,e)=>t.costType.localeCompare(e.costType)||(this.dateOf(e)||"").localeCompare(this.dateOf(t)||""))},currentIds(){const t={};return this.rules.forEach(e=>{const i=this.dateOf(e);if(e.active===!1||i&&i>this.today)return;const s=`${e.costType}|${e.calculationId??""}`;(!t[s]||(i||"")>(this.dateOf(t[s])||""))&&(t[s]=e)}),new Set(Object.values(t).map(e=>e.id))},costTypeOptions(){return T.map(t=>({value:t,label:this.$tc(`ld-mensura.logistics.costTypes.${t}`)}))},formOptions(){return $.map(t=>({value:t,label:this.$tc(`ld-mensura.logistics.forms.${t}`)}))},thresholdOptions(){return["total_area","quantity"].map(t=>({value:t,label:this.$tc(`ld-mensura.logistics.thresholdBases.${t}`)}))},scopeOptions(){return["position","order"].map(t=>({value:t,label:this.$tc(`ld-mensura.logistics.scopes.${t}`)}))},rampError(){var t;return!((t=this.draft)!=null&&t.useRamp)||this.draft.rampFrom===null||this.draft.rampTo===null?null:this.draft.rampTo<this.draft.rampFrom+100?{detail:this.$tc("ld-mensura.logistics.errorRamp")}:null},thresholdError(){var t;return!((t=this.draft)!=null&&t.useThreshold)||this.draft.thresholdFrom===null||this.draft.thresholdTo===null?null:this.draft.thresholdTo<=this.draft.thresholdFrom?{detail:this.$tc("ld-mensura.logistics.errorThreshold")}:null},draftValid(){const t=this.draft;return!t||t.value===null||t.value===void 0||!t.costType||!t.form||t.useRamp&&(t.rampFrom===null||t.rampTo===null||this.rampError)||t.useThreshold&&(!t.thresholdBasis||t.thresholdFrom===null||t.thresholdTo===null||this.thresholdError)||t.useMinimum&&(t.minimumAmount===null||t.minimumAmount===void 0)?!1:!(t.isNewVersion&&!t.validFrom)},draftWarnings(){var l,o;const t=this.draft;if(!t)return[];const e=[],i=t.useRamp||t.useThreshold||t.useMinimum||t.form==="percent";this.target.optionValueId&&i&&((l=this.optionInfo)==null?void 0:l.showPriceHint)!==!1&&e.push(this.$tc("ld-mensura.logistics.warnHint")),t.useMinimum&&t.minimumScope==="order"&&((o=this.optionInfo)==null?void 0:o.groupType)!=="delivery"&&e.push(this.$tc("ld-mensura.logistics.warnOrderScope"));const s=this.rules.find(r=>this.currentIds.has(r.id)&&r.costType===t.costType&&(r.calculationId??null)===(t.calculationId??null)&&r.id!==t.id&&r.form===t.form),a=t.isNewVersion?t.previousValue:s==null?void 0:s.value;if(a!=null&&t.value!==null){const r=t.form==="percent"?2:t.form==="per_sqm"?1:null;r!==null&&Math.abs(t.value-a)>r&&e.push(this.$tc("ld-mensura.logistics.warnStep",{limit:this.num(r,0),unit:this.unitOf(t.form)}))}return this.previewWarn&&e.push(this.$tc("ld-mensura.logistics.warnShare",{percent:this.num(this.warnPercent,0)})),e},previewWarn(){return this.previewRows.some(t=>(t==null?void 0:t.valid)&&t.warn)},rampPoints(){var s,a;const t=Number(((s=this.draft)==null?void 0:s.rampFrom)??0),e=Number(((a=this.draft)==null?void 0:a.rampTo)??0),i=[];for(let l=0;l<=3e3;l+=50){const o=e>t?Math.min(1,Math.max(0,(l-t)/(e-t))):1;i.push(`${l/3e3*300},${58-o*54}`)}return i.join(" ")}},watch:{targetKey(t,e){t!==e&&this.load()},calculationId(t){this.previewCalculationId=t},previewCalculationId(){this.schedulePreview()}},created(){this.load(),this.loadVectura()},beforeUnmount(){clearTimeout(this.previewTimer)},methods:{criteria(){const t=new n(1,100);return this.target.optionValueId?t.addFilter(n.equals("optionValueId",this.target.optionValueId)):this.target.materialId?t.addFilter(n.equals("materialId",this.target.materialId)):(t.addFilter(n.equals("calculationId",this.target.calculationId)),t.addFilter(n.equals("materialId",null)),t.addFilter(n.equals("optionValueId",null))),t},async load(){if(!this.target.calculationId&&!this.target.materialId&&!this.target.optionValueId){this.rules=[];return}this.isLoading=!0;try{this.rules=[...await this.repository.search(this.criteria(),Shopware.Context.api)]}catch{this.rules=[],this.createNotificationError({message:this.$tc("ld-mensura.logistics.errorLoad")})}finally{this.isLoading=!1}this.schedulePreview()},async loadVectura(){let t=!1;try{t=!this.hasVectura&&window.localStorage.getItem(y)==="1"}catch{t=!1}if(t){const i=await u(()=>import("./vectura-suggestions.example-BE7SdbCT.js"),[]);this.applySuggestions({status:"ok",data:i.default??i},!0);return}const e=await w(Shopware.Application.getContainer("init").httpClient,{Authorization:`Bearer ${Shopware.Context.api.authToken.access}`},this.hasVectura,b());this.applySuggestions(e,!1)},applySuggestions(t,e){var i,s,a;this.suggestionDemo=e,this.suggestionStatus=t.status,this.suggestionMeta={period:((i=t.data)==null?void 0:i.period)??null,generatedAt:((s=t.data)==null?void 0:s.generatedAt)??null},this.suggestionList=((a=t.data)==null?void 0:a.suggestions)??[]},currentFor(t){return this.rules.find(e=>this.currentIds.has(e.id)&&e.costType===t&&!e.calculationId)??null},suggestionWarnings(t){const e=[...t.warnings??[]];return!e.includes("step_limit_exceeded")&&g(t,this.currentFor(t.costType))&&e.push("step_limit_exceeded"),e},adoptSuggestion(t){this.draft={...this.emptyDraft(),...v(t,this.suggestionMeta,this.adoptDate)},this.schedulePreview()},dateOf(t){return t.validFrom?String(t.validFrom).slice(0,10):null},statusOf(t){if(t.active===!1)return{key:"inactive",state:"off",label:this.$tc("lm-design.states.off")};const e=this.dateOf(t);return e&&e>this.today?{key:"planned",state:"warn",label:this.$tc("ld-mensura.logistics.states.planned")}:this.currentIds.has(t.id)?{key:"current",state:"ok",label:this.$tc("ld-mensura.logistics.states.current")}:{key:"replaced",state:"off",label:this.$tc("ld-mensura.logistics.states.replaced")}},costColor(t){return S[t]??"c4"},unitOf(t){return{percent:"%",per_sqm:"€/m²",per_piece:"€/Stk",per_position:"€"}[t]??"€"},valueText(t){return`${this.num(t.value,(t.form==="percent",2))} ${this.unitOf(t.form)}`},ruleDetail(t){const e=[];return t.rampFrom!==null&&t.rampFrom!==void 0&&e.push(this.$tc("ld-mensura.logistics.detailRamp",{from:this.num(t.rampFrom,0),to:this.num(t.rampTo,0)})),t.thresholdBasis&&e.push(this.$tc(`ld-mensura.logistics.detailThreshold.${t.thresholdBasis}`,{from:this.num(t.thresholdFrom,2),to:this.num(t.thresholdTo,2)})),t.minimumAmount!==null&&t.minimumAmount!==void 0&&e.push(this.$tc(`ld-mensura.logistics.detailMinimum.${t.minimumScope??"position"}`,{amount:this.money(t.minimumAmount)})),t.source==="vectura"&&e.push(this.$tc("ld-mensura.logistics.fromVectura")),e.join(" · ")||"–"},emptyDraft(){var t;return{id:null,isNewVersion:!1,costType:this.target.optionValueId?"express":"base",form:"per_sqm",value:null,validFrom:null,useRamp:!1,rampFrom:1e3,rampTo:1400,useThreshold:!1,thresholdBasis:"total_area",thresholdFrom:null,thresholdTo:null,useMinimum:!1,minimumAmount:null,minimumScope:((t=this.optionInfo)==null?void 0:t.groupType)==="delivery"?"order":"position",calculationId:this.target.optionValueId?null:this.target.calculationId??null,note:""}},openCreate(){this.draft=this.emptyDraft(),this.schedulePreview()},openEdit(t){const e=this.statusOf(t).key,i=new Date(Date.now()+864e5).toISOString().slice(0,10);this.draft={...this.emptyDraft(),id:t.id,isNewVersion:e==="current"||e==="replaced",previousValue:t.value,costType:t.costType,form:t.form,value:t.value,validFrom:e==="current"||e==="replaced"?i:this.dateOf(t),useRamp:t.rampFrom!==null&&t.rampFrom!==void 0,rampFrom:t.rampFrom??1e3,rampTo:t.rampTo??1400,useThreshold:!!t.thresholdBasis,thresholdBasis:t.thresholdBasis??"total_area",thresholdFrom:t.thresholdFrom,thresholdTo:t.thresholdTo,useMinimum:t.minimumAmount!==null&&t.minimumAmount!==void 0,minimumAmount:t.minimumAmount,minimumScope:t.minimumScope??"position",calculationId:t.calculationId??null,note:t.note??""},this.schedulePreview()},ruleData(t){return Object.fromEntries(["id","calculationId","materialId","optionValueId","costType","form","value","rampFrom","rampTo","thresholdBasis","thresholdFrom","thresholdTo","minimumAmount","minimumScope","validFrom","active"].map(i=>[i,t[i]??null]))},draftPayload(){const t=this.draft,e=!!this.target.optionValueId;return{calculationId:e?t.calculationId||null:this.target.materialId?null:this.target.calculationId,materialId:this.target.materialId??null,optionValueId:this.target.optionValueId??null,costType:t.costType,form:t.form,value:Number(t.value),rampFrom:t.useRamp?t.rampFrom:null,rampTo:t.useRamp?t.rampTo:null,thresholdBasis:t.useThreshold?t.thresholdBasis:null,thresholdFrom:t.useThreshold?t.thresholdFrom:null,thresholdTo:t.useThreshold?t.thresholdTo:null,minimumAmount:t.useMinimum?t.minimumAmount:null,minimumScope:t.useMinimum?e?t.minimumScope:"position":null,validFrom:t.validFrom?String(t.validFrom).slice(0,10):null,active:!0,note:t.note||null}},async saveDraft(){var e,i;if(!this.draftValid)return;this.isSaving=!0;const t=this.draftWarnings;try{const s=this.draftPayload(),a=this.draft.id&&!this.draft.isNewVersion?this.rules.find(l=>l.id===this.draft.id):this.repository.create(Shopware.Context.api);(e=a.isNew)!=null&&e.call(a)&&Object.assign(a,this.draft.source==="vectura"?{source:"vectura",sourceReference:{...this.draft.sourceReference,adoptedAt:new Date().toISOString(),adoptedBy:((i=Shopware.Store.get("session").currentUser)==null?void 0:i.username)??null}}:{source:"manual"}),Object.assign(a,s),await this.repository.save(a,Shopware.Context.api),this.draft=null,t.forEach(l=>this.createNotificationWarning({message:l})),this.createNotificationSuccess({message:this.$tc("ld-mensura.logistics.saved")}),await this.load()}catch{this.createNotificationError({message:this.$tc("ld-mensura.logistics.errorSave")})}finally{this.isSaving=!1}},async confirmDelete(){const t=this.deleteRule;this.deleteRule=null;try{await this.repository.delete(t.id,Shopware.Context.api),await this.load()}catch{this.createNotificationError({message:this.$tc("ld-mensura.logistics.errorSave")})}},schedulePreview(){clearTimeout(this.previewTimer),this.previewTimer=setTimeout(()=>this.runPreview(),400)},async runPreview(){if(!this.previewCalculationId){this.previewRows=[];return}const t=[];if(this.draft&&this.draftValid){const e=this.draftPayload(),i=this.draft.id&&!this.draft.isNewVersion;this.rules.filter(s=>s.costType===e.costType&&(s.calculationId??null)===(e.calculationId??null)&&!(i&&s.id===this.draft.id)).forEach(s=>t.push({...this.ruleData(s),active:!1})),t.push({...e,id:i?this.draft.id:null,validFrom:null})}try{const{data:e}=await Shopware.Application.getContainer("init").httpClient.post("/_action/ld-mensura/logistics/preview",{calculationId:this.previewCalculationId,language:Shopware.Store.get("session").currentLocale??"de-DE",rules:t,samples:this.samples.map(i=>({...this.sample,format:{width:i.width,height:i.height},perMotif:i.perMotif}))},{headers:{Authorization:`Bearer ${Shopware.Context.api.authToken.access}`}});this.previewRows=e.samples??[],this.warnPercent=e.warnPercent??15}catch{this.previewRows=[]}},num(t,e=2){return d(t,e)},money(t){return c(t)},date(t){return t?Shopware.Utils.format.date(t,{hour:void 0,minute:void 0}):"–"}}};export{_ as default};
