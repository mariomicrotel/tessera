<script setup>
import { computed } from 'vue';
import { DocumentArrowDownIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    rendicontiYears: {
        type: Array,
        default: () => [],
    },
    rendicontiUrls: {
        type: Object,
        default: () => ({}),
    },
    sectionStyle: {
        type: Object,
        default: null,
    },
});

const sectionStyleObj = computed(() => {
    const s = props.sectionStyle;
    if (!s || typeof s !== 'object') return {};
    const bgColor = s.bg_color ?? s.bgColor ?? '';
    const textColor = s.text_color ?? s.textColor ?? '';
    const bgImageUrl = s.background_image_url ?? s.backgroundImageUrl ?? '';
    const out = {};
    if (bgColor) out.backgroundColor = bgColor;
    if (textColor) out.color = textColor;
    if (bgImageUrl) {
        out.backgroundImage = `url(${bgImageUrl})`;
        out.backgroundSize = 'cover';
        out.backgroundPosition = 'center';
    }
    return out;
});
</script>

<template>
    <section
        id="rendiconti"
        class="py-16 sm:py-20 bg-white dark:bg-gray-800"
        :style="sectionStyleObj"
    >
        <div class="mx-auto max-w-4xl px-4 sm:px-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">Rendiconti anni precedenti</h2>
            <p class="mt-2 text-gray-600 dark:text-gray-300">
                Scarica il rendiconto economico per cassa (Modello D) per gli anni disponibili.
            </p>
            <div v-if="rendicontiYears.length === 0" class="mt-6 text-gray-500 dark:text-gray-400">
                Nessun rendiconto disponibile.
            </div>
            <ul v-else class="mt-6 grid gap-3 sm:grid-cols-2">
                <li v-for="year in rendicontiYears" :key="year">
                    <a
                        :href="rendicontiUrls[year]"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 px-4 py-3 text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                    >
                        <DocumentArrowDownIcon class="size-5 shrink-0 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                        <span class="font-medium">Rendiconto {{ year }}</span>
                    </a>
                </li>
            </ul>
        </div>
    </section>
</template>
