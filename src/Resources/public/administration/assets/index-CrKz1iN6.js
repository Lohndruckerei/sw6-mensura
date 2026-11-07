const{Criteria:r}=Shopware.Data,s={standard:"regular-sliders-v",delivery:"regular-truck",format:"regular-crop"},i={standard:"c1",delivery:"c3",format:"c2"},o={template:`
<lm-entity-list
    entity="ld_mensura_option_group"
    area="option"
    :title="$tc('ld-mensura.areas.option.title')"
    icon="regular-sliders-v"
    snippet="ld-mensura.option.list"
    detail-route="ld.mensura.optionDetail"
    create-route="ld.mensura.optionCreate"
    default-sort="position"
    :columns="columns"
    :filters="filters"
    :associations="['values', 'setGroups.optionSet']"
>
    <template #column-type="{ item }">
        <lm-chip :color="typeColor(item.type)" :icon="typeIcon(item.type)" :label="$tc('ld-mensura.option.types.' + item.type)" />
    </template>
    <template #column-values="{ item }">
        <span class="lm-num">{{ item.values ? item.values.length : 0 }}</span>
        <span class="lm-muted lm-option-list__preview">{{ preview(item) }}</span>
    </template>
    <template #column-sets="{ item }">
        <lm-chip v-for="row in item.setGroups" :key="row.id" color="c4" icon="regular-list" :label="row.optionSet?.name || ''" />
        <span v-if="!item.setGroups || item.setGroups.length === 0" class="lm-muted">–</span>
    </template>
    <template #column-required="{ item }">
        {{ item.required ? $tc('ld-mensura.general.yes') : $tc('ld-mensura.general.no') }}
    </template>
    <template #tile="{ item }">
        <div><lm-chip :color="typeColor(item.type)" :icon="typeIcon(item.type)" :label="$tc('ld-mensura.option.types.' + item.type)" /></div>
        <div class="lm-tile__meta"><span>{{ $tc('ld-mensura.option.list.valueCount', { count: item.values ? item.values.length : 0 }, item.values ? item.values.length : 0) }}</span><span>{{ preview(item) }}</span></div>
    </template>
</lm-entity-list>`,computed:{filters(){const e=t=>()=>[r.equals("type",t)];return[{name:"all",label:this.$tc("ld-mensura.filter.all"),icon:"regular-sliders-v",variant:"all"},{name:"standard",label:this.$tc("ld-mensura.option.types.standard"),icon:"regular-sliders-v",variant:"ok",filters:e("standard")},{name:"delivery",label:this.$tc("ld-mensura.option.types.delivery"),icon:"regular-truck",variant:"ok",filters:e("delivery")},{name:"format",label:this.$tc("ld-mensura.option.types.format"),icon:"regular-crop",variant:"ok",filters:e("format")}]}},methods:{columns(e){const t=[{property:"technicalName",label:"ld-mensura.option.list.columnName",routerLink:"ld.mensura.optionDetail",primary:!0,allowResize:!0}];return e==="a"&&t.push({property:"name",label:"ld-mensura.material.list.columnLabel",allowResize:!0}),[...t,{property:"type",label:"ld-mensura.option.list.columnType",allowResize:!0},{property:"values",label:"ld-mensura.option.list.columnValues",sortable:!1,allowResize:!0},{property:"sets",label:"ld-mensura.option.list.columnSets",sortable:!1,allowResize:!0},{property:"required",label:"ld-mensura.option.list.columnRequired",allowResize:!0}]},typeIcon(e){return s[e]??"regular-sliders-v"},typeColor(e){return i[e]??"c1"},preview(e){const t=[...e.values??[]].sort((l,a)=>(l.position??0)-(a.position??0)).map(l=>{var a;return((a=l.translated)==null?void 0:a.name)||l.name||l.technicalName});return t.slice(0,4).join(", ")+(t.length>4?" …":"")}}};export{o as default};
