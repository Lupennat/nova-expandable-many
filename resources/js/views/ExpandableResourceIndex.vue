<template>
    <Card v-if="!shouldBeCollapsed">
        <ResourceTableToolbar
            v-if="filterHasLoaded"
            :all-matching-resource-count="allMatchingResourceCount"
            :authorized-to-delete-any-resources="authorizedToDeleteAnyResources"
            :authorized-to-delete-selected-resources="authorizedToDeleteSelectedResources"
            :authorized-to-force-delete-any-resources="authorizedToForceDeleteAnyResources"
            :authorized-to-force-delete-selected-resources="authorizedToForceDeleteSelectedResources"
            :authorized-to-restore-any-resources="authorizedToRestoreAnyResources"
            :authorized-to-restore-selected-resources="authorizedToRestoreSelectedResources"
            :clear-selected-filters="clearSelectedFilters"
            :close-delete-modal="closeDeleteModal"
            :currently-polling="currentlyPolling"
            :current-page-count="resources.length"
            :delete-all-matching-resources="deleteAllMatchingResources"
            :delete-selected-resources="deleteSelectedResources"
            :filter-changed="filterChanged"
            :force-delete-all-matching-resources="forceDeleteAllMatchingResources"
            :force-delete-selected-resources="forceDeleteSelectedResources"
            :has-filters="hasFilters"
            :per-page="perPage"
            :per-page-options="perPageOptions"
            :resources="resources"
            :resource-information="resourceInformation"
            :resource-name="resourceName"
            :restore-all-matching-resources="restoreAllMatchingResources"
            :restore-selected-resources="restoreSelectedResources"
            :select-all-matching-checked="selectAllMatchingResources"
            @deselect="clearResourceSelections"
            :selected-resources="selectedResources"
            :should-show-check-boxes="shouldShowCheckBoxes"
            :should-show-delete-menu="shouldShowDeleteMenu"
            :should-show-polling-toggle="shouldShowPollingToggle"
            :soft-deletes="softDeletes"
            @start-polling="startPolling"
            @stop-polling="stopPolling"
            :toggle-select-all="toggleSelectAll"
            :toggle-select-all-matching="toggleSelectAllMatching"
            :trashed="trashed"
            :trashed-changed="trashedChanged"
            :update-per-page-changed="updatePerPageChanged"
            :via-has-one="false"
            :via-many-to-many="viaManyToMany"
            :via-resource="viaResource"
            :show-search="showSearch"
            :search="search"
            @searched="performLazySearch"
        />

        <LoadingView :loading="loading">
            <IndexErrorDialog
                v-if="resourceResponseError != null"
                :resource="resourceInformation"
                @click="getResources"
            />

            <template v-else>
                <IndexEmptyDialog
                    v-if="!loading && !resources.length"
                    :create-button-label="createButtonLabel"
                    :singular-name="singularName"
                    :resource-name="resourceName"
                    :via-resource="viaResource"
                    :via-resource-id="viaResourceId"
                    :via-relationship="viaRelationship"
                    :relationship-type="relationshipType"
                    :authorized-to-create="authorizedToCreate"
                    :authorized-to-relate="authorizedToRelate"
                />

                <ResourceTable
                    :authorized-to-relate="authorizedToRelate"
                    :resource-name="resourceName"
                    :resources="resources"
                    :singular-name="singularName"
                    :selected-resources="selectedResources"
                    :selected-resource-ids="selectedResourceIds"
                    :actions-are-available="false"
                    :should-show-checkboxes="shouldShowCheckBoxes"
                    :via-resource="viaResource"
                    :via-resource-id="viaResourceId"
                    :via-relationship="viaRelationship"
                    :relationship-type="relationshipType"
                    :update-selection-status="updateSelectionStatus"
                    :sortable="sortable"
                    @order="orderByField"
                    @reset-order-by="resetOrderBy"
                    @delete="deleteResources"
                    @restore="restoreResources"
                    @actionExecuted="getResources"
                    ref="resourceTable"
                />

                <ResourcePagination
                    v-if="shouldShowPagination"
                    :pagination-component="paginationComponent"
                    :has-next-page="hasNextPage"
                    :has-previous-page="hasPreviousPage"
                    :load-more="loadMore"
                    :select-page="selectPage"
                    :total-pages="totalPages"
                    :current-page="page"
                    :per-page="perPage"
                    :resource-count-label="resourceCountLabel"
                    :current-resource-count="resourceCount"
                    :all-matching-resource-count="allMatchingResourceCount"
                />
            </template>
        </LoadingView>
    </Card>
</template>
<script>
    import { CancelToken, isCancel } from 'axios';
    import { Deletable, InteractsWithResourceInformation, SupportsPolling, mapProps, InteractsWithQueryString } from '@/mixins';
    import ResourceTable from '../components/ResourceTable';
    import ResourceTableToolbar from '../components/ResourceTableToolbar';

    import { computed } from 'vue';

    import { minimum } from '@/util';
    import { mapActions } from 'vuex';

    export default {
        name: 'ExpandableResourceIndex',

        mixins: [
            Deletable,
            InteractsWithResourceInformation,
            SupportsPolling,
            InteractsWithQueryString
        ],

        components: { ResourceTable, ResourceTableToolbar },

        props: {
            ...mapProps(['resourceName', 'viaResource', 'viaResourceId', 'viaRelationship', 'relationshipType']),

            field: {
                type: Object
            },
            initialPerPage: {
                type: Number,
                default: 25
            },
            shouldOverrideMeta: {
                type: Boolean,
                default: false
            },
            expandableOpened: {
                type: Boolean,
                required: true
            },
            expandableStoreQuery: {
                type: Boolean,
                required: true
            }
        },

        provide() {
            return {
                authorizedToViewAnyResources: computed(() => this.authorizedToViewAnyResources),
                authorizedToUpdateAnyResources: computed(() => this.authorizedToUpdateAnyResources),
                authorizedToDeleteAnyResources: computed(() => this.authorizedToDeleteAnyResources),
                authorizedToRestoreAnyResources: computed(() => this.authorizedToRestoreAnyResources),
                selectedResourcesCount: computed(() => this.selectedResources.length),
                selectAllChecked: computed(() => this.selectAllChecked),
                selectAllMatchingChecked: computed(() => this.selectAllMatchingChecked),
                selectAllOrSelectAllMatchingChecked: computed(() => this.selectAllOrSelectAllMatchingChecked),
                selectAllAndSelectAllMatchingChecked: computed(() => this.selectAllAndSelectAllMatchingChecked),
                selectAllIndeterminate: computed(() => this.selectAllIndeterminate)
            };
        },

        data() {
            return {
                allMatchingResourceCount: 0,
                pageLoadMore: null,
                deleteModalOpen: false,
                lens: false,
                page: 1,
                loading: true,
                resourceResponse: null,
                resourceResponseError: null,
                canceller: null,
                resources: [],
                selectedResources: [],
                selectAllMatchingResources: false,
                perPage: 25,
                softDeletes: false,
                sortable: true,
                collapsed: false,
                authorizedToRelate: false,
                orderBy: '',
                orderByDirection: '',
                trashed: '',
                search: '',
                filterHasLoaded: false,
                filterIsActive: false,
                watchersEnabled: false
            };
        },

        watch: {
            expandableOpened(value) {
                if (!value) {
                    this.disableWatchers();
                    this.stopPolling();
                    this.resetToDefaults();
                }
                this.collapsed = !value;
                this.handleCollapsableChange();
            }
        },

        /**
         * Mount the component and retrieve its initial data.
         */
        created() {
            Nova.$on('refresh-resources', this.getResources);
            this.collapsed = !this.expandableOpened;

            this.registerWatchers();

            if (!this.collapsed) {
                this.initializeExpandable(true);
            }
        },

        /**
         * Unbind the keydown even listener when the before component is destroyed
         */
        beforeUnmount() {
            if (this.canceller !== null) {
                this.canceller();
            }

            Nova.$off('refresh-resources', this.getResources);
        },

        methods: {
            registerWatchers() {
                this.$watch('watchHook', () => {
                    if (this.watchersEnabled) {
                        if (this.page === 1) {
                            this.pageLoadMore = null;
                        }

                        this.getResources();
                    }
                });
            },

            ...mapActions(['fetchPolicies']),

            updateQueryStringIfRequired(object) {
                if (this.expandableStoreQuery) {
                    this.updateOnlyNecessaryQueryString(object);
                }
            },

            updateOnlyNecessaryQueryString(object) {
                object = Object.keys(object)
                    .filter(key => {
                        switch (key) {
                            case this.pageParameter:
                                return this.queryStringPageParameterValue != object[key];
                            case this.filterParameter:
                                return this.queryStringFilterParameterValue != object[key];
                            case this.orderByParameter:
                                return this.queryStringOrderByParameterValue != object[key];
                            case this.orderByDirectionParameter:
                                return this.queryStringOrderByDirectionParameterValue != object[key];
                            case this.trashedParameter:
                                return this.queryStringTrashedParameterValue != object[key];
                            case this.searchParameter:
                                return this.queryStringSearchParameterValue != object[key];
                            case this.perPageParameter:
                                return this.queryStringPerPageParameterValue != object[key];
                            default:
                                return false;
                        }
                    })
                    .reduce((carry, key) => {
                        carry[key] = object[key];
                        return carry;
                    }, {});

                if (Object.keys(object).length) {
                    this.updateQueryString(object);
                }
            },

            async initializeExpandable(onCreated) {
                if (onCreated) {
                    this.initializeSearch();
                    this.initializePage();
                    this.initializeTrashed();
                    this.initializeOrdering();
                }
                await this.initializeFilters(onCreated);
                await this.getResources();
                await this.getAuthorizationToRelate();
                this.enableWatchers();
            },

            enableWatchers() {
                this.watchersEnabled = true;
            },

            disableWatchers() {
                this.watchersEnabled = false;
            },

            /**
             * Get the resources based on the current page, search, filters, etc.
             */
            getResources() {
                if (this.shouldBeCollapsed) {
                    this.loading = false;
                    return;
                }

                this.loading = true;
                this.resourceResponseError = null;

                this.$nextTick(() => {
                    this.clearResourceSelections();

                    if (this.canceller !== null) {
                        this.canceller();
                    }
                    return minimum(
                        Nova.request().get('/nova-api/' + this.resourceName, {
                            params: this.resourceRequestQueryString,
                            cancelToken: new CancelToken(canceller => {
                                this.canceller = canceller;
                            })
                        }),
                        300
                    )
                        .then(({ data }) => {
                            this.resources = [];

                            this.resourceResponse = data;
                            this.resources = data.resources;
                            this.softDeletes = data.softDeletes;
                            this.perPage = data.per_page;
                            this.sortable = data.sortable;

                            this.handleResourcesLoaded();
                        })
                        .catch(e => {
                            if (isCancel(e)) {
                                return;
                            }

                            this.loading = false;
                            this.resourceResponseError = e;

                            throw e;
                        })
                        .finally(() => {
                            this.updateQueryStringIfRequired({
                                [this.pageParameter]: this.page == 1 ? '' : this.page,
                                [this.filterParameter]: this.encodedFilters,
                                [this.orderByParameter]: this.orderBy,
                                [this.orderByDirectionParameter]: this.orderByDirection,
                                [this.trashedParameter]: this.trashed,
                                [this.searchParameter]: this.search,
                                [this.perPageParameter]: this.perPage == (this.initialPerPage || 25) ? '' : this.perPage
                            });
                        });
                });
            },

            handleResourcesLoaded() {
                this.loading = false;

                if (this.resourceResponse.total !== null) {
                    this.allMatchingResourceCount = this.resourceResponse.total;
                } else {
                    this.getAllMatchingResourceCount();
                }

                Nova.$emit('resources-loaded', {
                    resourceName: this.resourceName,
                    mode: this.isRelation ? 'related' : 'index'
                });

                this.initializePolling();
            },

            /**
             * Get the relatable authorization status for the resource.
             */
            getAuthorizationToRelate() {
                if (
                    this.shouldBeCollapsed ||
                    (!this.authorizedToCreate &&
                        this.relationshipType !== 'belongsToMany' &&
                        this.relationshipType !== 'morphToMany')
                ) {
                    return;
                }

                if (!this.viaResource) {
                    return (this.authorizedToRelate = true);
                }

                return Nova.request()
                    .get(
                        '/nova-api/' +
                            this.resourceName +
                            '/relate-authorization' +
                            '?viaResource=' +
                            this.viaResource +
                            '&viaResourceId=' +
                            this.viaResourceId +
                            '&viaRelationship=' +
                            this.viaRelationship +
                            '&relationshipType=' +
                            this.relationshipType
                    )
                    .then(response => {
                        this.authorizedToRelate = response.data.authorized;
                    });
            },

            /**
             * Get the count of all of the matching resources.
             */
            getAllMatchingResourceCount() {
                Nova.request()
                    .get('/nova-api/' + this.resourceName + '/count', {
                        params: this.resourceRequestQueryString
                    })
                    .then(response => {
                        this.allMatchingResourceCount = response.data.count;
                    });
            },

            /**
             * Load more resources.
             */
            loadMore() {
                if (this.pageLoadMore === null) {
                    this.pageLoadMore = this.page;
                }

                this.pageLoadMore = this.pageLoadMore + 1;

                return minimum(
                    Nova.request().get('/nova-api/' + this.resourceName, {
                        params: {
                            ...this.resourceRequestQueryString,
                            page: this.pageLoadMore // We do this to override whatever page number is in the URL
                        }
                    }),
                    300
                ).then(({ data }) => {
                    this.resourceResponse = data;
                    this.resources = [...this.resources, ...data.resources];

                    if (data.total !== null) {
                        this.allMatchingResourceCount = data.total;
                    } else {
                        this.getAllMatchingResourceCount();
                    }

                    Nova.$emit('resources-loaded', {
                        resourceName: this.resourceName,
                        mode: this.isRelation ? 'related' : 'index'
                    });
                });
            },

            async handleCollapsableChange() {
                this.loading = true;

                if (!this.collapsed) {
                    await this.initializeExpandable();
                    this.restartPolling();
                } else {
                    this.loading = false;
                }
            },

            /*
             * Update the resource selection status
             */
            updateSelectionStatus(resource) {
                if (!_.includes(this.selectedResources, resource)) {
                    this.selectedResources.push(resource);
                } else {
                    const index = this.selectedResources.indexOf(resource);
                    if (index > -1) this.selectedResources.splice(index, 1);
                }

                this.selectAllMatchingResources = false;
            },

            /**
             * Sort the resources by the given field.
             */
            orderByField(field) {
                let direction = this.orderByDirection == 'asc' ? 'desc' : 'asc';

                if (this.orderBy != field.sortableUriKey) {
                    direction = 'asc';
                }

                this.orderByDirection = direction;
                this.orderBy = field.sortableUriKey;
            },

            /**
             * Reset the order by to its default state
             */
            resetOrderBy(field) {
                this.orderByDirection = '';
                this.orderBy = field.sortableUriKey;
            },

            /**
             * Select the next page.
             */
            selectPage(page) {
                this.page = page;
            },

            /**
             * Clear filters and reset the resource table
             */
            async clearSelectedFilters() {
                await this.clearFiltersStore();

                this.page = 1;

                Nova.$emit('filter-reset');
            },

            async clearFiltersStore() {
                await this.$store.dispatch(`${this.resourceName}/resetFilterState`, {
                    resourceName: this.resourceName
                });
            },

            /**
             * Handle a filter state change.
             */
            filterChanged() {
                let filtersAreApplied = this.$store.getters[`${this.resourceName}/filtersAreApplied`];

                if (filtersAreApplied || this.filterIsActive) {
                    this.filterIsActive = true;
                    this.page = 1;
                }
            },

            /**
             * Sync the current search value from the query string.
             */
            initializeSearch() {
                this.search = this.queryStringSearchParameterValue;
            },

            /**
             * Sync the per page values from the query string.
             */
            initializePage() {
                this.page = Number(this.queryStringPageParameterValue || 1);
                this.perPage = Number(this.queryStringPerPageParameterValue || this.initialPerPage || 25);
            },

            /**
             * Sync the trashed state values from the query string.
             */
            initializeTrashed() {
                this.trashed = this.queryStringTrashedParameterValue;
            },

            /**
             * Sync the current order by values from the query string.
             */
            initializeOrdering() {
                this.orderBy = this.queryStringOrderByParameterValue;
                this.orderByDirection = this.queryStringOrderByDirectionParameterValue;
            },

            /**
             * Set up filters for the current view
             */
            async initializeFilters(onCreated) {
                if (this.filterHasLoaded === true) {
                    return;
                }

                // Clear out the filters from the store first
                this.$store.commit(`${this.resourceName}/clearFilters`);

                await this.$store.dispatch(
                    `${this.resourceName}/fetchFilters`,
                    _.pickBy(
                        {
                            resourceName: this.resourceName,
                            viaResource: this.viaResource,
                            viaResourceId: this.viaResourceId,
                            viaRelationship: this.viaRelationship,
                            relationshipType: this.relationshipType
                        },
                        _.identity
                    )
                );

                await this.initializeState(onCreated);

                this.filterHasLoaded = true;
            },

            /**
             * Initialize the filter state
             */
            async initializeState(onCreated) {
                (onCreated || this.expandableStoreQuery) && this.queryStringFilterParameterValue
                    ? await this.$store.dispatch(
                          `${this.resourceName}/initializeCurrentFilterValuesFromQueryString`,
                          this.queryStringFilterParameterValue
                      )
                    : await this.$store.dispatch(`${this.resourceName}/resetFilterState`, {
                          resourceName: this.resourceName
                      });
            },

            /**
             * Clear the selected resouces and the "select all" states.
             */
            clearResourceSelections() {
                this.selectAllMatchingResources = false;
                this.selectedResources = [];
            },

            /**
             * Toggle the selection of all matching resources in the database
             */
            toggleSelectAllMatching(e) {
                e.preventDefault();

                if (!this.selectAllMatchingResources) {
                    this.selectAllResources();
                    this.selectAllMatchingResources = true;
                } else {
                    this.selectAllMatchingResources = false;
                }
            },

            /**
             * Toggle the selection of all resources
             */
            toggleSelectAll(e) {
                e.preventDefault();

                if (this.selectAllChecked) {
                    this.clearResourceSelections();
                } else {
                    this.selectAllResources();
                }
            },

            /**
             * Select all of the available resources
             */
            selectAllResources() {
                this.selectedResources = this.resources.slice(0);
            },

            /**
             * Close the delete modal.
             */
            closeDeleteModal() {
                this.deleteModalOpen = false;
            },

            /**
             * Update the trashed constraint for the resource listing.
             */
            trashedChanged(trashedStatus) {
                this.trashed = trashedStatus;
            },

            /**
             * Update the per page parameter in the query string
             */
            updatePerPageChanged(perPage) {
                this.perPage = perPage;
            },

            resetToDefaults() {
                this.filterHasLoaded = false;
                this.clearFiltersStore();
                this.search = '';
                this.page = 1;
                this.perPage = 25;
                this.orderBy = '';
                this.orderByDirection = '';
                this.trashed = '';

                // always reset filters also on query string if necessary
                this.updateOnlyNecessaryQueryString({
                    [this.pageParameter]: '',
                    [this.filterParameter]: '',
                    [this.orderByParameter]: '',
                    [this.orderByDirectionParameter]: '',
                    [this.trashedParameter]: '',
                    [this.searchParameter]: '',
                    [this.perPageParameter]: ''
                });
            },

            performLazySearch(search) {
                const debouncer = _.debounce(callback => callback(), this.resourceInformation.debounce);
                debouncer(() => {
                    this.performSearch(search);
                });
            },

            /**
             * Execute a search against the resource.
             */
            performSearch(search) {
                this.search = search;
            }
        },

        computed: {
            /**
             * Get the endpoint for this resource's metrics.
             */
            cardsEndpoint() {
                return `/nova-api/${this.resourceName}/cards`;
            },

            /**
             * Build the resource request query string.
             */
            resourceRequestQueryString() {
                return {
                    search: this.search,
                    filters: this.encodedFilters,
                    orderBy: this.orderBy,
                    orderByDirection: this.orderByDirection,
                    perPage: this.perPage,
                    trashed: this.trashed,
                    page: this.page,
                    viaResource: this.viaResource,
                    viaResourceId: this.viaResourceId,
                    viaRelationship: this.viaRelationship,
                    viaResourceRelationship: this.viaResourceRelationship,
                    relationshipType: this.relationshipType
                };
            },

            /**
             * Get the query string for a deletable resource request.
             */
            deletableQueryString() {
                return {
                    search: this.search,
                    filters: this.encodedFilters,
                    trashed: this.trashed,
                    viaResource: this.viaResource,
                    viaResourceId: this.viaResourceId,
                    viaRelationship: this.viaRelationship
                };
            },

            /**
             * Determine whether the user is authorized to perform actions on the delete menu
             */
            canShowDeleteMenu() {
                return Boolean(
                    this.authorizedToDeleteSelectedResources ||
                        this.authorizedToForceDeleteSelectedResources ||
                        this.authorizedToRestoreSelectedResources ||
                        this.selectAllMatchingChecked
                );
            },

            /**
             * Determine if the index view should be collapsed.
             */
            shouldBeCollapsed() {
                return this.collapsed;
            },

            ariaExpanded() {
                return this.collapsed === false ? 'true' : 'false';
            },

            /**
             * Determine if the index is a relation field
             */
            isRelation() {
                return Boolean(this.viaResourceId && this.viaRelationship);
            },

            /**
             * Determine if there are any resources for the view
             */
            hasResources() {
                return Boolean(this.resources.length > 0);
            },

            /**
             * Determine whether the delete menu should be shown to the user
             */
            shouldShowDeleteMenu() {
                return Boolean(this.selectedResources.length > 0) && this.canShowDeleteMenu;
            },

            /**
             * Determine if any selected resources may be deleted.
             */
            authorizedToDeleteSelectedResources() {
                return Boolean(_.find(this.selectedResources, resource => resource.authorizedToDelete));
            },

            /**
             * Determine if any selected resources may be force deleted.
             */
            authorizedToForceDeleteSelectedResources() {
                return Boolean(_.find(this.selectedResources, resource => resource.authorizedToForceDelete));
            },

            /**
             * Determine if the user is authorized to view any listed resource.
             */
            authorizedToViewAnyResources() {
                return (
                    this.resources.length > 0 && Boolean(_.find(this.resources, resource => resource.authorizedToView))
                );
            },

            /**
             * Determine if the user is authorized to view any listed resource.
             */
            authorizedToUpdateAnyResources() {
                return (
                    this.resources.length > 0 &&
                    Boolean(_.find(this.resources, resource => resource.authorizedToUpdate))
                );
            },

            /**
             * Determine if the user is authorized to force delete any listed resource.
             */
            authorizedToForceDeleteAnyResources() {
                return (
                    this.resources.length > 0 &&
                    Boolean(_.find(this.resources, resource => resource.authorizedToForceDelete))
                );
            },

            /**
             * Determine if any selected resources may be restored.
             */
            authorizedToRestoreSelectedResources() {
                return Boolean(_.find(this.selectedResources, resource => resource.authorizedToRestore));
            },

            /**
             * Determine if the user is authorized to delete any listed resource.
             */
            authorizedToDeleteAnyResources() {
                return (
                    this.resources.length > 0 &&
                    Boolean(_.find(this.resources, resource => resource.authorizedToDelete))
                );
            },

            /**
             * Determine if the user is authorized to restore any listed resource.
             */
            authorizedToRestoreAnyResources() {
                return (
                    this.resources.length > 0 &&
                    Boolean(_.find(this.resources, resource => resource.authorizedToRestore))
                );
            },

            /**
             * Return the pagination component for the resource.
             */
            paginationComponent() {
                return `pagination-${Nova.config('pagination') || 'links'}`;
            },

            /**
             * Determine if the resources has a next page.
             */
            hasNextPage() {
                return Boolean(this.resourceResponse && this.resourceResponse.next_page_url);
            },

            /**
             * Determine if the resources has a previous page.
             */
            hasPreviousPage() {
                return Boolean(this.resourceResponse && this.resourceResponse.prev_page_url);
            },

            /**
             * The per-page options configured for this resource.
             */
            perPageOptions() {
                if (this.resourceResponse) {
                    return this.resourceResponse.per_page_options;
                }
            },

            /**
             * Return the resource count label
             */
            resourceCountLabel() {
                const first = this.perPage * (this.page - 1);

                return (
                    this.resources.length &&
                    `${Nova.formatNumber(first + 1)}-${Nova.formatNumber(first + this.resources.length)} ${this.__(
                        'of'
                    )} ${Nova.formatNumber(this.allMatchingResourceCount)}`
                );
            },

            /**
             * Return the current count of all resources
             */
            resourceCount() {
                return this.resources.length;
            },

            /**
             * Return the total pages for the resource.
             */
            totalPages() {
                return Math.ceil(this.allMatchingResourceCount / this.perPage);
            },

            /**
             * Get the default label for the create button
             */
            createButtonLabel() {
                if (this.resourceInformation) return this.resourceInformation.createButtonLabel;

                return this.__('Create');
            },

            /**
             * Get the singular name for the resource
             */
            singularName() {
                if (this.isRelation && this.field) {
                    return _.capitalize(this.field.singularLabel);
                }

                if (this.resourceInformation) {
                    return _.capitalize(this.resourceInformation.singularLabel);
                }
            },

            /**
             * Determine whether the pagination component should be shown.
             */
            shouldShowPagination() {
                return this.resourceResponse && (this.hasResources || this.hasPreviousPage);
            },

            /**
             * Determine if the resource has any filters
             */
            hasFilters() {
                return this.$store.getters[`${this.resourceName}/hasFilters`];
            },

            /**
             * Return the currently encoded filter string from the store
             */
            encodedFilters() {
                return this.$store.getters[`${this.resourceName}/currentEncodedFilters`];
            },

            /**
             * Determine whether to show the selection checkboxes for resources
             */
            shouldShowCheckBoxes() {
                return (
                    Boolean(this.hasResources) && Boolean(this.authorizedToDeleteAnyResources || this.canShowDeleteMenu)
                );
            },

            /**
             * Determine if Select All Dropdown state is indeterminate.
             */
            selectAllIndeterminate() {
                return (
                    Boolean(this.selectAllChecked || this.selectAllMatchingChecked) &&
                    Boolean(!this.selectAllAndSelectAllMatchingChecked)
                );
            },

            selectAllAndSelectAllMatchingChecked() {
                return this.selectAllChecked && this.selectAllMatchingChecked;
            },

            /**
             * Determine if all matching resources are selected.
             */
            selectAllMatchingChecked() {
                return this.selectAllMatchingResources;
            },

            /**
             * Get the IDs for the selected resources.
             */
            selectedResourceIds() {
                return _.map(this.selectedResources, resource => resource.id.value);
            },

            /**
             * Determine if all resources are selected on the page.
             */
            selectAllChecked() {
                return this.selectedResources.length == this.resources.length;
            },

            selectAllOrSelectAllMatchingChecked() {
                return this.selectAllChecked || this.selectAllMatchingChecked;
            },

            showSearch() {
                return this.resourceInformation && this.resourceInformation.searchable;
            },

            /**
             * Determine if the current resource listing is via a many-to-many relationship.
             */
            viaManyToMany() {
                return this.relationshipType == 'belongsToMany' || this.relationshipType == 'morphToMany';
            },

            watchHook() {
                return (
                    this.encodedFilters +
                    this.search +
                    this.page +
                    this.perPage +
                    this.orderBy +
                    this.orderByDirection +
                    this.trashed
                );
            },

            /**
             * Get the name of the page query string variable.
             */
            pageParameter() {
                return this.viaRelationship ? this.viaRelationship + '_page' : this.resourceName + '_page';
            },

            /**
             * Get the name of the filter query string variable.
             */
            filterParameter() {
                return this.resourceName + '_filter';
            },

            /**
             * Get the name of the order by query string variable.
             */
            orderByParameter() {
                return this.viaRelationship ? this.viaRelationship + '_order' : this.resourceName + '_order';
            },

            /**
             * Get the name of the order by direction query string variable.
             */
            orderByDirectionParameter() {
                return this.viaRelationship ? this.viaRelationship + '_direction' : this.resourceName + '_direction';
            },

            /**
             * Get the name of the trashed constraint query string variable.
             */
            trashedParameter() {
                return this.viaRelationship ? this.viaRelationship + '_trashed' : this.resourceName + '_trashed';
            },

            /**
             * Get the name of the search query string variable.
             */
            searchParameter() {
                return this.viaRelationship ? this.viaRelationship + '_search' : this.resourceName + '_search';
            },

            /**
             * Get the name of the per page query string variable.
             */
            perPageParameter() {
                return this.viaRelationship ? this.viaRelationship + '_per_page' : this.resourceName + '_per_page';
            },

            queryStringPageParameterValue() {
                return this.queryStringParams[this.pageParameter] || '';
            },

            queryStringFilterParameterValue() {
                return this.queryStringParams[this.filterParameter] || '';
            },

            queryStringOrderByParameterValue() {
                return this.queryStringParams[this.orderByParameter] || '';
            },

            queryStringOrderByDirectionParameterValue() {
                return this.queryStringParams[this.orderByDirectionParameter] || '';
            },

            queryStringTrashedParameterValue() {
                return this.queryStringParams[this.trashedParameter] || '';
            },

            queryStringSearchParameterValue() {
                return this.queryStringParams[this.searchParameter] || '';
            },

            queryStringPerPageParameterValue() {
                return this.queryStringParams[this.perPageParameter] || '';
            }
        }
    };
</script>
