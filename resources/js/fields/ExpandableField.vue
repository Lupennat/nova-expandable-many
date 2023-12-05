<template>
    <div>
        <span class="flex items-center" @click.stop="expandableToogle">
            <span>{{ expandableOpened ? __(field.expandableHideLabel) : __(field.expandableShowLabel) }}</span>
            <button
                class="rounded border border-transparent h-6 w-6 ml-1 inline-flex items-center justify-center focus:outline-none focus:ring ring-primary-200"
                :aria-label="expandableOpened ? __(field.expandableHideLabel) : __(field.expandableShowLabel)"
                :aria-expanded="!expandableOpened ? 'true' : 'false'"
            >
                <CollapseButton :collapsed="!expandableOpened" />
            </button>
        </span>
        <Teleport :to="expandableRow" v-if="isMounted">
            <td :colspan="columnCount">
                <div class="py-4 px-2">
                    <slot
                        :expandable-opened="expandableOpened"
                        :expandable-store-query="shouldStoreQueryStringRelations"
                    ></slot>
                </div>
            </td>
        </Teleport>
    </div>
</template>

<script>
    import { RouteParameters } from '@/mixins';
    import InteractsWithQueryString from '../mixins/InteractsWithQueryString';

    export default {
        props: ['field', 'resource', 'resourceName'],

        mixins: [RouteParameters, InteractsWithQueryString],

        data() {
            return {
                rowExpanded: false,
                expandableRow: null,
                columnCount: 1,
                isMounted: false,
                openedField: '',
                openedId: ''
            };
        },
        watch: {},
        created() {
            this.openedField = this.currentOpenedField;
            this.openedId = this.currentOpenedId;
            Nova.$on('expandable-opened', (openedField, openedId) => {
                this.openedField = openedField;
                this.openedId = openedId;
            });
            this.$watch('expandableOpened', function (value) {
                this.expandableRow.classList[value ? 'remove' : 'add']('hidden');
            });
        },
        unmounted() {
            this.isMounted = false;
        },
        mounted() {
            this.$nextTick(() => {
                const tr = this.$el.parentElement.closest('tr');
                this.columnCount = tr.querySelectorAll('td').length || this.columnCount;
                const expandableRow = document.createElement('tr');
                expandableRow.classList.add('bg-gray-100', 'dark:bg-gray-700');
                if (!this.expandableOpened) {
                    expandableRow.classList.add('hidden');
                }
                tr.insertAdjacentElement('afterend', expandableRow);
                this.expandableRow = expandableRow;
                this.isMounted = true;
            });
        },
        methods: {
            expandableToogle() {
                const openedField = this.expandableOpened ? '' : this.field.resourceName;
                const openedId = this.expandableOpened ? '' : this.resource.id.value;
                this.updateQueryStringIfRequired(openedField, openedId);
                Nova.$emit('expandable-opened', openedField, openedId);
            },
            updateQueryStringIfRequired(openedField, openedId) {
                if (this.shouldStoreQueryStringAccordion) {
                    this.updateQueryString({
                        [this.expandableFieldParameter]: openedField,
                        [this.expandableIdParameter]: openedId
                    });
                }
            }
        },
        computed: {
            shouldStoreQueryStringAccordion() {
                return this.field.expandableStoreStatus === 'accordion' || this.shouldStoreQueryStringRelations;
            },
            shouldStoreQueryStringRelations() {
                return this.field.expandableStoreStatus === 'full';
            },
            expandableFieldParameter() {
                return `${this.resourceName}_expfield`;
            },
            expandableIdParameter() {
                return `${this.resourceName}_expid`;
            },
            expandableOpened() {
                return this.openedField == this.field.resourceName && this.openedId == this.resource.id.value;
            },
            currentOpenedId() {
                return this.route.params[this.expandableIdParameter] || '';
            },
            currentOpenedField() {
                return this.route.params[this.expandableFieldParameter] || '';
            }
        }
    };
</script>
