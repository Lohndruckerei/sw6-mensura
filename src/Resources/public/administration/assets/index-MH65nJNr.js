import{f as y,m as b}from"./margin-CYtqVi4K.js";const v={template:`
<div class="lm-testcalc">
    <mt-card position-identifier="lm-testcalc-input" :title="$tc('ld-mensura.calculation.test.cardInput')">
        <p class="lm-muted lm-card-intro">{{ $tc('ld-mensura.calculation.test.intro') }}</p>
        <mt-banner v-if="loadError" variant="attention">{{ $tc('ld-mensura.calculation.test.notReady') }}</mt-banner>
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
                :value="(group.type === 'delivery' ? input.deliveryValue : input.options[group.technicalName]) ?? null"
                :options="group.options"
                :label="group.label"
                @update:value="setOption(group, $event)"
            />
        </sw-container>
        <mt-button variant="primary" :is-loading="isLoading" :disabled="!configurator || undefined" @click="calculate">
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
</div>`,inject:["repositoryFactory"],mixins:[Shopware.Mixin.getByName("notification")],props:{source:{type:Object,required:!0},reloadKey:{type:[Number,String],required:!1,default:0}},data(){return{isLoading:!1,configurator:null,loadError:null,response:null,input:{materialId:null,width:1e3,height:500,motifs:1,perMotif:1,customerGroupId:null,options:{},deliveryValue:null}}},computed:{httpClient(){return Shopware.Application.getContainer("init").httpClient},requestHeaders(){return{Authorization:`Bearer ${Shopware.Context.api.authToken.access}`}},language(){return Shopware.Store.get("session").currentLocale??"de-DE"},materials(){var t;return((t=this.configurator)==null?void 0:t.materials)??[]},materialOptions(){const t=new Set(this.materials.map(e=>{var l;return(l=e.calculation)==null?void 0:l.technicalName}));return this.materials.map(e=>{var l;return{value:e.id,label:t.size>1?`${e.name} · ${(l=e.calculation)==null?void 0:l.name}`:e.name}})},selectedMaterial(){return this.materials.find(t=>t.id===this.input.materialId)??null},groupChoices(){var l,i,n,o,u;const t=(l=this.selectedMaterial)==null?void 0:l.technicalName,e=((o=(n=(i=this.response)==null?void 0:i.result)==null?void 0:n.configuration)==null?void 0:o.options)??null;return(((u=this.configurator)==null?void 0:u.groups)??[]).filter(a=>a.type!=="format"&&a.mode!=="hidden").filter(a=>{var s,c;return!e||a.type==="delivery"||a.technicalName in e||(((c=(s=this.response)==null?void 0:s.result)==null?void 0:c.errors)??[]).some(d=>d.field===`options.${a.technicalName}`)}).map(a=>({technicalName:a.technicalName,type:a.type,label:a.mode==="fixed"?`${a.name} (${this.$tc("ld-mensura.profile.modes.fixed")})`:a.name,options:a.values.filter(s=>!s.availableForMaterials||s.availableForMaterials.includes(t)).map(s=>({value:s.technicalName,label:s.name}))})).filter(a=>a.options.length>0)},result(){var t;return((t=this.response)==null?void 0:t.result)??{}},margin(){var t;return(t=this.response)!=null&&t.purchase?b(this.response.purchase.total,this.response.sale.total):null},rows(){var a,s,c,d;const t=((a=this.response)==null?void 0:a.sale)??{},e=((s=this.response)==null?void 0:s.purchase)??{},l=(r,p)=>(r.lines??[]).find(m=>m.key===p),i=(r,p,m=2,h=" €")=>{const f=l(r,p);return f&&f.amount!==void 0?`${this.num(f.amount,m)}${h}`:"–"},n=[{key:"material",label:this.$tc("ld-mensura.calculation.test.lineMaterial"),sale:i(t,"material",4," €/m²"),purchase:i(e,"material",4," €/m²")}],o=((c=l(t,"components"))==null?void 0:c.items)??[],u=((d=l(e,"components"))==null?void 0:d.items)??[];return o.forEach((r,p)=>{const m=u.find(h=>h.source===r.source&&h.type===r.type);n.push({key:`component-${p}`,label:`${this.sourceLabel(r.source)} · ${this.$tc(`ld-mensura.components.types.${r.type}`)}`,sale:this.num(r.amount,4),purchase:m?this.num(m.amount,4):"–"})}),n.push({key:"piece",label:this.$tc("ld-mensura.calculation.test.linePiece"),sale:i(t,"piece",4),purchase:i(e,"piece",4)},{key:"quantity",label:this.$tc("ld-mensura.calculation.test.lineQuantity"),sale:this.num(t.quantity,0),purchase:this.num(e.quantity??null,0)},{key:"setup",label:this.$tc("ld-mensura.calculation.test.lineSetup"),sale:i(t,"setup"),purchase:i(e,"setup")},{key:"tier",label:this.$tc("ld-mensura.calculation.test.lineTier"),sale:i(t,"tierDiscountPercent",1," %"),purchase:i(e,"tierDiscountPercent",1," %")},{key:"percent",label:this.$tc("ld-mensura.calculation.test.linePercent"),sale:i(t,"percent",1," %"),purchase:i(e,"percent",1," %")},{key:"subtotal",label:this.$tc("ld-mensura.calculation.test.lineSubtotal"),sale:i(t,"subtotal"),purchase:i(e,"subtotal")},{key:"minimum",label:this.$tc("ld-mensura.calculation.test.lineMinimum"),sale:i(t,"minimum"),purchase:i(e,"minimum")},{key:"group",label:this.$tc("ld-mensura.calculation.test.lineCustomerGroup"),sale:i(t,"customerGroupPercent",1," %"),purchase:"–"},{key:"total",label:this.$tc("ld-mensura.calculation.test.total"),sale:this.money(t.total),purchase:this.money(e.total??null)}),n}},watch:{"input.materialId"(){this.response=null},reloadKey(){this.loadConfigurator()}},created(){this.loadConfigurator()},methods:{async loadConfigurator(){var t,e,l,i,n,o,u;this.loadError=null;try{const{data:a}=await this.httpClient.post("/_action/ld-mensura/test-configurator",{...this.source,customerGroupId:this.input.customerGroupId,language:this.language},{headers:this.requestHeaders});this.configurator=a;const s=a.defaults??{};this.materials.some(c=>c.id===this.input.materialId)||(this.input.materialId=s.materialId??((t=this.materials[0])==null?void 0:t.id)??null,this.source.productId&&(this.input.width=((e=s.format)==null?void 0:e.width)??this.input.width,this.input.height=((l=s.format)==null?void 0:l.height)??this.input.height),this.input.motifs=s.motifs??1,this.input.perMotif=s.perMotif??1,this.input.options={...s.options??{}},this.input.deliveryValue=s.deliveryValue??null)}catch(a){this.configurator=null,this.loadError=((u=(o=(n=(i=a==null?void 0:a.response)==null?void 0:i.data)==null?void 0:n.errors)==null?void 0:o[0])==null?void 0:u.code)??"error"}},setOption(t,e){if(t.type==="delivery"){this.input.deliveryValue=e;return}this.input.options={...this.input.options,[t.technicalName]:e}},async calculate(){this.isLoading=!0;try{const{data:t}=await this.httpClient.post("/_action/ld-mensura/test-calculate",{...this.source,customerGroupId:this.input.customerGroupId,language:this.language,configuration:{materialId:this.input.materialId,format:{width:this.input.width,height:this.input.height},motifs:this.input.motifs,perMotif:this.input.perMotif,options:this.input.options,deliveryValue:this.input.deliveryValue}},{headers:this.requestHeaders});this.response=t,this.input.options={...t.result.configuration.options},this.input.deliveryValue=t.result.configuration.deliveryValue??this.input.deliveryValue}catch{this.createNotificationError({message:this.$tc("ld-mensura.calculation.test.error")})}finally{this.isLoading=!1}},sourceLabel(t){return t.startsWith("option:")?t.slice(7):this.$tc(`ld-mensura.calculation.test.sources.${t}`)},num(t,e=2){return y(t,e)},money(t){return t==null?"–":`${y(t,2)} €`}}};export{v as default};
