<template>
    <span :class="`text-${field.textAlign}`">
        <ExpandableField :field="field" :resource="resource" v-slot="{ expandableOpened, expandableUid }">
            <ExpandableResourceIndex
                :field="field"
                :resource-name="field.resourceName"
                :via-resource="resourceName"
                :via-resource-id="resource.id.value"
                :via-relationship="field.hasManyThroughRelationship"
                :relationship-type="'hasManyThrough'"
                @actionExecuted="actionExecuted"
                :load-cards="false"
                :initialPerPage="field.perPage || 5"
                :should-override-meta="false"
                :expandable-opened="expandableOpened"
                :expandable-field-id="expandableUid"
                :expandable-resource-id="resource.id.value"
            />
        </ExpandableField>
    </span>
</template>

<script>
    import { mapProps } from '@/mixins';
    import ExpandableField from './ExpandableField';
    import ExpandableResourceIndex from '../views/ExpandableResourceIndex';

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
