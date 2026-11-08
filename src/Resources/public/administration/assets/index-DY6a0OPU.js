import{C as l}from"./ld-mensura-BrmPsxbL.js";const i={template:`
<span
    class="lm-chip"
    :style="{ '--lm-t': 'var(--lm-' + color + ')' }"
    :title="title"
>
    <mt-icon
        v-if="icon"
        class="lm-chip__icon"
        :name="icon"
        size="12px"
    />
    <span class="lm-chip__label">{{ label }}</span>
</span>`,props:{label:{type:String,required:!0},color:{type:String,required:!1,default:"c1",validator:e=>l.includes(e)},icon:{type:String,required:!1,default:null},title:{type:String,required:!1,default:null}}};export{i as default};
