import{D as t,f as s,s as l,m as i}from"./margin-fIe6izj8.js";const u={template:`
<div class="lm-price-pair">
    <div class="lm-price-pair__fields" :class="{ 'is--single': !showPurchase }">
        <mt-number-field
            v-if="showPurchase"
            :model-value="purchase"
            :label="label + ' · ' + $tc('ld-mensura.price.purchase')"
            :help-text="helpPurchase || $tc('ld-mensura.price.helpPurchase')"
            :digits="digits"
            :disabled="disabled || undefined"
            :placeholder="unit"
            @update:model-value="$emit('update:purchase', $event)"
        />
        <mt-number-field
            :model-value="sale"
            :label="label + ' · ' + $tc('ld-mensura.price.sale')"
            :help-text="helpSale || $tc('ld-mensura.price.helpSale')"
            :digits="digits"
            :disabled="disabled || undefined"
            :placeholder="unit"
            @update:model-value="$emit('update:sale', $event)"
        />
    </div>
    <div v-if="showPurchase && (info || suggestion !== null)" class="lm-price-pair__margin">
        <template v-if="info">
            <lm-status :state="info.state" :label="statusLabel" />
            <span class="lm-muted">{{ $tc('ld-mensura.price.marginLine', { margin: fmt(info.margin, 1), markup: fmt(info.markup, 1) }) }}</span>
        </template>
        <mt-button
            v-if="suggestion !== null && suggestion !== Number(sale)"
            size="small"
            variant="secondary"
            :disabled="disabled || undefined"
            @click="$emit('update:sale', suggestion)"
        >
            {{ $tc('ld-mensura.price.applySuggestion', { price: fmt(suggestion, digits) }) }}
        </mt-button>
    </div>
</div>`,emits:["update:purchase","update:sale"],props:{purchase:{type:Number,required:!1,default:null},sale:{type:Number,required:!1,default:null},label:{type:String,required:!0},unit:{type:String,required:!1,default:""},helpPurchase:{type:String,required:!1,default:""},helpSale:{type:String,required:!1,default:""},rule:{type:Object,required:!1,default:()=>t},showPurchase:{type:Boolean,required:!1,default:!0},disabled:{type:Boolean,required:!1,default:!1},digits:{type:Number,required:!1,default:2}},computed:{info(){return i(this.purchase,this.sale,this.rule)},suggestion(){return l(this.purchase,this.rule)},statusLabel(){if(!this.info)return"";const e={ok:"marginOk",warn:"marginLow",err:"marginNegative"};return this.$tc(`ld-mensura.price.${e[this.info.state]}`)}},methods:{fmt(e,a){return s(e,a)}}};export{u as default};
