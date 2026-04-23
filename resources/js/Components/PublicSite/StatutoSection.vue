<script setup>
import { computed } from 'vue';
import { DocumentTextIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    statutoUrl: {
        type: String,
        default: null,
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
        id="statuto"
        class="py-16 sm:py-20 bg-gray-50 dark:bg-gray-900"
        :style="sectionStyleObj"
    >
        <div class="mx-auto max-w-4xl px-4 sm:px-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">Statuto</h2>
            <p v-if="!statutoUrl" class="mt-4 text-gray-500 dark:text-gray-400">
                Lo statuto non è al momento disponibile per il download.
            </p>
            <a
                v-else
                :href="statutoUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-4 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-medium text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
            >
                <DocumentTextIcon class="size-5" aria-hidden="true" />
                Scarica statuto
            </a>
        </div>
    </section>
</template>
