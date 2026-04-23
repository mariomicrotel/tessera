<script setup>
import { computed, ref, watch } from 'vue';
import {
    PlusIcon, ArrowDownTrayIcon, ArrowLeftIcon, ArrowRightIcon,
    BanknotesIcon, UserGroupIcon, ClockIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    shares:              Object,
    situazione_capitale: Object,
    filters:             Object,
});

// ── Filtri ────────────────────────────────────────────────────────────────────

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
let filterTimeout = null;

function applyFilters() {
    router.get(
        route('capitale-sociale.index'),
        { search: search.value || undefined, status: status.value || undefined },
        { preserveState: true, replace: true },
    );
}

watch([search, status], () => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(applyFilters, 300);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

const fmt = (v) => new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(Number(v) || 0);

const memberName = (share) => {
    const m = share.member;
    if (!m) return `—`;
    if (m.tipo_persona === 'giuridica') return m.ragione_sociale ?? '—';
    return `${m.cognome ?? ''} ${m.nome ?? ''}`.trim() || '—';
};

const statusConfig = {
    sottoscritta:         { label: 'Sottoscritta',     cls: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' },
    parzialmente_versata: { label: 'Parz. versata',    cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' },
    versata:              { label: 'Versata',           cls: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' },
    riscattata:           { label: 'Riscattata',        cls: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' },
    annullata:            { label: 'Annullata',         cls: 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400' },
};

const badgeFor = (s) => statusConfig[s] ?? { label: s, cls: 'bg-gray-100 text-gray-600' };

const daVersare = computed(() => Number(props.situazione_capitale?.capitale_da_versare ?? 0));
</script>

<template>
    <AppLayout title="Capitale Sociale">
        <Head title="Capitale Sociale" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-2">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Capitale Sociale
                </h2>
                <div class="flex gap-2">
                    <a
                        :href="route('capitale-sociale.export')"
                        class="inline-flex items-center gap-1.5 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        <ArrowDownTrayIcon class="size-4" aria-hidden="true" />
                        Esporta CSV
                    </a>
                    <Link :href="route('capitale-sociale.create')">
                        <PrimaryButton>
                            <PlusIcon class="size-4 me-2" aria-hidden="true" />
                            Nuova Sottoscrizione
                        </PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- KPI Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Totale Sottoscritto -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 flex items-start gap-4">
                        <div class="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/40 shrink-0">
                            <BanknotesIcon class="size-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide truncate">Sottoscritto</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">
                                {{ fmt(situazione_capitale?.totale_sottoscritto) }}
                            </p>
                        </div>
                    </div>

                    <!-- Totale Versato -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 flex items-start gap-4">
                        <div class="p-2 rounded-lg bg-green-100 dark:bg-green-900/40 shrink-0">
                            <CheckCircleIcon class="size-5 text-green-600 dark:text-green-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide truncate">Versato</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">
                                {{ fmt(situazione_capitale?.totale_versato) }}
                            </p>
                        </div>
                    </div>

                    <!-- Da Versare -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 flex items-start gap-4">
                        <div
                            class="p-2 rounded-lg shrink-0"
                            :class="daVersare > 0 ? 'bg-amber-100 dark:bg-amber-900/40' : 'bg-gray-100 dark:bg-gray-700'"
                        >
                            <ClockIcon
                                class="size-5"
                                :class="daVersare > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400'"
                            />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide truncate">Da versare</p>
                            <p
                                class="text-xl font-bold mt-0.5"
                                :class="daVersare > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white'"
                            >
                                {{ fmt(daVersare) }}
                            </p>
                        </div>
                    </div>

                    <!-- N° Soci con quote -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 flex items-start gap-4">
                        <div class="p-2 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 shrink-0">
                            <UserGroupIcon class="size-5 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide truncate">Soci con quote</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">
                                {{ situazione_capitale?.numero_soci_con_quote ?? 0 }}
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                {{ situazione_capitale?.numero_quote_totali ?? 0 }} quote tot.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Filtri -->
                <div class="flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Cerca socio</label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Nome, cognome, ragione sociale…"
                            class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm w-56"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Status</label>
                        <select
                            v-model="status"
                            class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                        >
                            <option value="">Tutti</option>
                            <option value="sottoscritta">Sottoscritta</option>
                            <option value="parzialmente_versata">Parz. versata</option>
                            <option value="versata">Versata</option>
                            <option value="riscattata">Riscattata</option>
                            <option value="annullata">Annullata</option>
                        </select>
                    </div>
                </div>

                <!-- Tabella -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Socio</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tipo</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">N° Quote</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Val. Unit.</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Sottoscritto</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Versato</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="s in shares.data"
                                :key="s.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                :class="{ 'opacity-60': s.status === 'annullata' }"
                            >
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ memberName(s) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {{ s.member?.tipo_persona === 'giuridica' ? '🏢 Giuridica' : '👤 Fisica' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-semibold text-gray-800 dark:text-gray-200">
                                    {{ s.numero_quote }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">
                                    {{ fmt(s.valore_unitario) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-800 dark:text-gray-200">
                                    {{ fmt(s.totale_sottoscritto) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right">
                                    <span :class="Number(s.totale_versato) < Number(s.totale_sottoscritto) ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400'" class="font-medium">
                                        {{ fmt(s.totale_versato) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium"
                                        :class="badgeFor(s.status).cls"
                                    >
                                        {{ badgeFor(s.status).label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-sm space-x-3 whitespace-nowrap">
                                    <Link
                                        :href="route('capitale-sociale.show', s.id)"
                                        class="text-indigo-600 dark:text-indigo-400 hover:underline"
                                    >Dettaglio</Link>
                                    <Link
                                        v-if="!['versata','riscattata','annullata'].includes(s.status)"
                                        :href="route('capitale-sociale.show', s.id) + '#versa'"
                                        class="text-green-600 dark:text-green-400 hover:underline"
                                    >Versa</Link>
                                    <Link
                                        v-if="!['riscattata','annullata'].includes(s.status)"
                                        :href="route('capitale-sociale.show', s.id) + '#riscatta'"
                                        class="text-red-500 dark:text-red-400 hover:underline"
                                    >Riscatta</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="!shares.data?.length" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                        Nessuna quota trovata.
                    </p>

                    <!-- Paginazione -->
                    <div v-if="shares.prev_page_url || shares.next_page_url" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <Link
                            v-if="shares.prev_page_url"
                            :href="shares.prev_page_url"
                            class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline text-sm"
                        >
                            <ArrowLeftIcon class="size-4" />Indietro
                        </Link>
                        <span v-else></span>
                        <span class="text-xs text-gray-400">Pagina {{ shares.current_page }} / {{ shares.last_page }}</span>
                        <Link
                            v-if="shares.next_page_url"
                            :href="shares.next_page_url"
                            class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline text-sm"
                        >
                            Avanti<ArrowRightIcon class="size-4" />
                        </Link>
                        <span v-else></span>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
