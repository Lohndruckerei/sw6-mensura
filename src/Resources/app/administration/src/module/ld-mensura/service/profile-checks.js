/**
 * Prüfungen eines Produktprofils beim Speichern (KONZEPT 3a Punkt 5): liefert Snippet-Schlüssel unter
 * ld-mensura.profile.warnings mit Parametern. Warnt nur, blockiert nicht.
 */
export function profileWarnings(profile) {
    if (!profile) {
        return [];
    }
    const warnings = [];
    const materials = [...(profile.materials ?? [])];
    const options = [...(profile.options ?? [])];

    if (materials.length === 0) {
        warnings.push({ key: 'noMaterial' });
    }
    if (materials.some((row) => !row.calculationId)) {
        warnings.push({ key: 'materialWithoutCalculation' });
    }
    if (options.some((rule) => ['fixed', 'hidden'].includes(rule.mode) && !rule.defaultValueId)) {
        warnings.push({ key: 'fixedWithoutValue' });
    }
    if (options.some((rule) => rule.mode === 'restrict' && (!rule.valueIds || rule.valueIds.length === 0))) {
        warnings.push({ key: 'restrictWithoutValues' });
    }
    const minMax = [['widthMin', 'widthMax'], ['heightMin', 'heightMax'], ['minQuantity', 'maxQuantity']];
    if (minMax.some(([min, max]) => profile[min] && profile[max] && profile[min] > profile[max])) {
        warnings.push({ key: 'minAboveMax' });
    }

    return warnings;
}
