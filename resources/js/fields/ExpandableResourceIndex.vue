<template>
    <Card v-if="!shouldBeCollapsed">
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

    import { computed } from 'vue';
    import { escapeUnicode } from '@/util/escapeUnicode';

    import { minimum } from '@/util';
    import { mapActions } from 'vuex';

    export default {
        name: 'ExpandableResourceIndex',

        mixins: [Deletable, InteractsWithResourceInformation, SupportsPolling],

        props: {
            ...mapProps(['resourceName', 'viaResource', 'viaResourceId', 'viaRelationship', 'relationshipType']),

            field: {
                type: Object,
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
                authorizedToRestoreAnyResources: computed(() => this.authorizedToRestoreAnyResources)
            };
        },

        data() {
            return {
                allMatchingResourceCount: 0,
                currentPageLoadMore: null,
                page: 1,
                loading: true,
                resourceResponse: null,
                resourceResponseError: null,
                canceller: null,
                resources: [],
                selectedResources: [],
                perPage: this.initialPerPage,
                softDeletes: false,
                sortable: true,
                collapsed: false,
                authorizedToRelate: false,
                orderBy: '',
                orderByDirection: null,
                trashed: '',
                search: '',
                filters: {}
            };
        },

        watch: {
            expandableOpened(value) {
                this.collapsed = !value;
                this.handleCollapsableChange();
            }
        },

        /**
         * Mount the component and retrieve its initial data.
         */
        async created() {
            Nova.$on('refresh-resources', this.getResources);
            this.collapsed = !this.expandableOpened;
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
            async initializeFilters(lens) {
                this.filterHasLoaded = true;
            },

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
                    this.resources.length > 0 && Boolean(_.find(this.resources, resource => resource.authorizedToUpdate))
                );
            },

            /**
             * Determine if the user is authorized to delete any listed resource.
             */
            authorizedToDeleteAnyResources() {
                return (
                    this.resources.length > 0 && Boolean(_.find(this.resources, resource => resource.authorizedToDelete))
                );
            },

            /**
             * Determine if the user is authorized to restore any listed resource.
             */
            authorizedToRestoreAnyResources() {
                return (
                    this.resources.length > 0 && Boolean(_.find(this.resources, resource => resource.authorizedToRestore))
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
                return Math.ceil(this.allMatchingResourceCount / this.currentPerPage)
            },

            /**
             * Get the current search value from the query string.
             */
            currentSearch() {
                return this.search;
            },

            encodedFilters() {
                return btoa(escapeUnicode(JSON.stringify(this.currentFilters)));
            },

            /**
             * The current unencoded filter value payload
             */
            currentFilters() {
                return this.filters;
            },

            /**
             * Return the current filters encoded to a string.
             */
            currentEncodedFilters: (state, getters) => btoa(escapeUnicode(JSON.stringify(getters.currentFilters))),

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
            }
        }
    };
</script>
