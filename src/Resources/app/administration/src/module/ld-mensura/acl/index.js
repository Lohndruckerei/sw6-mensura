/**
 * Rechte für Mensura. Die Entitäten (Kalkulationen, Materialien, Optionen) folgen in den
 * nächsten Schritten und werden dann hier ergänzt.
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: null,
    key: 'ld_mensura',
    roles: {
        viewer: {
            privileges: [
                'system_config:read',
                'user_config:read',
                'user_config:create',
                'user_config:update',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [],
            dependencies: ['ld_mensura.viewer'],
        },
        creator: {
            privileges: [],
            dependencies: ['ld_mensura.viewer', 'ld_mensura.editor'],
        },
        deleter: {
            privileges: [],
            dependencies: ['ld_mensura.viewer'],
        },
    },
});
