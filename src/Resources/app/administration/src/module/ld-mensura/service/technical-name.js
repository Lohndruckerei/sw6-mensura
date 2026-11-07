/**
 * Technischen Namen aus der Bezeichnung ableiten, wenn er leer ist
 * („Platte 3 mm Aluverbund“ → „platte-3-mm-aluverbund“).
 */
export function technicalNameFrom(name) {
    return String(name ?? '')
        .toLowerCase()
        .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 64);
}

/** Setzt entity.technicalName aus der Bezeichnung, falls leer. */
export function ensureTechnicalName(entity) {
    if (entity && !entity.technicalName) {
        const derived = technicalNameFrom(entity.translated?.name || entity.name);
        if (derived) {
            entity.technicalName = derived;
        }
    }
}
