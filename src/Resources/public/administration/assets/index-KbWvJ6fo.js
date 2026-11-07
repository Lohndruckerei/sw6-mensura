import{f as y,m as f}from"./margin-CYtqVi4K.js";const v={template:`
<div class="lm-testcalc">
    <mt-card position-identifier="lm-testcalc-input" :title="$tc('ld-mensura.calculation.test.cardInput')">
        <p class="lm-muted lm-card-intro">{{ $tc('ld-mensura.calculation.test.intro') }}</p>
        <sw-container columns="repeat(auto-fit, minmax(180px, 1fr))" gap="0 24px">
            <sw-single-select v-model:value="input.materialId" :options="materialOptions" :label="$tc('ld-mensura.areas.material.title')" />
            <mt-number-field v-model="input.width" number-type="int" :label="$tc('ld-mensura.calculation.test.width')" />
            <mt-number-field v-model="input.height" number-type="int" :label="$tc('ld-mensura.calculation.test.height')" />
            <mt-number-field v-model="input.motifs" number-type="int" :min="1" :label="$tc('ld-mensura.calculation.test.motifs')" />
            <mt-number-field v-model="input.perMotif" number-type="int" :min="1" :label="$tc('ld-mensura.calculation.test.perMotif')" />
            <sw-entity-single-select v-model:value="input.customerGroupId" entity="customer_group" label-property="name" :label="$tc('ld-mensura.calculation.test.customerGroup')" :help-text="$tc('ld-mensura.calculation.test.helpCustomerGroup')" />
        </sw-container>
        <sw-container columns="repeat(auto-fit, minmax(180px, 1fr))" gap="0 24px">
            <sw-single-select
                v-for="group in groupChoices"
                :key="group.technicalName"
                :value="input.options[group.technicalName] ?? null"
                :options="group.options"
                :label="group.label"
                @update:value="setOption(group, $event)"
            />
        </sw-container>
        <mt-button variant="primary" :is-loading="isLoading" @click="calculate">
            {{ $tc('ld-mensura.calculation.test.buttonCalculate') }}
        </mt-button>
        <span class="lm-muted lm-testcalc__note">{{ $tc('ld-mensura.calculation.test.noteSaved') }}</span>
    </mt-card>

    <mt-card v-if="response" position-identifier="lm-testcalc-result" :title="$tc('ld-mensura.calculation.test.cardResult')">
        <div v-if="!result.valid" class="lm-testcalc__errors">
            <mt-banner v-for="error in result.errors" :key="error.code" variant="critical" :title="error.code">{{ error.message }}</mt-banner>
        </div>
        <template v-else>
            <div class="lm-kpis lm-testcalc__kpis">
                <div class="lm-kpi lm-kpi--all"><mt-icon name="regular-euro" size="20px" /><span><b>{{ money(result.price.total) }}</b><small>{{ $tc('ld-mensura.calculation.test.total') }}</small></span></div>
                <div class="lm-kpi lm-kpi--all"><mt-icon name="regular-products" size="20px" /><span><b>{{ money(result.price.perPiece) }}</b><small>{{ $tc('ld-mensura.calculation.test.perPiece') }}</small></span></div>
                <div class="lm-kpi lm-kpi--all"><mt-icon name="regular-crop" size="20px" /><span><b>{{ money(result.price.perSqm) }}</b><small>{{ $tc('ld-mensura.calculation.test.perSqm') }}</small></span></div>
                <div v-if="response.purchase" class="lm-kpi" :class="'lm-kpi--' + (margin ? margin.state : 'all')"><mt-icon name="regular-chart-line" size="20px" /><span><b>{{ money(response.contributionMargin) }}</b><small>{{ $tc('ld-mensura.calculation.test.contribution', { margin: margin ? num(margin.margin, 1) : '–' }) }}</small></span></div>
            </div>
            <mt-banner v-for="hint in result.hints" :key="hint.code" variant="info">{{ hint.message }}</mt-banner>

            <table class="lm-breakdown">
                <thead>
                    <tr>
                        <th>{{ $tc('ld-mensura.calculation.test.line') }}</th>
                        <th class="lm-num">{{ $tc('ld-mensura.price.sale') }}</th>
                        <th v-if="response.purchase" class="lm-num">{{ $tc('ld-mensura.price.purchase') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.key">
                        <td>{{ row.label }}</td>
                        <td class="lm-num">{{ row.sale }}</td>
                        <td v-if="response.purchase" class="lm-num">{{ row.purchase }}</td>
                    </tr>
                </tbody>
            </table>
            <p class="lm-muted">
                {{ $tc('ld-mensura.calculation.test.areaLine', { piece: num(response.areas?.piece, 4), total: num(response.areas?.total, 4), billed: num(response.areas?.billed, 4) }) }}
                <template v-if="response.weight"> · {{ $tc('ld-mensura.calculation.test.weightLine', { total: num(response.weight.total, 2) }) }}</template>
            </p>
        </template>
    </mt-card>
</div>`,inject:["repositoryFactory"],mixins:[Shopware.Mixin.getByName("notification")],props:{calculation:{type:Object,required:!0},groups:{type:Array,required:!0}},data(){var e,l;const t=((l=(e=this.calculation.materials)==null?void 0:e.first)==null?void 0:l.call(e))??null;return{isLoading:!1,response:null,input:{materialId:(t==null?void 0:t.id)??null,width:1e3,height:500,motifs:1,perMotif:1,customerGroupId:null,options:{},deliveryValue:null}}},computed:{httpClient(){return Shopware.Application.getContainer("init").httpClient},materialOptions(){return[...this.calculation.materials??[]].map(t=>{var e;return{value:t.id,label:((e=t.translated)==null?void 0:e.name)||t.technicalName}})},groupChoices(){const t=[...this.calculation.optionValues??[]].filter(e=>e.active!==!1&&(e.materialId===null||e.materialId===this.input.materialId));return[...this.groups].sort((e,l)=>{var a,i;return(e.position??0)-(l.position??0)||(((a=e.translated)==null?void 0:a.name)||e.technicalName).localeCompare(((i=l.translated)==null?void 0:i.name)||l.technicalName)}).map(e=>{var a;const l=[...e.values??[]].filter(i=>t.some(n=>n.optionValueId===i.id)).sort((i,n)=>(i.position??0)-(n.position??0)).map(i=>{var n;return{value:i.technicalName,label:((n=i.translated)==null?void 0:n.name)||i.technicalName}});return{technicalName:e.technicalName,type:e.type,label:((a=e.translated)==null?void 0:a.name)||e.technicalName,options:l}}).filter(e=>e.options.length>0&&e.type!=="format")},result(){var t;return((t=this.response)==null?void 0:t.result)??{}},margin(){var t;return(t=this.response)!=null&&t.purchase?f(this.response.purchase.total,this.response.sale.total):null},rows(){var m,p,d,h;const t=((m=this.response)==null?void 0:m.sale)??{},e=((p=this.response)==null?void 0:p.purchase)??{},l=(s,o)=>(s.lines??[]).find(r=>r.key===o),a=(s,o,r=2,c=" €")=>{const u=l(s,o);return u&&u.amount!==void 0?`${this.num(u.amount,r)}${c}`:"–"},i=[{key:"material",label:this.$tc("ld-mensura.calculation.test.lineMaterial"),sale:a(t,"material",4," €/m²"),purchase:a(e,"material",4," €/m²")}],n=((d=l(t,"components"))==null?void 0:d.items)??[],b=((h=l(e,"components"))==null?void 0:h.items)??[];return n.forEach((s,o)=>{const r=b.find(c=>c.source===s.source&&c.type===s.type);i.push({key:`component-${o}`,label:`${this.sourceLabel(s.source)} · ${this.$tc(`ld-mensura.components.types.${s.type}`)}`,sale:this.num(s.amount,4),purchase:r?this.num(r.amount,4):"–"})}),i.push({key:"piece",label:this.$tc("ld-mensura.calculation.test.linePiece"),sale:a(t,"piece",4),purchase:a(e,"piece",4)},{key:"quantity",label:this.$tc("ld-mensura.calculation.test.lineQuantity"),sale:this.num(t.quantity,0),purchase:this.num(e.quantity??null,0)},{key:"setup",label:this.$tc("ld-mensura.calculation.test.lineSetup"),sale:a(t,"setup"),purchase:a(e,"setup")},{key:"tier",label:this.$tc("ld-mensura.calculation.test.lineTier"),sale:a(t,"tierDiscountPercent",1," %"),purchase:a(e,"tierDiscountPercent",1," %")},{key:"percent",label:this.$tc("ld-mensura.calculation.test.linePercent"),sale:a(t,"percent",1," %"),purchase:a(e,"percent",1," %")},{key:"subtotal",label:this.$tc("ld-mensura.calculation.test.lineSubtotal"),sale:a(t,"subtotal"),purchase:a(e,"subtotal")},{key:"minimum",label:this.$tc("ld-mensura.calculation.test.lineMinimum"),sale:a(t,"minimum"),purchase:a(e,"minimum")},{key:"group",label:this.$tc("ld-mensura.calculation.test.lineCustomerGroup"),sale:a(t,"customerGroupPercent",1," %"),purchase:"–"},{key:"total",label:this.$tc("ld-mensura.calculation.test.total"),sale:this.money(t.total),purchase:this.money(e.total??null)}),i}},methods:{setOption(t,e){if(t.type==="delivery"){this.input.deliveryValue=e;return}this.input.options={...this.input.options,[t.technicalName]:e}},async calculate(){var t,e;this.isLoading=!0;try{const{data:l}=await this.httpClient.post("/_action/ld-mensura/test-calculate",{calculationId:this.calculation.id,customerGroupId:this.input.customerGroupId,language:Shopware.Store.get("session").currentLocale??"de-DE",configuration:{materialId:this.input.materialId,format:{width:this.input.width,height:this.input.height},motifs:this.input.motifs,perMotif:this.input.perMotif,options:this.input.options,deliveryValue:this.input.deliveryValue}},{headers:((e=(t=Shopware.Service("loginService")).getBasicHeaders)==null?void 0:e.call(t))??{Authorization:`Bearer ${Shopware.Context.api.authToken.access}`}});this.response=l,this.input.options={...l.result.configuration.options},this.input.deliveryValue=l.result.configuration.deliveryValue??this.input.deliveryValue}catch{this.createNotificationError({message:this.$tc("ld-mensura.calculation.test.error")})}finally{this.isLoading=!1}},sourceLabel(t){return t.startsWith("option:")?t.slice(7):this.$tc(`ld-mensura.calculation.test.sources.${t}`)},num(t,e=2){return y(t,e)},money(t){return t==null?"–":`${y(t,2)} €`}}};export{v as default};
