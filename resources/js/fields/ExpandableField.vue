<template>
    <span class="flex items-center" @click="expandableOpened = !expandableOpened">
        <span>{{ expandableOpened ? __(field.expandableHideLabel) : __(field.expandableShowLabel) }}</span>
        <button
            class="rounded border border-transparent h-6 w-6 ml-1 inline-flex items-center justify-center focus:outline-none focus:ring ring-primary-200"
            :aria-label="expandableOpened ? __(field.expandableHideLabel) : __(field.expandableShowLabel)"
            :aria-expanded="!expandableOpened ? 'true' : 'false'"
        >
            <CollapseButton :collapsed="!expandableOpened" />
        </button>
    </span>
    <Teleport :to="expandableRow">
        <td :colspan="columnCount">
            <div class="px-4">
                <slot :expandable-opened="expandableOpened" :expandable-uid="uid"></slot>
            </div>
        </td>
    </Teleport>
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
            uid: uid
        }),
        computed() {},
        created() {
            Nova.$on('expandable-row-opened', (resourceId, uid) => {
                if (this.resource.id === id && uid !== this.uid) {
                    this.expandableOpened = false;
                }
            });
        },
        mounted() {
            this.$nextTick(() => {
                const indexField = this.$refs.indexField;
                const tr = indexField.closest('tr');
                this.columnCount = (tr.querySelectorAll('td').length || 1) - 1;
                const expandableRow = document.createElement('tr');
                expandableRow.classList.add('bg-gray-100', 'dark:bg-gray-700');
                tr.insertAdjacentElement('afterend', expandableRow);
                this.expandableRow = expandableRow;
            });
        }
    };
</script>
