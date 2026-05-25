<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import PeriodSelector from '@/Components/Consultant/PeriodSelector.vue';
import { useConsultantStats } from '@/Composables/useConsultantStats.js';
import {
    ClipboardDocumentListIcon,
    PencilSquareIcon,
    ClockIcon,
    UsersIcon,
    BanknotesIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    BuildingLibraryIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline';

/* ── Props ────────────────────────────────────────────────────────────────── */
const props = defineProps({
    entity:           { type: Object, required: true },
    richieste:        { type: Array,  default: () => [] },
    note_fissate:     { type: Array,  default: () => [] },
    kpi:              { type: Object, default: () => ({}) },
    stats:            { type: Object, default: () => ({}) },
    currentPeriod:    { type: String, default: 'this_year' },
    periodDates:      { type: Object, default: () => ({ from: '', to: '' }) },
    availablePeriods: { type: Object, default: () => ({ min_date: '', max_date: '' }) },
});

/* ── Stats composable ─────────────────────────────────────────────────────── */
const statsLoading = ref(false);
const {
    formatCurrency, formatPercent,
    totaleEmesso, countFatture, daIncassare,
    totaleCosti, inScadenza30gg,
    totaleIncassi, totaleSpese, saldoCashflow,
    percRiconciliato,
    trendLabels, trendEntrate, trendUscite,
    membriAttivi: etsMembriAttivi, nuoviMembri, totaleDonazioni,
    capitaleSociale, prestitoSociale,
    ets, coop,
} = useConsultantStats(computed(() => props.stats));

/* ── Chart.js (lazy init) ─────────────────────────────────────────────────── */
const chartCanvas = ref(null);
let chartInstance = null;

async function initChart() {
    if (!chartCanvas.value || !trendLabels.value.length) return;
    const { Chart, registerables } = await import('chart.js');
    Chart.register(...registerables);

    if (chartInstance) chartInstance.destroy();

    chartInstance = new Chart(chartCanvas.value, {
        type: 'line',
        data: {
            labels: trendLabels.value,
            datasets: [
                {
                    label: 'Entrate',
                    data: trendEntrate.value,
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16,185,129,0.08)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                },
                {
                    label: 'Uscite',
                    data: trendUscite.value,
                    borderColor: '#EF4444',
                    backgroundColor: 'rgba(239,68,68,0.05)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { font: { size: 11 }, usePointStyle: true } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ` ${new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(ctx.raw)}`,
                    },
                },
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                y: {
                    ticks: {
                        font: { size: 10 },
                        callback: (v) => new Intl.NumberFormat('it-IT', { notation: 'compact', style: 'currency', currency: 'EUR' }).format(v),
                    },
                },
            },
        },
    });
}

onMounted(() => initChart());

/* ── Badge helpers ────────────────────────────────────────────────────────── */
const badgeClass = (color) => ({
    blue:   'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    green:  'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    gray:   'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    red:    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    orange: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
})[color] ?? 'bg-gray-100 text-gray-600';

const statoLabel = (s) => ({
    aperta: 'Aperta', in_attesa_risposta: 'In attesa',
    risposta_ricevuta: 'Risposta ricevuta', chiusa: 'Chiusa', annullata: 'Annullata',
})[s] ?? s;

const prioritaLabel = (p) => ({ urgente: 'Urgente', alta: 'Alta', normale: 'Normale', bassa: 'Bassa' })[p] ?? p;

const isScaduta = (d) => d && new Date(d) < new Date();

/* ── Saldo coloring ───────────────────────────────────────────────────────── */
const saldoColor = computed(() =>
    saldoCashflow.value >= 0
        ? 'text-green-600 dark:text-green-400'
        : 'text-red-500 dark:text-red-400'
);
</script>

<template>
    <AppLayout :title="entity.name">
        <Head :title="`${entity.name} — Consulente`" />

        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <Link :href="route('consultant.entities.index')"
                            class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                            ← Enti
                        </Link>
                    </div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ entity.name }}
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 capitalize">
                        {{ entity.organization_type ?? 'Ente' }} · {{ entity.plan ?? '—' }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('consultant.notes.index', entity.slug)">
                        <PrimaryButton class="text-sm">
                            <PencilSquareIcon class="size-4 me-1.5" />Note
                        </PrimaryButton>
                    </Link>
                    <Link :href="route('consultant.requests.create', entity.slug)">
                        <PrimaryButton class="text-sm">
                            <ClipboardDocumentListIcon class="size-4 me-1.5" />Nuova richiesta
                        </PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- ── Selettore periodo ───────────────────────────────────── -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-5 py-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Periodo:</span>
                        <PeriodSelector
                            :entity-slug="entity.slug"
                            :current-period="currentPeriod"
                            :period-dates="periodDates"
                            :available-periods="availablePeriods"
                            :loading="statsLoading"
                            @loading="(v) => { statsLoading = v; if (!v) initChart(); }"
                        />
                    </div>
                </div>

                <!-- ── KPI row ─────────────────────────────────────────────── -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">

                    <!-- Soci attivi -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="p-1.5 bg-green-50 dark:bg-green-900/30 rounded-lg">
                                <UsersIcon class="size-4 text-green-600 dark:text-green-400" />
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Soci attivi</span>
                        </div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ entity.is_cooperativa ? (coop?.soci_attivi ?? kpi.membri_attivi ?? '—') : (etsMembriAttivi || kpi.membri_attivi || '—') }}
                        </p>
                        <p v-if="nuoviMembri > 0 && !entity.is_cooperativa" class="text-xs text-green-600 dark:text-green-400 mt-0.5">+{{ nuoviMembri }} nel periodo</p>
                    </div>

                    <!-- Fatturato emesso -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="p-1.5 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                                <ArrowTrendingUpIcon class="size-4 text-blue-600 dark:text-blue-400" />
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Fatturato</span>
                        </div>
                        <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ formatCurrency(totaleEmesso) }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ countFatture }} fatture</p>
                    </div>

                    <!-- Costi -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="p-1.5 bg-red-50 dark:bg-red-900/30 rounded-lg">
                                <ArrowTrendingDownIcon class="size-4 text-red-500 dark:text-red-400" />
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Costi</span>
                        </div>
                        <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ formatCurrency(totaleCosti) }}</p>
                        <p v-if="inScadenza30gg > 0" class="text-xs text-orange-500 mt-0.5">{{ inScadenza30gg }} in scadenza 30gg</p>
                    </div>

                    <!-- Saldo cash -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="p-1.5 bg-purple-50 dark:bg-purple-900/30 rounded-lg">
                                <BanknotesIcon class="size-4 text-purple-600 dark:text-purple-400" />
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Saldo</span>
                        </div>
                        <p :class="['text-xl font-bold', saldoColor]">{{ formatCurrency(saldoCashflow) }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">In: {{ formatCurrency(totaleIncassi) }}</p>
                    </div>

                    <!-- Riconciliazione banca -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="p-1.5 bg-teal-50 dark:bg-teal-900/30 rounded-lg">
                                <BuildingLibraryIcon class="size-4 text-teal-600 dark:text-teal-400" />
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Banca riconcil.</span>
                        </div>
                        <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ formatPercent(percRiconciliato) }}</p>
                        <div class="mt-1 h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                            <div :style="{ width: percRiconciliato + '%' }"
                                class="h-full bg-teal-500 rounded-full transition-all duration-500" />
                        </div>
                    </div>

                    <!-- Richieste aperte -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="p-1.5 bg-yellow-50 dark:bg-yellow-900/30 rounded-lg">
                                <ClipboardDocumentListIcon class="size-4 text-yellow-600 dark:text-yellow-400" />
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Richieste aperte</span>
                        </div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ richieste.length }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ note_fissate.length }} note in evidenza</p>
                    </div>
                </div>

                <!-- ── Trend mensile (grafico) + info specifiche per tipo ──── -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- Grafico entrate/uscite -->
                    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Trend mensile — entrate vs uscite</h3>
                        <div class="relative" style="height: 220px;">
                            <div v-if="!trendLabels.length"
                                class="absolute inset-0 flex items-center justify-center text-sm text-gray-400">
                                Nessun dato disponibile per il periodo selezionato
                            </div>
                            <canvas v-else ref="chartCanvas" />
                        </div>
                    </div>

                    <!-- Sezione ETS o Cooperativa -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">

                        <!-- ETS -->
                        <template v-if="!entity.is_cooperativa && ets">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-1.5">
                                <CheckCircleIcon class="size-4 text-blue-500" />
                                Dati ETS
                            </h3>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center py-2 border-b border-gray-50 dark:border-gray-700">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Donazioni ricevute</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(totaleDonazioni) }}</span>
                                </div>
                                <div v-if="ets.rendiconto" class="flex justify-between items-center py-2 border-b border-gray-50 dark:border-gray-700">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Entrate istituzionali</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(ets.rendiconto.entrate_istituzionali) }}</span>
                                </div>
                                <div v-if="ets.rendiconto" class="flex justify-between items-center py-2">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Uscite istituzionali</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(ets.rendiconto.uscite_istituzionali) }}</span>
                                </div>
                            </div>
                        </template>

                        <!-- Cooperativa -->
                        <template v-else-if="entity.is_cooperativa && coop">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-1.5">
                                <BuildingLibraryIcon class="size-4 text-purple-500" />
                                Dati cooperativa
                            </h3>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center py-2 border-b border-gray-50 dark:border-gray-700">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Capitale sociale versato</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(capitaleSociale) }}</span>
                                </div>
                                <div v-if="coop.capitale_sociale" class="flex justify-between items-center py-2 border-b border-gray-50 dark:border-gray-700">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Quote in sospeso</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ coop.capitale_sociale.quote_in_sospeso ?? 0 }}</span>
                                </div>
                                <div class="flex justify-between items-center py-2">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Prestito sociale</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(prestitoSociale) }}</span>
                                </div>
                            </div>
                        </template>

                        <!-- No data -->
                        <template v-else>
                            <div class="flex items-center justify-center h-full text-sm text-gray-400 py-8">
                                Nessun dato specifico disponibile
                            </div>
                        </template>
                    </div>
                </div>

                <!-- ── Seconda riga: richieste aperte + note fissate ───────── -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- Richieste aperte -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Richieste aperte</h3>
                            <Link :href="route('consultant.requests.index', entity.slug)"
                                class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                Vedi tutte
                            </Link>
                        </div>
                        <div v-if="richieste.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                            Nessuna richiesta aperta
                        </div>
                        <ul v-else class="divide-y divide-gray-50 dark:divide-gray-700">
                            <li v-for="r in richieste" :key="r.id"
                                class="px-5 py-3 flex items-center justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span :class="['text-xs font-medium px-1.5 py-0.5 rounded', badgeClass(r.priorita_color)]">
                                            {{ prioritaLabel(r.priorita) }}
                                        </span>
                                    </div>
                                    <Link :href="route('consultant.requests.show', [entity.slug, r.id])"
                                        class="text-sm text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400 line-clamp-1 font-medium">
                                        {{ r.titolo }}
                                    </Link>
                                </div>
                                <div v-if="r.data_scadenza" class="flex-shrink-0 flex items-center gap-1 text-xs"
                                    :class="isScaduta(r.data_scadenza) ? 'text-red-500' : 'text-gray-400 dark:text-gray-500'">
                                    <ClockIcon class="size-3.5" />
                                    {{ new Date(r.data_scadenza).toLocaleDateString('it-IT') }}
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Note fissate -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Note in evidenza</h3>
                            <Link :href="route('consultant.notes.index', entity.slug)"
                                class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                Tutte le note
                            </Link>
                        </div>
                        <div v-if="note_fissate.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                            Nessuna nota in evidenza
                        </div>
                        <ul v-else class="divide-y divide-gray-50 dark:divide-gray-700 px-5 py-3 space-y-2">
                            <li v-for="n in note_fissate" :key="n.id" class="py-2">
                                <div class="flex items-start gap-2">
                                    <span class="mt-0.5 text-yellow-500 text-xs">★</span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-gray-800 dark:text-gray-200 line-clamp-2">{{ n.testo }}</p>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span :class="[
                                                'text-xs px-1.5 py-0.5 rounded',
                                                n.visibilita === 'condivisa'
                                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                    : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
                                            ]">
                                                {{ n.visibilita === 'condivisa' ? 'Condivisa' : 'Interna' }}
                                            </span>
                                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ n.updated_at }}</span>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- ── Avvisi (fatture scadute, banca non riconciliata) ──── -->
                <div v-if="inScadenza30gg > 0 || percRiconciliato < 50"
                    class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <ExclamationTriangleIcon class="size-5 text-amber-500 flex-shrink-0 mt-0.5" />
                        <div class="space-y-1">
                            <p v-if="inScadenza30gg > 0" class="text-sm text-amber-800 dark:text-amber-200">
                                <strong>{{ inScadenza30gg }}</strong> fatture passive in scadenza nei prossimi 30 giorni
                            </p>
                            <p v-if="percRiconciliato < 50" class="text-sm text-amber-800 dark:text-amber-200">
                                Solo <strong>{{ formatPercent(percRiconciliato) }}</strong> dei movimenti bancari è riconciliato
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
