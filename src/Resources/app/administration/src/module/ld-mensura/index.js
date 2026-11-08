import './acl';
import './mensura.scss';
import './extension/sw-product-detail';
import { NAV_ITEMS } from './constants';

// Seitenrahmen und Bausteine
Shopware.Component.register('lm-page', () => import('./component/lm-page'));
Shopware.Component.register('lm-info-card', () => import('./component/lm-info-card'));
Shopware.Component.register('lm-price-pair', () => import('./component/lm-price-pair'));
Shopware.Component.register('lm-price-components', () => import('./component/lm-price-components'));
Shopware.Component.register('lm-tier-editor', () => import('./component/lm-tier-editor'));
Shopware.Component.register('lm-supplier-codes', () => import('./component/lm-supplier-codes'));
Shopware.Component.register('lm-entity-list', () => import('./component/lm-entity-list'));
Shopware.Component.register('lm-detail-page', () => import('./component/lm-detail-page'));
Shopware.Component.register('lm-value-modal', () => import('./component/lm-value-modal'));
Shopware.Component.register('lm-assignment-modal', () => import('./component/lm-assignment-modal'));
Shopware.Component.register('lm-price-tables', () => import('./component/lm-price-tables'));
Shopware.Component.register('lm-test-calculator', () => import('./component/lm-test-calculator'));
Shopware.Component.register('lm-logistics-card', () => import('./component/lm-logistics-card'));

// Seiten
Shopware.Component.register('lm-overview', () => import('./page/lm-overview'));
Shopware.Component.register('lm-material-list', () => import('./page/lm-material-list'));
Shopware.Component.register('lm-material-detail', () => import('./page/lm-material-detail'));
Shopware.Component.register('lm-calculation-list', () => import('./page/lm-calculation-list'));
Shopware.Component.register('lm-calculation-detail', () => import('./page/lm-calculation-detail'));
Shopware.Component.register('lm-option-list', () => import('./page/lm-option-list'));
Shopware.Component.register('lm-option-detail', () => import('./page/lm-option-detail'));
Shopware.Component.register('lm-option-set-list', () => import('./page/lm-option-set-list'));
Shopware.Component.register('lm-option-set-detail', () => import('./page/lm-option-set-detail'));
Shopware.Component.register('lm-profile-list', () => import('./page/lm-profile-list'));
Shopware.Component.register('lm-product-profile', () => import('./page/lm-product-profile'));

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
        // Profile werden im Produkt bearbeitet (Reiter „Mensura“), hier nur die Übersicht
        profile: { component: 'lm-profile-list', path: 'profile', meta: viewer },
        ...areaRoutes('calculation', 'lm-calculation-list', 'lm-calculation-detail', 'calculationId'),
        ...areaRoutes('material', 'lm-material-list', 'lm-material-detail', 'materialId'),
        ...areaRoutes('option', 'lm-option-list', 'lm-option-detail', 'groupId'),
        ...areaRoutes('optionSet', 'lm-option-set-list', 'lm-option-set-detail', 'setId'),
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

/**
 * Reiter „Mensura“ in der Produktdetailseite: Route als Kind von sw.product.detail
 * (Reiter selbst in extension/sw-product-detail).
 */
Shopware.Module.register('ld-mensura-product', {
    type: 'plugin',
    name: 'ld-mensura-product',
    title: 'ld-mensura.profile.tab',
    description: 'ld-mensura.profile.info.title',
    color: COLOR,
    icon: 'regular-crop',

    routeMiddleware(next, currentRoute) {
        const name = 'sw.product.detail.ldMensura';
        if (currentRoute.name === 'sw.product.detail' && !currentRoute.children.some((route) => route.name === name)) {
            currentRoute.children.push({
                name,
                path: '/sw/product/detail/:id?/ld-mensura',
                component: 'lm-product-profile',
                meta: { parentPath: 'sw.product.index', privilege: 'ld_mensura.viewer' },
            });
        }
        next(currentRoute);
    },
});
