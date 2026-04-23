<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { CalendarDaysIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
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

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString('it-IT', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <section
        id="eventi"
        class="py-16 sm:py-20 bg-gray-50 dark:bg-gray-900"
        :style="sectionStyleObj"
    >
        <div class="mx-auto max-w-4xl px-4 sm:px-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">Eventi</h2>
            <div v-if="events.length === 0" class="mt-8 text-center text-gray-500 dark:text-gray-400">
                Nessun evento in programma al momento.
            </div>
            <ul v-else class="mt-8 space-y-6">
                <li
                    v-for="event in events"
                    :key="event.id"
                    class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm"
                >
                    <div class="flex gap-4">
                        <div class="shrink-0 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 p-3">
                            <CalendarDaysIcon class="size-8 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ event.title }}
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ formatDate(event.start_at) }}
                                <span v-if="event.solo_soci" class="ml-2 rounded bg-amber-100 dark:bg-amber-900/40 px-2 py-0.5 text-xs text-amber-800 dark:text-amber-200">Solo soci</span>
                            </p>
                            <p v-if="event.description" class="mt-2 text-gray-600 dark:text-gray-300">
                                {{ event.description }}
                            </p>
                            <Link
                                v-if="$page.props.auth?.user"
                                :href="route('events.show', event.id)"
                                class="mt-3 inline-block text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
                            >
                                Dettagli e iscrizione →
                            </Link>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </section>
</template>
