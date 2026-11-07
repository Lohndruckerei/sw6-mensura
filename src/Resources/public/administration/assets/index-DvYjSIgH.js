import{S as s,a}from"./ld-mensura-z8Pk-lci.js";const n={template:`
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
</span>`,props:{state:{type:String,required:!0,validator:t=>s.includes(t)},label:{type:String,required:!0}},computed:{icon(){return a[this.state]}}};export{n as default};
//# sourceMappingURL=index-DvYjSIgH.js.map
