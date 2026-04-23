<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    align: {
        type: String,
        default: 'right',
    },
    /** 'bottom' = sotto il trigger (default), 'right' = a destra del trigger */
    position: {
        type: String,
        default: 'bottom',
    },
    width: {
        type: String,
        default: '48',
    },
    contentClasses: {
        type: Array,
        default: () => ['py-1', 'bg-white dark:bg-gray-700'],
    },
});

let open = ref(false);

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));

const widthClass = computed(() => {
    return {
        '48': 'w-48',
    }[props.width.toString()];
});

const alignmentClasses = computed(() => {
    if (props.position === 'right') {
        // A destra del trigger, bordo inferiore allineato al trigger (ultima voce appena sopra il fondo pagina)
        return 'ltr:start-full ltr:ml-2 rtl:end-full rtl:mr-2 rtl:ml-0 bottom-0 ltr:origin-bottom-left rtl:origin-bottom-right';
    }
    if (props.align === 'left') {
        return 'ltr:origin-top-left rtl:origin-top-right start-0';
    }
    if (props.align === 'right') {
        return 'ltr:origin-top-right rtl:origin-top-left end-0';
    }
    return 'origin-top';
});

const positionClasses = computed(() => {
    return props.position === 'right' ? '' : 'mt-2';
});
</script>

<template>
    <div class="relative">
        <div @click="open = ! open">
            <slot name="trigger" />
        </div>

        <!-- Full Screen Dropdown Overlay -->
        <div v-show="open" class="fixed inset-0 z-40" @click="open = false" />

        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-show="open"
                class="absolute z-50 rounded-md shadow-lg"
                :class="[widthClass, alignmentClasses, positionClasses]"
                @click="open = false"
            >
                <div class="rounded-md ring-1 ring-black ring-opacity-5" :class="contentClasses">
                    <slot name="content" />
                </div>
            </div>
        </transition>
    </div>
</template>
