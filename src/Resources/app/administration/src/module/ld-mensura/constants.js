/**
 * Bereiche von Mensura. Eine Liste für Hauptmenü, Übersichtskacheln und Bereichsnavigation –
 * neue Oberflächen hier ergänzen (plus Route in ../index.js und Snippets).
 *
 * color: Bereichsfarbe c1–c4 (Kategorie-Farben aus ld-admin-design)
 */
export const AREAS = [
    { name: 'profile', route: 'ld.mensura.profile', icon: 'regular-products', color: 'c1', group: 'shop' },
    { name: 'calculation', route: 'ld.mensura.calculation', icon: 'regular-euro', color: 'c1', group: 'setup' },
    { name: 'material', route: 'ld.mensura.material', icon: 'regular-layer-group', color: 'c2', group: 'setup' },
    { name: 'option', route: 'ld.mensura.option', icon: 'regular-sliders-v', color: 'c3', group: 'setup' },
    { name: 'optionSet', route: 'ld.mensura.optionSet', icon: 'regular-list', color: 'c4', group: 'setup' },
];

export const OVERVIEW = { name: 'overview', route: 'ld.mensura.overview', icon: 'regular-dashboard', group: 'start' };

export const NAV_ITEMS = [OVERVIEW, ...AREAS];
