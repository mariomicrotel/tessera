<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ArrowLeftIcon, DocumentArrowDownIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    tenants:                 { type: Array, default: () => [] },
    preselected_tenant_slug: { type: String, default: null },
    formats:                 { type: Array, default: () => [] },
    data_sources:            { type: Array, default: () => [] },
});

const preselected = computed(() =>
    props.preselected_tenant_slug
        ? props.tenants.find(t => t.slug === props.preselected_tenant_slug)
        : null
);

const today = new Date();
const startOfYear = `${today.getFullYear()}-01-01`;
const todayISO    = today.toISOString().slice(0, 10);

const form = useForm({
    tenant_id:    preselected.value?.id ?? (props.tenants[0]?.id ?? ''),
    period_from:  startOfYear,
    period_to:    todayISO,
    formats:      props.formats.map(f => f.key),     // all by default
    data_types:   props.data_sources.map(s => s.key),// all by default
});

const selectedFormats = computed(() => props.formats.filter(f => form.formats.includes(f.key)));

// True se almeno un formato selezionato richiede DataSource utente
const needsDataSources = computed(() =>
    selectedFormats.value.some(f => f.requires_data_sources === true)
);

// DataSource selezionabili = unione dei supportati dai formati che ne richiedono
//  - supported_data_sources = null  → wildcard (tutti i DataSource)
//  - supported_data_sources = []    → formato self-contained (non contribuisce)
//  - supported_data_sources = [...] → solo quei DataSource
const allowedDataSources = computed(() => {
    const reqs = selectedFormats.value.filter(f => f.requires_data_sources === true);
    if (reqs.length === 0) return [];
    const hasWildcard = reqs.some(f => f.supported_data_sources === null);
    if (hasWildcard) return props.data_sources.map(s => s.key);
    const set = new Set();
    reqs.forEach(f => (f.supported_data_sources ?? []).forEach(k => set.add(k)));
    return [...set];
});

const toggleFormat = (key) => {
    const i = form.formats.indexOf(key);
    if (i >= 0) form.formats.splice(i, 1);
    else        form.formats.push(key);

    // Pulisci data_types non più supportati
    form.data_types = form.data_types.filter(k => allowedDataSources.value.includes(k));
};

const toggleDataType = (key) => {
    const i = form.data_types.indexOf(key);
    if (i >= 0) form.data_types.splice(i, 1);
    else        form.data_types.push(key);
};

const selectAllDataTypes = () => {
    form.data_types = [...allowedDataSources.value];
};
const clearAllDataTypes = () => {
    form.data_types = [];
};

const submit = () => {
    form.post(route('consultant.exports.store'));
};

const canSubmit = computed(() =>
    form.tenant_id && form.period_from && form.period_to
    && form.formats.length > 0
    && (! needsDataSources.value || form.data_types.length > 0)
);
</script>

<template>
    <AppLayout title="Nuovo export">
        <Head title="Nuovo export dati" />

        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <Link :href="route('consultant.exports.index')"
                            class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1">
                            <ArrowLeftIcon class="size-3" /> Export
                        </Link>
                    </div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Nuovo export dati
                    </h2>
                </div>
            </div>
        </template>

        <div class="py-6">
            <form @submit.prevent="submit" class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Step 1: Ente -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">1. Ente</h3>
                    <select v-model="form.tenant_id"
                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                        <option v-for="t in tenants" :key="t.id" :value="t.id">
                            {{ t.name }} ({{ t.organization_type === 'cooperative' ? 'Cooperativa' : 'ETS' }})
                        </option>
                    </select>
                    <p v-if="form.errors.tenant_id" class="text-xs text-red-500 mt-1">{{ form.errors.tenant_id }}</p>
                </div>

                <!-- Step 2: Periodo -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">2. Periodo</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="text-xs text-gray-600 dark:text-gray-400">Dal:</span>
                            <input v-model="form.period_from" type="date" required
                                class="mt-1 w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700" />
                        </label>
                        <label class="block">
                            <span class="text-xs text-gray-600 dark:text-gray-400">Al:</span>
                            <input v-model="form.period_to" type="date" required :min="form.period_from"
                                class="mt-1 w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700" />
                        </label>
                    </div>
                    <p v-if="form.errors.period_from" class="text-xs text-red-500 mt-1">{{ form.errors.period_from }}</p>
                    <p v-if="form.errors.period_to" class="text-xs text-red-500 mt-1">{{ form.errors.period_to }}</p>
                </div>

                <!-- Step 3: Formati -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">3. Formati di output</h3>
                    <div class="space-y-2">
                        <label v-for="f in formats" :key="f.key"
                            class="flex items-start gap-3 cursor-pointer p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-700">
                            <input type="checkbox"
                                :checked="form.formats.includes(f.key)"
                                @change="toggleFormat(f.key)"
                                class="mt-0.5 rounded text-blue-600" />
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ f.label }}</span>
                                    <span class="text-xs font-mono bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-1.5 py-0.5 rounded">
                                        .{{ f.extension }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ f.description }}</p>
                            </div>
                        </label>
                    </div>
                    <p v-if="form.errors.formats" class="text-xs text-red-500 mt-1">{{ form.errors.formats }}</p>
                </div>

                <!-- Info box per formati self-contained -->
                <div v-if="selectedFormats.length > 0 && !needsDataSources"
                    class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
                    <p class="text-sm text-blue-800 dark:text-blue-200">
                        I formati selezionati attingono i dati direttamente dai modelli IVA del periodo
                        (es. liquidazioni IVA chiuse). Non occorre selezionare tabelle.
                    </p>
                </div>

                <!-- Step 4: Tabelle (solo se servono) -->
                <div v-if="needsDataSources"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">4. Tabelle da includere</h3>
                        <div class="flex items-center gap-2 text-xs">
                            <button type="button" @click="selectAllDataTypes" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">Tutte</button>
                            <span class="text-gray-300">|</span>
                            <button type="button" @click="clearAllDataTypes" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">Nessuna</button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label v-for="s in data_sources" :key="s.key"
                            :class="['flex items-start gap-2 p-2 rounded-lg border',
                                allowedDataSources.includes(s.key)
                                    ? 'cursor-pointer border-gray-200 dark:border-gray-700 hover:border-blue-300'
                                    : 'opacity-40 cursor-not-allowed border-gray-100 dark:border-gray-800']">
                            <input type="checkbox"
                                :checked="form.data_types.includes(s.key)"
                                :disabled="!allowedDataSources.includes(s.key)"
                                @change="toggleDataType(s.key)"
                                class="mt-0.5 rounded text-blue-600" />
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ s.label }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ s.description }}</div>
                                <div class="text-xs text-gray-400 dark:text-gray-500 font-mono mt-0.5">{{ s.file_name }}</div>
                            </div>
                        </label>
                    </div>
                    <p v-if="form.errors.data_types" class="text-xs text-red-500 mt-1">{{ form.errors.data_types }}</p>
                </div>

                <!-- Submit -->
                <div class="flex items-center justify-end gap-2">
                    <Link :href="route('consultant.exports.index')">
                        <SecondaryButton type="button" class="text-sm">Annulla</SecondaryButton>
                    </Link>
                    <PrimaryButton type="submit" :disabled="!canSubmit || form.processing" class="text-sm">
                        <DocumentArrowDownIcon class="size-4 me-1.5" />
                        {{ form.processing ? 'Avvio…' : 'Avvia export' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
