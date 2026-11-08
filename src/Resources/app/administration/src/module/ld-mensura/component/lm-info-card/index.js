/**
 * Erklärkarte (KONZEPT 3a Punkt 2, DESIGN.md 7.9): Info-Banner oben im Bereich, schließbar; die Wahl wird im
 * Browser gemerkt (je Karte). Über „Erklärung anzeigen“ wieder einblendbar.
 */
export default {
    template: `
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
</div>`,

    props: {
        name: { type: String, required: true },
        title: { type: String, required: true },
    },

    data() {
        return { open: true };
    },

    created() {
        try {
            this.open = window.localStorage.getItem(this.storageKey) !== 'closed';
        } catch {
            this.open = true;
        }
    },

    computed: {
        storageKey() {
            return `ld-mensura.info.${this.name}`;
        },
    },

    methods: {
        setOpen(open) {
            this.open = open;
            try {
                window.localStorage.setItem(this.storageKey, open ? 'open' : 'closed');
            } catch {
                // ohne Speicher bleibt die Karte bis zum Neuladen geschlossen
            }
        },
    },
};
