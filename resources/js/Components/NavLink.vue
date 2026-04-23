<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: String,
    active: Boolean,
});

const classes = computed(() => {
    return props.active
        ? 'block w-full flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md border-l-4 border-indigo-400 dark:border-indigo-600 text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-gray-700/50 focus:outline-none transition duration-150 ease-in-out'
        : 'block w-full flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md border-l-4 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 focus:outline-none transition duration-150 ease-in-out';
});
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
