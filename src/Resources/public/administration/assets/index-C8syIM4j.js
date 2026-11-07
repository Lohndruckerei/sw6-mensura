import{A as t}from"./ld-mensura-z8Pk-lci.js";const i={template:`
<lm-page
    :title="$tc('ld-mensura.areas.' + area + '.title')"
    :active="area"
>
    <sw-empty-state
        :title="$tc('ld-mensura.areas.' + area + '.title')"
        :subline="$tc('ld-mensura.areas.' + area + '.description')"
        :icon="icon"
        :absolute="false"
    />
</lm-page>`,props:{area:{type:String,required:!0}},computed:{icon(){var e;return((e=t.find(a=>a.name===this.area))==null?void 0:e.icon)??"regular-crop"}}};export{i as default};
//# sourceMappingURL=index-C8syIM4j.js.map
