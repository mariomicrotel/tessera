<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    TableCellsIcon, ChevronLeftIcon, PencilSquareIcon,
    TrashIcon, ChartBarIcon, ArrowRightCircleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    asset:             Object,
    pianoPreview:      Array,
    vnc:               Number,
    fondoCumulato:     Number,
    statiLabel:        Object,
    tipiDisposalLabel: Object,
});

// ── Tab attivo ──
const tab = ref('anagrafica');

// ── Dismissione ──
const showDismissione = ref(false);
const dismissioneForm = useForm({
    tipo:             'rottamazione',
    data_dismissione: new Date().toISOString().substring(0, 10),
    valore_realizzo:  0,
    note:             '',
});
const previewDismissione = ref(null);
const previewLoading     = ref(false);

async function calcolaPreview() {
    previewLoading.value = true;
    try {
        const res = await fetch(route('cespiti.preview-dismissione', props.asset.id), {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
            body:    JSON.stringify({
                valore_realizzo:  Number(dismissioneForm.valore_realizzo),
                data_dismissione: dismissioneForm.data_dismissione,
            }),
        });
        previewDismissione.value = await res.json();
    } finally {
        previewLoading.value = false;
    }
}

function submitDismissione() {
    dismissioneForm.post(route('cespiti.dismetti', props.asset.id));
}

function confirmDelete() {
    if (confirm(`Eliminare definitivamente il cespite "${props.asset.name}"? L'operazione non è reversibile.`)) {
        router.delete(route('cespiti.destroy', props.asset.id));
    }
}

// ── Helpers ──
function fmt(n) {
    return Number(n ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function statoBadgeClass(stato) {
    if (stato === 'in_uso')   return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
    if (stato === 'dismesso') return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400';
    if (stato === 'venduto')  return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
    return 'bg-gray-100 text-gray-600';
}
const isActive = computed(() => props.asset.stato === 'in_uso');
</script>

<template>
    <AppLayout :title="asset.name">
        <Head :title="asset.name" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('cespiti.index')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <ChevronLeftIcon class="size-5" />
                    </Link>
                    <TableCellsIcon class="size-6 text-gray-500" />
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ asset.name }}</h2>
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statoBadgeClass(asset.stato)">
                        {{ statiLabel[asset.stato] ?? asset.stato }}
                    </span>
                </div>
                <div v-if="isActive" class="flex items-center gap-2">
                    <Link :href="route('cespiti.edit', asset.id)"
                          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <PencilSquareIcon class="size-4" />
                        Modifica
                    </Link>
                    <button @click="showDismissione = true"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                        <ArrowRightCircleIcon class="size-4" />
                        Dismetti
                    </button>
                    <button @click="confirmDelete"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm border border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                        <TrashIcon class="size-4" />
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- KPI cards -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Costo storico</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100">€ {{ fmt(asset.costo_storico) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Fondo cumulato</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100">€ {{ fmt(fondoCumulato) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">VNC corrente</p>
                        <p class="text-lg font-bold" :class="vnc > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400'">€ {{ fmt(vnc) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Ammortizzato</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100">
                            {{ asset.costo_storico > 0 ? Math.round(fondoCumulato / asset.costo_storico * 100) : 0 }}%
                        </p>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex gap-1">
                        <button v-for="t in ['anagrafica', 'piano', 'movimenti', 'dismissione']" :key="t"
                                @click="tab = t"
                                class="px-4 py-2.5 text-sm font-medium border-b-2 transition -mb-px"
                                :class="tab === t
                                    ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'">
                            {{ { anagrafica: 'Anagrafica', piano: 'Piano ammortamento', movimenti: 'Movimenti', dismissione: 'Dismissione' }[t] }}
                        </button>
                    </nav>
                </div>

                <!-- ── TAB: Anagrafica ── -->
                <div v-if="tab === 'anagrafica'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-4">Dati anagrafici</h3>
                        <dl class="space-y-3">
                            <div class="flex justify-between text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Descrizione</dt>
                                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ asset.name }}</dd>
                            </div>
                            <div v-if="asset.code" class="flex justify-between text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Codice</dt>
                                <dd class="text-gray-700 dark:text-gray-300">{{ asset.code }}</dd>
                            </div>
                            <div v-if="asset.matricola" class="flex justify-between text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Matricola</dt>
                                <dd class="text-gray-700 dark:text-gray-300">{{ asset.matricola }}</dd>
                            </div>
                            <div v-if="asset.supplier" class="flex justify-between text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Fornitore</dt>
                                <dd class="text-gray-700 dark:text-gray-300">{{ asset.supplier.ragione_sociale || asset.supplier.name }}</dd>
                            </div>
                            <div v-if="asset.purchase_date" class="flex justify-between text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Data acquisto</dt>
                                <dd class="text-gray-700 dark:text-gray-300">{{ asset.purchase_date.substring(0, 10) }}</dd>
                            </div>
                            <div v-if="asset.notes" class="text-sm">
                                <dt class="text-gray-500 dark:text-gray-400 mb-1">Note</dt>
                                <dd class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ asset.notes }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-4">Dati fiscali</h3>
                        <dl class="space-y-3">
                            <div v-if="asset.category" class="flex justify-between text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Categoria</dt>
                                <dd class="text-gray-700 dark:text-gray-300">{{ asset.category.codice }} — {{ asset.category.descrizione }}</dd>
                            </div>
                            <div class="flex justify-between text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Metodo</dt>
                                <dd class="text-gray-700 dark:text-gray-300 capitalize">{{ asset.metodo_ammortamento }}</dd>
                            </div>
                            <div class="flex justify-between text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Aliquota effettiva</dt>
                                <dd class="text-gray-700 dark:text-gray-300">
                                    {{ asset.aliquota_custom || asset.category?.coefficiente_ministeriale || '—' }}%
                                    <span v-if="asset.aliquota_custom" class="text-xs text-gray-400 ml-1">(custom)</span>
                                </dd>
                            </div>
                            <div class="flex justify-between text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Deducibilità</dt>
                                <dd class="text-gray-700 dark:text-gray-300">{{ asset.percentuale_deducibilita }}%</dd>
                            </div>
                            <div class="flex justify-between text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Primo anno ridotto</dt>
                                <dd class="text-gray-700 dark:text-gray-300">{{ asset.primo_anno_ridotto ? 'Sì' : 'No' }}</dd>
                            </div>
                            <div class="flex justify-between text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Inizio ammortamento</dt>
                                <dd class="text-gray-700 dark:text-gray-300">{{ asset.data_inizio_ammortamento?.substring(0, 10) ?? '—' }}</dd>
                            </div>
                            <div v-if="asset.note_fiscali" class="text-sm">
                                <dt class="text-gray-500 dark:text-gray-400 mb-1">Note fiscali</dt>
                                <dd class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ asset.note_fiscali }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- ── TAB: Piano ammortamento ── -->
                <div v-if="tab === 'piano'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
                        <ChartBarIcon class="size-5 text-gray-400" />
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">Piano di ammortamento teorico</h3>
                        <span class="ml-auto text-xs text-gray-400">Simulazione — aliquote DM 31/12/1988</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Esercizio</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Aliquota</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Quota</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Fondo fine anno</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">VNC fine anno</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Registrata</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-if="pianoPreview.length === 0">
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">
                                        Impossibile calcolare il piano: verificare categoria e costo storico.
                                    </td>
                                </tr>
                                <tr v-for="riga in pianoPreview" :key="riga.esercizio"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100">{{ riga.esercizio }}</td>
                                    <td class="px-4 py-2.5 text-right text-gray-600 dark:text-gray-400">{{ riga.aliquota }}%</td>
                                    <td class="px-4 py-2.5 text-right font-mono text-gray-800 dark:text-gray-200">€ {{ fmt(riga.quota) }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono text-gray-800 dark:text-gray-200">€ {{ fmt(riga.fondo) }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono"
                                        :class="riga.residuo > 0 ? 'text-gray-800 dark:text-gray-200' : 'text-gray-400'">
                                        € {{ fmt(riga.residuo) }}
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <template v-if="asset.depreciation_schedules">
                                            <span v-if="asset.depreciation_schedules.find(s => s.esercizio === riga.esercizio && s.stato === 'definitivo')"
                                                  class="text-green-600 dark:text-green-400 text-xs font-medium">✓ Definitiva</span>
                                            <span v-else-if="asset.depreciation_schedules.find(s => s.esercizio === riga.esercizio)"
                                                  class="text-yellow-500 dark:text-yellow-400 text-xs">Bozza</span>
                                            <span v-else class="text-gray-300 dark:text-gray-600 text-xs">—</span>
                                        </template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ── TAB: Movimenti ── -->
                <div v-if="tab === 'movimenti'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">Quote ammortamento registrate</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Esercizio</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Quota calcolata</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Quota registrata</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Stato</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Data reg.</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-if="!asset.depreciation_schedules?.length">
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">
                                        Nessuna quota registrata.
                                        <Link :href="route('cespiti.ammortamento.index')" class="ml-1 text-blue-600 hover:underline">
                                            Vai alla sezione Ammortamenti
                                        </Link>.
                                    </td>
                                </tr>
                                <tr v-for="s in asset.depreciation_schedules" :key="s.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100">{{ s.esercizio }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono text-gray-700 dark:text-gray-300">€ {{ fmt(s.quota_calcolata) }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono text-gray-700 dark:text-gray-300">€ {{ fmt(s.quota_registrata) }}</td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                              :class="{
                                                  'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400': s.stato === 'bozza',
                                                  'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': s.stato === 'definitivo',
                                                  'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400': s.stato === 'stornato',
                                              }">
                                            {{ { bozza: 'Bozza', definitivo: 'Definitiva', stornato: 'Stornata' }[s.stato] ?? s.stato }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-center text-gray-500 dark:text-gray-400">
                                        {{ s.data_registrazione ?? '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ── TAB: Dismissione ── -->
                <div v-if="tab === 'dismissione'">
                    <!-- Dismissione già presente -->
                    <div v-if="asset.disposal" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-4">Dismissione registrata</h3>
                        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Tipo</dt>
                                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ tipiDisposalLabel[asset.disposal.tipo] ?? asset.disposal.tipo }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Data dismissione</dt>
                                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ asset.disposal.data_dismissione }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Valore realizzo</dt>
                                <dd class="font-medium text-gray-900 dark:text-gray-100">€ {{ fmt(asset.disposal.valore_realizzo) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">VNC al momento</dt>
                                <dd class="font-medium text-gray-900 dark:text-gray-100">€ {{ fmt(asset.disposal.valore_netto_contabile) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Plus/minusvalenza</dt>
                                <dd class="font-bold" :class="asset.disposal.plusvalenza_minusvalenza >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                    {{ asset.disposal.plusvalenza_minusvalenza >= 0 ? '+' : '' }}€ {{ fmt(asset.disposal.plusvalenza_minusvalenza) }}
                                </dd>
                            </div>
                            <div v-if="asset.disposal.note">
                                <dt class="text-gray-500 dark:text-gray-400">Note</dt>
                                <dd class="text-gray-700 dark:text-gray-300">{{ asset.disposal.note }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Form dismissione -->
                    <div v-else-if="isActive" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">Registra dismissione</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">
                            VNC corrente: <strong class="text-gray-900 dark:text-gray-100">€ {{ fmt(vnc) }}</strong>
                        </p>
                        <form @submit.prevent="submitDismissione" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo dismissione *</label>
                                    <select v-model="dismissioneForm.tipo"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:outline-none">
                                        <option v-for="(label, key) in tipiDisposalLabel" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data dismissione *</label>
                                    <input v-model="dismissioneForm.data_dismissione" type="date" required
                                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:outline-none" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valore realizzo (€)</label>
                                    <input v-model="dismissioneForm.valore_realizzo" type="number" step="0.01" min="0"
                                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:outline-none"
                                           @change="calcolaPreview" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Note</label>
                                    <input v-model="dismissioneForm.note" type="text"
                                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:outline-none" />
                                </div>
                            </div>

                            <!-- Preview plus/minus -->
                            <div v-if="previewDismissione" class="rounded-lg p-4 border"
                                 :class="previewDismissione.plusvalenza_minusvalenza >= 0
                                     ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700'
                                     : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-700'">
                                <div class="grid grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-500 dark:text-gray-400">VNC alla data</p>
                                        <p class="font-bold text-gray-900 dark:text-gray-100">€ {{ fmt(previewDismissione.vnc) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 dark:text-gray-400">Fondo cumulato</p>
                                        <p class="font-bold text-gray-900 dark:text-gray-100">€ {{ fmt(previewDismissione.fondo_cumulato) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 dark:text-gray-400">
                                            {{ previewDismissione.plusvalenza_minusvalenza >= 0 ? 'Plusvalenza' : 'Minusvalenza' }}
                                        </p>
                                        <p class="font-bold text-lg"
                                           :class="previewDismissione.plusvalenza_minusvalenza >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                            {{ previewDismissione.plusvalenza_minusvalenza >= 0 ? '+' : '' }}€ {{ fmt(previewDismissione.plusvalenza_minusvalenza) }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-between items-center pt-2">
                                <button type="button" @click="calcolaPreview" :disabled="previewLoading"
                                        class="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-50">
                                    {{ previewLoading ? 'Calcolo…' : 'Calcola anteprima' }}
                                </button>
                                <button type="submit" :disabled="dismissioneForm.processing"
                                        class="px-4 py-2 rounded-lg bg-orange-600 text-white text-sm font-medium hover:bg-orange-700 disabled:opacity-50 transition">
                                    {{ dismissioneForm.processing ? 'Registrazione…' : 'Conferma dismissione' }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <div v-else class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-400 dark:text-gray-500 text-sm">
                        Il cespite è già stato dismesso o venduto.
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal dismissione (bottone header) -->
        <div v-if="showDismissione" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Dismissione: {{ asset.name }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Usa il tab <strong>Dismissione</strong> per vedere il form completo con anteprima.
                </p>
                <div class="flex justify-end gap-3">
                    <button @click="showDismissione = false; tab = 'dismissione'"
                            class="px-4 py-2 bg-orange-600 text-white text-sm rounded-lg hover:bg-orange-700 transition">
                        Vai al form
                    </button>
                    <button @click="showDismissione = false"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        Chiudi
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
