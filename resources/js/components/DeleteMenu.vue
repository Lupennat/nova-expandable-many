<script>
    import DeleteMenu from '@/components/DeleteMenu';

    export default {
        extends: DeleteMenu,
        props: ['isOnlyTrashed'],
        computed: {
            trashedOnlyMode() {
                return this.isOnlyTrashed;
            },
        },
    };
</script>
