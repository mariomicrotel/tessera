<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <nav v-if="links && links.length > 3" class="flex items-center justify-between">
        <div class="flex flex-wrap gap-1">
            <template v-for="(link, i) in links" :key="i">
                <span
                    v-if="!link.url"
                    class="px-3 py-1.5 text-sm rounded border border-gray-200 text-gray-400 bg-white cursor-default select-none"
                    v-html="link.label"
                />
                <Link
                    v-else
                    :href="link.url"
                    class="px-3 py-1.5 text-sm rounded border transition-colors"
                    :class="
                        link.active
                            ? 'bg-indigo-600 border-indigo-600 text-white font-semibold'
                            : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50 hover:border-gray-300'
                    "
                    preserve-scroll
                    v-html="link.label"
                />
            </template>
        </div>
    </nav>
</template>
