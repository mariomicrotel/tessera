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
        id="chi-siamo"
        class="py-16 sm:py-20 bg-white dark:bg-gray-800"
        :style="sectionStyleObj"
    >
        <div class="mx-auto max-w-4xl px-4 sm:px-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">Chi siamo</h2>
            <div v-if="site.chi_siamo_text" class="mt-6 whitespace-pre-line text-gray-600 dark:text-gray-300">
                {{ site.chi_siamo_text }}
            </div>
            <div v-else class="mt-6 space-y-4 text-gray-600 dark:text-gray-300">
                <p v-if="site.nome_associazione" class="text-lg">
                    <strong class="text-gray-900 dark:text-white">{{ site.nome_associazione }}</strong>
                </p>
                <p v-if="site.indirizzo_associazione" class="whitespace-pre-line">
                    {{ site.indirizzo_associazione }}
                </p>
                <p v-if="site.codice_fiscale_associazione" class="text-sm">
                    Codice fiscale: {{ site.codice_fiscale_associazione }}
                </p>
            </div>
        </div>
    </section>
</template>
