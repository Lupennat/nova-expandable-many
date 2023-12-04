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
                    :selected-resources="selectedResources"
                    :selected-resource-ids="selectedResourceIds"
                    :actions-are-available="allActions.length > 0"
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
                    :per-page="perPage"
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
    import {
        HasCards,
        Paginatable,
        PerPageable,
        Deletable,
        LoadsResources,
        IndexConcerns,
        InteractsWithResourceInformation,
        InteractsWithQueryString,
        SupportsPolling
    } from '@/mixins';
    import { minimum } from '@/util';
    import { mapActions } from 'vuex';

    export default {
        name: 'ExpandableResourceIndex',

        mixins: [
            Deletable,
            HasCards,
            Paginatable,
            PerPageable,
            LoadsResources,
            IndexConcerns,
            InteractsWithResourceInformation,
            InteractsWithQueryString,
            SupportsPolling
        ],

        props: {
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
                type: String,
                required: true
            }
        },

        data: () => ({
            lenses: [],
            sortable: true,
            actionCanceller: null,
            collapsed: false
        }),

        watch: {
            expandableOpened(value) {
                this.collapsed = value;
                this.handleCollapsableChange();
            }
        },

        /**
         * Mount the component and retrieve its initial data.
         */
        async created() {
            if (!this.resourceInformation) return;

            Nova.$on('refresh-resources', this.getResources);

            this.collapsed = !this.expandableOpened;
        },

        /**
         * Unbind the keydown even listener when the before component is destroyed
         */
        beforeUnmount() {
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
                    this.clearResourceSelections();

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
             * Get the actions available for the current resource.
             */
            getActions() {
                this.actions = [];
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
                    Nova.emit('expandable-row-opened', this.expandableResourceId, this.expandableFieldId);
                    if (!this.filterHasLoaded) {
                        await this.initializeFilters(null);
                        if (!this.hasFilters) {
                            await this.getResources();
                        }
                    } else {
                        await this.getResources();
                    }

                    await this.getAuthorizationToRelate();
                    await this.getActions();
                    this.restartPolling();
                } else {
                    this.loading = false;
                }
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
            }
        }
    };
</script>
