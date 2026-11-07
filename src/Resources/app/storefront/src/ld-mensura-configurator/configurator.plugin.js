/**
 * Shopware-Storefront-Plugin „LdMensuraConfigurator“ (registriert in main.js über den PluginManager).
 * Optionen kommen aus dem Twig-Template (data-ld-mensura-configurator-options).
 */

import { mountConfigurator } from './mount.js';

export default class LdMensuraConfiguratorPlugin extends window.PluginBaseClass {
    static options = {
        productId: null,
        accessKey: null,
        contextToken: null,
        storeApiUrl: '/store-api',
        locale: 'de-DE',
        currency: 'EUR',
        texts: {},
        /** Basis-URL der Fixtures statt Store-API (nur Entwicklung/Testseite) */
        fixtures: null,
        debounceMs: 250,
        offcanvasCartUrl: null,
        cartUrl: null,
    };

    init() {
        this.ready = mountConfigurator(this.el, {
            ...this.options,
            onCartAdded: () => this.openCart(),
        }).then((instance) => {
            this.instance = instance;
            this.$emitter?.publish('ldMensura/ready', { instance });
            return instance;
        });
    }

    openCart() {
        // Offcanvas-Warenkorb des Themes öffnen; ohne ihn zur Warenkorbseite
        const pm = window.PluginManager;
        const instances = pm?.getPluginInstances?.('OffCanvasCart') || [];
        if (instances.length && this.options.offcanvasCartUrl && typeof instances[0].openOffCanvas === 'function') {
            instances[0].openOffCanvas(this.options.offcanvasCartUrl, false);
            return;
        }
        if (this.options.cartUrl) window.location.href = this.options.cartUrl;
    }
}
