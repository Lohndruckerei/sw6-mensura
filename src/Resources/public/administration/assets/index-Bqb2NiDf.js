const i={template:`
<div class="lm-codes">
    <h4 class="lm-tiers__title">{{ $tc('ld-mensura.supplierCodes.title') }}</h4>
    <p class="lm-muted">{{ $tc('ld-mensura.supplierCodes.help') }}</p>
    <div v-for="(row, index) in rows" :key="index" class="lm-codes__row">
        <sw-entity-single-select
            v-if="hasLieferlotse"
            :value="row.supplierId"
            entity="ld_supplier"
            label-property="name"
            :label="index === 0 ? $tc('ld-mensura.general.labelSupplier') : undefined"
            :disabled="disabled || undefined"
            @update:value="update(index, 'supplierId', $event)"
        />
        <mt-text-field
            v-else
            :model-value="row.supplierId"
            :label="index === 0 ? $tc('ld-mensura.general.labelSupplierId') : undefined"
            :disabled="disabled || undefined"
            @update:model-value="update(index, 'supplierId', $event)"
        />
        <mt-text-field
            :model-value="row.code"
            :label="index === 0 ? $tc('ld-mensura.supplierCodes.code') : undefined"
            :placeholder="$tc('ld-mensura.supplierCodes.placeholder')"
            :disabled="disabled || undefined"
            @update:model-value="update(index, 'code', $event)"
        />
        <mt-button size="small" variant="secondary" square :aria-label="$tc('ld-mensura.components.remove')" :disabled="disabled || undefined" @click="remove(index)">
            <mt-icon name="regular-trash" size="14px" />
        </mt-button>
    </div>
    <mt-button size="small" variant="secondary" :disabled="disabled || undefined" @click="add">
        <mt-icon name="regular-plus-circle" size="14px" />
        {{ $tc('ld-mensura.supplierCodes.add') }}
    </mt-button>
</div>`,emits:["update:value"],props:{value:{type:Array,required:!1,default:null},hasLieferlotse:{type:Boolean,required:!1,default:!1},disabled:{type:Boolean,required:!1,default:!1}},computed:{rows(){return Array.isArray(this.value)?this.value:[]}},methods:{emit(e){this.$emit("update:value",e.length?e:null)},update(e,d,l){const a=this.rows.map(s=>({...s}));a[e][d]=l,this.emit(a)},add(){this.emit([...this.rows.map(e=>({...e})),{supplierId:null,code:""}])},remove(e){this.emit(this.rows.filter((d,l)=>l!==e))}}};export{i as default};
