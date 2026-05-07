<script setup>
import { computed, onMounted, ref, shallowRef } from 'vue';

const Bar      = shallowRef(null);
const Line     = shallowRef(null);
const Doughnut = shallowRef(null);
const ready    = ref(false);

const props = defineProps({
    labels:       { type: Array,  default: () => [] },
    incassi:      { type: Array,  default: () => [] },
    uscite:       { type: Array,  default: () => [] },
    sociStato:    { type: Object, default: () => ({}) },
    iscrizioni:   { type: Array,  default: () => [] },
    cessazioni:   { type: Array,  default: () => [] },
    donazioni:    { type: Object, default: null },
    isCooperativa: { type: Boolean, default: false },
});

/* ── Grafico 1: BarChart Incassi/Uscite ───────────────────────────────── */
const barData = computed(() => ({
    labels: props.labels,
    datasets: [
        {
            label: 'Incassi',
            data: props.incassi,
            backgroundColor: 'rgba(34,197,94,0.7)',
            borderColor: 'rgba(22,163,74,1)',
            borderWidth: 1,
            borderRadius: 3,
        },
        {
            label: 'Uscite',
            data: props.uscite,
            backgroundColor: 'rgba(239,68,68,0.7)',
            borderColor: 'rgba(220,38,38,1)',
            borderWidth: 1,
            borderRadius: 3,
        },
    ],
}));

const barOptions = {
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
        legend: { position: 'top' },
        title: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => `${ctx.dataset.label}: € ${Number(ctx.parsed.y).toLocaleString('it-IT', { minimumFractionDigits: 2 })}`,
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: (v) => '€ ' + Number(v).toLocaleString('it-IT'),
            },
        },
    },
};

/* ── Grafico 2: DoughnutChart Composizione Soci ───────────────────────── */
const statoLabels = {
    attivo:     'Attivi',
    aspirante:  'Aspiranti',
    moroso:     'Morosi',
    sospeso:    'Sospesi',
    dimesso:    'Dimessi',
    escluso:    'Esclusi',
    cessato:    'Cessati',
    decesso:    'Deceduti',
};

const statoColors = {
    attivo:     'rgba(34,197,94,0.8)',
    aspirante:  'rgba(99,102,241,0.8)',
    moroso:     'rgba(234,179,8,0.8)',
    sospeso:    'rgba(251,146,60,0.8)',
    dimesso:    'rgba(148,163,184,0.8)',
    escluso:    'rgba(239,68,68,0.8)',
    cessato:    'rgba(107,114,128,0.8)',
    decesso:    'rgba(30,41,59,0.8)',
};

const doughnutSociData = computed(() => {
    const entries = Object.entries(props.sociStato).filter(([, v]) => v > 0);
    return {
        labels: entries.map(([k]) => statoLabels[k] ?? k),
        datasets: [{
            data: entries.map(([, v]) => v),
            backgroundColor: entries.map(([k]) => statoColors[k] ?? 'rgba(203,213,225,0.8)'),
            borderWidth: 1,
        }],
    };
});

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
        legend: { position: 'right' },
    },
};

/* ── Grafico 5: LineChart Andamento Soci ──────────────────────────────── */
const lineData = computed(() => ({
    labels: props.labels,
    datasets: [
        {
            label: 'Nuove iscrizioni',
            data: props.iscrizioni,
            borderColor: 'rgba(34,197,94,1)',
            backgroundColor: 'rgba(34,197,94,0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 3,
        },
        {
            label: 'Cessazioni',
            data: props.cessazioni,
            borderColor: 'rgba(239,68,68,1)',
            backgroundColor: 'rgba(239,68,68,0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 3,
        },
    ],
}));

const lineOptions = {
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
        legend: { position: 'top' },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { stepSize: 1 },
        },
    },
};

/* ── Grafico 6: DoughnutChart Donazioni (solo ETS) ────────────────────── */
const doughnutDonazioniData = computed(() => {
    if (!props.donazioni) return null;
    return {
        labels: ['Quote sociali', 'Donazioni', 'Incassi generici'],
        datasets: [{
            data: [props.donazioni.quote, props.donazioni.donazioni, props.donazioni.altri],
            backgroundColor: [
                'rgba(99,102,241,0.8)',
                'rgba(34,197,94,0.8)',
                'rgba(251,191,36,0.8)',
            ],
            borderWidth: 1,
        }],
    };
});

/* ── Lazy load chart.js on mount, then show on scroll ────────────────── */
const root = ref(null);
const visible = ref(false);
onMounted(async () => {
    try {
        const chartjs   = await import('chart.js');
        const vueChartjs = await import('vue-chartjs');

        chartjs.Chart.register(
            chartjs.CategoryScale, chartjs.LinearScale,
            chartjs.BarElement, chartjs.LineElement, chartjs.PointElement, chartjs.ArcElement,
            chartjs.Title, chartjs.Tooltip, chartjs.Legend,
            chartjs.Filler,
        );

        Bar.value      = vueChartjs.Bar;
        Line.value     = vueChartjs.Line;
        Doughnut.value = vueChartjs.Doughnut;
        ready.value    = true;
    } catch (e) {
        console.warn('AnalyticsCharts: failed to load chart.js', e);
        return;
    }

    const observer = new IntersectionObserver(
        ([e]) => { if (e.isIntersecting) { visible.value = true; observer.disconnect(); } },
        { threshold: 0.1 }
    );
    if (root.value) observer.observe(root.value);
});
</script>

<template>
    <div ref="root">
        <div v-if="ready && visible" class="grid grid-cols-1 lg:grid-cols-2 gap-4">

            <!-- Grafico 1: Incassi/Uscite per mese -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">
                    Incassi / Uscite — ultimi 12 mesi
                </h3>
                <component :is="Bar" :data="barData" :options="barOptions" />
            </div>

            <!-- Grafico 2: Composizione soci per stato -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">
                    Composizione soci per stato
                </h3>
                <component :is="Doughnut" :data="doughnutSociData" :options="doughnutOptions" />
            </div>

            <!-- Grafico 5: Andamento soci -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">
                    Andamento soci — ultimi 12 mesi
                </h3>
                <component :is="Line" :data="lineData" :options="lineOptions" />
            </div>

            <!-- Grafico 6: Distribuzione donazioni (solo ETS non-coop) -->
            <div v-if="!isCooperativa && donazioni" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">
                    Distribuzione incassi per tipo — ultimi 12 mesi
                </h3>
                <component :is="Doughnut" :data="doughnutDonazioniData" :options="doughnutOptions" />
            </div>

        </div>

        <!-- Placeholder mentre chart.js non è ancora caricato o elemento fuori viewport -->
        <div v-else class="h-8 flex items-center justify-center text-xs text-gray-400 dark:text-gray-500">
            <span v-if="!ready">Caricamento grafici...</span>
        </div>
    </div>
</template>
