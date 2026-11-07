import{N as a}from"./ld-mensura-sRn5qiHD.js";const r={template:`
<sw-page class="lm-page" :class="lmDesignClass">
    <template v-if="$slots['search-bar']" #search-bar>
        <slot name="search-bar" />
    </template>

    <template #smart-bar-header>
        <h2>
            {{ title }}
            <span v-if="amount !== null" class="sw-page__smart-bar-amount">({{ amount }})</span>
        </h2>
    </template>

    <template #smart-bar-actions>
        <lm-design-switch />
        <slot name="actions" />
    </template>

    <template #content>
        <div class="lm-page__content" :class="lmDesignClass">
            <lm-tabs
                v-if="lmDesign === 'b'"
                :items="navItems"
                :active="active"
                :aria-label="$tc('ld-mensura.general.mainMenuItem')"
                @select="go"
            />

            <div v-if="lmDesign === 'c'" class="lm-split">
                <lm-tabs
                    vertical
                    :items="navItems"
                    :groups="navGroups"
                    :active="active"
                    :aria-label="$tc('ld-mensura.general.mainMenuItem')"
                    @select="go"
                />
                <div class="lm-page__main">
                    <slot />
                </div>
            </div>

            <slot v-else />
        </div>
    </template>
</sw-page>`,mixins:[Shopware.Mixin.getByName("lm-design")],props:{title:{type:String,required:!0},active:{type:String,required:!0},amount:{type:Number,required:!1,default:null}},computed:{navItems(){return a.map(e=>({name:e.name,label:this.$tc(`ld-mensura.areas.${e.name}.title`),icon:e.icon,group:e.group}))},navGroups(){return{start:this.$tc("ld-mensura.groups.start"),setup:this.$tc("ld-mensura.groups.setup")}}},methods:{go(e){const t=a.find(s=>s.name===e);t&&e!==this.active&&this.$router.push({name:t.route})}}};export{r as default};
