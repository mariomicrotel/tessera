<script setup>
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const appVersion = page.props.appVersion || '0.0.0';
</script>

<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-900">
        <div>
            <slot name="logo" />
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg">
            <slot />
        </div>

        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">v{{ appVersion }}</p>
    </div>
</template>
