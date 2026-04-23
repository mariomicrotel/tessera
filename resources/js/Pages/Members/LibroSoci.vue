<script setup>
import { computed, ref, watch } from 'vue';
import { ArrowLeftIcon, ArrowRightIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    members:      Object,
    filters:      Object,
    totale_quote: Number,
});

const page          = usePage();
const isCooperativa = computed(() => page.props.is_cooperativa === true);
const isCoopLavoro  = computed(() => isCooperativa.value && page.props.cooperative_type === 'lavoro');

// Filtro tipo socio
const tipoSocio = ref(props.filters?.tipo_socio || '');
let filterTimeout = null;
watch(tipoSocio, (val) => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => {
        router.get(
            route('libro-soci.index'),
            { tipo_socio: val || undefined },
            { preserveState: true, replace: true },
        );
    }, 200);
});

// Badge tipo persona
const badgeTipoPersona = (m) =>
    m.tipo_persona === 'giuridica'
        ? { label: '🏢 Giuridica', cls: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' }
        : { label: '👤 Fisica',    cls: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' };

// Badge qualifica socio cooperativa
const badgeQualifica = (m) => {
    if (m.socio_lavoratore) return { label: 'Lavoratore', cls: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' };
    if (m.socio_sovventore) return { label: 'Sovventore', cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' };
    if (m.socio_onorario)   return { label: 'Onorario',   cls: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' };
    return                         { label: 'Ordinario',  cls: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' };
};

// Nome visualizzato per riga
const displayName = (m) =>
    m.tipo_persona === 'giuridica'
        ? (m.ragione_sociale ?? '—')
        : (m.cognome ?? '—');

const displayNome = (m) =>
    m.tipo_persona === 'giuridica'
        ? (m.referente_nome ? `${m.referente_nome} ${m.referente_cognome ?? ''}`.trim() : '—')
        : (m.nome ?? '—');
</script>

<template>
    <AppLayout title="Libro soci">
        <Head title="Libro soci" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Libro soci</h2>
                <div class="flex items-center gap-3">
                    <!-- Export CSV (solo cooperative) -->
                    <a
                        v-if="isCooperativa"
                        :href="route('libro-soci.export-coop')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md"
                    >
                        <ArrowDownTrayIcon class="size-4" aria-hidden="true" />
                        Esporta CSV
                    </a>
                    <Link :href="route('members.index')" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
                        <ArrowLeftIcon class="size-4" aria-hidden="true" />Elenco soci
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Elenco dei soci ammessi (con data di iscrizione), ordinato per data di ammissione.
                    Sono esclusi i cessati, dimessi, esclusi e morosi.
                </p>

                <!-- Filtri cooperativa -->
                <div v-if="isCooperativa" class="flex flex-wrap items-center gap-3">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Tipo socio:</label>
                    <div class="flex gap-2">
                        <button
                            v-for="opt in [
                                { val: '',            label: 'Tutti' },
                                { val: 'lavoratori',  label: '🟢 Lavoratori', show: isCoopLavoro },
                                { val: 'sovventori',  label: '🟡 Sovventori' },
                                { val: 'onorari',     label: '🔴 Onorari' },
                            ]"
                            :key="opt.val"
                            v-show="opt.show !== false"
                            @click="tipoSocio = opt.val"
                            :class="[
                                'px-3 py-1 rounded-full text-xs font-medium border transition-colors',
                                tipoSocio === opt.val
                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                    : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700',
                            ]"
                        >{{ opt.label }}</button>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">N.</th>
                                <!-- Colonne cooperative -->
                                <th v-if="isCooperativa" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tipo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    {{ isCooperativa ? 'Cognome / Ragione sociale' : 'Cognome' }}
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    {{ isCooperativa ? 'Nome / Referente' : 'Nome' }}
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data iscrizione</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    {{ isCooperativa ? 'CF / P.IVA' : 'Codice fiscale' }}
                                </th>
                                <!-- Colonne extra cooperative -->
                                <th v-if="isCooperativa" class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Quote</th>
                                <th v-if="isCooperativa" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Qualifica</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="(m, index) in members.data" :key="m.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ m.numero_tessera ?? '—' }}</td>

                                <!-- Badge tipo persona (coop) -->
                                <td v-if="isCooperativa" class="px-4 py-2">
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium" :class="badgeTipoPersona(m).cls">
                                        {{ badgeTipoPersona(m).label }}
                                    </span>
                                </td>

                                <td class="px-4 py-2">
                                    <Link :href="route('members.show', m.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ isCooperativa ? displayName(m) : m.cognome }}
                                    </Link>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                                    {{ isCooperativa ? displayNome(m) : m.nome }}
                                </td>
                                <td class="px-4 py-2 text-sm">
                                    {{ m.data_iscrizione ? new Date(m.data_iscrizione).toLocaleDateString('it-IT') : '—' }}
                                </td>
                                <td class="px-4 py-2 text-sm font-mono">
                                    {{ isCooperativa
                                        ? (m.tipo_persona === 'giuridica' ? (m.partita_iva || '—') : (m.codice_fiscale || '—'))
                                        : (m.codice_fiscale || '—') }}
                                </td>

                                <!-- Quote capitale (coop) -->
                                <td v-if="isCooperativa" class="px-4 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    {{ m.numero_quote_capitale ?? 0 }}
                                </td>

                                <!-- Badge qualifica (coop) -->
                                <td v-if="isCooperativa" class="px-4 py-2">
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium" :class="badgeQualifica(m).cls">
                                        {{ badgeQualifica(m).label }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>

                        <!-- Totale quote (coop) -->
                        <tfoot v-if="isCooperativa && members.data?.length">
                            <tr class="bg-gray-50 dark:bg-gray-700/50 border-t-2 border-gray-300 dark:border-gray-600">
                                <td :colspan="5" class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 text-right">
                                    Totale quote sottoscritte (soci attivi):
                                </td>
                                <td class="px-4 py-2 text-right text-sm font-bold text-gray-900 dark:text-white">
                                    {{ totale_quote ?? 0 }}
                                </td>
                                <td class="px-4 py-2"></td>
                            </tr>
                        </tfoot>
                    </table>

                    <p v-if="!members.data?.length" class="px-4 py-8 text-center text-gray-500">Nessun socio nel libro.</p>

                    <div v-if="members.prev_page_url || members.next_page_url" class="px-4 py-2 border-t flex justify-between">
                        <Link v-if="members.prev_page_url" :href="members.prev_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
                            <ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro
                        </Link>
                        <span v-else></span>
                        <Link v-if="members.next_page_url" :href="members.next_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
                            Avanti<ArrowRightIcon class="size-4" aria-hidden="true" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
