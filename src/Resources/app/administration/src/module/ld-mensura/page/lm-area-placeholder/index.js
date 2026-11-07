import { AREAS } from '../../constants';

/**
 * Platzhalter für Bereiche, die noch gebaut werden. Wird Bereich für Bereich durch
 * die echten Listen-/Detailseiten ersetzt.
 */
export default {
    template: `
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
</lm-page>`,

    props: {
        area: {
            type: String,
            required: true,
        },
    },

    computed: {
        icon() {
            return AREAS.find((item) => item.name === this.area)?.icon ?? 'regular-crop';
        },
    },
};
