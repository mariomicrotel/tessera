<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    stats: { type: Object, required: true },
});

const live = ref(props.stats);
const autoRefresh = ref(true);
const lastFetch = ref(new Date());
let intervalId = null;

const fmtEur = (n) => '€ ' + Number(n ?? 0).toFixed(2).replace('.', ',');
const fmtNum = (n) => Number(n ?? 0).toLocaleString('it-IT');
const fmtDate = (s) => new Date(s).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit' });
const fmtTime = (d) => d.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

const todayPct = computed(() => live.value?.today?.pct ?? 0);
const todayCostPct = computed(() => {
    const cost = live.value?.today?.cost ?? 0;
    const warn = live.value?.today?.warning_cost ?? 10;
    return Math.min(100, Math.round((cost / warn) * 100));
});
const monthCostPct = computed(() => {
    const cost = live.value?.month?.cost ?? 0;
    const warn = live.value?.month?.warning_cost ?? 200;
    return Math.min(100, Math.round((cost / warn) * 100));
});

const barColor = (pct) => {
    if (pct >= 90) return 'bg-red-500';
    if (pct >= 70) return 'bg-amber-500';
    if (pct >= 40) return 'bg-blue-500';
    return 'bg-green-500';
};

const maxDayCalls = computed(() => Math.max(1, ...(live.value?.history_30d ?? []).map(d => d.calls)));

const refresh = async () => {
    try {
        const res = await fetch(route('company-enrichment.stats'), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (res.ok) {
            live.value = await res.json();
            lastFetch.value = new Date();
        }
    } catch (e) {
        // silent
    }
};

const startAuto = () => {
    if (intervalId) clearInterval(intervalId);
    if (autoRefresh.value) {
        intervalId = setInterval(refresh, 15000); // 15 secondi
    }
};

const toggleAuto = () => {
    autoRefresh.value = !autoRefresh.value;
    startAuto();
};

onMounted(startAuto);
onBeforeUnmount(() => { if (intervalId) clearInterval(intervalId); });
</script>

<template>
    <AppLayout title="Contatore costi API">
        <Head title="Consumo API OpenAPI Company" />
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Contatore costi OpenAPI Company
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Monitoraggio chiamate e costi in tempo reale (auto-refresh 15s)
                    </p>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Ultimo aggiornamento: {{ fmtTime(lastFetch) }}
                    </span>
                    <button @click="refresh"
                        class="px-3 py-1.5 text-xs rounded-md border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        ↻ Aggiorna
                    </button>
                    <button @click="toggleAuto"
                        :class="[
                            'px-3 py-1.5 text-xs rounded-md font-medium transition',
                            autoRefresh
                                ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-700'
                                : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600'
                        ]">
                        {{ autoRefresh ? '● Auto-refresh ON' : '○ Auto-refresh OFF' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- ═══ KPI Cards ═══ -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <!-- Oggi calls -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Chiamate oggi</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">
                            {{ fmtNum(live.today?.calls) }}
                            <span class="text-sm font-normal text-gray-400">/ {{ live.today?.limit }}</span>
                        </p>
                        <div class="mt-2 w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5">
                            <div class="h-1.5 rounded-full transition-all"
                                :class="barColor(todayPct)"
                                :style="{ width: todayPct + '%' }"></div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ todayPct }}% del limite giornaliero</p>
                    </div>

                    <!-- Oggi costo -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Costo oggi</p>
                        <p class="text-2xl font-bold mt-1"
                            :class="todayCostPct >= 90 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100'">
                            {{ fmtEur(live.today?.cost) }}
                        </p>
                        <div class="mt-2 w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5">
                            <div class="h-1.5 rounded-full transition-all"
                                :class="barColor(todayCostPct)"
                                :style="{ width: todayCostPct + '%' }"></div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Warning a {{ fmtEur(live.today?.warning_cost) }}
                        </p>
                    </div>

                    <!-- Settimana -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Settimana corrente</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ fmtEur(live.week?.cost) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            {{ fmtNum(live.week?.calls) }} chiamate
                        </p>
                    </div>

                    <!-- Mese -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Mese corrente</p>
                        <p class="text-2xl font-bold mt-1"
                            :class="monthCostPct >= 90 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100'">
                            {{ fmtEur(live.month?.cost) }}
                        </p>
                        <div class="mt-2 w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5">
                            <div class="h-1.5 rounded-full transition-all"
                                :class="barColor(monthCostPct)"
                                :style="{ width: monthCostPct + '%' }"></div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ fmtNum(live.month?.calls) }} chiamate · warning a {{ fmtEur(live.month?.warning_cost) }}
                        </p>
                    </div>
                </div>

                <!-- ═══ Breakdown oggi per endpoint ═══ -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="font-semibold text-sm text-gray-900 dark:text-gray-100">Oggi · per endpoint</h3>
                        </div>
                        <div v-if="(live.today?.by_endpoint ?? []).length === 0"
                            class="px-5 py-8 text-center text-sm text-gray-400">
                            Nessuna chiamata oggi
                        </div>
                        <table v-else class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs text-gray-500 dark:text-gray-400 uppercase">
                                <tr>
                                    <th class="text-left px-5 py-2">Endpoint</th>
                                    <th class="text-right px-5 py-2">Chiamate</th>
                                    <th class="text-right px-5 py-2">Costo unitario</th>
                                    <th class="text-right px-5 py-2">Totale</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                                <tr v-for="r in live.today?.by_endpoint ?? []" :key="r.endpoint">
                                    <td class="px-5 py-2 font-mono text-xs text-gray-700 dark:text-gray-300">{{ r.endpoint }}</td>
                                    <td class="px-5 py-2 text-right tabular-nums">{{ fmtNum(r.calls) }}</td>
                                    <td class="px-5 py-2 text-right tabular-nums text-xs text-gray-500 dark:text-gray-400">
                                        {{ fmtEur(live.costs_per_call?.[r.endpoint] ?? 0) }}
                                    </td>
                                    <td class="px-5 py-2 text-right tabular-nums font-medium">{{ fmtEur(r.cost) }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-50 dark:bg-gray-700/50 text-sm font-semibold">
                                <tr>
                                    <td class="px-5 py-2 text-gray-500 dark:text-gray-400 text-xs">TOTALE</td>
                                    <td class="px-5 py-2 text-right tabular-nums">{{ fmtNum(live.today?.calls) }}</td>
                                    <td></td>
                                    <td class="px-5 py-2 text-right tabular-nums">{{ fmtEur(live.today?.cost) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Listino costi per endpoint -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <h3 class="font-semibold text-sm text-gray-900 dark:text-gray-100">Listino costi (€/chiamata)</h3>
                            <span class="text-xs text-gray-400">indicativo</span>
                        </div>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                                <tr v-for="(cost, endpoint) in live.costs_per_call ?? {}" :key="endpoint">
                                    <td class="px-5 py-1.5 font-mono text-xs text-gray-700 dark:text-gray-300">{{ endpoint }}</td>
                                    <td class="px-5 py-1.5 text-right tabular-nums text-sm">{{ fmtEur(cost) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ═══ Storico 30 giorni ═══ -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="font-semibold text-sm text-gray-900 dark:text-gray-100">Storico ultimi 30 giorni</h3>
                    </div>
                    <div class="p-5">
                        <!-- Mini-grafico a barre -->
                        <div class="flex items-end gap-1 h-32 mb-2">
                            <div v-for="d in live.history_30d ?? []" :key="d.date"
                                class="flex-1 flex flex-col items-center justify-end gap-0.5 group relative">
                                <div class="w-full rounded-t transition-all hover:opacity-80"
                                    :class="d.calls > 0 ? 'bg-indigo-500 dark:bg-indigo-600' : 'bg-gray-100 dark:bg-gray-700'"
                                    :style="{ height: Math.max(2, (d.calls / maxDayCalls) * 100) + '%' }">
                                </div>
                                <!-- Tooltip -->
                                <div class="absolute bottom-full mb-1 hidden group-hover:block bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-xs rounded px-2 py-1 whitespace-nowrap z-10">
                                    <p class="font-medium">{{ fmtDate(d.date) }}</p>
                                    <p>{{ fmtNum(d.calls) }} chiamate</p>
                                    <p>{{ fmtEur(d.cost) }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-400">
                            <span>{{ fmtDate(live.history_30d?.[0]?.date) }}</span>
                            <span>{{ fmtDate(live.history_30d?.[live.history_30d.length - 1]?.date) }}</span>
                        </div>
                    </div>
                </div>

                <!-- ═══ Breakdown mese per endpoint ═══ -->
                <div v-if="(live.month?.by_endpoint ?? []).length > 0"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="font-semibold text-sm text-gray-900 dark:text-gray-100">Mese corrente · per endpoint</h3>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs text-gray-500 dark:text-gray-400 uppercase">
                            <tr>
                                <th class="text-left px-5 py-2">Endpoint</th>
                                <th class="text-right px-5 py-2">Chiamate</th>
                                <th class="text-right px-5 py-2">Costo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                            <tr v-for="r in live.month?.by_endpoint ?? []" :key="r.endpoint">
                                <td class="px-5 py-2 font-mono text-xs text-gray-700 dark:text-gray-300">{{ r.endpoint }}</td>
                                <td class="px-5 py-2 text-right tabular-nums">{{ fmtNum(r.calls) }}</td>
                                <td class="px-5 py-2 text-right tabular-nums font-medium">{{ fmtEur(r.cost) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Link rapidi -->
                <div class="flex items-center gap-3 text-sm">
                    <Link :href="route('anagrafica.index')"
                        class="text-blue-600 dark:text-blue-400 hover:underline">
                        → Vai ad Anagrafica (auto-compila)
                    </Link>
                    <span class="text-gray-400">·</span>
                    <Link :href="route('company-enrichment.search-page')"
                        class="text-blue-600 dark:text-blue-400 hover:underline">
                        → Ricerca aziende (IT-search)
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
