<template>
    <div
        class="flex flex-col md:flex-row md:items-center"
        :class="{
            'py-3 border-b border-gray-200 dark:border-gray-700':
                shouldShowCheckboxes || shouldShowDeleteMenu || softDeletes || !viaResource || hasFilters
        }"
    >
        <div class="flex items-center flex-1">
            <div class="md:ml-3">
                <SelectAllDropdown
                    v-if="shouldShowCheckboxes"
                    :all-matching-resource-count="allMatchingResourceCount"
                    :current-page-count="currentPageCount"
                    @toggle-select-all="toggleSelectAll"
                    @toggle-select-all-matching="toggleSelectAllMatching"
                    @deselect="$emit('deselect')"
                />
            </div>

            <IndexSearchInput
                v-if="showSearch"
                :searchable="showSearch"
                v-model:keyword="componentSearch"
                @update:keyword="updateSearch"
            />

            <!-- Toolbar Items -->
            <div class="h-9 ml-auto flex items-center pr-2 md:pr-3">
                <!-- Resource Polling -->
                <Button
                    @click="togglePolling"
                    v-if="shouldShowPollingToggle"
                    icon="clock"
                    variant="link"
                    :state="currentlyPolling ? 'default' : 'mellow'"
                />

                <!-- Filters -->
                <FilterMenu
                    :resource-name="resourceName"
                    :soft-deletes="softDeletes"
                    :via-resource="viaResource"
                    :trashed="trashed"
                    :per-page="perPage"
                    :per-page-options="filterPerPageOptions"
                    @clear-selected-filters="clearSelectedFilters"
                    @filter-changed="filterChanged"
                    @trashed-changed="trashedChanged"
                    @per-page-changed="updatePerPageChanged"
                />

                <DeleteMenu
                    class="flex"
                    v-if="shouldShowDeleteMenu"
                    dusk="delete-menu"
                    :soft-deletes="softDeletes"
                    :resources="resources"
                    :selected-resources="selectedResources"
                    :via-many-to-many="viaManyToMany"
                    :all-matching-resource-count="allMatchingResourceCount"
                    :all-matching-selected="selectAllMatchingChecked"
                    :authorized-to-delete-selected-resources="authorizedToDeleteSelectedResources"
                    :authorized-to-force-delete-selected-resources="authorizedToForceDeleteSelectedResources"
                    :authorized-to-delete-any-resources="authorizedToDeleteAnyResources"
                    :authorized-to-force-delete-any-resources="authorizedToForceDeleteAnyResources"
                    :authorized-to-restore-selected-resources="authorizedToRestoreSelectedResources"
                    :authorized-to-restore-any-resources="authorizedToRestoreAnyResources"
                    @deleteSelected="deleteSelectedResources"
                    @deleteAllMatching="deleteAllMatchingResources"
                    @forceDeleteSelected="forceDeleteSelectedResources"
                    @forceDeleteAllMatching="forceDeleteAllMatchingResources"
                    @restoreSelected="restoreSelectedResources"
                    @restoreAllMatching="restoreAllMatchingResources"
                    @close="closeDeleteModal"
                    :trashed-parameter="trashedParameter"
                    :is-only-trashed="trashed == 'only'"
                />
            </div>
        </div>
    </div>
</template>

<script>
    import { Button } from 'laravel-nova-ui'
    import DeleteMenu from './DeleteMenu';
    import IndexSearchInput from './IndexSearchInput';
    import FilterMenu from '@/components/FilterMenu';

    export default {
        emits: ['start-polling', 'stop-polling', 'deselect', 'searched'],

        components: { Button, DeleteMenu, IndexSearchInput },

        props: [
            'allMatchingResourceCount',
            'authorizedToDeleteAnyResources',
            'authorizedToDeleteSelectedResources',
            'authorizedToForceDeleteAnyResources',
            'authorizedToForceDeleteSelectedResources',
            'authorizedToRestoreAnyResources',
            'authorizedToRestoreSelectedResources',
            'clearSelectedFilters',
            'closeDeleteModal',
            'currentlyPolling',
            'currentPageCount',
            'deleteAllMatchingResources',
            'deleteSelectedResources',
            'filterChanged',
            'forceDeleteAllMatchingResources',
            'forceDeleteSelectedResources',
            'hasFilters',
            'perPage',
            'perPageOptions',
            'resources',
            'resourceInformation',
            'resourceName',
            'restoreAllMatchingResources',
            'restoreSelectedResources',
            'selectAllMatchingChecked',
            'selectedResources',
            'shouldShowCheckboxes',
            'shouldShowDeleteMenu',
            'shouldShowPollingToggle',
            'softDeletes',
            'toggleSelectAll',
            'toggleSelectAllMatching',
            'togglePolling',
            'trashed',
            'trashedChanged',
            'trashedParameter',
            'updatePerPageChanged',
            'viaManyToMany',
            'viaResource',
            'showSearch',
            'search'
        ],

        data() {
            return {
                componentSearch: this.search
            };
        },

        methods: {
            updateSearch(search) {
                this.componentSearch = search;
                this.$emit('searched', this.componentSearch);
            }
        },

        computed: {
            filterPerPageOptions() {
                if (this.resourceInformation) {
                    return this.perPageOptions || this.resourceInformation.perPageOptions;
                }
            }
        }
    };
</script>
