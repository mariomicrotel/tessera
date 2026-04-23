<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { UserPlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    canRegister: {
        type: Boolean,
        default: false,
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
        id="modulo-iscrizione"
        class="py-16 sm:py-20 bg-white dark:bg-gray-800"
        :style="sectionStyleObj"
    >
        <div class="mx-auto max-w-4xl px-4 sm:px-6 text-center">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">Diventa socio</h2>
            <p class="mt-4 text-gray-600 dark:text-gray-300">
                Per richiedere l'iscrizione all'associazione puoi registrarti sul portale: riceverai le istruzioni per completare la richiesta.
            </p>
            <div v-if="canRegister" class="mt-8">
                <Link
                    :href="route('register')"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-3 text-base font-medium text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                >
                    <UserPlusIcon class="size-5" aria-hidden="true" />
                    Registrati
                </Link>
            </div>
            <p v-else class="mt-6 text-sm text-gray-500 dark:text-gray-400">
                Il modulo di iscrizione è al momento non disponibile. Contatta la segreteria per informazioni.
            </p>
        </div>
    </section>
</template>
