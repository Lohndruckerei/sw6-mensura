/**
 * Bereiche von Mensura. Eine Liste für Hauptmenü, Übersichtskacheln und Bereichsnavigation –
 * neue Oberflächen hier ergänzen (plus Route in ../index.js und Snippets).
 *
 * color: Bereichsfarbe c1–c4 aus lm-design.scss
 */
export const AREAS = [
    { name: 'calculation', route: 'ld.mensura.calculation', icon: 'regular-euro', color: 'c1', group: 'setup' },
    { name: 'material', route: 'ld.mensura.material', icon: 'regular-layer-group', color: 'c2', group: 'setup' },
    { name: 'option', route: 'ld.mensura.option', icon: 'regular-sliders-v', color: 'c3', group: 'setup' },
];

export const OVERVIEW = { name: 'overview', route: 'ld.mensura.overview', icon: 'regular-dashboard', group: 'start' };

export const NAV_ITEMS = [OVERVIEW, ...AREAS];
