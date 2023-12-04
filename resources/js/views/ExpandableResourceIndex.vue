<template>
    <Card v-if="!shouldBeCollapsed">
        <ResourceTableToolbar
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
            :per-page="currentPerPage"
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
                    :current-page="currentPage"
                    :per-page="currentPerPage"
                    :resource-count-label="resourceCountLabel"
                    :current-resource-count="currentResourceCount"
                    :all-matching-resource-count="allMatchingResourceCount"
                />
            </template>
        </LoadingView>
    </Card>
</template>
<script>
    import { CancelToken, isCancel } from 'axios';
    import { Deletable, InteractsWithResourceInformation, SupportsPolling, mapProps } from '@/mixins';
    import ResourceTable from '../components/ResourceTable';
    import ResourceTableToolbar from '../components/ResourceTableToolbar';

    import { computed } from 'vue';

    import { minimum } from '@/util';
    import { mapActions } from 'vuex';

    export default {
        name: 'ExpandableResourceIndex',

        mixins: [Deletable, InteractsWithResourceInformation, SupportsPolling],

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
            expandableFieldId: {
                type: String,
                required: true
            },
            expandableResourceId: {
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
                currentPageLoadMore: null,
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
                perPage: this.initialPerPage,
                softDeletes: false,
                sortable: true,
                collapsed: false,
                authorizedToRelate: false,
                orderBy: '',
                orderByDirection: null,
                trashed: '',
                search: '',
                filterHasLoaded: false,
                filterIsActive: false
            };
        },

        watch: {
            expandableOpened(value) {
                this.collapsed = !value;
                if (this.filterHasLoaded) {
                    this.clearSelectedFilters();
                }
                this.handleCollapsableChange();
            }
        },

        /**
         * Mount the component and retrieve its initial data.
         */
        async created() {
            Nova.$on('refresh-resources', this.getResources);
            this.collapsed = !this.expandableOpened;
            this.$watch(
                () => {
                    return (
                        this.encodedFilters +
                        this.currentSearch +
                        this.currentPage +
                        this.currentPerPage +
                        this.currentOrderBy +
                        this.currentOrderByDirection +
                        this.currentTrashed
                    );
                },
                () => {
                    if (this.currentPage === 1) {
                        this.currentPageLoadMore = null;
                    }
                    if (!this.collapsed) {
                        this.getResources();
                    }
                }
            );
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
            ...mapActions(['fetchPolicies']),

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
                        });
                });
            },

            handleResourcesLoaded() {
                this.loading = false;

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
                if (this.currentPageLoadMore === null) {
                    this.currentPageLoadMore = this.currentPage;
                }

                this.currentPageLoadMore = this.currentPageLoadMore + 1;

                return minimum(
                    Nova.request().get('/nova-api/' + this.resourceName, {
                        params: {
                            ...this.resourceRequestQueryString,
                            page: this.currentPageLoadMore // We do this to override whatever page number is in the URL
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
                    Nova.$emit('expandable-row-opened', this.expandableResourceId, this.expandableFieldId);

                    if (!this.filterHasLoaded) {
                        await this.initializeFilters(null);
                    }
                    await this.getResources();

                    await this.getAuthorizationToRelate();
                    this.restartPolling();
                } else {
                    this.loading = false;
                }
            },

            updateSelectionStatus() {},

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
                this.orderByDirection = null;
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
                await this.$store.dispatch(`${this.resourceName}/resetFilterState`, {
                    resourceName: this.resourceName
                });

                this.page = 1;

                Nova.$emit('filter-reset');
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
             * Set up filters for the current view
             */
            async initializeFilters() {
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

                await this.initializeState();

                this.filterHasLoaded = true;
            },

            /**
             * Initialize the filter state
             */
            async initializeState() {
                await this.$store.dispatch(`${this.resourceName}/resetFilterState`, {
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
                    search: this.currentSearch,
                    filters: this.encodedFilters,
                    orderBy: this.currentOrderBy,
                    orderByDirection: this.currentOrderByDirection,
                    perPage: this.currentPerPage,
                    trashed: this.currentTrashed,
                    page: this.currentPage,
                    viaResource: this.viaResource,
                    viaResourceId: this.viaResourceId,
                    viaRelationship: this.viaRelationship,
                    viaResourceRelationship: this.viaResourceRelationship,
                    relationshipType: this.relationshipType
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
                const first = this.perPage * (this.currentPage - 1);

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
            currentResourceCount() {
                return this.resources.length;
            },

            /**
             * Get the current trashed constraint value from the query string.
             */
            currentTrashed() {
                return this.trashed;
            },

            /**
             * Get the current order by value from the query string.
             */
            currentOrderBy() {
                return this.orderBy;
            },

            /**
             * Get the current order by direction from the query string.
             */
            currentOrderByDirection() {
                return this.orderByDirection;
            },

            /**
             * Get the current page from the query string.
             */
            currentPage() {
                return this.page;
            },

            /**
             * Get the current per page value from the query string.
             */
            currentPerPage() {
                return this.perPage;
            },

            /**
             * Return the total pages for the resource.
             */
            totalPages() {
                return Math.ceil(this.allMatchingResourceCount / this.currentPerPage);
            },

            /**
             * Get the current search value from the query string.
             */
            currentSearch() {
                return this.search;
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

            /**
             * Determine if the current resource listing is via a many-to-many relationship.
             */
            viaManyToMany() {
                return this.relationshipType == 'belongsToMany' || this.relationshipType == 'morphToMany';
            }
        }
    };
</script>
