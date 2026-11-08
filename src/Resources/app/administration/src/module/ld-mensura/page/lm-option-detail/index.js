import { ensureTechnicalName } from '../../service/technical-name';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const TYPES = ['standard', 'delivery', 'format'];
const SCOPES = ['position', 'supplier'];
const DISPLAY_TYPES = ['select', 'tiles', 'radio', 'checkbox', 'number', 'multi'];

/**
 * Optionsgruppe mit ihren Werten (KONZEPT 3.3, Bibliothek). Preise je Wert stehen in der Kalkulation.
 */
export default {
    template: `
<lm-detail-page
    :title="title"
    icon="regular-sliders-v"
    :tabs="tabs"
    :groups="tabGroups"
    v-model:active="activeTab"
    v-model:save-success="isSaveSuccessful"
    :ready="group !== null"
    :is-loading="isLoading"
    :can-edit="canEdit"
    @save="onSave"
    @cancel="onCancel"
>
    <template #header-meta>
        <span>{{ group.technicalName }}</span>
        <span>{{ $tc('ld-mensura.option.types.' + group.type) }}</span>
    </template>
    <template #header-chips>
        <lm-chip v-for="row in group.setGroups" :key="row.id" color="c4" icon="regular-list" :label="row.optionSet?.name || ''" />
    </template>
    <template #header-stats>
        <span class="lm-header__stat"><b>{{ group.values ? group.values.length : 0 }}</b>{{ $tc('ld-mensura.option.detail.tabValues') }}</span>
        <span class="lm-header__stat"><b>{{ group.setGroups ? group.setGroups.length : 0 }}</b>{{ $tc('ld-mensura.areas.optionSet.title') }}</span>
    </template>

    <template #default="{ active }">
        <template v-if="active === 'base'">
            <mt-card position-identifier="lm-option-base" :title="$tc('ld-mensura.option.detail.cardBase')">
                <sw-container columns="repeat(auto-fit, minmax(250px, 1fr))" gap="0 30px">
                    <mt-text-field v-model="group.name" :label="$tc('ld-mensura.option.detail.labelName')" :help-text="$tc('ld-mensura.option.detail.helpName')" :disabled="!canEdit || undefined" required />
                    <mt-text-field v-model="group.technicalName" :label="$tc('ld-mensura.general.labelTechnicalName')" :help-text="$tc('ld-mensura.option.detail.helpTechnicalName')" :disabled="!canEdit || undefined" required />
                    <sw-single-select v-model:value="group.type" :options="typeOptions" :label="$tc('ld-mensura.option.detail.labelType')" :help-text="$tc('ld-mensura.option.detail.helpType')" :disabled="!canEdit || undefined" />
                    <sw-single-select v-model:value="group.displayType" :options="displayOptions" :label="$tc('ld-mensura.option.detail.labelDisplayType')" :help-text="$tc('ld-mensura.option.detail.helpDisplayType')" :disabled="!canEdit || undefined" />
                    <sw-single-select v-model:value="group.scope" :options="scopeOptions" :label="$tc('ld-mensura.option.detail.labelScope')" :help-text="$tc('ld-mensura.option.detail.helpScope')" :disabled="!canEdit || undefined" />
                    <mt-number-field v-model="group.position" number-type="int" :label="$tc('ld-mensura.general.labelPosition')" :help-text="$tc('ld-mensura.option.detail.helpPosition')" :disabled="!canEdit || undefined" />
                </sw-container>
                <mt-textarea v-model="group.helpText" :label="$tc('ld-mensura.option.detail.labelHelpText')" :help-text="$tc('ld-mensura.option.detail.helpHelpText')" :disabled="!canEdit || undefined" />
                <mt-textarea v-model="group.description" :label="$tc('ld-mensura.option.detail.labelDescription')" :help-text="$tc('ld-mensura.option.detail.helpDescription')" :disabled="!canEdit || undefined" />
                <mt-switch v-model="group.required" :label="$tc('ld-mensura.option.detail.labelRequired')" :help-text="$tc('ld-mensura.option.detail.helpRequired')" :disabled="!canEdit || undefined" bordered />
                <mt-switch v-model="group.showPriceHint" :label="$tc('ld-mensura.option.detail.labelShowPriceHint')" :help-text="$tc('ld-mensura.option.detail.helpShowPriceHint')" :disabled="!canEdit || undefined" bordered />
            </mt-card>
        </template>

        <template v-if="active === 'values'">
            <mt-card position-identifier="lm-option-values" :title="$tc('ld-mensura.option.detail.cardValues')">
                <p class="lm-muted lm-card-intro">{{ $tc('ld-mensura.option.detail.introValues') }}</p>
                <sw-data-grid
                    v-if="sortedValues.length"
                    :data-source="sortedValues"
                    :columns="valueColumns"
                    :show-selection="false"
                    :show-actions="true"
                    :allow-inline-edit="false"
                    plain-appearance
                >
                    <template #column-name="{ item }">
                        <a href="#" @click.prevent="editValue = item">{{ item.translated?.name || item.name || item.technicalName }}</a>
                    </template>
                    <template #column-detail="{ item }">
                        <span class="lm-muted">{{ valueDetail(item) }}</span>
                    </template>
                    <template #column-optionSet="{ item }">
                        <span>{{ setName(item.optionSetId) }}</span>
                    </template>
                    <template #actions="{ item }">
                        <sw-context-menu-item @click="editValue = item">{{ $tc('global.default.edit') }}</sw-context-menu-item>
                        <sw-context-menu-item variant="danger" :disabled="!canEdit || undefined" @click="removeValue(item)">{{ $tc('global.default.delete') }}</sw-context-menu-item>
                    </template>
                </sw-data-grid>
                <sw-empty-state v-else :title="$tc('ld-mensura.option.detail.emptyValuesTitle')" :subline="$tc('ld-mensura.option.detail.emptyValuesSubline')" icon="regular-sliders-v" :absolute="false" />
                <mt-button size="small" variant="secondary" :disabled="!canEdit || undefined" @click="addValue">
                    <mt-icon name="regular-plus-circle" size="14px" />
                    {{ $tc('ld-mensura.option.detail.buttonAddValue') }}
                </mt-button>
            </mt-card>
        </template>

        <template v-if="active === 'logistics' && showLogistics">
            <mt-card position-identifier="lm-option-logistics-value" :title="$tc('ld-mensura.logistics.cardValue')">
                <p class="lm-muted lm-card-intro">{{ $tc('ld-mensura.logistics.introValue') }}</p>
                <sw-single-select
                    v-model:value="logisticsValueId"
                    :options="valueOptions"
                    :label="$tc('ld-mensura.logistics.labelValueChoice')"
                    :help-text="$tc('ld-mensura.logistics.helpValueChoice')"
                />
            </mt-card>
            <lm-logistics-card
                v-if="logisticsValue"
                :target="{ optionValueId: logisticsValue.id }"
                :sample="logisticsSample"
                :option-info="{ groupType: group.type, showPriceHint: group.showPriceHint !== false }"
                :keys="{ valueKey: logisticsValue.technicalName }"
            />
        </template>

        <lm-value-modal v-if="editValue" :value="editValue" :group-type="group.type" :disabled="!canEdit" @close="editValue = null" />
    </template>
</lm-detail-page>`,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('discard-detail-page-changes')('group')],

    shortcuts: { 'SYSTEMKEY+S': 'onSave', ESCAPE: 'onCancel' },

    props: {
        groupId: { type: String, required: false, default: null },
    },

    data() {
        return { group: null, isLoading: false, isSaveSuccessful: false, activeTab: 'base', editValue: null, sets: [], logisticsValueId: null };
    },

    metaInfo() {
        return { title: this.$createTitle(this.title) };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('ld_mensura_option_group');
        },

        valueRepository() {
            return this.repositoryFactory.create('ld_mensura_option_value');
        },

        title() {
            if (this.groupId && this.group === null) {
                return this.$tc('ld-mensura.areas.option.title');
            }

            return this.group?.translated?.name || this.group?.name || this.$tc('ld-mensura.option.detail.newTitle');
        },

        canEdit() {
            return this.acl.can(this.groupId ? 'ld_mensura.editor' : 'ld_mensura.creator');
        },

        tabs() {
            return [
                { name: 'base', label: this.$tc('ld-mensura.option.detail.tabBase'), icon: 'regular-file-text', group: 'group' },
                {
                    name: 'values',
                    label: this.$tc('ld-mensura.option.detail.tabValues'),
                    icon: 'regular-list',
                    group: 'group',
                    count: this.group?.values?.length ?? 0,
                    warn: (this.group?.values?.length ?? 0) === 0,
                    warnText: this.$tc('ld-mensura.option.detail.emptyValuesTitle'),
                },
                ...(this.showLogistics ? [{ name: 'logistics', label: this.$tc('ld-mensura.logistics.tab'), icon: 'regular-truck', group: 'group' }] : []),
            ];
        },

        /** Logistikanteil (KONZEPT 3d.6): nur mit eigenem Recht, nur für gespeicherte Werte. */
        showLogistics() {
            return !!this.groupId && this.group?.type !== 'format' && this.acl.can('ld_mensura.logistics');
        },

        valueOptions() {
            return this.sortedValues
                .filter((value) => !value.isNew?.())
                .map((value) => ({ value: value.id, label: value.translated?.name || value.name || value.technicalName }));
        },

        logisticsValue() {
            return this.sortedValues.find((value) => value.id === this.logisticsValueId) ?? null;
        },

        /** Beispielkonfiguration der Vorschau mit diesem Wert (Lieferzeit oder Option). */
        logisticsSample() {
            if (!this.logisticsValue) {
                return {};
            }
            if (this.group.type === 'delivery') {
                return { deliveryValue: this.logisticsValue.technicalName };
            }

            return { options: { [this.group.technicalName]: this.logisticsValue.technicalName } };
        },

        tabGroups() {
            return { group: this.$tc('ld-mensura.option.detail.cardBase') };
        },

        typeOptions() {
            return TYPES.map((value) => ({ value, label: this.$tc(`ld-mensura.option.types.${value}`) }));
        },

        scopeOptions() {
            return SCOPES.map((value) => ({ value, label: this.$tc(`ld-mensura.option.scopes.${value}`) }));
        },

        displayOptions() {
            return DISPLAY_TYPES.map((value) => ({ value, label: this.$tc(`ld-mensura.option.displayTypes.${value}`) }));
        },

        sortedValues() {
            return [...(this.group?.values ?? [])].sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
        },

        valueColumns() {
            return [
                { property: 'name', label: this.$tc('ld-mensura.option.value.labelName'), primary: true },
                { property: 'technicalName', label: this.$tc('ld-mensura.general.labelTechnicalName') },
                { property: 'detail', label: this.$tc('ld-mensura.option.detail.columnDetail') },
                { property: 'optionSet', label: this.$tc('ld-mensura.material.detail.labelOptionSet') },
                { property: 'position', label: this.$tc('ld-mensura.general.labelPosition'), align: 'right' },
            ];
        },
    },

    watch: {
        groupId() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
        this.loadSets();
    },

    methods: {
        createdComponent() {
            this.activeTab = 'base';
            if (this.groupId) {
                this.load();
                return;
            }
            this.group = this.repository.create();
            Object.assign(this.group, { type: 'standard', scope: 'position', displayType: 'select', required: true, showPriceHint: true, position: 0 });
        },

        async load() {
            this.isLoading = true;
            try {
                const criteria = new Criteria(1, 1);
                criteria.addAssociation('values');
                criteria.addAssociation('setGroups.optionSet');
                this.group = await this.repository.get(this.groupId, Shopware.Context.api, criteria);
            } catch {
                this.createNotificationError({ message: this.$tc('global.notification.notificationLoadingDataErrorMessage') });
            } finally {
                this.isLoading = false;
            }
        },

        async loadSets() {
            const result = await this.repositoryFactory.create('ld_mensura_option_set').search(new Criteria(1, 500));
            this.sets = [...result];
        },

        setName(id) {
            return this.sets.find((set) => set.id === id)?.name ?? '–';
        },

        valueDetail(item) {
            if (this.group.type === 'delivery') {
                return item.deliveryDays !== null && item.deliveryDays !== undefined
                    ? this.$tc('ld-mensura.option.detail.days', { days: item.deliveryDays }, item.deliveryDays) : '–';
            }
            if (this.group.type === 'format') {
                return item.width && item.height ? `${item.width} × ${item.height} mm` : '–';
            }

            return item.code || '';
        },

        addValue() {
            const value = this.valueRepository.create();
            const max = Math.max(0, ...this.sortedValues.map((entry) => entry.position ?? 0));
            Object.assign(value, { optionGroupId: this.group.id, position: max + 1, widthAdd: 0, heightAdd: 0, widthFactor: 1, heightFactor: 1 });
            this.group.values.add(value);
            this.editValue = value;
        },

        removeValue(item) {
            this.group.values.remove(item.id);
        },

        warnings() {
            const warnings = [];
            if ((this.group.values?.length ?? 0) === 0) {
                warnings.push(this.$tc('ld-mensura.option.warnings.noValues'));
            }
            if (this.group.type === 'delivery' && [...(this.group.values ?? [])].some((value) => value.deliveryDays === null || value.deliveryDays === undefined)) {
                warnings.push(this.$tc('ld-mensura.option.warnings.noDays'));
            }
            if (this.group.type === 'format' && [...(this.group.values ?? [])].some((value) => !value.width || !value.height)) {
                warnings.push(this.$tc('ld-mensura.option.warnings.noFormat'));
            }

            return warnings;
        },

        async onSave() {
            if (!this.canEdit) {
                return;
            }
            this.isLoading = true;
            ensureTechnicalName(this.group);
            try {
                await this.repository.save(this.group, Shopware.Context.api);
                this.isSaveSuccessful = true;
                this.warnings().forEach((message) => this.createNotificationWarning({ message }));
                if (!this.groupId) {
                    this.$router.push({ name: 'ld.mensura.optionDetail', params: { id: this.group.id } });
                    return;
                }
                await this.load();
            } catch (exception) {
                const detail = JSON.stringify(exception?.response?.data?.errors ?? []);
                this.createNotificationError({
                    message: detail.includes('technical_name')
                        ? this.$tc('ld-mensura.general.errorDuplicateTechnicalName')
                        : this.$tc('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        onCancel() {
            this.$router.push({ name: 'ld.mensura.option' });
        },
    },
};
