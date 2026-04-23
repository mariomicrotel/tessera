<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import {
    CheckCircleIcon,
    InformationCircleIcon,
    ExclamationTriangleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    message: {
        type: String,
        required: true,
    },
    type: {
        type: String,
        default: 'info',
    },
    timeout: {
        type: Number,
        default: 8000,
    },
});

const emit = defineEmits(['close']);

const visible = ref(true);
let timer = null;

const close = () => {
    if (!visible.value) return;

    visible.value = false;

    if (timer) {
        clearTimeout(timer);
        timer = null;
    }

    emit('close');
};

onMounted(() => {
    if (props.timeout > 0) {
        timer = setTimeout(() => {
            close();
        }, props.timeout);
    }
});

onBeforeUnmount(() => {
    if (timer) {
        clearTimeout(timer);
        timer = null;
    }
});
</script>

<template>
    <transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-2"
    >
        <div
            v-if="visible"
            class="max-w-sm w-full shadow-lg rounded-md px-4 py-3 border text-sm bg-white dark:bg-gray-800"
            :class="{
                'bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-300 border-green-200 dark:border-green-700':
                    type === 'success',
                'bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-700':
                    type === 'info',
                'bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-300 border-red-200 dark:border-red-700':
                    type === 'error',
            }"
        >
            <div class="flex items-start gap-3">
                <span class="mt-0.5">
                    <CheckCircleIcon
                        v-if="type === 'success'"
                        class="size-5 text-green-500 dark:text-green-300"
                        aria-hidden="true"
                    />
                    <InformationCircleIcon
                        v-else-if="type === 'info'"
                        class="size-5 text-amber-500 dark:text-amber-300"
                        aria-hidden="true"
                    />
                    <ExclamationTriangleIcon
                        v-else
                        class="size-5 text-red-500 dark:text-red-300"
                        aria-hidden="true"
                    />
                </span>
                <p class="flex-1 text-sm leading-snug">
                    {{ message }}
                </p>
                <button
                    type="button"
                    class="ms-2 inline-flex items-center justify-center rounded-md text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-900"
                    @click="close"
                >
                    <XMarkIcon class="size-4" aria-hidden="true" />
                    <span class="sr-only">Chiudi</span>
                </button>
            </div>
        </div>
    </transition>
</template>

