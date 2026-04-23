<script setup>
import { computed } from 'vue';

const props = defineProps({
    site: {
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
        class="relative py-16 sm:py-24 bg-gradient-to-b from-gray-100 to-white dark:from-gray-900 dark:to-gray-800"
        :style="sectionStyleObj"
    >
        <div class="mx-auto max-w-4xl px-4 sm:px-6 text-center">
            <h1 class="text-4xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-5xl">
                {{ site.hero_title || site.nome_associazione || 'Associazione' }}
            </h1>
            <p v-if="site.hero_subtitle" class="mt-4 text-lg text-gray-600 dark:text-gray-300">
                {{ site.hero_subtitle }}
            </p>
        </div>
    </section>
</template>
