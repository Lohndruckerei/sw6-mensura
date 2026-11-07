const t={template:`
<sw-page class="lm-detail" :class="lmDesignClass">
    <template #smart-bar-header>
        <h2>{{ title }}</h2>
    </template>

    <template #smart-bar-actions>
        <lm-design-switch />
        <mt-button variant="secondary" size="default" :disabled="isLoading || undefined" @click="$emit('cancel')">
            {{ $tc('global.default.cancel') }}
        </mt-button>
        <sw-button-process
            :process-success="saveSuccess"
            v-tooltip.bottom="tooltipSave"
            :is-loading="isLoading"
            :disabled="isLoading || !canEdit || undefined"
            variant="primary"
            @update:process-success="$emit('update:saveSuccess', $event)"
            @click.prevent="$emit('save')"
        >
            {{ $tc('global.default.save') }}
        </sw-button-process>
    </template>

    <template #content>
        <sw-card-view :class="lmDesignClass">
            <template v-if="!ready">
                <sw-skeleton variant="detail-bold" />
                <sw-skeleton />
            </template>
            <template v-else>
                <div v-if="lmDesign !== 'a'" class="lm-header" :class="'lm-header--' + lmDesign">
                    <span class="lm-header__icon"><mt-icon :name="icon" size="24px" /></span>
                    <div class="lm-header__main">
                        <h3 class="lm-header__title">
                            {{ title }}
                            <lm-status v-if="status" v-bind="status" />
                        </h3>
                        <div class="lm-header__meta"><slot name="header-meta" /></div>
                    </div>
                    <div v-if="lmDesign === 'b'" class="lm-header__chips"><slot name="header-chips" /></div>
                    <div v-else class="lm-header__stats"><slot name="header-stats" /></div>
                </div>

                <mt-tabs
                    v-if="lmDesign === 'a' && tabs.length > 1"
                    :position-identifier="'lm-detail-tabs-' + icon"
                    :items="tabs"
                    :default-item="active"
                    @new-item-active="select"
                />
                <lm-tabs v-else-if="lmDesign === 'b' && tabs.length > 1" :items="tabs" :active="active" :aria-label="title" @select="select" />

                <div :class="{ 'lm-split': lmDesign === 'c' && tabs.length > 1 }">
                    <lm-tabs v-if="lmDesign === 'c' && tabs.length > 1" vertical :items="tabs" :groups="groups" :active="active" :aria-label="title" @select="select" />
                    <div class="lm-detail__panels">
                        <slot :active="active" />
                    </div>
                </div>
            </template>
        </sw-card-view>
    </template>
</sw-page>`,mixins:[Shopware.Mixin.getByName("lm-design")],emits:["save","cancel","update:active","update:saveSuccess"],props:{title:{type:String,required:!0},icon:{type:String,required:!0},status:{type:Object,required:!1,default:null},tabs:{type:Array,required:!1,default:()=>[]},groups:{type:Object,required:!1,default:()=>({})},active:{type:String,required:!1,default:"base"},ready:{type:Boolean,required:!0},isLoading:{type:Boolean,required:!1,default:!1},canEdit:{type:Boolean,required:!1,default:!1},saveSuccess:{type:Boolean,required:!1,default:!1}},computed:{tooltipSave(){return this.canEdit?{message:`${this.$device.getSystemKey()} + S`,appearance:"light"}:{showDelay:300,message:this.$tc("sw-privileges.tooltip.warning"),disabled:this.canEdit,showOnDisabledElements:!0}}},methods:{select(e){this.$emit("update:active",typeof e=="string"?e:(e==null?void 0:e.name)??"base")}}};export{t as default};
