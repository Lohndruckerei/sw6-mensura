import './acl';
import './mensura.scss';
import { NAV_ITEMS } from './constants';

// Seitenrahmen und Bausteine
Shopware.Component.register('lm-page', () => import('./component/lm-page'));
Shopware.Component.register('lm-info-card', () => import('./component/lm-info-card'));
Shopware.Component.register('lm-price-pair', () => import('./component/lm-price-pair'));
Shopware.Component.register('lm-price-components', () => import('./component/lm-price-components'));
Shopware.Component.register('lm-tier-editor', () => import('./component/lm-tier-editor'));
Shopware.Component.register('lm-supplier-codes', () => import('./component/lm-supplier-codes'));

// Seiten
Shopware.Component.register('lm-overview', () => import('./page/lm-overview'));
Shopware.Component.register('lm-area-placeholder', () => import('./page/lm-area-placeholder'));
Shopware.Component.register('lm-material-list', () => import('./page/lm-material-list'));
Shopware.Component.register('lm-material-detail', () => import('./page/lm-material-detail'));

const COLOR = '#0B6E79';

const viewer = { parentPath: 'ld.mensura.overview', privilege: 'ld_mensura.viewer' };

/**
 * Detailrouten eines Bereichs: Liste, Neuanlage, Detail (ID als Prop).
 */
function areaRoutes(name, list, detail, propName) {
    return {
        [name]: { component: list, path: name, meta: viewer },
        [`${name}Create`]: {
            component: detail,
            path: `${name}/create`,
            meta: { parentPath: `ld.mensura.${name}`, privilege: 'ld_mensura.creator' },
        },
        [`${name}Detail`]: {
            component: detail,
            path: `${name}/detail/:id`,
            props: { default: (route) => ({ [propName]: route.params.id }) },
            meta: { parentPath: `ld.mensura.${name}`, privilege: 'ld_mensura.viewer' },
        },
    };
}

/**
 * Eigener Hauptmenüpunkt „Mensura“ mit Untermenüs.
 *
 * Shopware lässt Plugin-Module (type: 'plugin') nicht auf die erste Menüebene
 * (module.factory.ts: „Navigation entries from plugins are not allowed on the first level“).
 * Deshalb ist das Modul als type 'core' registriert – nur dadurch erscheint „Mensura“
 * als eigener Punkt in der linken Navigation.
 */
Shopware.Module.register('ld-mensura', {
    type: 'core',
    name: 'ld-mensura',
    title: 'ld-mensura.general.mainMenuItem',
    description: 'ld-mensura.general.description',
    color: COLOR,
    icon: 'regular-crop',

    routes: {
        overview: {
            component: 'lm-overview',
            path: 'overview',
            meta: { privilege: 'ld_mensura.viewer' },
        },
        calculation: {
            component: 'lm-area-placeholder',
            path: 'calculation',
            props: { default: { area: 'calculation' } },
            meta: viewer,
        },
        ...areaRoutes('material', 'lm-material-list', 'lm-material-detail', 'materialId'),
        option: {
            component: 'lm-area-placeholder',
            path: 'option',
            props: { default: { area: 'option' } },
            meta: viewer,
        },
    },

    navigation: [
        {
            id: 'ld-mensura',
            label: 'ld-mensura.general.mainMenuItem',
            color: COLOR,
            icon: 'regular-crop',
            position: 45,
        },
        ...NAV_ITEMS.map((item, index) => ({
            id: `ld-mensura-${item.name}`,
            parent: 'ld-mensura',
            path: item.route,
            label: `ld-mensura.areas.${item.name}.title`,
            color: COLOR,
            privilege: 'ld_mensura.viewer',
            position: (index + 1) * 10,
        })),
    ],
});
