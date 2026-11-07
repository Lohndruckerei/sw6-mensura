import{A as a}from"./ld-mensura-sRn5qiHD.js";const s={template:`
<lm-page
    :title="$tc('ld-mensura.general.mainMenuItem')"
    active="overview"
>
    <div class="lm-header">
        <span class="lm-header__icon">
            <mt-icon name="regular-crop" size="22px" />
        </span>
        <div class="lm-header__main">
            <h3 class="lm-header__title">
                {{ $tc('ld-mensura.overview.title') }}
                <lm-status state="warn" :label="$tc('ld-mensura.general.statusSetup')" />
            </h3>
            <div class="lm-header__meta">
                {{ $tc('ld-mensura.overview.subline') }}
            </div>
        </div>
    </div>

    <div class="lm-areas">
        <router-link
            v-for="area in areas"
            :key="area.name"
            class="lm-area"
            :class="'lm-area--' + area.color"
            :to="{ name: area.route }"
        >
            <span class="lm-area__icon">
                <mt-icon :name="area.icon" size="20px" />
            </span>
            <b>{{ $tc('ld-mensura.areas.' + area.name + '.title') }}</b>
            <p>{{ $tc('ld-mensura.areas.' + area.name + '.description') }}</p>
            <span class="lm-area__foot">
                <lm-status state="off" :label="$tc('ld-mensura.general.comingSoon')" />
            </span>
        </router-link>
    </div>
</lm-page>`,computed:{areas(){return a}}};export{s as default};
