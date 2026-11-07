/* GENERIERT aus ld-admin-design 1.0.0 (bd4dc06) – nicht hier ändern, sondern in ~/projekte/ld-admin-design/vorlage und ./sync.sh ausführen. */
import { STATES, STATUS_ICONS } from '../../constants';

/**
 * Status als Label (A), Pill mit Icon (B) oder Farbpunkt mit Text (C).
 * Immer mit Wort – nie nur Farbe.
 */
export default {
    template: `
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
</span>`,

    props: {
        state: {
            type: String,
            required: true,
            validator: (value) => STATES.includes(value),
        },

        label: {
            type: String,
            required: true,
        },
    },

    computed: {
        icon() {
            return STATUS_ICONS[this.state];
        },
    },
};
