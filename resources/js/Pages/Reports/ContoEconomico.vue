<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { FunnelIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    entrate:                { type: Array,  default: () => [] },
    uscite:                 { type: Array,  default: () => [] },
    totaleEntrate:          { type: Number, default: 0 },
    totaleUscite:           { type: Number, default: 0 },
    totaleEntrateConfronta: { type: Number, default: 0 },
    totaleUsciteConfronta:  { type: Number, default: 0 },
    avanzo:                 { type: Number, default: 0 },
    avanzoConfronta:        { type: Number, default: 0 },
    anno:                   { type: Number, required: true },
    confrontaAnno:          { type: Number, required: true },
    anniDisponibili:        { type: Array,  default: () => [] },
    gestione:               { type: String, default: null },
    filters:                { type: Object, default: () => ({}) },
});

const form = reactive({
    anno:           String(props.anno),
    confronta_anno: String(props.confrontaAnno),
    gestione:       props.gestione ?? '',
});

const apply = () =>
    router.get(route('reports.conto-economico'), {
        anno:           form.anno,
        confronta_anno: form.confronta_anno,
        gestione:       form.gestione || undefined,
    }, { preserveState: false, replace: true });

// Raggruppa voci per macro_name
function groupByMacro(voci) {
    const map = new Map();
    for (const v of voci) {
        const key = v.macro_name || '—';
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(v);
    }
    return [...map.entries()].map(([macro, rows]) => ({ macro, rows }));
}

const groupedEntrate = computed(() => groupByMacro(props.entrate));
const groupedUscite  = computed(() => groupByMacro(props.uscite));

// Variazione % per i totali
function varPct(val, ref) {
    if (ref === 0) return null;
    return ((val - ref) / Math.abs(ref) * 100).toFixed(1);
}

const varEntrate = computed(() => varPct(props.totaleEntrate, props.totaleEntrateConfronta));
const varUscite  = computed(() => varPct(props.totaleUscite,  props.totaleUsciteConfronta));
const varAvanzo  = computed(() => varPct(props.avanzo, props.avanzoConfronta));

function fmtEur(val) {
    return '€\u00a0' + Number(val ?? 0).toFixed(2);
}

function varClass(v) {
    if (v === null) return 'text-gray-400';
    return Number(v) >= 0 ? 'text-green-600 dark:text-green-400 font-medium' : 'text-red-600 dark:text-red-400 font-medium';
}

function varLabel(v) {
    if (v === null) return '—';
    const n = Number(v);
    return (n >= 0 ? '+' : '') + n.toFixed(1) + '%';
}

// avanzo class
const avanzoClass = computed(() =>
    props.avanzo > 0 ? 'text-green-600 dark:text-green-400 font-bold' :
    props.avanzo < 0 ? 'text-red-600 dark:text-red-400 font-bold' :
    'text-gray-500 dark:text-gray-400 font-bold'
);

const exportUrl = computed(() => {
    const params = new URLSearchParams({
        anno: form.anno,
        confronta_anno: form.confronta_anno,
        ...(form.gestione ? { gestione: form.gestione } : {}),
    });
    return route('reports.conto-economico.export') + '?' + params.toString();
});
</script>

<template>
    <AppLayout title="Conto Economico Semplificato">
        <Head title="Conto Economico Semplificato" />
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Conto Economico Semplificato
                </h2>
                <a :href="exportUrl">
                    <PrimaryButton type="button">
                        <ArrowDownTrayIcon class="size-4 me-2" aria-hidden="true" />Export CSV
                    </PrimaryButton>
                </a>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-5">

                <!-- Filtri -->
                <form @submit.prevent="apply" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 flex flex-wrap gap-4 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Anno</label>
                        <select v-model="form.anno" @change="apply"
                            class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                            <option v-for="a in anniDisponibili" :key="a" :value="String(a)">{{ a }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Confronta con</label>
                        <select v-model="form.confronta_anno" @change="apply"
                            class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                            <option v-for="a in anniDisponibili" :key="a" :value="String(a)">{{ a }}</option>
                            <option :value="String(Math.min(...anniDisponibili) - 1)">{{ Math.min(...anniDisponibili) - 1 }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Gestione</label>
                        <select v-model="form.gestione" @change="apply"
                            class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                            <option value="">Tutte</option>
                            <option value="istituzionale">Istituzionale</option>
                            <option value="commerciale">Commerciale</option>
                        </select>
                    </div>
                    <PrimaryButton type="submit">
                        <FunnelIcon class="size-4 me-2" aria-hidden="true" />Applica
                    </PrimaryButton>
                </form>

                <!-- Tabella report -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase w-16">Cod.</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Voce</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase w-36">{{ confrontaAnno }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase w-36">{{ anno }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase w-24">Var %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">

                            <!-- ── SEZIONE ENTRATE ── -->
                            <tr class="bg-green-50 dark:bg-green-900/20">
                                <td colspan="5" class="px-4 py-2 text-xs font-bold text-green-800 dark:text-green-300 uppercase tracking-wider">
                                    ENTRATE
                                </td>
                            </tr>

                            <template v-for="group in groupedEntrate" :key="group.macro">
                                <!-- Intestazione macro area -->
                                <tr class="bg-gray-50/70 dark:bg-gray-700/30">
                                    <td colspan="5" class="px-4 py-1 text-xs font-semibold text-gray-500 dark:text-gray-400 italic">
                                        {{ group.macro }}
                                    </td>
                                </tr>
                                <!-- Voci -->
                                <tr v-for="row in group.rows" :key="row.code"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700/30"
                                    :class="(row.anno === 0 && row.confronta_anno === 0) ? 'opacity-40' : ''">
                                    <td class="px-4 py-1.5 text-xs text-gray-400 dark:text-gray-500 font-mono">{{ row.ministerial_code }}</td>
                                    <td class="px-4 py-1.5 text-gray-700 dark:text-gray-300">{{ row.label }}</td>
                                    <td class="px-4 py-1.5 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ fmtEur(row.confronta_anno) }}</td>
                                    <td class="px-4 py-1.5 text-right tabular-nums text-gray-900 dark:text-gray-100">{{ fmtEur(row.anno) }}</td>
                                    <td class="px-4 py-1.5 text-right tabular-nums" :class="varClass(row.variazione)">{{ varLabel(row.variazione) }}</td>
                                </tr>
                            </template>

                            <!-- Totale entrate -->
                            <tr class="bg-green-50 dark:bg-green-900/10 border-t-2 border-green-200 dark:border-green-700">
                                <td class="px-4 py-2"></td>
                                <td class="px-4 py-2 font-semibold text-green-800 dark:text-green-300">Totale entrate</td>
                                <td class="px-4 py-2 text-right tabular-nums font-semibold text-green-700 dark:text-green-400">{{ fmtEur(totaleEntrateConfronta) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums font-semibold text-green-700 dark:text-green-400">{{ fmtEur(totaleEntrate) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums" :class="varClass(varEntrate)">{{ varLabel(varEntrate) }}</td>
                            </tr>

                            <!-- ── SEZIONE USCITE ── -->
                            <tr class="bg-red-50 dark:bg-red-900/20">
                                <td colspan="5" class="px-4 py-2 text-xs font-bold text-red-800 dark:text-red-300 uppercase tracking-wider">
                                    USCITE
                                </td>
                            </tr>

                            <template v-for="group in groupedUscite" :key="group.macro">
                                <tr class="bg-gray-50/70 dark:bg-gray-700/30">
                                    <td colspan="5" class="px-4 py-1 text-xs font-semibold text-gray-500 dark:text-gray-400 italic">
                                        {{ group.macro }}
                                    </td>
                                </tr>
                                <tr v-for="row in group.rows" :key="row.code"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700/30"
                                    :class="(row.anno === 0 && row.confronta_anno === 0) ? 'opacity-40' : ''">
                                    <td class="px-4 py-1.5 text-xs text-gray-400 dark:text-gray-500 font-mono">{{ row.ministerial_code }}</td>
                                    <td class="px-4 py-1.5 text-gray-700 dark:text-gray-300">{{ row.label }}</td>
                                    <td class="px-4 py-1.5 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ fmtEur(row.confronta_anno) }}</td>
                                    <td class="px-4 py-1.5 text-right tabular-nums text-gray-900 dark:text-gray-100">{{ fmtEur(row.anno) }}</td>
                                    <td class="px-4 py-1.5 text-right tabular-nums" :class="varClass(row.variazione)">{{ varLabel(row.variazione) }}</td>
                                </tr>
                            </template>

                            <!-- Totale uscite -->
                            <tr class="bg-red-50 dark:bg-red-900/10 border-t-2 border-red-200 dark:border-red-700">
                                <td class="px-4 py-2"></td>
                                <td class="px-4 py-2 font-semibold text-red-800 dark:text-red-300">Totale uscite</td>
                                <td class="px-4 py-2 text-right tabular-nums font-semibold text-red-700 dark:text-red-400">{{ fmtEur(totaleUsciteConfronta) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums font-semibold text-red-700 dark:text-red-400">{{ fmtEur(totaleUscite) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums" :class="varClass(varUscite)">{{ varLabel(varUscite) }}</td>
                            </tr>

                            <!-- ── AVANZO / DISAVANZO ── -->
                            <tr class="border-t-2 border-gray-300 dark:border-gray-500 bg-gray-50 dark:bg-gray-700/50">
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wide">
                                    {{ avanzo >= 0 ? 'Avanzo di gestione' : 'Disavanzo di gestione' }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums font-bold"
                                    :class="avanzoConfronta > 0 ? 'text-green-600 dark:text-green-400' : avanzoConfronta < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500'">
                                    {{ fmtEur(avanzoConfronta) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-lg" :class="avanzoClass">
                                    {{ fmtEur(avanzo) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums" :class="varClass(varAvanzo)">{{ varLabel(varAvanzo) }}</td>
                            </tr>

                        </tbody>
                    </table>
                </div>

                <!-- Nota voci a zero -->
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    Le voci con importo zero in entrambi gli anni sono visualizzate in grigio chiaro.
                </p>

            </div>
        </div>
    </AppLayout>
</template>
