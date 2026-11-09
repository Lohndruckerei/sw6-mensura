import{f as o}from"./margin-fIe6izj8.js";const d={template:`
<div class="lm-price-tables">
    <mt-card
        v-for="table in sortedTables"
        :key="table.id"
        :position-identifier="'lm-price-table-' + table.id"
        :title="table.name || $tc('ld-mensura.calculation.tables.untitled')"
    >
        <sw-container columns="repeat(auto-fit, minmax(220px, 1fr))" gap="0 30px">
            <mt-text-field v-model="table.name" :label="$tc('ld-mensura.calculation.tables.labelName')" :help-text="$tc('ld-mensura.calculation.tables.helpName')" :disabled="disabled || undefined" />
            <sw-single-select
                :value="table.materialId || '__all'"
                :options="materialOptions"
                :label="$tc('ld-mensura.calculation.assignment.labelMaterial')"
                :help-text="$tc('ld-mensura.calculation.tables.helpMaterial')"
                :disabled="disabled || undefined"
                @update:value="table.materialId = $event === '__all' ? null : $event"
            />
            <sw-single-select
                v-model:value="table.priceType"
                :options="typeOptions"
                :label="$tc('ld-mensura.calculation.tables.labelType')"
                :help-text="$tc('ld-mensura.calculation.tables.helpType')"
                :disabled="disabled || undefined"
            />
        </sw-container>
        <sw-entity-multi-id-select
            :ids="table.optionValueIds || []"
            :repository="valueRepository"
            label-property="name"
            :label="$tc('ld-mensura.calculation.tables.labelKey')"
            :help-text="$tc('ld-mensura.calculation.tables.helpKey')"
            :disabled="disabled || undefined"
            @update:ids="table.optionValueIds = $event"
        />
        <mt-switch v-model="table.active" :label="$tc('ld-mensura.general.labelActive')" :disabled="disabled || undefined" bordered />

        <div class="lm-table-rows">
            <div class="lm-table-rows__head" :class="{ 'is--single': !showPurchase }">
                <!-- Hilfetexte der Zeilen als Hilfe-Symbol im Spaltenkopf (DESIGN 7.9) -->
                <span>{{ $tc('ld-mensura.calculation.tables.quantity') }} <sw-help-text :text="$tc('ld-mensura.rowHelp.tableQuantity')" /></span>
                <span v-if="showPurchase">{{ $tc('ld-mensura.price.purchase') }} <sw-help-text :text="$tc('ld-mensura.rowHelp.purchase')" /></span>
                <span>{{ $tc('ld-mensura.price.sale') }} <sw-help-text :text="$tc('ld-mensura.rowHelp.sale')" /></span>
                <span />
            </div>
            <div v-for="row in sortedRows(table)" :key="row.id" class="lm-table-rows__row" :class="{ 'is--single': !showPurchase }">
                <mt-number-field v-model="row.quantity" number-type="int" :disabled="disabled || undefined" />
                <mt-number-field v-if="showPurchase" v-model="row.pricePurchase" :digits="4" :disabled="disabled || undefined" />
                <mt-number-field v-model="row.priceSale" :digits="4" :disabled="disabled || undefined" />
                <mt-button size="small" variant="secondary" square :aria-label="$tc('ld-mensura.components.remove')" :disabled="disabled || undefined" @click="table.rows.remove(row.id)">
                    <mt-icon name="regular-trash" size="14px" />
                </mt-button>
            </div>
            <p v-if="!table.rows || table.rows.length === 0" class="lm-muted">{{ $tc('ld-mensura.calculation.tables.emptyRows') }}</p>
        </div>

        <div class="lm-table-actions">
            <mt-button size="small" variant="secondary" :disabled="disabled || undefined" @click="addRow(table)">
                <mt-icon name="regular-plus-circle" size="14px" /> {{ $tc('ld-mensura.calculation.tables.addRow') }}
            </mt-button>
            <mt-button size="small" variant="secondary" @click="exportCsv(table)">
                <mt-icon name="regular-file-export" size="14px" /> {{ $tc('ld-mensura.calculation.tables.export') }}
            </mt-button>
            <mt-button size="small" variant="secondary" :disabled="disabled || undefined" @click="importTable = table">
                <mt-icon name="regular-upload" size="14px" /> {{ $tc('ld-mensura.calculation.tables.import') }}
            </mt-button>
            <mt-button size="small" variant="critical" :disabled="disabled || undefined" @click="tables.remove(table.id)">
                {{ $tc('ld-mensura.calculation.tables.remove') }}
            </mt-button>
        </div>
    </mt-card>

    <sw-empty-state v-if="sortedTables.length === 0" :title="$tc('ld-mensura.calculation.tables.emptyTitle')" :subline="$tc('ld-mensura.calculation.tables.emptySubline')" icon="regular-list" :absolute="false" />
    <mt-button variant="secondary" :disabled="disabled || undefined" @click="addTable">
        <mt-icon name="regular-plus-circle" size="14px" /> {{ $tc('ld-mensura.calculation.tables.add') }}
    </mt-button>

    <sw-modal v-if="importTable" :title="$tc('ld-mensura.calculation.tables.importTitle')" @modal-close="importTable = null">
        <p class="lm-muted lm-card-intro">{{ showPurchase ? $tc('ld-mensura.calculation.tables.importHelp') : $tc('ld-mensura.calculation.tables.importHelpSale') }}</p>
        <mt-textarea v-model="importText" :label="$tc('ld-mensura.calculation.tables.importLabel')" placeholder="100;8,00;11,00" />
        <p v-if="importError" class="lm-tiers__warn">{{ importError }}</p>
        <template #modal-footer>
            <mt-button size="small" variant="secondary" @click="importTable = null">{{ $tc('global.default.cancel') }}</mt-button>
            <mt-button size="small" variant="primary" @click="runImport">{{ $tc('ld-mensura.calculation.tables.importApply') }}</mt-button>
        </template>
    </sw-modal>
</div>`,inject:["repositoryFactory"],props:{tables:{type:Object,required:!0},calculationId:{type:String,required:!0},materials:{type:Array,required:!1,default:()=>[]},showPurchase:{type:Boolean,required:!1,default:!0},disabled:{type:Boolean,required:!1,default:!1}},data(){return{importTable:null,importText:"",importError:""}},computed:{tableRepository(){return this.repositoryFactory.create("ld_mensura_price_table")},rowRepository(){return this.repositoryFactory.create("ld_mensura_price_table_row")},valueRepository(){return this.repositoryFactory.create("ld_mensura_option_value")},sortedTables(){return[...this.tables].sort((e,t)=>(e.position??0)-(t.position??0))},materialOptions(){return[{value:"__all",label:this.$tc("ld-mensura.calculation.assignment.allMaterials")},...this.materials.map(e=>{var t;return{value:e.id,label:((t=e.translated)==null?void 0:t.name)||e.technicalName}})]},typeOptions(){return["piece","total"].map(e=>({value:e,label:this.$tc(`ld-mensura.calculation.tables.types.${e}`)}))}},methods:{sortedRows(e){return[...e.rows??[]].sort((t,l)=>(t.quantity??0)-(l.quantity??0))},addTable(){const e=this.tableRepository.create();Object.assign(e,{calculationId:this.calculationId,priceType:"piece",active:!0,position:this.tables.length+1,optionValueIds:[]}),this.tables.add(e)},addRow(e,t={}){const l=this.rowRepository.create(),a=this.sortedRows(e).pop();Object.assign(l,{priceTableId:e.id,quantity:a?a.quantity*2:100,pricePurchase:null,priceSale:null},t),e.rows.add(l)},exportCsv(e){const t=s=>s==null?"":o(s,4).replace(/\./g,""),l=this.showPurchase?"Auflage;EK;VK":"Auflage;VK",a=this.sortedRows(e).map(s=>this.showPurchase?`${s.quantity};${t(s.pricePurchase)};${t(s.priceSale)}`:`${s.quantity};${t(s.priceSale)}`),r=new Blob([`\uFEFF${l}
${a.join(`
`)}
`],{type:"text/csv;charset=utf-8"}),i=document.createElement("a");i.href=URL.createObjectURL(r),i.download=`${(e.name||"preistabelle").replace(/[^a-z0-9-_]+/gi,"_")}.csv`,i.click(),URL.revokeObjectURL(i.href)},parseNumber(e){const t=String(e??"").trim().replace(/\s|€/g,"");if(t==="")return null;const l=t.includes(",")?t.replace(/\./g,"").replace(",","."):t,a=Number(l);return Number.isFinite(a)?a:NaN},runImport(){const e=[];for(const l of this.importText.split(/\r?\n/)){const a=l.split(/[;\t]/).map(n=>n.trim());if(a.join("")===""||Number.isNaN(this.parseNumber(a[0]))||this.parseNumber(a[0])===null)continue;const r=this.parseNumber(a[0]),i=this.showPurchase&&a.length>=3?this.parseNumber(a[1]):null,s=this.parseNumber(a[this.showPurchase&&a.length>=3?2:1]);if([r,i,s].some(n=>Number.isNaN(n))){this.importError=this.$tc("ld-mensura.calculation.tables.importErrorLine",{line:l},0);return}e.push({quantity:Math.round(r),pricePurchase:i,priceSale:s})}if(!e.length){this.importError=this.$tc("ld-mensura.calculation.tables.importErrorEmpty");return}const t=this.importTable;[...t.rows].forEach(l=>t.rows.remove(l.id)),e.forEach(l=>this.addRow(t,l)),this.importTable=null,this.importText="",this.importError=""}}};export{d as default};
