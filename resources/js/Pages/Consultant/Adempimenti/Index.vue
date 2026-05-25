<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {
    ArrowLeftIcon, ArrowPathIcon, CheckCircleIcon, ClockIcon,
    ExclamationTriangleIcon, ChevronRightIcon, ChevronDownIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    entity:               { type: Object, required: true },
    anno:                 { type: Number, required: true },
    anni_disponibili:     { type: Array, default: () => [] },
    stats:                { type: Object, required: true },
    items_per_categoria:  { type: Array, default: () => [] },
});

const CATEGORIA_LABEL = {
    iva: 'IVA', ritenute: 'Ritenute', dichiarativi: 'Dichiarativi',
    bilancio: 'Bilancio', ets: 'ETS', cooperative: 'Cooperative', altro: 'Altro',
};

const STATO_COLOR = {
    gray:  'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    blue:  'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    cyan:  'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300',
    green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    red:   'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
};

const PRIORITA_COLOR = {
    critica: 'text-red-600 dark:text-red-400',
    alta:    'text-orange-600 dark:text-orange-400',
    normale: 'text-blue-600 dark:text-blue-400',
    bassa:   'text-gray-500 dark:text-gray-400',
};

const cambiaAnno = (a) => {
    router.get(route('consultant.adempimenti.index', props.entity.slug), { anno: a }, { preserveScroll: true });
};

const fmt = (s) => s ? new Date(s).toLocaleDateString('it-IT') : '—';

// ── Generazione anno ──────────────────────────────────────────────────
const generate = () => {
    if (! confirm(`Generare gli adempimenti standard per l'anno ${props.anno}? Quelli già presenti non vengono toccati.`)) return;
    router.post(route('consultant.adempimenti.generate', props.entity.slug), { anno: props.anno });
};

// ── Update inline di un item ──────────────────────────────────────────
const openedItem = ref(null);
const updateForm = useForm({
    stato: '',
    note: '',
    data_invio_telematico: '',
    protocollo_invio: '',
});

const openItem = (item) => {
    if (openedItem.value === item.id) {
        openedItem.value = null;
        return;
    }
    openedItem.value = item.id;
    updateForm.stato = item.stato;
    updateForm.note = item.note ?? '';
    updateForm.data_invio_telematico = item.data_invio_telematico ?? '';
    updateForm.protocollo_invio = item.protocollo_invio ?? '';
};

const submitUpdate = (item) => {
    updateForm.put(route('consultant.adempimenti.update', [props.entity.slug, item.id]), {
        onSuccess: () => { openedItem.value = null; router.reload({ only: ['items_per_categoria', 'stats'] }); },
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :title="`Adempimenti — ${entity.name}`">
        <Head :title="`Adempimenti — ${entity.name}`" />

        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <Link :href="route('consultant.adempimenti.dashboard')"
                            class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1">
                            <ArrowLeftIcon class="size-3" /> Compliance
                        </Link>
                    </div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Adempimenti — {{ entity.name }}
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <select :value="anno" @change="cambiaAnno($event.target.value)"
                        class="text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                        <option v-for="a in anni_disponibili" :key="a" :value="a">Anno {{ a }}</option>
                    </select>
                    <button type="button" @click="generate">
                        <PrimaryButton type="button" class="text-sm">
                            <ArrowPathIcon class="size-4 me-1.5" /> Genera anno
                        </PrimaryButton>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Stats -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Totali</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stats.totali }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                            <CheckCircleIcon class="size-3.5 text-green-500" /> Completati
                        </p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ stats.completati }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                            <ExclamationTriangleIcon class="size-3.5 text-red-500" /> Scaduti
                        </p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ stats.scaduti }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Compliance</p>
                        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ stats.compliance_pct }}%</p>
                    </div>
                </div>

                <!-- Generazione vuota -->
                <div v-if="items_per_categoria.length === 0"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-12 text-center">
                    <ClockIcon class="size-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" />
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Nessun adempimento generato per l'anno {{ anno }}.
                    </p>
                    <button type="button" @click="generate">
                        <PrimaryButton type="button" class="text-sm">
                            <ArrowPathIcon class="size-4 me-1.5" /> Genera adempimenti standard
                        </PrimaryButton>
                    </button>
                </div>

                <!-- Lista raggruppata per categoria -->
                <div v-for="group in items_per_categoria" :key="group.categoria"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider">
                            {{ CATEGORIA_LABEL[group.categoria] ?? group.categoria }}
                        </h3>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li v-for="item in group.items" :key="item.id"
                            :class="['hover:bg-gray-50 dark:hover:bg-gray-900/30',
                                item.is_scaduto && !['completato','non_applicabile'].includes(item.stato) ? 'bg-red-50/40 dark:bg-red-900/10' : '']">
                            <button @click="openItem(item)"
                                class="w-full text-left px-5 py-3 flex items-center justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-0.5">
                                        <component :is="openedItem === item.id ? ChevronDownIcon : ChevronRightIcon"
                                            class="size-3.5 text-gray-400 shrink-0" />
                                        <span :class="['inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium', STATO_COLOR[item.stato_badge_color]]">
                                            {{ item.stato_label }}
                                        </span>
                                        <span v-if="item.periodo" class="text-xs text-gray-500 dark:text-gray-400">{{ item.periodo }}</span>
                                        <span :class="['text-xs', PRIORITA_COLOR[item.priorita] || PRIORITA_COLOR.normale]">
                                            • {{ item.priorita }}
                                        </span>
                                    </div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 ms-5">{{ item.nome }}</p>
                                    <p v-if="item.riferimento" class="text-xs text-gray-400 ms-5 mt-0.5 italic">{{ item.riferimento }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p :class="['text-sm font-medium',
                                        item.is_scaduto && !['completato','non_applicabile'].includes(item.stato) ? 'text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-300']">
                                        {{ fmt(item.data_scadenza) }}
                                    </p>
                                    <p v-if="!['completato','non_applicabile'].includes(item.stato)" class="text-xs text-gray-400">
                                        <span v-if="item.giorni_alla_scadenza < 0">{{ Math.abs(item.giorni_alla_scadenza) }}g fa</span>
                                        <span v-else-if="item.giorni_alla_scadenza === 0">oggi</span>
                                        <span v-else>tra {{ item.giorni_alla_scadenza }}g</span>
                                    </p>
                                </div>
                            </button>

                            <!-- Pannello edit -->
                            <div v-if="openedItem === item.id" class="px-5 pb-4 border-t border-gray-50 dark:border-gray-700 bg-gray-50/40 dark:bg-gray-900/20">
                                <div v-if="item.descrizione" class="text-xs text-gray-600 dark:text-gray-400 mb-3 mt-3 italic">
                                    {{ item.descrizione }}
                                </div>
                                <div v-if="item.documenti_richiesti?.length > 0" class="mb-3">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Documenti richiesti:</p>
                                    <ul class="text-xs text-gray-700 dark:text-gray-300 list-disc list-inside">
                                        <li v-for="d in item.documenti_richiesti" :key="d">{{ d }}</li>
                                    </ul>
                                </div>

                                <form @submit.prevent="submitUpdate(item)" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                                    <label class="block">
                                        <span class="text-xs text-gray-600 dark:text-gray-400">Stato</span>
                                        <select v-model="updateForm.stato"
                                            class="mt-1 w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                                            <option value="da_fare">Da fare</option>
                                            <option value="in_lavorazione">In lavorazione</option>
                                            <option value="consegnato">Consegnato</option>
                                            <option value="completato">Completato</option>
                                            <option value="non_applicabile">Non applicabile</option>
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-xs text-gray-600 dark:text-gray-400">Data invio telematico</span>
                                        <input v-model="updateForm.data_invio_telematico" type="date"
                                            class="mt-1 w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700" />
                                    </label>
                                    <label class="block sm:col-span-2">
                                        <span class="text-xs text-gray-600 dark:text-gray-400">Protocollo invio (Entratel, PEC)</span>
                                        <input v-model="updateForm.protocollo_invio" type="text" maxlength="100"
                                            class="mt-1 w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700" />
                                    </label>
                                    <label class="block sm:col-span-2">
                                        <span class="text-xs text-gray-600 dark:text-gray-400">Note</span>
                                        <textarea v-model="updateForm.note" rows="2" maxlength="2000"
                                            class="mt-1 w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700"></textarea>
                                    </label>
                                    <div class="sm:col-span-2 flex justify-end">
                                        <PrimaryButton type="submit" :disabled="updateForm.processing" class="text-sm">
                                            {{ updateForm.processing ? 'Salvataggio…' : 'Salva' }}
                                        </PrimaryButton>
                                    </div>
                                </form>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
