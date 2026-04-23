<script setup>
import { QuestionMarkCircleIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    /** Testo esplicativo mostrato al passaggio del mouse o al focus sull’icona. */
    text: {
        type: String,
        required: true,
    },
    /** Larghezza fissa del tooltip: Tailwind class (es. w-80 = 20rem). */
    widthClass: {
        type: String,
        default: 'w-80',
    },
});

const tooltipId = computed(() => `help-tooltip-${Math.random().toString(36).slice(2, 9)}`);
</script>

<template>
    <span class="relative inline-flex group">
        <button
            type="button"
            :aria-describedby="tooltipId"
            aria-label="Aiuto"
            class="inline-flex items-center justify-center rounded-full text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 dark:focus:ring-offset-gray-900"
        >
            <QuestionMarkCircleIcon class="size-4" aria-hidden="true" />
        </button>
        <span
            :id="tooltipId"
            role="tooltip"
            :class="[widthClass]"
            class="absolute left-full top-1/2 ml-1.5 -translate-y-1/2 z-20 min-w-0 px-3 py-2 text-sm font-normal text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-sm opacity-0 invisible group-hover:opacity-100 group-hover:visible group-focus-within:opacity-100 group-focus-within:visible transition-opacity duration-150 pointer-events-none whitespace-normal"
        >
            {{ text }}
        </span>
    </span>
</template>
