// Styles: scss/base.scss (kompiliert der Theme-Compiler automatisch)
// Mensura-Konfigurator (kompakt/geführt) auf der Produktdetailseite – nur für Produkte mit Mensura-Profil
window.PluginManager.register(
    'LdMensuraConfigurator',
    () => import('./ld-mensura-configurator/configurator.plugin.js'),
    '[data-ld-mensura-configurator]',
);
