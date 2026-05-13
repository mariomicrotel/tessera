<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    usage: { type: Object, default: () => ({}) },
});

const form = ref({
    companyName:      '',
    autocomplete:     '',
    province:         '',
    municipality:     '',
    atecoCode:        '',
    reaCode:          '',
    sdiCode:          '',
    legalForm:        '',
    activityStatus:   '',
    pec:              '',
    taxCodeOwner:     '',
    employeesMin:     null,
    employeesMax:     null,
    turnoverMin:      null,
    turnoverMax:      null,
    creationDateFrom: '',
    creationDateTo:   '',
    skip:             0,
    limit:            10,
    dryRun:           false,
    enrichWith:       [],
});

const loading      = ref(false);
const error        = ref(null);
const results      = ref([]);
const lastMeta     = ref(null);
const currentUsage = ref(props.usage?.['IT-search'] ?? {});
const advanced     = ref(false);

const activityStatusOptions = [
    { value: '',                    label: '— Qualsiasi —' },
    { value: 'ACTIVE',              label: 'Attiva' },
    { value: 'CEASED',              label: 'Cessata' },
    { value: 'REGISTERED',          label: 'Registrata' },
    { value: 'INACTIVE',            label: 'Inattiva' },
    { value: 'SUSPENDED',           label: 'Sospesa' },
    { value: 'UNDER_REGISTRATION',  label: 'In iscrizione' },
];

const cleanPayload = computed(() => {
    const payload = {};
    Object.entries(form.value).forEach(([k, v]) => {
        if (v !== '' && v !== null && !(Array.isArray(v) && v.length === 0)) {
            payload[k] = v;
        }
    });
    return payload;
});

const hasCriteria = computed(() => {
    const ignored = ['skip', 'limit', 'dryRun', 'enrichWith'];
    return Object.entries(cleanPayload.value).some(
        ([k, v]) => !ignored.includes(k) && v !== '' && v !== null
    );
});

const search = async (reset = true) => {
    if (!hasCriteria.value) {
        error.value = 'Inserisci almeno un criterio di ricerca.';
        return;
    }
    error.value = null;
    loading.value = true;
    if (reset) form.value.skip = 0;
    try {
        const { data } = await axios.post(route('company-enrichment.it-search'), cleanPayload.value);
        if (data.success) {
            results.value     = data.data ?? [];
            lastMeta.value    = data.meta ?? null;
            currentUsage.value = data.usage ?? currentUsage.value;
        } else {
            error.value = data.error ?? 'Errore non specificato.';
            results.value = [];
        }
    } catch (e) {
        error.value = e.response?.data?.error ?? e.message ?? 'Errore di rete.';
        results.value = [];
    } finally {
        loading.value = false;
    }
};

const reset = () => {
    Object.keys(form.value).forEach((k) => {
        if (Array.isArray(form.value[k])) form.value[k] = [];
        else if (typeof form.value[k] === 'boolean') form.value[k] = false;
        else if (typeof form.value[k] === 'number') form.value[k] = k === 'limit' ? 10 : 0;
        else form.value[k] = '';
    });
    results.value = [];
    lastMeta.value = null;
    error.value = null;
};

const nextPage = () => {
    form.value.skip = (form.value.skip || 0) + form.value.limit;
    search(false);
};
const prevPage = () => {
    form.value.skip = Math.max(0, (form.value.skip || 0) - form.value.limit);
    search(false);
};

const usagePct = computed(() => {
    const used = currentUsage.value?.used ?? 0;
    const limit = currentUsage.value?.limit ?? 100;
    return Math.min(100, Math.round((used / limit) * 100));
});
</script>

<template>
    <AppLayout title="Ricerca Aziende">
        <Head title="Ricerca Aziende — OpenAPI Company" />
        <template #header>
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Ricerca Aziende (IT-search)
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    Ricerca aziende italiane su OpenAPI per criteri multipli
                </p>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-5">

                <!-- Usage banner -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Consumo IT-search oggi</span>
                        <span class="text-sm tabular-nums text-gray-900 dark:text-gray-100">
                            {{ currentUsage?.used ?? 0 }} / {{ currentUsage?.limit ?? 100 }}
                        </span>
                    </div>
                    <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                        <div class="h-2 rounded-full transition-all"
                            :class="usagePct >= 80 ? 'bg-red-500' : usagePct >= 50 ? 'bg-yellow-500' : 'bg-green-500'"
                            :style="{ width: usagePct + '%' }"></div>
                    </div>
                </div>

                <!-- Form di ricerca -->
                <form @submit.prevent="search(true)"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">

                    <!-- Riga base -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <InputLabel for="companyName" value="Ragione sociale (anche con *)" />
                            <TextInput id="companyName" v-model="form.companyName" type="text"
                                class="mt-1 block w-full" placeholder="Es: COOP MARCO POLO*" />
                        </div>
                        <div>
                            <InputLabel for="atecoCode" value="Codice ATECO" />
                            <TextInput id="atecoCode" v-model="form.atecoCode" type="text"
                                class="mt-1 block w-full" placeholder="Es: 94.99.20" />
                        </div>
                        <div>
                            <InputLabel for="province" value="Provincia (sigla)" />
                            <TextInput id="province" v-model="form.province" type="text" maxlength="2"
                                class="mt-1 block w-full uppercase" placeholder="MI" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <InputLabel for="activityStatus" value="Stato attività" />
                            <select id="activityStatus" v-model="form.activityStatus"
                                class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200 text-sm px-3 py-2">
                                <option v-for="opt in activityStatusOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="reaCode" value="Codice REA" />
                            <TextInput id="reaCode" v-model="form.reaCode" type="text"
                                class="mt-1 block w-full" placeholder="Es: MI-1234567" />
                        </div>
                        <div>
                            <InputLabel for="sdiCode" value="Codice SDI" />
                            <TextInput id="sdiCode" v-model="form.sdiCode" type="text"
                                class="mt-1 block w-full" placeholder="7 caratteri" />
                        </div>
                    </div>

                    <!-- Toggle filtri avanzati -->
                    <button type="button" @click="advanced = !advanced"
                        class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                        {{ advanced ? '↑ Nascondi filtri avanzati' : '↓ Mostra filtri avanzati' }}
                    </button>

                    <!-- Filtri avanzati -->
                    <div v-if="advanced" class="space-y-4 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <InputLabel for="municipality" value="Comune (cod. catastale Belfiore)" />
                                <TextInput id="municipality" v-model="form.municipality" type="text"
                                    class="mt-1 block w-full" placeholder="Es: F205" />
                            </div>
                            <div>
                                <InputLabel for="pec" value="PEC" />
                                <TextInput id="pec" v-model="form.pec" type="email"
                                    class="mt-1 block w-full" placeholder="azienda@pec.it" />
                            </div>
                            <div>
                                <InputLabel for="taxCodeOwner" value="CF proprietario/socio" />
                                <TextInput id="taxCodeOwner" v-model="form.taxCodeOwner" type="text" maxlength="16"
                                    class="mt-1 block w-full uppercase" placeholder="RSSMRA80A01H501Z" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <InputLabel for="employeesMin" value="Dipendenti min" />
                                <TextInput id="employeesMin" v-model.number="form.employeesMin" type="number" min="0"
                                    class="mt-1 block w-full" />
                            </div>
                            <div>
                                <InputLabel for="employeesMax" value="Dipendenti max" />
                                <TextInput id="employeesMax" v-model.number="form.employeesMax" type="number" min="0"
                                    class="mt-1 block w-full" />
                            </div>
                            <div>
                                <InputLabel for="turnoverMin" value="Fatturato min (€)" />
                                <TextInput id="turnoverMin" v-model.number="form.turnoverMin" type="number" min="0"
                                    class="mt-1 block w-full" />
                            </div>
                            <div>
                                <InputLabel for="turnoverMax" value="Fatturato max (€)" />
                                <TextInput id="turnoverMax" v-model.number="form.turnoverMax" type="number" min="0"
                                    class="mt-1 block w-full" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="creationDateFrom" value="Data creazione: dal" />
                                <TextInput id="creationDateFrom" v-model="form.creationDateFrom" type="date"
                                    class="mt-1 block w-full" />
                            </div>
                            <div>
                                <InputLabel for="creationDateTo" value="Data creazione: al" />
                                <TextInput id="creationDateTo" v-model="form.creationDateTo" type="date"
                                    class="mt-1 block w-full" />
                            </div>
                        </div>

                        <div class="flex items-center gap-4 flex-wrap">
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" v-model="form.dryRun"
                                    class="rounded border-gray-300 dark:border-gray-600" />
                                Solo conteggio (dry run)
                            </label>
                            <div class="flex items-center gap-2">
                                <InputLabel for="limit" value="Risultati per pagina" class="!mb-0" />
                                <select id="limit" v-model.number="form.limit"
                                    class="rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm px-2 py-1">
                                    <option :value="5">5</option>
                                    <option :value="10">10</option>
                                    <option :value="25">25</option>
                                    <option :value="50">50</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Errori -->
                    <div v-if="error" class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-3 text-sm text-red-700 dark:text-red-400">
                        {{ error }}
                    </div>

                    <!-- Azioni -->
                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <button type="button" @click="reset"
                            class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400">
                            Reset
                        </button>
                        <PrimaryButton type="submit" :disabled="loading || !hasCriteria">
                            {{ loading ? 'Ricerca in corso…' : 'Cerca' }}
                        </PrimaryButton>
                    </div>
                </form>

                <!-- Risultati -->
                <div v-if="lastMeta || results.length > 0"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">

                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between flex-wrap gap-2">
                        <h3 class="font-semibold text-sm text-gray-900 dark:text-gray-100">
                            Risultati
                            <span v-if="lastMeta" class="text-gray-500 dark:text-gray-400 text-xs ml-2">
                                ({{ lastMeta.count }} mostrati<span v-if="lastMeta.total !== null && lastMeta.total !== undefined">, {{ lastMeta.total }} totali</span>)
                            </span>
                        </h3>
                        <div v-if="results.length > 0" class="flex items-center gap-2">
                            <button @click="prevPage" :disabled="form.skip === 0 || loading"
                                class="text-xs px-2 py-1 rounded border border-gray-200 dark:border-gray-600 disabled:opacity-50">
                                ← Indietro
                            </button>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ form.skip + 1 }}–{{ form.skip + (lastMeta?.count ?? 0) }}
                            </span>
                            <button @click="nextPage" :disabled="(lastMeta?.count ?? 0) < form.limit || loading"
                                class="text-xs px-2 py-1 rounded border border-gray-200 dark:border-gray-600 disabled:opacity-50">
                                Avanti →
                            </button>
                        </div>
                    </div>

                    <div v-if="lastMeta?.dryRun"
                        class="px-5 py-8 text-center text-sm">
                        <p class="font-medium text-gray-800 dark:text-gray-200">Dry run completato</p>
                        <p v-if="lastMeta.total !== null && lastMeta.total !== undefined" class="text-gray-500 dark:text-gray-400 mt-1">
                            {{ lastMeta.total }} aziende corrispondono ai criteri
                        </p>
                    </div>

                    <div v-else-if="results.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                        Nessuna azienda trovata
                    </div>

                    <ul v-else class="divide-y divide-gray-50 dark:divide-gray-700">
                        <li v-for="(r, i) in results" :key="r.provider_company_id ?? i"
                            class="px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                        {{ r.ragione_sociale ?? '— (senza ragione sociale)' }}
                                    </p>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-3 flex-wrap">
                                        <span v-if="r.partita_iva">P.IVA: {{ r.partita_iva }}</span>
                                        <span v-if="r.codice_fiscale && r.codice_fiscale !== r.partita_iva">CF: {{ r.codice_fiscale }}</span>
                                        <span v-if="r.stato_attivita">
                                            <span class="px-1.5 py-0.5 rounded text-xs"
                                                :class="r.stato_attivita === 'ACTIVE'
                                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'">
                                                {{ r.stato_attivita }}
                                            </span>
                                        </span>
                                    </div>
                                    <p v-if="r.indirizzo || r.comune" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ [r.indirizzo, r.cap, r.comune, r.provincia].filter(Boolean).join(', ') }}
                                    </p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
