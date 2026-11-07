import{f as t}from"./margin-CYtqVi4K.js";const{Criteria:a}=Shopware.Data,r={template:`
<lm-entity-list
    entity="ld_mensura_calculation"
    area="calculation"
    :title="$tc('ld-mensura.areas.calculation.title')"
    icon="regular-euro"
    snippet="ld-mensura.calculation.list"
    detail-route="ld.mensura.calculationDetail"
    create-route="ld.mensura.calculationCreate"
    default-sort="name"
    :name-of="nameOf"
    :status-of="statusOf"
    :columns="columns"
    :filters="filters"
    :associations="['materials']"
    :clone-overwrites="cloneOverwrites"
>
    <template #column-unit="{ item }">
        {{ $tc('ld-mensura.calculation.units.' + item.unit) }}
    </template>
    <template #column-materials="{ item }">
        <lm-chip v-for="material in item.materials" :key="material.id" color="c2" icon="regular-layer-group" :label="material.translated?.name || material.technicalName" />
        <span v-if="!item.materials || item.materials.length === 0" class="lm-muted">–</span>
    </template>
    <template #column-minimumPriceSale="{ item }">
        <span class="lm-num">{{ item.minimumPriceSale === null ? '–' : fmt(item.minimumPriceSale) + ' €' }}</span>
    </template>
    <template #column-saleRule="{ item }">
        {{ $tc('ld-mensura.saleRule.' + item.saleRule) }} {{ item.saleRule === 'free' ? '' : fmt(item.saleRuleValue, 1) }}
    </template>
    <template #column-active="{ item }">
        <lm-status v-bind="statusOf(item)" />
    </template>
    <template #tile="{ item }">
        <div><lm-chip v-for="material in item.materials" :key="material.id" color="c2" icon="regular-layer-group" :label="material.translated?.name || material.technicalName" /></div>
        <div class="lm-tile__meta">
            <span>{{ $tc('ld-mensura.calculation.units.' + item.unit) }}</span>
            <span v-if="item.minimumPriceSale !== null">{{ $tc('ld-mensura.calculation.list.minimumShort', { price: fmt(item.minimumPriceSale) }) }}</span>
        </div>
    </template>
</lm-entity-list>`,computed:{filters(){return[{name:"all",label:this.$tc("ld-mensura.filter.all"),icon:"regular-euro",variant:"all"},{name:"active",label:this.$tc("ld-mensura.filter.active"),icon:"regular-checkmark-s",variant:"ok",filters:()=>[a.equals("active",!0)]},{name:"noMaterial",label:this.$tc("ld-mensura.calculation.list.filterNoMaterial"),icon:"regular-exclamation-triangle",variant:"warn",filters:()=>[a.equals("materials.id",null)]},{name:"inactive",label:this.$tc("ld-mensura.filter.inactive"),icon:"regular-ban",variant:"off",filters:()=>[a.equals("active",!1)]}]}},methods:{nameOf(e){return e.name||e.technicalName},statusOf(e){return e.active?!e.materials||e.materials.length===0?{state:"warn",label:this.$tc("ld-mensura.calculation.list.filterNoMaterial")}:{state:"ok",label:this.$tc("lm-design.states.ok")}:{state:"off",label:this.$tc("lm-design.states.off")}},cloneOverwrites(e){return{name:`${e.name} (${this.$tc("ld-mensura.general.copySuffix")})`,active:!1}},columns(e){return[{property:"technicalName",label:"ld-mensura.calculation.list.columnName",routerLink:"ld.mensura.calculationDetail",primary:!0,allowResize:!0},...e==="a"?[{property:"name",label:"ld-mensura.material.list.columnLabel",allowResize:!0}]:[],{property:"unit",label:"ld-mensura.calculation.list.columnUnit",allowResize:!0},{property:"materials",label:"ld-mensura.areas.material.title",sortable:!1,allowResize:!0},{property:"minimumPriceSale",label:"ld-mensura.calculation.list.columnMinimum",align:"right",allowResize:!0},{property:"saleRule",label:"ld-mensura.calculation.list.columnSaleRule",allowResize:!0},{property:"active",label:"ld-mensura.general.columnStatus",allowResize:!0}]},fmt(e,l=2){return t(e,l)}}};export{r as default};
