const i={template:`
<sw-modal
    :title="value.translated?.name || value.name || $tc('ld-mensura.option.value.newTitle')"
    variant="large"
    @modal-close="$emit('close')"
>
    <sw-container columns="repeat(auto-fit, minmax(220px, 1fr))" gap="0 30px">
        <mt-text-field v-model="value.name" :label="$tc('ld-mensura.option.value.labelName')" :help-text="$tc('ld-mensura.option.value.helpName')" :disabled="disabled || undefined" required />
        <mt-text-field v-model="value.technicalName" :label="$tc('ld-mensura.general.labelTechnicalName')" :help-text="$tc('ld-mensura.option.value.helpTechnicalName')" :disabled="disabled || undefined" required />
        <mt-text-field v-model="value.code" :label="$tc('ld-mensura.option.value.labelCode')" :help-text="$tc('ld-mensura.option.value.helpCode')" :disabled="disabled || undefined" />
        <mt-number-field v-model="value.position" number-type="int" :label="$tc('ld-mensura.general.labelPosition')" :help-text="$tc('ld-mensura.general.helpPosition')" :disabled="disabled || undefined" />
    </sw-container>
    <mt-textarea v-model="value.description" :label="$tc('ld-mensura.option.value.labelDescription')" :help-text="$tc('ld-mensura.option.value.helpDescription')" :disabled="disabled || undefined" />
    <sw-multi-tag-select
        :value="value.badges || []"
        :label="$tc('ld-mensura.material.detail.labelBadges')"
        :help-text="$tc('ld-mensura.option.value.helpBadges')"
        :placeholder="$tc('ld-mensura.material.detail.placeholderBadges')"
        :disabled="disabled || undefined"
        @update:value="value.badges = $event.length ? $event : null"
    />

    <template v-if="groupType === 'delivery'">
        <h4 class="lm-tiers__title">{{ $tc('ld-mensura.option.value.headingDelivery') }}</h4>
        <sw-container columns="repeat(auto-fit, minmax(220px, 1fr))" gap="0 30px">
            <mt-number-field v-model="value.deliveryDays" number-type="int" :label="$tc('ld-mensura.option.value.labelDeliveryDays')" :help-text="$tc('ld-mensura.option.value.helpDeliveryDays')" :disabled="disabled || undefined" />
            <mt-number-field :model-value="availability.maxQuantity ?? null" number-type="int" :label="$tc('ld-mensura.option.value.labelMaxQuantity')" :help-text="$tc('ld-mensura.option.value.helpMaxQuantity')" :disabled="disabled || undefined" @update:model-value="setAvailability('maxQuantity', $event)" />
            <mt-number-field :model-value="availability.maxArea ?? null" :digits="2" :label="$tc('ld-mensura.option.value.labelMaxArea')" :help-text="$tc('ld-mensura.option.value.helpMaxArea')" :disabled="disabled || undefined" @update:model-value="setAvailability('maxArea', $event)" />
        </sw-container>
        <sw-entity-multi-id-select
            :ids="availability.materialIds || []"
            :repository="materialRepository"
            label-property="name"
            :label="$tc('ld-mensura.option.value.labelMaterials')"
            :help-text="$tc('ld-mensura.option.value.helpMaterials')"
            :disabled="disabled || undefined"
            @update:ids="setAvailability('materialIds', $event)"
        />
    </template>

    <template v-if="groupType === 'format'">
        <h4 class="lm-tiers__title">{{ $tc('ld-mensura.option.value.headingFormat') }}</h4>
        <sw-container columns="repeat(auto-fit, minmax(220px, 1fr))" gap="0 30px">
            <mt-number-field v-model="value.width" number-type="int" :label="$tc('ld-mensura.option.value.labelWidth')" :help-text="$tc('ld-mensura.option.value.helpFormat')" :disabled="disabled || undefined" />
            <mt-number-field v-model="value.height" number-type="int" :label="$tc('ld-mensura.option.value.labelHeight')" :help-text="$tc('ld-mensura.option.value.helpFormat')" :disabled="disabled || undefined" />
        </sw-container>
    </template>

    <h4 class="lm-tiers__title">{{ $tc('ld-mensura.option.value.headingAdditions') }}</h4>
    <sw-container columns="repeat(auto-fit, minmax(180px, 1fr))" gap="0 30px">
        <mt-number-field v-model="value.widthAdd" number-type="int" :label="$tc('ld-mensura.option.value.labelWidthAdd')" :help-text="$tc('ld-mensura.option.value.helpAdd')" :disabled="disabled || undefined" />
        <mt-number-field v-model="value.heightAdd" number-type="int" :label="$tc('ld-mensura.option.value.labelHeightAdd')" :help-text="$tc('ld-mensura.option.value.helpAdd')" :disabled="disabled || undefined" />
        <mt-number-field v-model="value.widthFactor" :digits="3" :label="$tc('ld-mensura.option.value.labelWidthFactor')" :help-text="$tc('ld-mensura.option.value.helpFactor')" :disabled="disabled || undefined" />
        <mt-number-field v-model="value.heightFactor" :digits="3" :label="$tc('ld-mensura.option.value.labelHeightFactor')" :help-text="$tc('ld-mensura.option.value.helpFactor')" :disabled="disabled || undefined" />
    </sw-container>

    <h4 class="lm-tiers__title">{{ $tc('ld-mensura.option.value.headingFlow') }}</h4>
    <sw-entity-single-select
        v-model:value="value.optionSetId"
        entity="ld_mensura_option_set"
        label-property="name"
        :label="$tc('ld-mensura.material.detail.labelOptionSet')"
        :help-text="$tc('ld-mensura.option.value.helpOptionSet')"
        :disabled="disabled || undefined"
    />
    <sw-entity-multi-id-select
        :ids="value.exclusions || []"
        :repository="valueRepository"
        :criteria="exclusionCriteria"
        label-property="name"
        :label="$tc('ld-mensura.option.value.labelExclusions')"
        :help-text="$tc('ld-mensura.option.value.helpExclusions')"
        :disabled="disabled || undefined"
        @update:ids="value.exclusions = $event.length ? $event : null"
    />
    <sw-media-field v-model:value="value.mediaId" :label="$tc('ld-mensura.general.labelImage')" :disabled="disabled || undefined" />

    <template #modal-footer>
        <mt-button size="small" variant="secondary" @click="$emit('close')">{{ $tc('ld-mensura.option.value.buttonDone') }}</mt-button>
    </template>
</sw-modal>`,inject:["repositoryFactory"],emits:["close"],props:{value:{type:Object,required:!0},groupType:{type:String,required:!0},disabled:{type:Boolean,required:!1,default:!1}},computed:{materialRepository(){return this.repositoryFactory.create("ld_mensura_material")},valueRepository(){return this.repositoryFactory.create("ld_mensura_option_value")},exclusionCriteria(){const l=new Shopware.Data.Criteria(1,25);return l.addFilter(Shopware.Data.Criteria.not("AND",[Shopware.Data.Criteria.equals("id",this.value.id)])),l.addAssociation("optionGroup"),l},availability(){return this.value.availability??{}}},methods:{setAvailability(l,t){const e={...this.availability,[l]:t};Object.keys(e).forEach(a=>{(e[a]===null||e[a]===void 0||Array.isArray(e[a])&&e[a].length===0)&&delete e[a]}),this.value.availability=Object.keys(e).length?e:null}}};export{i as default};
