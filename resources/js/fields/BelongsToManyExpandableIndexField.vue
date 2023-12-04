<template>
    <div :class="`text-${field.textAlign}`">
        <ExpandableField v-slot="{ expandableOpened, expandableUid }">
            <ExpandableResourceIndex
                :field="field"
                :resource-name="field.resourceName"
                :via-resource="resourceName"
                :via-resource-id="resourceId"
                :via-relationship="field.belongsToManyRelationship"
                :relationship-type="'belongsToMany'"
                @actionExecuted="actionExecuted"
                :load-cards="false"
                :initialPerPage="field.perPage || 5"
                :should-override-meta="false"
                :expandable-opened="expandableOpened"
                :expandable-field-id="expandableUid"
                :expandable-resource-id="resourceId"
            />
        </ExpandableField>
    </div>
</template>

<script>
    import { mapProps } from '@/mixins';
    import ExpandableField from './ExpandableField';
    import ExpandableResourceIndex from './ExpandableResourceIndex';

    export default {
        emits: ['actionExecuted'],

        props: {
            ...mapProps(['resourceId', 'field']),
            resourceName: {},
            resource: {}
        },
        components: { ExpandableField, ExpandableResourceIndex },

        methods: {
            /**
             * Handle the actionExecuted event and pass it up the chain.
             */
            actionExecuted() {
                this.$emit('actionExecuted');
            }
        }
    };
</script>
