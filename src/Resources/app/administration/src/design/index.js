/**
 * Umschaltbares Admin-Design (A · Hafen, B · Druckbogen, C · Kompass) – gleiche Designsprache
 * wie im LieferLotse, aber eigenständig (eigener Store, eigene Klassen mit Präfix `lm-`),
 * damit Mensura ohne LieferLotse läuft und sich die Plugins nicht gegenseitig überschreiben.
 *
 * - Standard für alle: Plugin-Konfiguration `LdMensura.config.adminDesign`
 * - Eigene Wahl je Benutzer: user_config `ld-mensura.admin-design` (Schalter „Design“)
 *
 * Komponenten nutzen das Mixin `lm-design` (`lmDesign`, `lmDesignClass`) und setzen
 * `lmDesignClass` an ihr Wurzelelement; Farben und Varianten stecken in lm-design.scss.
 */
import './lm-design.scss';
import { DESIGNS, DEFAULT_DESIGN } from './constants';

const USER_CONFIG_KEY = 'ld-mensura.admin-design';
const SYSTEM_CONFIG_DOMAIN = 'LdMensura.config';
const SYSTEM_CONFIG_KEY = 'LdMensura.config.adminDesign';

const normalize = (value) => (DESIGNS.includes(value) ? value : null);

Shopware.Store.register({
    id: 'lmDesign',

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
            return Shopware.Store.get('lmDesign').design;
        },

        lmDesignClass() {
            return ['lm-design', `lm-design--${this.lmDesign}`];
        },
    },

    created() {
        Shopware.Store.get('lmDesign').ensureLoaded();
    },
});

Shopware.Component.register('lm-design-switch', () => import('./component/lm-design-switch'));
Shopware.Component.register('lm-status', () => import('./component/lm-status'));
Shopware.Component.register('lm-tabs', () => import('./component/lm-tabs'));
