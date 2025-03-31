<template>
</template>

<script>
    import { Button } from 'laravel-nova-ui';
    import DeleteMenu from './DeleteMenu';
    import IndexSearchInput from './IndexSearchInput';

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
            'search',
        ],

        data() {
            return {
                componentSearch: this.search,
            };
        },

        methods: {
            updateSearch(search) {
                this.componentSearch = search;
                this.$emit('searched', this.componentSearch);
            },
        },

        computed: {
            /**
             * Return the filters from state
             */
            filters() {
                return this.$store.getters[`${this.resourceName}/filters`];
            },

            /**
             * Determine via state whether filters are applied
             */
            filtersAreApplied() {
                return this.$store.getters[`${this.resourceName}/filtersAreApplied`];
            },

            /**
             * Return the number of active filters
             */
            activeFilterCount() {
                return this.$store.getters[`${this.resourceName}/activeFilterCount`];
            },

            filterPerPageOptions() {
                if (this.resourceInformation) {
                    return this.perPageOptions || this.resourceInformation.perPageOptions;
                }
            },
        },
    };
</script>
