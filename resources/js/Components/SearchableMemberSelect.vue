<script setup>
import { ChevronDownIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    /** Elenco soci: [{ id, cognome, nome }, ...] */
    members: {
        type: Array,
        default: () => [],
    },
    modelValue: {
        type: [String, Number],
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Seleziona socio',
    },
    /** Testo opzione "nessuna selezione" (es. "Anonimo", "Seleziona socio"). Se impostato, è possibile deselezionare. */
    emptyOption: {
        type: String,
        default: '',
    },
    disabled: Boolean,
    id: String,
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const filterQuery = ref('');
const inputRef = ref(null);
const listRef = ref(null);

const selectedMember = computed(() => {
    if (props.modelValue === '' || props.modelValue == null) return null;
    return props.members.find(m => m.id == props.modelValue) ?? null;
});

const displayText = computed(() => {
    if (selectedMember.value) {
        return `${selectedMember.value.cognome} ${selectedMember.value.nome}`.trim();
    }
    if (props.emptyOption) return props.emptyOption;
    return '';
});

const filteredMembers = computed(() => {
    const q = filterQuery.value.trim().toLowerCase();
    if (!q) return props.members;
    return props.members.filter(m => {
        const full = `${(m.cognome ?? '')} ${(m.nome ?? '')}`.trim().toLowerCase();
        const fullReverse = `${(m.nome ?? '')} ${(m.cognome ?? '')}`.trim().toLowerCase();
        return full.includes(q) || fullReverse.includes(q);
    });
});

watch(isOpen, (open) => {
    if (open) {
        filterQuery.value = '';
        setTimeout(() => inputRef.value?.focus(), 0);
    }
});

function select(id) {
    emit('update:modelValue', id === '' || id == null ? '' : id);
    isOpen.value = false;
    filterQuery.value = '';
}

function onInputBlur() {
    setTimeout(() => {
        if (!listRef.value?.contains(document.activeElement) && document.activeElement !== inputRef.value) {
            isOpen.value = false;
        }
    }, 150);
}

function onKeydown(e) {
    if (!isOpen.value) {
        if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
            e.preventDefault();
            isOpen.value = true;
        }
        return;
    }
    if (e.key === 'Escape') {
        e.preventDefault();
        isOpen.value = false;
        return;
    }
}
</script>

<template>
    <div class="relative" @keydown="onKeydown">
        <div
            class="flex rounded-md shadow-sm border border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus-within:ring-1 focus-within:ring-indigo-500 dark:focus-within:ring-indigo-600 focus-within:border-indigo-500 dark:focus-within:border-indigo-600 min-h-[38px]"
            :class="{ 'opacity-60': disabled }"
        >
            <span class="inline-flex items-center pl-3 text-gray-400 pointer-events-none">
                <MagnifyingGlassIcon class="size-4" aria-hidden="true" />
            </span>
            <input
                :id="id"
                ref="inputRef"
                type="text"
                :value="isOpen ? filterQuery : displayText"
                :placeholder="isOpen ? 'Cerca per nome o cognome...' : placeholder"
                :disabled="disabled"
                class="block w-full rounded-l-none rounded-r-md border-0 bg-transparent py-2 pr-8 pl-1 text-gray-900 dark:text-gray-300 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-0 text-sm"
                :readonly="!isOpen"
                @focus="isOpen = true"
                @blur="onInputBlur"
                @input="filterQuery = $event.target.value"
            />
            <button
                type="button"
                class="absolute right-1 top-1/2 -translate-y-1/2 p-1 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none"
                :disabled="disabled"
                aria-label="Apri elenco"
                @mousedown.prevent="isOpen = !isOpen; if (isOpen) inputRef?.focus()"
            >
                <ChevronDownIcon class="size-4" :class="{ 'rotate-180': isOpen }" aria-hidden="true" />
            </button>
        </div>
        <div
            v-show="isOpen"
            ref="listRef"
            class="absolute z-20 mt-1 w-full max-h-60 overflow-auto rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg py-1 text-sm"
        >
            <button
                v-if="emptyOption"
                type="button"
                class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700"
                :class="{ 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300': modelValue === '' }"
                @mousedown.prevent="select('')"
            >
                {{ emptyOption }}
            </button>
            <button
                v-for="m in filteredMembers"
                :key="m.id"
                type="button"
                class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700"
                :class="{ 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300': m.id == modelValue }"
                @mousedown.prevent="select(m.id)"
            >
                {{ m.cognome }} {{ m.nome }}
            </button>
            <p v-if="filteredMembers.length === 0" class="px-3 py-2 text-gray-500 dark:text-gray-400">
                Nessun socio trovato.
            </p>
        </div>
    </div>
</template>
