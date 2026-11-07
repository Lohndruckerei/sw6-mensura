const t={template:`
<div class="lm-info-card">
    <mt-banner
        v-if="open"
        variant="info"
        icon="regular-lightbulb"
        :title="title"
        closable
        @close="setOpen(false)"
    >
        <slot />
    </mt-banner>
    <button v-else type="button" class="lm-info-card__reopen" @click="setOpen(true)">
        <mt-icon name="regular-question-circle" size="14px" />
        {{ $tc('ld-mensura.general.showExplanation') }}
    </button>
</div>`,props:{name:{type:String,required:!0},title:{type:String,required:!0}},data(){return{open:!0}},created(){try{this.open=window.localStorage.getItem(this.storageKey)!=="closed"}catch{this.open=!0}},computed:{storageKey(){return`ld-mensura.info.${this.name}`}},methods:{setOpen(e){this.open=e;try{window.localStorage.setItem(this.storageKey,e?"open":"closed")}catch{}}}};export{t as default};
