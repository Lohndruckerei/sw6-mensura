const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * Optionsset (KONZEPT 3.7): Gruppen in Reihenfolge, Pflicht je Set; Verwendung als Start-Set (Kalkulation) bzw.
 * Folge-Set (Material, Optionswert).
 */
export default {
    template: `
<lm-detail-page
    :title="title"
    icon="regular-list"
    v-model:save-success="isSaveSuccessful"
    :ready="set !== null"
    :is-loading="isLoading"
    :can-edit="canEdit"
    @save="onSave"
    @cancel="$router.push({ name: 'ld.mensura.optionSet' })"
>
    <template #header-meta>
        <span>{{ set.technicalName }}</span>
    </template>
    <template #header-chips>
        <lm-chip v-for="row in sortedRows" :key="row.id" color="c1" icon="regular-sliders-v" :label="groupName(row)" />
    </template>
    <template #header-stats>
        <span class="lm-header__stat"><b>{{ sortedRows.length }}</b>{{ $tc('ld-mensura.optionSet.detail.statGroups') }}</span>
        <span class="lm-header__stat" :class="{ 'is--warn': usageCount === 0 }"><b>{{ usageCount }}</b>{{ $tc('ld-mensura.optionSet.detail.statUsage') }}</span>
    </template>

    <lm-info-card name="option-set" :title="$tc('ld-mensura.optionSet.list.info.title')">
        {{ $tc('ld-mensura.optionSet.list.info.text') }}
    </lm-info-card>

    <mt-card position-identifier="lm-set-base" :title="$tc('ld-mensura.optionSet.detail.cardBase')">
        <sw-container columns="repeat(auto-fit, minmax(250px, 1fr))" gap="0 30px">
            <mt-text-field v-model="set.name" :label="$tc('ld-mensura.optionSet.detail.labelName')" :help-text="$tc('ld-mensura.optionSet.detail.helpName')" :disabled="!canEdit || undefined" required />
            <mt-text-field v-model="set.technicalName" :label="$tc('ld-mensura.general.labelTechnicalName')" :help-text="$tc('ld-mensura.optionSet.detail.helpTechnicalName')" :disabled="!canEdit || undefined" required />
        </sw-container>
        <mt-textarea v-model="set.description" :label="$tc('ld-mensura.optionSet.detail.labelDescription')" :help-text="$tc('ld-mensura.optionSet.detail.helpDescription')" :disabled="!canEdit || undefined" />
    </mt-card>

    <mt-card position-identifier="lm-set-groups" :title="$tc('ld-mensura.optionSet.detail.cardGroups')">
        <p class="lm-muted lm-card-intro">{{ $tc('ld-mensura.optionSet.detail.introGroups') }}</p>
        <div v-for="(row, index) in sortedRows" :key="row.id" class="lm-set-row">
            <span class="lm-set-row__pos">{{ index + 1 }}</span>
            <span class="lm-set-row__name">
                <b>{{ groupName(row) }}</b>
                <small class="lm-muted">{{ row.optionGroup?.technicalName }}</small>
            </span>
            <sw-single-select
                :value="requiredValue(row)"
                :options="requiredOptions"
                :disabled="!canEdit || undefined"
                @update:value="setRequired(row, $event)"
            />
            <mt-button size="small" variant="secondary" square :aria-label="$tc('ld-mensura.optionSet.detail.moveUp')" :disabled="!canEdit || index === 0 || undefined" @click="move(index, -1)">
                <mt-icon name="regular-chevron-up-s" size="14px" />
            </mt-button>
            <mt-button size="small" variant="secondary" square :aria-label="$tc('ld-mensura.optionSet.detail.moveDown')" :disabled="!canEdit || index === sortedRows.length - 1 || undefined" @click="move(index, 1)">
                <mt-icon name="regular-chevron-down-s" size="14px" />
            </mt-button>
            <mt-button size="small" variant="secondary" square :aria-label="$tc('ld-mensura.components.remove')" :disabled="!canEdit || undefined" @click="removeRow(row)">
                <mt-icon name="regular-trash" size="14px" />
            </mt-button>
        </div>
        <p v-if="sortedRows.length === 0" class="lm-muted">{{ $tc('ld-mensura.optionSet.detail.emptyGroups') }}</p>
        <sw-entity-single-select
            :value="null"
            entity="ld_mensura_option_group"
            label-property="name"
            :criteria="addCriteria"
            :label="$tc('ld-mensura.optionSet.detail.labelAddGroup')"
            :help-text="$tc('ld-mensura.optionSet.detail.helpAddGroup')"
            :disabled="!canEdit || undefined"
            @update:value="addGroup"
        />
    </mt-card>

    <mt-card v-if="setId" position-identifier="lm-set-usage" :title="$tc('ld-mensura.optionSet.detail.cardUsage')">
        <p class="lm-muted lm-card-intro">{{ $tc('ld-mensura.optionSet.detail.introUsage') }}</p>
        <ul class="lm-usage">
            <li v-for="item in set.calculations" :key="item.id">
                <lm-chip color="c1" icon="regular-euro" :label="$tc('ld-mensura.optionSet.detail.usageStart')" /> {{ item.name }}
            </li>
            <li v-for="item in set.materials" :key="item.id">
                <lm-chip color="c2" icon="regular-layer-group" :label="$tc('ld-mensura.optionSet.detail.usageMaterial')" />
                <router-link :to="{ name: 'ld.mensura.materialDetail', params: { id: item.id } }">{{ item.translated?.name || item.technicalName }}</router-link>
            </li>
            <li v-for="item in set.optionValues" :key="item.id">
                <lm-chip color="c3" icon="regular-sliders-v" :label="$tc('ld-mensura.optionSet.detail.usageValue')" />
                <router-link :to="{ name: 'ld.mensura.optionDetail', params: { id: item.optionGroupId } }">{{ item.translated?.name || item.technicalName }}</router-link>
            </li>
        </ul>
        <p v-if="usageCount === 0" class="lm-muted">{{ $tc('ld-mensura.optionSet.usage.none') }}</p>
    </mt-card>
</lm-detail-page>`,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('discard-detail-page-changes')('set')],

    shortcuts: { 'SYSTEMKEY+S': 'onSave', ESCAPE: 'onCancel' },

    props: {
        setId: { type: String, required: false, default: null },
    },

    data() {
        return { set: null, isLoading: false, isSaveSuccessful: false };
    },

    metaInfo() {
        return { title: this.$createTitle(this.title) };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('ld_mensura_option_set');
        },

        rowRepository() {
            return this.repositoryFactory.create('ld_mensura_option_set_group');
        },

        title() {
            if (this.setId && this.set === null) {
                return this.$tc('ld-mensura.areas.optionSet.title');
            }

            return this.set?.name || this.$tc('ld-mensura.optionSet.detail.newTitle');
        },

        canEdit() {
            return this.acl.can(this.setId ? 'ld_mensura.editor' : 'ld_mensura.creator');
        },

        sortedRows() {
            return [...(this.set?.groups ?? [])].sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
        },

        usageCount() {
            return (this.set?.calculations?.length ?? 0) + (this.set?.materials?.length ?? 0) + (this.set?.optionValues?.length ?? 0);
        },

        addCriteria() {
            const criteria = new Criteria(1, 25);
            const used = this.sortedRows.map((row) => row.optionGroupId);
            if (used.length) {
                criteria.addFilter(Criteria.not('AND', [Criteria.equalsAny('id', used)]));
            }
            criteria.addFilter(Criteria.equals('type', 'standard'));

            return criteria;
        },

        requiredOptions() {
            return [
                { value: 'inherit', label: this.$tc('ld-mensura.optionSet.detail.requiredInherit') },
                { value: 'yes', label: this.$tc('ld-mensura.optionSet.detail.requiredYes') },
                { value: 'no', label: this.$tc('ld-mensura.optionSet.detail.requiredNo') },
            ];
        },
    },

    watch: {
        setId() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.setId) {
                this.load();
                return;
            }
            this.set = this.repository.create();
        },

        async load() {
            this.isLoading = true;
            try {
                const criteria = new Criteria(1, 1);
                criteria.addAssociation('groups.optionGroup');
                criteria.addAssociation('calculations');
                criteria.addAssociation('materials');
                criteria.addAssociation('optionValues');
                this.set = await this.repository.get(this.setId, Shopware.Context.api, criteria);
            } catch {
                this.createNotificationError({ message: this.$tc('global.notification.notificationLoadingDataErrorMessage') });
            } finally {
                this.isLoading = false;
            }
        },

        groupName(row) {
            return row.optionGroup?.translated?.name || row.optionGroup?.name || row.optionGroup?.technicalName || '';
        },

        requiredValue(row) {
            if (row.required === null || row.required === undefined) {
                return 'inherit';
            }

            return row.required ? 'yes' : 'no';
        },

        setRequired(row, value) {
            row.required = value === 'inherit' ? null : value === 'yes';
        },

        renumber(rows) {
            rows.forEach((row, index) => {
                row.position = (index + 1) * 10;
            });
        },

        move(index, direction) {
            const rows = [...this.sortedRows];
            const [row] = rows.splice(index, 1);
            rows.splice(index + direction, 0, row);
            this.renumber(rows);
        },

        async addGroup(groupId) {
            if (!groupId) {
                return;
            }
            const row = this.rowRepository.create();
            row.optionSetId = this.set.id;
            row.optionGroupId = groupId;
            row.position = (this.sortedRows.length + 1) * 10;
            row.optionGroup = await this.repositoryFactory.create('ld_mensura_option_group').get(groupId);
            this.set.groups.add(row);
        },

        removeRow(row) {
            this.set.groups.remove(row.id);
            this.renumber(this.sortedRows);
        },

        async onSave() {
            if (!this.canEdit) {
                return;
            }
            this.isLoading = true;
            try {
                await this.repository.save(this.set, Shopware.Context.api);
                this.isSaveSuccessful = true;
                if (this.sortedRows.length === 0) {
                    this.createNotificationWarning({ message: this.$tc('ld-mensura.optionSet.warnings.noGroups') });
                }
                if (!this.setId) {
                    this.$router.push({ name: 'ld.mensura.optionSetDetail', params: { id: this.set.id } });
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
            this.$router.push({ name: 'ld.mensura.optionSet' });
        },
    },
};
