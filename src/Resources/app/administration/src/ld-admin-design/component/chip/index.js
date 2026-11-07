/* GENERIERT aus ld-admin-design 1.0.0 (bd4dc06) – nicht hier ändern, sondern in ~/projekte/ld-admin-design/vorlage und ./sync.sh ausführen. */
import { CATEGORY_COLORS } from '../../constants';

/**
 * Kategorie-Chip. Aussehen je Design: A grau mit farbigem Icon,
 * B getönt mit Farbbalken (CMYK), C farbiges Quadrat.
 *
 * color: c1 | c2 | c3 | c4 (Kategorie-Farbe aus DESIGN.md)
 */
export default {
    template: `
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
</span>`,

    props: {
        label: {
            type: String,
            required: true,
        },

        color: {
            type: String,
            required: false,
            default: 'c1',
            validator: (value) => CATEGORY_COLORS.includes(value),
        },

        icon: {
            type: String,
            required: false,
            default: null,
        },

        title: {
            type: String,
            required: false,
            default: null,
        },
    },
};
