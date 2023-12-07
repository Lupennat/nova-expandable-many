<template>
    <div class="w-full md:ml-3 md:w-1/3 relative h-9 md:shrink-0">
        <Icon type="search" width="20" class="absolute ml-2 text-gray-400" :style="{ top: '4px' }" />

        <RoundInput
            dusk="search-input"
            class="appearance-none bg-white dark:bg-gray-800 shadow rounded-full h-8 w-full dark:focus:bg-gray-800"
            :placeholder="__('Search')"
            type="search"
            :value="keyword"
            @input="handleChange"
            spellcheck="false"
            :aria-label="__('Search')"
        />
    </div>
</template>

<script>
    import IndexSearchInput from '@/components/Inputs/IndexSearchInput';

    export default {
        extends: IndexSearchInput
    };
</script>
