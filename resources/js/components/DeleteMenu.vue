<script>
    import DeleteMenu from '@/components/DeleteMenu';

    export default {
        extends: DeleteMenu,
        props: [
            'allMatchingResourceCount',
            'allMatchingSelected',
            'authorizedToDeleteAnyResources',
            'authorizedToDeleteSelectedResources',
            'authorizedToForceDeleteAnyResources',
            'authorizedToForceDeleteSelectedResources',
            'authorizedToRestoreAnyResources',
            'authorizedToRestoreSelectedResources',
            'resources',
            'selectedResources',
            'show',
            'softDeletes',
            'isOnlyTrashed',
            'viaManyToMany',
        ],
        computed: {
            trashedOnlyMode() {
                return this.isOnlyTrashed;
            },
        },
    };
</script>
