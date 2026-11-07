/**
 * Rechte für Mensura. Die Rechenweg-Schnappschüsse (ld_mensura_order_line_item) enthalten
 * Einkaufspreise und sind nur lesbar, schreiben tut nur der Bestellprozess.
 */
const MASTER_DATA = [
    'ld_mensura_calculation',
    'ld_mensura_calculation_material',
    'ld_mensura_calculation_option_value',
    'ld_mensura_customer_group_rule',
    'ld_mensura_material',
    'ld_mensura_material_translation',
    'ld_mensura_option_group',
    'ld_mensura_option_group_translation',
    'ld_mensura_option_value',
    'ld_mensura_option_value_translation',
    'ld_mensura_option_set',
    'ld_mensura_option_set_group',
    'ld_mensura_product_profile',
    'ld_mensura_product_profile_material',
    'ld_mensura_product_profile_option',
    'ld_mensura_price_table',
    'ld_mensura_price_table_row',
    'ld_mensura_feature',
    'ld_mensura_feature_translation',
    'ld_mensura_feature_value',
    'ld_mensura_use_case',
    'ld_mensura_use_case_translation',
    'ld_mensura_use_case_assignment',
    'ld_mensura_accessory_rule',
    'ld_mensura_accessory_rule_translation',
];

const privileges = (action) => MASTER_DATA.map((entity) => `${entity}:${action}`);

Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: null,
    key: 'ld_mensura',
    roles: {
        viewer: {
            privileges: [
                ...privileges('read'),
                'ld_mensura_order_line_item:read',
                'customer_group:read',
                'media:read',
                'system_config:read',
                'user_config:read',
                'user_config:create',
                'user_config:update',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [...privileges('update'), ...privileges('create')],
            dependencies: ['ld_mensura.viewer'],
        },
        creator: {
            privileges: [...privileges('create')],
            dependencies: ['ld_mensura.viewer', 'ld_mensura.editor'],
        },
        deleter: {
            privileges: [...privileges('delete')],
            dependencies: ['ld_mensura.viewer'],
        },
    },
});
