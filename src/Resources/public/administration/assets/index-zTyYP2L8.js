import{D as s}from"./ld-mensura-BMXvwQzn.js";const n={template:`
<div class="lm-design-switch" role="group" :aria-label="$tc('lm-design.switchLabel')">
    <span class="lm-design-switch__label">{{ $tc('lm-design.switchLabel') }}</span>
    <button
        v-for="design in designs"
        :key="design.key"
        v-tooltip.bottom="{ message: design.title, appearance: 'light' }"
        type="button"
        class="lm-design-switch__option"
        :class="{ 'is--active': design.key === current }"
        :aria-pressed="design.key === current ? 'true' : 'false'"
        @click="select(design.key)"
    >
        {{ design.key.toUpperCase() }}
    </button>
</div>`,computed:{store(){return Shopware.Store.get("lmDesign")},current(){return this.store.design},designs(){return s.map(e=>{const t=e===this.store.defaultDesign;return{key:e,title:this.$tc(`lm-design.designs.${e}`)+(t?` (${this.$tc("lm-design.default")})`:"")}})}},created(){this.store.ensureLoaded()},methods:{async select(e){if(e!==this.current)try{await this.store.setDesign(e)}catch{}}}};export{n as default};
