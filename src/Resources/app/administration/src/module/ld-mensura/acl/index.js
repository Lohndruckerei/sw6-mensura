/**
 * Rechte für Mensura (KONZEPT 3b), freigegeben je Rolle unter Einstellungen → Benutzer & Rechte → Rollen.
 *
 * Katalog (Ansehen/Bearbeiten/Anlegen/Löschen, Schlüssel `ld_mensura`): Kalkulationen, Materialien, Optionen,
 * Optionssets, Preistabellen – nur VK. Zusätzliche Rechte (`ld_mensura.<recht>`):
 * - profiles        Produktprofile pflegen (Reiter „Mensura“ im Produkt)
 * - purchase        Einkaufspreise, Margen, Deckungsbeitrag sehen und pflegen – serverseitig geschützt
 *                   (Service\Security\PurchaseProtectionSubscriber), Rolle wird dort als Schlüssel geprüft
 * - customer_groups Kundengruppen-Aufschläge pflegen
 * - orders          Rechenweg-Schnappschuss in Bestellungen lesen (EK darin nur zusammen mit purchase)
 * - settings        Plugin-Einstellungen
 */
const CATALOG = [
    'ld_mensura_calculation',
    'ld_mensura_calculation_material',
    'ld_mensura_calculation_option_value',
    'ld_mensura_material',
    'ld_mensura_material_translation',
    'ld_mensura_option_group',
    'ld_mensura_option_group_translation',
    'ld_mensura_option_value',
    'ld_mensura_option_value_translation',
    'ld_mensura_option_set',
    'ld_mensura_option_set_group',
    'ld_mensura_price_table',
    'ld_mensura_price_table_row',
    'ld_mensura_feature',
    'ld_mensura_feature_translation',
    'ld_mensura_use_case',
    'ld_mensura_use_case_translation',
    'ld_mensura_accessory_rule',
    'ld_mensura_accessory_rule_translation',
];

const PROFILES = [
    'ld_mensura_product_profile',
    'ld_mensura_product_profile_material',
    'ld_mensura_product_profile_option',
    'ld_mensura_feature_value',
    'ld_mensura_use_case_assignment',
];

const READ_ONLY = ['ld_mensura_customer_group_rule'];

const privileges = (entities, ...actions) => entities.flatMap((entity) => actions.map((action) => `${entity}:${action}`));

Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: null,
    key: 'ld_mensura',
    roles: {
        viewer: {
            privileges: [
                ...privileges([...CATALOG, ...PROFILES, ...READ_ONLY], 'read'),
                'customer_group:read',
                'product:read',
                'sales_channel:read',
                'sales_channel_domain:read',
                'media:read',
                'system_config:read',
                'user_config:read',
                'user_config:create',
                'user_config:update',
            ],
            dependencies: [],
        },
        editor: {
            privileges: privileges(CATALOG, 'update', 'create'),
            dependencies: ['ld_mensura.viewer'],
        },
        creator: {
            privileges: privileges(CATALOG, 'create'),
            dependencies: ['ld_mensura.viewer', 'ld_mensura.editor'],
        },
        deleter: {
            privileges: privileges(CATALOG, 'delete'),
            dependencies: ['ld_mensura.viewer'],
        },
    },
});

Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'additional_permissions',
    parent: null,
    key: 'ld_mensura',
    roles: {
        profiles: {
            privileges: [...privileges(PROFILES, 'create', 'update', 'delete'), 'product:update'],
            dependencies: ['ld_mensura.viewer'],
        },
        purchase: {
            privileges: [],
            dependencies: ['ld_mensura.viewer'],
        },
        customer_groups: {
            privileges: privileges(['ld_mensura_customer_group_rule'], 'create', 'update', 'delete'),
            dependencies: ['ld_mensura.viewer'],
        },
        orders: {
            privileges: ['ld_mensura_order_line_item:read', 'order:read', 'order_line_item:read'],
            dependencies: [],
        },
        settings: {
            privileges: ['system_config:read', 'system_config:create', 'system_config:update'],
            dependencies: ['ld_mensura.viewer'],
        },
    },
});
