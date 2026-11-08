import{f as r,m as i,r as n}from"./margin-fIe6izj8.js";const{Criteria:l}=Shopware.Data,m={template:`
<lm-entity-list
    entity="ld_mensura_material"
    area="material"
    :title="$tc('ld-mensura.areas.material.title')"
    icon="regular-layer-group"
    snippet="ld-mensura.material.list"
    info-snippet="ld-mensura.material.info"
    detail-route="ld.mensura.materialDetail"
    create-route="ld.mensura.materialCreate"
    default-sort="position"
    :status-of="statusOf"
    :columns="columns"
    :filters="filters"
    :associations="['calculations']"
    :clone-overwrites="cloneOverwrites"
>
    <template #column-priceSale="{ item }">
        <span class="lm-num">{{ priceLabel(item) }}</span>
    </template>
    <template #column-margin="{ item }">
        <lm-status v-if="marginOf(item)" :state="marginOf(item).state" :label="fmt(marginOf(item).margin, 1) + ' %'" />
        <span v-else class="lm-muted">–</span>
    </template>
    <template #column-calculations="{ item }">
        <lm-chip v-for="calculation in item.calculations" :key="calculation.id" color="c1" icon="regular-euro" :label="calculation.name" />
        <span v-if="!item.calculations || item.calculations.length === 0" class="lm-muted">–</span>
    </template>
    <template #column-active="{ item }">
        <lm-status v-bind="statusOf(item)" />
    </template>
    <template #tile="{ item }">
        <div><lm-chip v-for="calculation in item.calculations" :key="calculation.id" color="c1" icon="regular-euro" :label="calculation.name" /></div>
        <div class="lm-tile__meta">
            <span>{{ priceLabel(item) }}</span>
            <span v-if="item.weightPerSqm">{{ fmt(item.weightPerSqm, 2) }} kg/m²</span>
        </div>
    </template>
</lm-entity-list>`,inject:["acl"],metaInfo(){return{title:this.$createTitle()}},computed:{showPurchase(){return this.acl.can("ld_mensura.purchase")},filters(){return[{name:"all",label:this.$tc("ld-mensura.filter.all"),icon:"regular-layer-group",variant:"all"},{name:"active",label:this.$tc("ld-mensura.filter.active"),icon:"regular-checkmark-s",variant:"ok",filters:()=>[l.equals("active",!0)]},{name:"noPrice",label:this.$tc("ld-mensura.filter.noPrice"),icon:"regular-exclamation-triangle",variant:"warn",filters:()=>[l.equals("priceSale",null)]},{name:"inactive",label:this.$tc("ld-mensura.filter.inactive"),icon:"regular-ban",variant:"off",filters:()=>[l.equals("active",!1)]}]}},methods:{columns(e){return[{property:"technicalName",label:"ld-mensura.material.list.columnName",routerLink:"ld.mensura.materialDetail",primary:!0,allowResize:!0},...e==="a"?[{property:"name",label:"ld-mensura.material.list.columnLabel",allowResize:!0}]:[],{property:"priceSale",label:"ld-mensura.material.list.columnPrice",align:"right",allowResize:!0},...this.showPurchase?[{property:"margin",label:"ld-mensura.material.list.columnMargin",sortable:!1,allowResize:!0}]:[],{property:"calculations",label:"ld-mensura.material.list.columnCalculations",sortable:!1,allowResize:!0},{property:"active",label:"ld-mensura.general.columnStatus",allowResize:!0}]},statusOf(e){return e.active?e.priceSale===null||e.priceSale===void 0?{state:"warn",label:this.$tc("ld-mensura.material.state.noPrice")}:{state:"ok",label:this.$tc("lm-design.states.ok")}:{state:"off",label:this.$tc("lm-design.states.off")}},cloneOverwrites(){return{active:!1}},marginOf(e){var a,t;return i(e.pricePurchase,e.priceSale,n(((t=(a=e.calculations)==null?void 0:a.first)==null?void 0:t.call(a))??null))},priceLabel(e){return e.priceSale===null||e.priceSale===void 0?"–":`${r(e.priceSale)} €/m²`},fmt(e,a){return r(e,a)}}};export{m as default};
