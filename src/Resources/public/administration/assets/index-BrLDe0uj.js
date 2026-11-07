const{Criteria:s}=Shopware.Data,n={template:`
<lm-entity-list
    entity="ld_mensura_option_set"
    area="optionSet"
    :title="$tc('ld-mensura.areas.optionSet.title')"
    icon="regular-list"
    snippet="ld-mensura.optionSet.list"
    detail-route="ld.mensura.optionSetDetail"
    create-route="ld.mensura.optionSetCreate"
    default-sort="name"
    :name-of="nameOf"
    :columns="columns"
    :filters="filters"
    :associations="['groups.optionGroup', 'calculations', 'materials', 'optionValues']"
>
    <template #column-groups="{ item }">
        <lm-chip v-for="row in sortedGroups(item)" :key="row.id" color="c1" icon="regular-sliders-v" :label="row.optionGroup?.translated?.name || row.optionGroup?.technicalName || ''" />
    </template>
    <template #column-usage="{ item }">
        <span class="lm-muted">{{ usage(item) }}</span>
    </template>
    <template #tile="{ item }">
        <div><lm-chip v-for="row in sortedGroups(item)" :key="row.id" color="c1" icon="regular-sliders-v" :label="row.optionGroup?.translated?.name || row.optionGroup?.technicalName || ''" /></div>
        <div class="lm-tile__meta"><span>{{ usage(item) }}</span></div>
    </template>
</lm-entity-list>`,computed:{filters(){const e=()=>[s.equals("calculations.id",null),s.equals("materials.id",null),s.equals("optionValues.id",null)];return[{name:"all",label:this.$tc("ld-mensura.filter.all"),icon:"regular-list",variant:"all"},{name:"unused",label:this.$tc("ld-mensura.optionSet.list.filterUnused"),icon:"regular-exclamation-triangle",variant:"warn",filters:e}]}},methods:{nameOf(e){return e.name||e.technicalName},columns(e){return[{property:"technicalName",label:"ld-mensura.optionSet.list.columnName",routerLink:"ld.mensura.optionSetDetail",primary:!0,allowResize:!0},...e==="a"?[{property:"name",label:"ld-mensura.material.list.columnLabel",allowResize:!0}]:[],{property:"groups",label:"ld-mensura.optionSet.list.columnGroups",sortable:!1,allowResize:!0},{property:"usage",label:"ld-mensura.optionSet.list.columnUsage",sortable:!1,allowResize:!0}]},sortedGroups(e){return[...e.groups??[]].sort((t,a)=>(t.position??0)-(a.position??0))},usage(e){const t=[],a=(l,r)=>{const o=(l==null?void 0:l.length)??0;o&&t.push(this.$tc(`ld-mensura.optionSet.usage.${r}`,{count:o},o))};return a(e.calculations,"calculations"),a(e.materials,"materials"),a(e.optionValues,"values"),t.length?t.join(" · "):this.$tc("ld-mensura.optionSet.usage.none")}}};export{n as default};
