const o=["none","quantity","total_area","piece_area","total_price"];function c(e,s){var i,n,a,d;const t=()=>({basis:"none",steps:[]});if(!e)return{purchase:t(),sale:t()};if(Array.isArray(e)){const l=u=>({basis:s||"none",steps:e.map(r=>r.discount!==void 0&&r.discount!==null?{from:r.from,discount:r.discount}:{from:r.from,price:r[u]??r.price??null})});return{purchase:l("purchase"),sale:l("sale")}}return{purchase:{basis:((i=e.purchase)==null?void 0:i.basis)??"none",steps:[...((n=e.purchase)==null?void 0:n.steps)??[]]},sale:{basis:((a=e.sale)==null?void 0:a.basis)??"none",steps:[...((d=e.sale)==null?void 0:d.steps)??[]]}}}const p={template:`
<div class="lm-tiers">
    <div v-for="side in sides" :key="side" class="lm-tiers__side">
        <h4 class="lm-tiers__title">{{ $tc('ld-mensura.tiers.' + side) }}</h4>
        <sw-single-select
            :value="tiers[side].basis"
            :options="basisOptions"
            :label="$tc('ld-mensura.tiers.basis')"
            :help-text="$tc('ld-mensura.tiers.helpBasis')"
            :disabled="disabled || undefined"
            @update:value="setBasis(side, $event)"
        />
        <template v-if="tiers[side].basis !== 'none'">
            <div v-for="(step, index) in tiers[side].steps" :key="index" class="lm-tiers__row">
                <mt-number-field
                    :model-value="step.from"
                    :label="index === 0 ? $tc('ld-mensura.tiers.from') : undefined"
                    :digits="2"
                    :disabled="disabled || undefined"
                    @update:model-value="setStep(side, index, 'from', $event)"
                />
                <sw-single-select
                    :value="step.discount !== undefined ? 'discount' : 'price'"
                    :options="modeOptions"
                    :label="index === 0 ? $tc('ld-mensura.tiers.mode') : undefined"
                    :disabled="disabled || undefined"
                    @update:value="setMode(side, index, $event)"
                />
                <mt-number-field
                    :model-value="step.discount !== undefined ? step.discount : step.price"
                    :label="index === 0 ? $tc('ld-mensura.tiers.value') : undefined"
                    :digits="4"
                    :disabled="disabled || undefined"
                    @update:model-value="setStep(side, index, step.discount !== undefined ? 'discount' : 'price', $event)"
                />
                <mt-button
                    size="small"
                    variant="secondary"
                    square
                    :aria-label="$tc('ld-mensura.tiers.remove')"
                    :disabled="disabled || undefined"
                    @click="removeStep(side, index)"
                >
                    <mt-icon name="regular-trash" size="14px" />
                </mt-button>
            </div>
            <mt-button size="small" variant="secondary" :disabled="disabled || undefined" @click="addStep(side)">
                <mt-icon name="regular-plus-circle" size="14px" />
                {{ $tc('ld-mensura.tiers.add') }}
            </mt-button>
            <p v-if="jumps[side]" class="lm-tiers__warn">
                <mt-icon name="regular-exclamation-triangle" size="14px" />
                {{ $tc('ld-mensura.tiers.warnOrder') }}
            </p>
        </template>
    </div>
</div>`,emits:["update:value"],props:{value:{type:[Object,Array],required:!1,default:null},legacyBasis:{type:String,required:!1,default:null},showPurchase:{type:Boolean,required:!1,default:!0},disabled:{type:Boolean,required:!1,default:!1}},computed:{tiers(){return c(this.value,this.legacyBasis)},sides(){return this.showPurchase?["sale","purchase"]:["sale"]},basisOptions(){return o.map(e=>({value:e,label:this.$tc(`ld-mensura.tiers.bases.${e}`)}))},modeOptions(){return[{value:"price",label:this.$tc("ld-mensura.tiers.modePrice")},{value:"discount",label:this.$tc("ld-mensura.tiers.modeDiscount")}]},jumps(){const e=s=>s.some((t,i)=>i>0&&(Number(t.from)<=Number(s[i-1].from)||t.price!==void 0&&s[i-1].price!==void 0&&Number(t.price)>Number(s[i-1].price)));return{sale:e(this.tiers.sale.steps),purchase:e(this.tiers.purchase.steps)}}},methods:{emit(e){const s=t=>t.basis==="none";this.$emit("update:value",s(e.sale)&&s(e.purchase)?null:e)},clone(){return JSON.parse(JSON.stringify(this.tiers))},setBasis(e,s){const t=this.clone();t[e].basis=s,s==="none"?t[e].steps=[]:t[e].steps.length===0&&t[e].steps.push({from:0,price:null}),this.emit(t)},setStep(e,s,t,i){const n=this.clone();n[e].steps[s][t]=i,this.emit(n)},setMode(e,s,t){const i=this.clone(),n=i[e].steps[s],a=n.discount??n.price??null;i[e].steps[s]=t==="discount"?{from:n.from,discount:a}:{from:n.from,price:a},this.emit(i)},addStep(e){const s=this.clone(),t=s[e].steps[s[e].steps.length-1];s[e].steps.push({from:t?Number(t.from)+1:0,price:null}),this.emit(s)},removeStep(e,s){const t=this.clone();t[e].steps.splice(s,1),this.emit(t)}}};export{o as BASES,p as default,c as normalizeTiers};
