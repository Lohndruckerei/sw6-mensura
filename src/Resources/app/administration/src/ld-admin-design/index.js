/* GENERIERT aus ld-admin-design 1.0.0 (bd4dc06) – nicht hier ändern, sondern in ~/projekte/ld-admin-design/vorlage und ./sync.sh ausführen. */
/**
 * Lohndruckerei Admin-Design (A · Hafen, B · Druckbogen, C · Kompass) für LdMensura.
 *
 * - Standard für alle: Plugin-Konfiguration `LdMensura.config.adminDesign` (a|b|c)
 * - Eigene Wahl je Benutzer: user_config `ld-mensura.admin-design` (Schalter „Design“)
 *
 * Komponenten nutzen das Mixin `lm-design` (`lmDesign`, `lmDesignClass`) und setzen
 * `lmDesignClass` an ihr Wurzelelement. Bausteine: lm-design-switch, lm-status,
 * lm-chip, lm-transport-chip, lm-tabs; Klassen siehe design.scss und DESIGN.md.
 */
import './design.scss';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';
import { DESIGNS, DEFAULT_DESIGN } from './constants';

const STORE_ID = 'lmDesign';
const USER_CONFIG_KEY = 'ld-mensura.admin-design';
const SYSTEM_CONFIG_DOMAIN = 'LdMensura.config';
const SYSTEM_CONFIG_KEY = 'LdMensura.config.adminDesign';

const normalize = (value) => (DESIGNS.includes(value) ? value : null);

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

Shopware.Store.register({
    id: STORE_ID,

    state: () => ({
        design: DEFAULT_DESIGN,
        defaultDesign: DEFAULT_DESIGN,
        userDesign: null,
        userConfigId: null,
        loading: null,
    }),

    actions: {
        ensureLoaded() {
            if (!this.loading) {
                this.loading = this.load();
            }

            return this.loading;
        },

        async load() {
            try {
                const values = await Shopware.Service('systemConfigApiService').getValues(SYSTEM_CONFIG_DOMAIN);
                this.defaultDesign = normalize(values?.[SYSTEM_CONFIG_KEY]) ?? DEFAULT_DESIGN;
            } catch {
                // Ohne Leserecht auf die Systemkonfiguration gilt der eingebaute Standard.
                this.defaultDesign = DEFAULT_DESIGN;
            }

            try {
                const userConfig = await this.findUserConfig();
                this.userConfigId = userConfig?.id ?? null;
                this.userDesign = normalize(userConfig?.value?.design);
            } catch {
                this.userDesign = null;
            }

            this.design = this.userDesign ?? this.defaultDesign;
        },

        async findUserConfig() {
            const userId = Shopware.Store.get('session').currentUser?.id;
            if (!userId) {
                return null;
            }

            const { Criteria } = Shopware.Data;
            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('key', USER_CONFIG_KEY));
            criteria.addFilter(Criteria.equals('userId', userId));

            const result = await this.repository().search(criteria, Shopware.Context.api);

            return result.first();
        },

        /**
         * Speichert die Wahl für den aktuellen Benutzer. `null` = zurück zum Standard.
         */
        async setDesign(design) {
            const value = normalize(design);
            this.userDesign = value;
            this.design = value ?? this.defaultDesign;

            const userId = Shopware.Store.get('session').currentUser?.id;
            if (!userId) {
                return;
            }

            const repository = this.repository();
            let entity = this.userConfigId ? await repository.get(this.userConfigId, Shopware.Context.api) : null;
            if (!entity) {
                entity = repository.create(Shopware.Context.api);
                entity.key = USER_CONFIG_KEY;
                entity.userId = userId;
            }
            entity.value = { design: value };

            await repository.save(entity, Shopware.Context.api);
            this.userConfigId = entity.id;
        },

        repository() {
            return Shopware.Service('repositoryFactory').create('user_config');
        },
    },
});

Shopware.Mixin.register('lm-design', {
    computed: {
        lmDesign() {
            return Shopware.Store.get(STORE_ID).design;
        },

        lmDesignClass() {
            return ['lm-design', `lm-design--${this.lmDesign}`];
        },
    },

    created() {
        Shopware.Store.get(STORE_ID).ensureLoaded();
    },
});

Shopware.Component.register('lm-design-switch', () => import('./component/design-switch'));
Shopware.Component.register('lm-status', () => import('./component/status'));
Shopware.Component.register('lm-chip', () => import('./component/chip'));
Shopware.Component.register('lm-transport-chip', () => import('./component/transport-chip'));
Shopware.Component.register('lm-tabs', () => import('./component/tabs'));
