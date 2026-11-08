import template from './lm-product-profile.html.twig';
import { profileWarnings } from '../../service/profile-checks';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const ALL = '__all';
const DISPLAY_KEYS = ['showPriceHints', 'showArea', 'showPerSqm', 'showPerPiece', 'showFromPrice'];
const PROFILE_FIELDS = [
    'type', 'layout', 'widthMin', 'widthMax', 'heightMin', 'heightMax', 'minQuantity', 'maxQuantity', 'minimumPriceSale',
    'surchargePercent', 'priceComponents', 'defaults', 'steps', 'quantitySteps', 'display',
];

/**
 * Reiter „Mensura“ in der Shopware-Produktdetailseite (KONZEPT 3.6): Produktprofil 1:1 zum Produkt
 * (`product.extensions.ldMensura`). Gespeichert wird mit dem Speichern-Knopf des Produkts.
 *
 * Kalkulationen rechnen, Produkte wählen aus: Materialien über Kalkulationen hinweg, Optionsregeln je Gruppe
 * (übernehmen / einschränken / fest / ausblenden), optional je Material, engere Grenzen, Aufschläge, Vorbelegung,
 * Darstellung im Shop, Testrechner mit dem gespeicherten Stand, Übernahme aus einem anderen Produkt.
 */
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('lm-design')],

    data() {
        return {
            calculations: null,
            groups: null,
            storefrontUrl: null,
            reloadKey: 0,
            showCopy: false,
            copySourceId: null,
            showRemove: false,
            ALL,
        };
    },

    computed: {
        store() {
            return Shopware.Store.get('swProductDetail');
        },

        product() {
            return this.store.product;
        },

        storeLoading() {
            return this.store.isLoading;
        },

        profile() {
            return this.product?.extensions?.ldMensura ?? null;
        },

        canEdit() {
            return this.acl.can('ld_mensura.profiles') && this.acl.can('product.editor');
        },

        profileRepository() {
            return this.repositoryFactory.create('ld_mensura_product_profile');
        },

        ready() {
            return this.calculations !== null && this.groups !== null && this.product?.id;
        },

        isSaved() {
            return this.profile && !this.profile._isNew;
        },

        warnings() {
            return profileWarnings(this.profile);
        },

        calculationMap() {
            return Object.fromEntries((this.calculations ?? []).map((calculation) => [calculation.id, calculation]));
        },

        materialRows() {
            return [...(this.profile?.materials ?? [])].sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
        },

        /** Konkrete erlaubte Materialien (Zeile „alle Materialien“ aufgelöst), ohne Doppelte. */
        allowedMaterials() {
            const seen = new Map();
            this.materialRows.forEach((row) => {
                const calculation = this.calculationMap[row.calculationId];
                const materials = row.materialId
                    ? [...(calculation?.materials ?? [])].filter((material) => material.id === row.materialId)
                    : [...(calculation?.materials ?? [])];
                materials.forEach((material) => {
                    if (!seen.has(material.id)) {
                        seen.set(material.id, { id: material.id, name: this.nameOf(material), calculationName: calculation.name });
                    }
                });
            });

            return [...seen.values()];
        },

        allowedMaterialOptions() {
            return this.allowedMaterials.map((material) => ({ value: material.id, label: `${material.name} · ${material.calculationName}` }));
        },

        /** Gruppen, deren Werte in den gewählten Kalkulationen angeboten werden, mit diesen Werten. */
        relevantGroups() {
            const offered = new Set();
            const calculationIds = new Set(this.materialRows.map((row) => row.calculationId));
            calculationIds.forEach((id) => {
                [...(this.calculationMap[id]?.optionValues ?? [])]
                    .filter((row) => row.active !== false)
                    .forEach((row) => offered.add(row.optionValueId));
            });

            return (this.groups ?? [])
                .map((group) => ({
                    group,
                    values: [...(group.values ?? [])]
                        .filter((value) => offered.has(value.id))
                        .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
                        .map((value) => ({ value: value.id, label: this.nameOf(value) })),
                }))
                .filter((entry) => entry.values.length > 0);
        },

        modeOptions() {
            return ['inherit', 'restrict', 'fixed', 'hidden'].map((mode) => ({ value: mode, label: this.$tc(`ld-mensura.profile.modes.${mode}`) }));
        },

        typeOptions() {
            return ['general', 'special'].map((type) => ({ value: type, label: this.$tc(`ld-mensura.profile.types.${type}`) }));
        },

        layoutOptions() {
            return [
                { value: '', label: this.$tc('ld-mensura.profile.layouts.default') },
                { value: 'compact', label: this.$tc('ld-mensura.profile.layouts.compact') },
                { value: 'guided', label: this.$tc('ld-mensura.profile.layouts.guided') },
            ];
        },

        preselectOptions() {
            return [
                { value: '', label: this.$tc('ld-mensura.profile.options.preselectInherit') },
                { value: 'on', label: this.$tc('ld-mensura.profile.options.preselectOn') },
                { value: 'off', label: this.$tc('ld-mensura.profile.options.preselectOff') },
            ];
        },

        displayOptions() {
            return [
                { value: '', label: this.$tc('ld-mensura.profile.display.default') },
                { value: 'on', label: this.$tc('ld-mensura.profile.display.on') },
                { value: 'off', label: this.$tc('ld-mensura.profile.display.off') },
            ];
        },

        displayKeys() {
            return DISPLAY_KEYS;
        },

        sourceCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.not('AND', [Criteria.equals('ldMensura.id', null)]));
            if (this.product?.id) {
                criteria.addFilter(Criteria.not('AND', [Criteria.equals('id', this.product.id)]));
            }

            return criteria;
        },

        previewUrl() {
            return this.storefrontUrl && this.product?.id ? `${this.storefrontUrl}/detail/${this.product.id}` : null;
        },

        defaults() {
            return this.profile?.defaults ?? {};
        },
    },

    watch: {
        storeLoading(loading, before) {
            // nach dem Speichern des Produkts: Testrechner mit dem neuen Stand
            if (before && !loading) {
                this.reloadKey += 1;
            }
        },
    },

    created() {
        this.loadData();
    },

    methods: {
        async loadData() {
            const calculationCriteria = new Criteria(1, 500);
            calculationCriteria.addAssociation('materials');
            calculationCriteria.addAssociation('optionValues');
            calculationCriteria.addSorting(Criteria.sort('name', 'ASC'));

            const groupCriteria = new Criteria(1, 500);
            groupCriteria.addAssociation('values');
            groupCriteria.addSorting(Criteria.sort('position', 'ASC'));

            const domainCriteria = new Criteria(1, 1);
            domainCriteria.addFilter(Criteria.equals('salesChannel.typeId', '8a243080f92e4c719546314b577cf82b'));

            const [calculations, groups, domains] = await Promise.all([
                this.repositoryFactory.create('ld_mensura_calculation').search(calculationCriteria),
                this.repositoryFactory.create('ld_mensura_option_group').search(groupCriteria),
                this.repositoryFactory.create('sales_channel_domain').search(domainCriteria).catch(() => null),
            ]);
            this.calculations = [...calculations];
            this.groups = [...groups];
            this.storefrontUrl = domains?.first?.()?.url ?? null;
        },

        nameOf(entity) {
            return entity?.translated?.name || entity?.name || entity?.technicalName || '';
        },

        createProfile() {
            const profile = this.profileRepository.create(Shopware.Context.api);
            profile.productId = this.product.id;
            profile.type = 'general';
            profile.active = false;
            this.product.extensions.ldMensura = profile;

            return profile;
        },

        // ---------- Materialien ----------
        childRepository(collection) {
            return this.repositoryFactory.create(collection.entity, collection.source);
        },

        addMaterialRow() {
            const row = this.childRepository(this.profile.materials).create(Shopware.Context.api);
            row.productProfileId = this.profile.id;
            row.calculationId = this.calculations[0]?.id ?? null;
            row.materialId = null;
            row.position = this.materialRows.length;
            row.surchargePercent = null;
            this.profile.materials.add(row);
        },

        removeMaterialRow(row) {
            this.profile.materials.remove(row.id);
        },

        moveMaterialRow(row, step) {
            const rows = this.materialRows;
            const index = rows.indexOf(row);
            const other = rows[index + step];
            if (!other) {
                return;
            }
            rows.forEach((entry, i) => {
                entry.position = i;
            });
            row.position = index + step;
            other.position = index;
        },

        calculationOptions() {
            return (this.calculations ?? []).map((calculation) => ({
                value: calculation.id,
                label: calculation.active === false ? `${calculation.name} (${this.$tc('lm-design.states.off')})` : calculation.name,
            }));
        },

        materialOptionsOf(row) {
            const calculation = this.calculationMap[row.calculationId];

            return [
                { value: ALL, label: this.$tc('ld-mensura.profile.materials.all') },
                ...[...(calculation?.materials ?? [])].map((material) => ({ value: material.id, label: this.nameOf(material) })),
            ];
        },

        setCalculation(row, calculationId) {
            row.calculationId = calculationId;
            row.materialId = null;
        },

        // ---------- Optionsregeln ----------
        ruleOf(groupId, materialId = null) {
            return [...(this.profile?.options ?? [])].find((rule) => rule.optionGroupId === groupId && (rule.materialId ?? null) === materialId) ?? null;
        },

        materialRulesOf(groupId) {
            return [...(this.profile?.options ?? [])].filter((rule) => rule.optionGroupId === groupId && rule.materialId);
        },

        ensureRule(groupId, materialId = null) {
            let rule = this.ruleOf(groupId, materialId);
            if (!rule) {
                rule = this.childRepository(this.profile.options).create(Shopware.Context.api);
                rule.productProfileId = this.profile.id;
                rule.optionGroupId = groupId;
                rule.materialId = materialId;
                rule.mode = 'inherit';
                rule.valueIds = null;
                rule.defaultValueId = null;
                rule.preselect = null;
                rule.position = 0;
                this.profile.options.add(rule);
            }

            return rule;
        },

        /** Allgemeine Regel ohne Wirkung wieder entfernen (übernehmen ohne Standardwert). */
        cleanupRule(rule) {
            if (rule && !rule.materialId && rule.mode === 'inherit' && !rule.defaultValueId && (rule.preselect ?? null) === null) {
                this.profile.options.remove(rule.id);
            }
        },

        modeOf(groupId) {
            return this.ruleOf(groupId)?.mode ?? 'inherit';
        },

        setMode(groupId, mode, materialId = null) {
            const rule = this.ensureRule(groupId, materialId);
            rule.mode = mode;
            if (mode !== 'restrict') {
                rule.valueIds = null;
            }
            this.cleanupRule(rule);
        },

        valueIdsOf(groupId, materialId = null) {
            return this.ruleOf(groupId, materialId)?.valueIds ?? [];
        },

        setValueIds(groupId, ids, materialId = null) {
            const rule = this.ensureRule(groupId, materialId);
            rule.valueIds = ids.length ? ids : null;
            if (rule.defaultValueId && ids.length && !ids.includes(rule.defaultValueId)) {
                rule.defaultValueId = null;
            }
        },

        defaultOf(groupId, materialId = null) {
            return this.ruleOf(groupId, materialId)?.defaultValueId ?? null;
        },

        setDefault(groupId, valueId, materialId = null) {
            const rule = this.ensureRule(groupId, materialId);
            rule.defaultValueId = valueId || null;
            this.cleanupRule(rule);
        },

        /** Vorauswahl der Regel: '' = wie Kalkulation, 'on', 'off' */
        preselectOf(groupId, materialId = null) {
            const value = this.ruleOf(groupId, materialId)?.preselect;

            return value === true ? 'on' : value === false ? 'off' : '';
        },

        setPreselect(groupId, value, materialId = null) {
            const rule = this.ensureRule(groupId, materialId);
            rule.preselect = value === 'on' ? true : value === 'off' ? false : null;
            this.cleanupRule(rule);
        },

        defaultChoices(entry, materialId = null) {
            const rule = this.ruleOf(entry.group.id, materialId);
            if (rule?.mode === 'restrict' && rule.valueIds?.length) {
                return entry.values.filter((value) => rule.valueIds.includes(value.value));
            }

            return entry.values;
        },

        addMaterialRule(groupId) {
            const used = new Set(this.materialRulesOf(groupId).map((rule) => rule.materialId));
            const material = this.allowedMaterials.find((entry) => !used.has(entry.id));
            if (!material) {
                return;
            }
            const rule = this.ensureRule(groupId, material.id);
            rule.mode = this.modeOf(groupId);
        },

        setRuleMaterial(rule, materialId) {
            if (materialId && !this.ruleOf(rule.optionGroupId, materialId)) {
                rule.materialId = materialId;
            }
        },

        removeRule(rule) {
            this.profile.options.remove(rule.id);
        },

        ruleSummary(entry) {
            const rule = this.ruleOf(entry.group.id);
            const mode = rule?.mode ?? 'inherit';
            const label = (id) => entry.values.find((value) => value.value === id)?.label ?? '–';
            if (mode === 'restrict') {
                return (rule.valueIds ?? []).map(label).join(', ');
            }
            if (mode === 'fixed' || mode === 'hidden') {
                return label(rule.defaultValueId);
            }

            return this.$tc('ld-mensura.profile.options.allValues', { count: entry.values.length }, entry.values.length);
        },

        // ---------- Grenzen, Anzeige, Vorbelegung ----------
        setQuantitySteps(values) {
            const steps = [...new Set(values.map((value) => parseInt(value, 10)).filter((value) => value > 0))].sort((a, b) => a - b);
            this.profile.quantitySteps = steps.length ? steps : null;
        },

        displayOf(key) {
            const value = this.profile.display?.[key];

            return value === true ? 'on' : value === false ? 'off' : '';
        },

        setDisplay(key, value) {
            const display = { ...(this.profile.display ?? {}) };
            if (value === 'on' || value === 'off') {
                display[key] = value === 'on';
            } else {
                delete display[key];
            }
            this.profile.display = Object.keys(display).length ? display : null;
        },

        setDefaultField(path, value) {
            const defaults = JSON.parse(JSON.stringify(this.profile.defaults ?? {}));
            if (path === 'width' || path === 'height') {
                defaults.format = { ...(defaults.format ?? {}) };
                if (value) {
                    defaults.format[path] = value;
                } else {
                    delete defaults.format[path];
                }
                if (Object.keys(defaults.format).length === 0) {
                    delete defaults.format;
                }
            } else if (value) {
                defaults[path] = value;
            } else {
                delete defaults[path];
            }
            this.profile.defaults = Object.keys(defaults).length ? defaults : null;
        },

        // ---------- Übernehmen / Entfernen ----------
        async applyCopy() {
            const sourceId = this.copySourceId;
            this.showCopy = false;
            this.copySourceId = null;
            if (!sourceId) {
                return;
            }
            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('productId', sourceId));
            criteria.addAssociation('materials');
            criteria.addAssociation('options');
            const source = (await this.profileRepository.search(criteria)).first();
            if (!source) {
                this.createNotificationError({ message: this.$tc('ld-mensura.profile.copy.notFound') });
                return;
            }

            const profile = this.profile ?? this.createProfile();
            PROFILE_FIELDS.forEach((field) => {
                const value = source[field];
                profile[field] = value !== null && typeof value === 'object' ? JSON.parse(JSON.stringify(value)) : value;
            });
            profile.templateProfileId = source.id;

            [...profile.materials].forEach((row) => profile.materials.remove(row.id));
            [...profile.options].forEach((rule) => profile.options.remove(rule.id));
            [...source.materials].forEach((sourceRow) => {
                const row = this.childRepository(profile.materials).create(Shopware.Context.api);
                Object.assign(row, {
                    productProfileId: profile.id,
                    calculationId: sourceRow.calculationId,
                    materialId: sourceRow.materialId,
                    position: sourceRow.position,
                    surchargePercent: sourceRow.surchargePercent,
                });
                profile.materials.add(row);
            });
            [...source.options].forEach((sourceRule) => {
                const rule = this.childRepository(profile.options).create(Shopware.Context.api);
                Object.assign(rule, {
                    productProfileId: profile.id,
                    optionGroupId: sourceRule.optionGroupId,
                    materialId: sourceRule.materialId,
                    mode: sourceRule.mode,
                    valueIds: sourceRule.valueIds ? [...sourceRule.valueIds] : null,
                    defaultValueId: sourceRule.defaultValueId,
                    preselect: sourceRule.preselect ?? null,
                    position: sourceRule.position,
                });
                profile.options.add(rule);
            });
            this.createNotificationInfo({ message: this.$tc('ld-mensura.profile.copy.applied') });
        },

        async removeProfile() {
            this.showRemove = false;
            const profile = this.profile;
            if (!profile._isNew) {
                try {
                    await this.profileRepository.delete(profile.id, Shopware.Context.api);
                } catch {
                    this.createNotificationError({ message: this.$tc('ld-mensura.profile.remove.error') });
                    return;
                }
            }
            this.product.extensions.ldMensura = null;
            this.createNotificationSuccess({ message: this.$tc('ld-mensura.profile.remove.done') });
        },
    },
};
