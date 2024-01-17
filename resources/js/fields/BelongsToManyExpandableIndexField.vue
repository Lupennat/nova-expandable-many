<template>
    <span :class="`text-${field.textAlign}`">
        <ExpandableField
            :field="field"
            :resource="resource"
            :resource-name="resourceName"
            v-slot="{ expandableOpened, expandableStoreQuery, expandableUseStandardActions }"
        >
            <ExpandableResourceIndex
                :field="field"
                :resource-name="field.resourceName"
                :via-resource="resourceName"
                :via-resource-id="resource.id.value"
                :via-relationship="field.belongsToManyRelationship"
                :relationship-type="'belongsToMany'"
                :load-cards="false"
                :initialPerPage="field.perPage || 5"
                :should-override-meta="false"
                :should-enable-shortcut="false"
                :expandable-opened="expandableOpened"
                :expandable-store-query="expandableStoreQuery"
                :expandable-use-standard-actions="expandableUseStandardActions"
            />
        </ExpandableField>
    </span>
</template>

<script>
    import { mapProps } from '@/mixins';
    import ExpandableField from './ExpandableField';
    import ExpandableResourceIndex from '../views/ExpandableResourceIndex';

    export default {
        props: {
            ...mapProps(['resourceId', 'field']),
            resourceName: {},
            resource: {},
        },
        components: { ExpandableField, ExpandableResourceIndex },
    };
</script>
