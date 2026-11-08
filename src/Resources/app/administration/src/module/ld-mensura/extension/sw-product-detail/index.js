import template from './sw-product-detail.html.twig';
import { profileWarnings } from '../../service/profile-checks';

/**
 * Shopware-Produktdetailseite: Reiter „Mensura“, Produktprofil mitladen (wird mit dem Produkt gespeichert)
 * und beim Speichern auf Lücken im Profil hinweisen (KONZEPT 3a Punkt 5 – warnt, blockiert nicht).
 */
Shopware.Component.override('sw-product-detail', {
    template,

    computed: {
        lmCanSeeProfile() {
            return Shopware.Service('acl').can('ld_mensura.viewer');
        },

        productCriteria() {
            const criteria = this.$super('productCriteria');
            if (this.lmCanSeeProfile) {
                criteria.addAssociation('ldMensura.materials');
                criteria.addAssociation('ldMensura.options');
            }

            return criteria;
        },
    },

    methods: {
        onSave() {
            const profile = this.product?.extensions?.ldMensura;
            profileWarnings(profile).forEach((warning) => {
                this.createNotificationWarning({
                    title: this.$tc('ld-mensura.profile.tab'),
                    message: this.$tc(`ld-mensura.profile.warnings.${warning.key}`),
                });
            });

            return this.$super('onSave');
        },
    },
});
