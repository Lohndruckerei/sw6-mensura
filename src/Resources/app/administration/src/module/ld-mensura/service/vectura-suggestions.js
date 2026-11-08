/**
 * Vorschläge aus Vectura für den Logistikanteil (KONZEPT 3d.7, INTEGRATION.md 7.2) – reine Funktionen ohne Shopware,
 * damit sie ohne Shop testbar sind (tests/Backend/vectura-suggestions.test.mjs).
 *
 * Kopplung lose: gemeinsame Sprache sind die Schlüssel (materialKey = technischer Name des Materials,
 * calculationKey = technischer Name der Kalkulation, deliveryKey/optionKey = technischer Name des Optionswerts),
 * keine Fremdschlüssel. Vectura liefert die Vorschläge, Mensura ordnet sie nur zu und übernimmt sie per Klick.
 */

export const SUGGESTIONS_PATH = '/_action/ld-vectura/logistics/suggestions';

/** Höchstens ±1 €/m² bzw. ±2 Prozentpunkte je Anpassung (LOGISTIKPAUSCHALE 3.4 Nr. 6). */
export const STEP_LIMITS = { per_sqm: 1, percent: 2 };

/**
 * Vorschläge, die zu diesem Ziel gehören.
 *
 * @param {Array} suggestions  `suggestions` aus der Vectura-Antwort
 * @param {{ calculationKey?: string, materialKey?: string, valueKey?: string }} keys  Schlüssel des Ziels
 */
export function matchSuggestions(suggestions, keys) {
    return (Array.isArray(suggestions) ? suggestions : []).filter((suggestion) => {
        const cause = suggestion?.cause ?? {};
        const materialKeys = Array.isArray(cause.materialKeys) ? cause.materialKeys : [];
        const valueKey = cause.deliveryKey ?? cause.optionKey ?? null;
        if (keys.valueKey) {
            return valueKey === keys.valueKey
                && (!cause.calculationKey || !keys.calculationKey || cause.calculationKey === keys.calculationKey);
        }
        if (valueKey) {
            return false;
        }
        if (keys.materialKey) {
            return materialKeys.includes(keys.materialKey);
        }
        if (keys.calculationKey) {
            return cause.calculationKey === keys.calculationKey && materialKeys.length === 0;
        }

        return false;
    });
}

/** Nächster Montag nach `today` (YYYY-MM-DD) – Standard für „gültig ab“ einer Übernahme. */
export function nextMonday(today = new Date()) {
    const date = new Date(Date.UTC(today.getFullYear(), today.getMonth(), today.getDate()));
    const day = date.getUTCDay();
    date.setUTCDate(date.getUTCDate() + ((8 - day) % 7 || 7));

    return date.toISOString().slice(0, 10);
}

/**
 * Prüfungen in Mensura zusätzlich zu den Warnungen aus Vectura: Schrittgrenze gegen die gültige Regel.
 *
 * @param {object} suggestion
 * @param {object|null} current  gültige Regel derselben Kostenart (oder null)
 */
export function stepExceeded(suggestion, current) {
    const proposal = suggestion?.proposal ?? {};
    const limit = STEP_LIMITS[proposal.form];
    if (limit === undefined || !current || current.form !== proposal.form) {
        return false;
    }

    return Math.abs(Number(proposal.value) - Number(current.value)) > limit + 1e-9;
}

/**
 * Entwurf für den Dialog „Logistikanteil bearbeiten“ aus einem Vorschlag – übernommen wird erst beim Speichern.
 *
 * @param {object} suggestion
 * @param {{ period?: object, generatedAt?: string }} meta  Rahmendaten der Vectura-Antwort
 * @param {string} validFrom  YYYY-MM-DD
 */
export function proposalToDraft(suggestion, meta, validFrom) {
    const proposal = suggestion.proposal ?? {};
    const has = (value) => value !== null && value !== undefined;

    return {
        costType: suggestion.costType ?? 'other',
        form: proposal.form ?? 'per_sqm',
        value: Number(proposal.value ?? 0),
        validFrom,
        useRamp: has(proposal.rampFrom) && has(proposal.rampTo),
        rampFrom: proposal.rampFrom ?? 1000,
        rampTo: proposal.rampTo ?? 1400,
        useThreshold: has(proposal.thresholdBasis) && has(proposal.thresholdFrom) && has(proposal.thresholdTo),
        thresholdBasis: proposal.thresholdBasis ?? 'total_area',
        thresholdFrom: proposal.thresholdFrom ?? null,
        thresholdTo: proposal.thresholdTo ?? null,
        useMinimum: has(proposal.minimumAmount),
        minimumAmount: proposal.minimumAmount ?? null,
        minimumScope: proposal.minimumScope ?? 'position',
        source: 'vectura',
        sourceReference: {
            suggestionId: suggestion.id ?? null,
            period: meta?.period ?? null,
            generatedAt: meta?.generatedAt ?? null,
            costs: suggestion.costs ?? null,
            revenue: suggestion.revenue ?? null,
            coverage: suggestion.coverage ?? null,
            targetCoverage: suggestion.targetCoverage ?? null,
            cases: suggestion.cases ?? null,
            warnings: suggestion.warnings ?? [],
        },
    };
}

/**
 * Vorschläge laden. Ergebnis: { status: 'ok' | 'missing' | 'forbidden' | 'error', data }.
 * `missing` = Vectura nicht installiert oder Route fehlt (404) → Karte bleibt unsichtbar, Pflege von Hand.
 *
 * @param {{ get: Function }} httpClient
 * @param {object} headers
 * @param {boolean} installed  Vectura ist installiert
 * @param {{ from: string, to: string }} period
 */
export async function loadSuggestions(httpClient, headers, installed, period) {
    if (!installed) {
        return { status: 'missing', data: null };
    }
    try {
        const { data } = await httpClient.get(SUGGESTIONS_PATH, { headers, params: period });

        return { status: 'ok', data };
    } catch (error) {
        const status = error?.response?.status;
        if (status === 404) {
            return { status: 'missing', data: null };
        }

        return { status: status === 403 ? 'forbidden' : 'error', data: null };
    }
}

/** Standard-Zeitraum: rollierend 3 Monate bis heute (LOGISTIKPAUSCHALE 7 Nr. 3). */
export function defaultPeriod(today = new Date()) {
    const to = new Date(Date.UTC(today.getFullYear(), today.getMonth(), today.getDate()));
    const from = new Date(to);
    from.setUTCMonth(from.getUTCMonth() - 3);

    return { from: from.toISOString().slice(0, 10), to: to.toISOString().slice(0, 10) };
}
