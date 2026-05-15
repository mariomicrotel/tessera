<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CheckCircleIcon, XCircleIcon, MagnifyingGlassIcon,
    ArrowPathIcon, ChevronDownIcon, ChevronUpIcon,
} from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    estratto:        Object,
    movimenti:       Array,
    totaleAvere:     Number,
    totaleDare:      Number,
    nonRiconciliati: Number,
});

const fmt     = (v) => Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2 });
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

// Filtro locale
const soloNonRiconc = ref(false);
const filtrati = computed(() =>
    soloNonRiconc.value
        ? props.movimenti.filter(m => !m.riconciliato)
        : props.movimenti
);

// Match panel
const matchingId   = ref(null); // id movimento aperto per match
const suggerimenti = ref([]);
const loadingSugg  = ref(false);
const matchForm    = ref({ entita_tipo: '', entita_id: '', nota: '' });

const openMatch = async (m) => {
    matchingId.value = m.id;
    matchForm.value  = { entita_tipo: '', entita_id: '', nota: '' };
    suggerimenti.value = [];
    loadingSugg.value = true;
    try {
        const res = await fetch(route('riconciliazione.suggerisci', m));
        suggerimenti.value = await res.json();
    } catch { /* silenzioso */ }
    loadingSugg.value = false;
};

const closeMatch = () => { matchingId.value = null; };

const useSuggerimento = (s) => {
    matchForm.value.entita_tipo = s.entita_tipo;
    matchForm.value.entita_id   = s.entita_id;
};

const submitMatch = (m) => {
    router.post(route('riconciliazione.match', m), matchForm.value, {
        preserveScroll: true,
        onSuccess: () => { matchingId.value = null; },
    });
};

const unmatch = (m) => {
    router.delete(route('riconciliazione.unmatch', m), { preserveScroll: true });
};
</script>

<template>
    <AppLayout :title="`Riconciliazione — ${estratto.nome_file}`">
        <Head :title="`Riconciliazione ${estratto.nome_file}`" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-2">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ estratto.nome_file }}
                    </h2>
                    <p class="text-sm text-gray-500">
                        {{ estratto.banca ?? '' }}
                        <span v-if="estratto.iban" class="font-mono ml-2">{{ estratto.iban }}</span>
                        — {{ fmtDate(estratto.periodo_dal) }} / {{ fmtDate(estratto.periodo_al) }}
                    </p>
                </div>
                <Link :href="route('riconciliazione.index')">
                    <SecondaryButton>← Torna all'elenco</SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- KPI -->
                <div class="grid grid-cols-4 gap-3">
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <div class="text-xs text-gray-500">Movimenti</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ movimenti.length }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <div class="text-xs text-gray-500">Totale Avere (+)</div>
                        <div class="text-2xl font-bold text-green-700">€ {{ fmt(totaleAvere) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <div class="text-xs text-gray-500">Totale Dare (−)</div>
                        <div class="text-2xl font-bold text-red-700">€ {{ fmt(totaleDare) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <div class="text-xs text-gray-500">Da riconciliare</div>
                        <div :class="['text-2xl font-bold', nonRiconciliati > 0 ? 'text-orange-600' : 'text-green-600']">
                            {{ nonRiconciliati }}
                        </div>
                    </div>
                </div>

                <!-- Filtro -->
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" v-model="soloNonRiconc" class="rounded" />
                        Mostra solo movimenti non riconciliati
                    </label>
                </div>

                <!-- Tabella movimenti -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-3 py-3 text-center text-gray-500 dark:text-gray-400 w-8"></th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Data valuta</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Descrizione</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Importo</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Tipo</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">Riconcilia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template v-for="m in filtrati" :key="m.id">
                                <tr :class="['hover:bg-gray-50 dark:hover:bg-gray-750',
                                             m.riconciliato ? 'opacity-60' : '']">
                                    <td class="px-3 py-3 text-center">
                                        <CheckCircleIcon v-if="m.riconciliato" class="size-4 text-green-500" />
                                        <div v-else class="size-4 rounded-full border-2 border-gray-300"></div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                        {{ fmtDate(m.data_valuta) }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300 max-w-xs">
                                        <div class="truncate" :title="m.descrizione">{{ m.descrizione }}</div>
                                        <div v-if="m.riferimento" class="text-xs text-gray-400 font-mono">{{ m.riferimento }}</div>
                                        <!-- Riconciliazioni esistenti -->
                                        <div v-if="m.riconciliato && m.riconciliazioni.length" class="mt-1 text-xs text-green-700 dark:text-green-400">
                                            <span v-for="r in m.riconciliazioni" :key="r.id">
                                                {{ r.entita_tipo?.replace(/_/g, ' ') ?? 'manuale' }}
                                                <span v-if="r.nota"> — {{ r.nota }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold"
                                        :class="m.tipo === 'avere' ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400'">
                                        {{ m.tipo === 'avere' ? '+' : '−' }}€ {{ fmt(m.importo) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span :class="['px-2 py-0.5 rounded-full text-xs font-medium',
                                               m.tipo === 'avere' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                                            {{ m.tipo === 'avere' ? 'Avere' : 'Dare' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button v-if="!m.riconciliato"
                                                @click="openMatch(m)"
                                                class="text-indigo-600 hover:text-indigo-800 text-xs underline">
                                            Abbina
                                        </button>
                                        <button v-else
                                                @click="unmatch(m)"
                                                class="text-gray-400 hover:text-red-600 text-xs underline">
                                            Rimuovi
                                        </button>
                                    </td>
                                </tr>

                                <!-- Pannello match inline -->
                                <tr v-if="matchingId === m.id" :key="`match-${m.id}`">
                                    <td colspan="6" class="px-6 py-4 bg-indigo-50 dark:bg-indigo-900/20 border-t border-indigo-200">
                                        <div class="space-y-3">
                                            <h4 class="font-semibold text-indigo-800 dark:text-indigo-200 text-sm">
                                                Abbina: {{ m.descrizione }} — {{ m.tipo === 'avere' ? '+' : '−' }}€ {{ fmt(m.importo) }}
                                            </h4>

                                            <!-- Suggerimenti automatici -->
                                            <div v-if="loadingSugg" class="text-xs text-indigo-600">Caricamento suggerimenti…</div>
                                            <div v-else-if="suggerimenti.length" class="flex flex-wrap gap-2">
                                                <button v-for="s in suggerimenti" :key="s.entita_id"
                                                        @click="useSuggerimento(s)"
                                                        :class="['text-xs px-2 py-1 rounded border transition',
                                                                 matchForm.entita_id === s.entita_id
                                                                     ? 'bg-indigo-600 text-white border-indigo-600'
                                                                     : 'bg-white text-indigo-700 border-indigo-300 hover:bg-indigo-50']">
                                                    {{ s.label }}
                                                </button>
                                            </div>
                                            <p v-else class="text-xs text-gray-500">Nessun suggerimento automatico trovato.</p>

                                            <!-- Form manuale -->
                                            <div class="flex flex-wrap gap-3 items-end">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-0.5">Tipo entità</label>
                                                    <select v-model="matchForm.entita_tipo"
                                                            class="rounded border-gray-300 dark:border-gray-600 text-xs">
                                                        <option value="">— manuale —</option>
                                                        <option value="fattura_passiva">Fattura passiva</option>
                                                        <option value="fattura_attiva">Fattura attiva</option>
                                                        <option value="prima_nota_entry">Prima nota</option>
                                                        <option value="incasso">Incasso</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-0.5">ID entità</label>
                                                    <input v-model="matchForm.entita_id" type="text"
                                                           placeholder="UUID o ID"
                                                           class="rounded border-gray-300 dark:border-gray-600 text-xs w-40" />
                                                </div>
                                                <div class="flex-1">
                                                    <label class="block text-xs font-medium text-gray-700 mb-0.5">Nota</label>
                                                    <input v-model="matchForm.nota" type="text"
                                                           class="block w-full rounded border-gray-300 dark:border-gray-600 text-xs" />
                                                </div>
                                                <PrimaryButton @click="submitMatch(m)" class="py-1 px-3 text-xs">
                                                    Conferma
                                                </PrimaryButton>
                                                <SecondaryButton @click="closeMatch" class="py-1 px-3 text-xs">
                                                    Annulla
                                                </SecondaryButton>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="!filtrati.length">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                                    Nessun movimento da mostrare.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
