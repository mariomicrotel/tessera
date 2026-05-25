<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { CalendarDaysIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';

/**
 * Selettore periodo per il cruscotto consulente.
 * Emette un reload Inertia parziale (solo stats + currentPeriod + periodDates).
 */
const props = defineProps({
    entitySlug:     { type: String,  required: true },
    currentPeriod:  { type: String,  default: 'this_year' },
    periodDates:    { type: Object,  default: () => ({ from: '', to: '' }) },
    availablePeriods: { type: Object, default: () => ({ min_date: '', max_date: '' }) },
    loading:        { type: Boolean, default: false },
});

const emit = defineEmits(['loading']);

const PRESETS = [
    { value: 'this_year',  label: 'Anno corrente' },
    { value: 'last_year',  label: 'Anno precedente' },
    { value: 'last_12m',   label: 'Ultimi 12 mesi' },
    { value: 'this_month', label: 'Mese corrente' },
    { value: 'custom',     label: 'Personalizzato' },
];

const selectedPreset = ref(props.currentPeriod);
const customFrom     = ref(props.periodDates.from);
const customTo       = ref(props.periodDates.to);
const isRefreshing   = ref(false);

const showCustom = computed(() => selectedPreset.value === 'custom');

const presetLabel = computed(
    () => PRESETS.find(p => p.value === props.currentPeriod)?.label ?? props.currentPeriod
);

function applyPeriod(refresh = false) {
    isRefreshing.value = true;
    emit('loading', true);

    const params = { period: selectedPreset.value };
    if (selectedPreset.value === 'custom') {
        params.from = customFrom.value;
        params.to   = customTo.value;
    }
    if (refresh) {
        params.refresh = 1;
    }

    router.reload({
        only: ['stats', 'currentPeriod', 'periodDates'],
        data: params,
        onFinish: () => {
            isRefreshing.value = false;
            emit('loading', false);
        },
    });
}

function forceRefresh() {
    applyPeriod(true);
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <!-- Preset buttons -->
        <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
            <button
                v-for="p in PRESETS.filter(x => x.value !== 'custom')"
                :key="p.value"
                @click="selectedPreset = p.value; applyPeriod()"
                :class="[
                    'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
                    currentPeriod === p.value
                        ? 'bg-white dark:bg-gray-600 text-blue-700 dark:text-blue-300 shadow-sm'
                        : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100'
                ]"
            >
                {{ p.label }}
            </button>
        </div>

        <!-- Custom range toggle -->
        <button
            @click="selectedPreset = 'custom'"
            :class="[
                'flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors',
                currentPeriod === 'custom'
                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300'
                    : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:border-gray-400'
            ]"
        >
            <CalendarDaysIcon class="size-3.5" />
            Personalizzato
        </button>

        <!-- Custom date inputs (shown when custom is selected) -->
        <Transition
            enter-active-class="transition-all duration-200"
            enter-from-class="opacity-0 -translate-x-2"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition-all duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showCustom" class="flex items-center gap-2">
                <input
                    v-model="customFrom"
                    type="date"
                    :min="availablePeriods.min_date"
                    :max="customTo || availablePeriods.max_date"
                    class="text-xs border border-gray-300 dark:border-gray-600 rounded-lg px-2 py-1.5 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                />
                <span class="text-xs text-gray-400">→</span>
                <input
                    v-model="customTo"
                    type="date"
                    :min="customFrom || availablePeriods.min_date"
                    :max="availablePeriods.max_date"
                    class="text-xs border border-gray-300 dark:border-gray-600 rounded-lg px-2 py-1.5 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                />
                <button
                    @click="applyPeriod()"
                    :disabled="!customFrom || !customTo"
                    class="px-3 py-1.5 text-xs font-medium bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white rounded-lg transition-colors"
                >
                    Applica
                </button>
            </div>
        </Transition>

        <!-- Refresh button -->
        <button
            @click="forceRefresh"
            :disabled="loading || isRefreshing"
            title="Aggiorna dati"
            class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 disabled:opacity-40 transition-colors rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
        >
            <ArrowPathIcon :class="['size-4', (loading || isRefreshing) ? 'animate-spin' : '']" />
        </button>

        <!-- Period label -->
        <span class="text-xs text-gray-400 dark:text-gray-500">
            {{ periodDates.from }} → {{ periodDates.to }}
        </span>
    </div>
</template>
