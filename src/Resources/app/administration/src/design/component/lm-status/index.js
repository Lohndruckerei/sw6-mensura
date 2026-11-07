const ICONS = {
    ok: 'regular-checkmark-s',
    warn: 'regular-exclamation-triangle',
    err: 'regular-times-circle',
    off: 'regular-ban',
};

/**
 * Status als Label (A), Pill mit Icon (B) oder Farbpunkt mit Text (C).
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
            validator: (value) => ['ok', 'warn', 'err', 'off'].includes(value),
        },

        label: {
            type: String,
            required: true,
        },
    },

    computed: {
        icon() {
            return ICONS[this.state];
        },
    },
};
