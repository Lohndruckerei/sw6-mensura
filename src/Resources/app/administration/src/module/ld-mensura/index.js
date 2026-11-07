import './acl';
import './mensura.scss';
import { NAV_ITEMS } from './constants';

Shopware.Component.register('lm-page', () => import('./component/lm-page'));
Shopware.Component.register('lm-overview', () => import('./page/lm-overview'));
Shopware.Component.register('lm-area-placeholder', () => import('./page/lm-area-placeholder'));

const COLOR = '#0B6E79';

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
            meta: {
                privilege: 'ld_mensura.viewer',
            },
        },
        calculation: {
            component: 'lm-area-placeholder',
            path: 'calculation',
            props: { default: { area: 'calculation' } },
            meta: {
                parentPath: 'ld.mensura.overview',
                privilege: 'ld_mensura.viewer',
            },
        },
        material: {
            component: 'lm-area-placeholder',
            path: 'material',
            props: { default: { area: 'material' } },
            meta: {
                parentPath: 'ld.mensura.overview',
                privilege: 'ld_mensura.viewer',
            },
        },
        option: {
            component: 'lm-area-placeholder',
            path: 'option',
            props: { default: { area: 'option' } },
            meta: {
                parentPath: 'ld.mensura.overview',
                privilege: 'ld_mensura.viewer',
            },
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
