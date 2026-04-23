<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    title: { type: String, default: '' },
    appName: { type: String, default: '' },
});
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <Head :title="title ? `${title} - ${appName}` : appName" />
        <header class="py-6 px-4 sm:px-6 lg:px-8 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            <div class="max-w-2xl mx-auto text-center">
                <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ appName }}</h1>
            </div>
        </header>
        <main class="py-8 px-4 sm:px-6 lg:px-8">
            <slot />
        </main>
    </div>
</template>
