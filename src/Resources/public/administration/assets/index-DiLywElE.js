const a={ok:"regular-checkmark-s",warn:"regular-exclamation-triangle",err:"regular-times-circle",off:"regular-ban"},t={template:`
<span
    class="lm-status"
    :class="'lm-status--' + state"
>
    <mt-icon
        class="lm-status__icon"
        :name="icon"
        size="11px"
    />
    <span>{{ label }}</span>
</span>`,props:{state:{type:String,required:!0,validator:e=>["ok","warn","err","off"].includes(e)},label:{type:String,required:!0}},computed:{icon(){return a[this.state]}}};export{t as default};
//# sourceMappingURL=index-DiLywElE.js.map
