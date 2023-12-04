<template>
    <div>
        <span class="flex items-center" @click.stop="expandableOpened = !expandableOpened">
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
                    <slot :expandable-opened="expandableOpened" :expandable-uid="uid"></slot>
                </div>
            </td>
        </Teleport>
    </div>
</template>

<script>
    import { uid } from 'uid/single';

    export default {
        props: ['field', 'resource'],

        data: () => ({
            rowExpanded: false,
            expandableRow: null,
            expandableOpened: false,
            columnCount: 1,
            uid: uid(),
            isMounted: false
        }),
        watch: {
            expandableOpened(value) {
                this.expandableRow.classList[value ? 'remove' : 'add']('hidden');
            }
        },
        created() {
            Nova.$on('expandable-row-opened', (resourceId, uid) => {
                if (this.resource.id.value !== resourceId || uid !== this.uid) {
                    this.expandableOpened = false;
                }
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
                expandableRow.classList.add('hidden', 'bg-gray-100', 'dark:bg-gray-700');
                tr.insertAdjacentElement('afterend', expandableRow);
                this.expandableRow = expandableRow;
                this.isMounted = true;
            });
        }
    };
</script>
