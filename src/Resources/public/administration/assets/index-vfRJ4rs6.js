const l=["per_area","per_piece","per_motif","per_order","per_perimeter","percent","factor","base_area"],n={template:`
<div class="lm-components">
    <div v-if="rows.length === 0" class="lm-muted lm-components__empty">
        {{ emptyText || $tc('ld-mensura.components.empty') }}
    </div>
    <div v-for="(row, index) in rows" :key="index" class="lm-components__row" :class="{ 'is--single': !showPurchase }">
        <sw-single-select
            :value="row.type"
            :options="typeOptions"
            :label="index === 0 ? $tc('ld-mensura.components.type') : undefined"
            :help-text="index === 0 ? $tc('ld-mensura.rowHelp.componentType') : undefined"
            :disabled="disabled || undefined"
            @update:value="update(index, 'type', $event)"
        />
        <mt-number-field
            v-if="showPurchase"
            :model-value="row.purchase"
            :label="index === 0 ? $tc('ld-mensura.price.purchase') : undefined"
            :help-text="index === 0 ? $tc('ld-mensura.rowHelp.purchase') : undefined"
            :digits="4"
            :disabled="disabled || undefined"
            @update:model-value="update(index, 'purchase', $event)"
        />
        <mt-number-field
            :model-value="row.sale"
            :label="index === 0 ? $tc('ld-mensura.price.sale') : undefined"
            :help-text="index === 0 ? $tc('ld-mensura.rowHelp.sale') : undefined"
            :digits="4"
            :disabled="disabled || undefined"
            @update:model-value="update(index, 'sale', $event)"
        />
        <mt-button
            class="lm-components__remove"
            size="small"
            variant="secondary"
            square
            :aria-label="$tc('ld-mensura.components.remove')"
            :disabled="disabled || undefined"
            @click="remove(index)"
        >
            <mt-icon name="regular-trash" size="14px" />
        </mt-button>
    </div>
    <mt-button size="small" variant="secondary" :disabled="disabled || undefined" @click="add">
        <mt-icon name="regular-plus-circle" size="14px" />
        {{ $tc('ld-mensura.components.add') }}
    </mt-button>
    <p class="lm-muted lm-components__hint">{{ $tc('ld-mensura.components.hint') }}</p>
</div>`,emits:["update:value"],props:{value:{type:Array,required:!1,default:null},showPurchase:{type:Boolean,required:!1,default:!0},disabled:{type:Boolean,required:!1,default:!1},emptyText:{type:String,required:!1,default:""}},computed:{rows(){return Array.isArray(this.value)?this.value:[]},typeOptions(){return l.map(e=>({value:e,label:this.$tc(`ld-mensura.components.types.${e}`)}))}},methods:{emit(e){this.$emit("update:value",e.length?e:null)},update(e,d,t){const s=this.rows.map(a=>({...a}));s[e][d]=t,this.emit(s)},add(){this.emit([...this.rows.map(e=>({...e})),{type:"per_piece",purchase:null,sale:null}])},remove(e){this.emit(this.rows.filter((d,t)=>t!==e))}}};export{l as COMPONENT_TYPES,n as default};
