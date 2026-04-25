<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    prospetto: Object,
    anno: Number,
    anni_disponibili: Array,
});

const page = usePage();
const tenant = computed(() => page.props.auth?.currentTenantSlug ?? page.props.tenant?.slug ?? '');
const annoSel = ref(props.anno);

const fmt = (v) =>
    new Intl.NumberFormat('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v ?? 0);

const cambia = () => {
    router.get(route('iva.acconto-iva', { tenant: tenant.value, anno: annoSel.value }));
};

const scaricaLipe = (trimestre) => {
    window.location.href = route('iva.lipe-xml', {
        tenant: tenant.value,
        anno: annoSel.value,
        trimestre,
    });
};

const metodi = computed(() => [
    {
        id:       'storico',
        label:    'Metodo Storico',
        descr:    '88% dell\'IVA netta versata per dicembre/Q4 dell\'anno precedente',
        base:     props.prospetto?.storico?.base ?? 0,
        acconto:  props.prospetto?.storico?.acconto ?? 0,
        fonte:    props.prospetto?.storico?.fonte ?? '',
        consigliato: true,
    },
    {
        id:       'previsionale',
        label:    'Metodo Previsionale',
        descr:    '88% dell\'IVA presunta per l\'intero dicembre (proiezione 1-20 dic.)',
        base:     props.prospetto?.previsionale?.base ?? 0,
        acconto:  props.prospetto?.previsionale?.acconto ?? 0,
        fonte:    props.prospetto?.previsionale?.fonte ?? '',
        consigliato: false,
    },
    {
        id:       'analitico',
        label:    'Metodo Analitico',
        descr:    'IVA effettiva operazioni 1-20 dicembre (100%, art. 6 comma 3-bis)',
        base:     props.prospetto?.analitico?.base ?? 0,
        acconto:  props.prospetto?.analitico?.acconto ?? 0,
        fonte:    props.prospetto?.analitico?.fonte ?? '',
        consigliato: false,
    },
]);
</script>

<template>
    <AppLayout title="Acconto IVA Dicembre">
        <Head title="Acconto IVA Dicembre" />

        <div class="py-6 max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-5">

            <!-- Header + filtri -->
            <div class="bg-white shadow rounded p-4 flex items-end gap-4">
                <div>
                    <h1 class="text-xl font-semibold">Acconto IVA Dicembre</h1>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Calcolo ai sensi dell'art. 6, L. 405/1990 — scadenza 27 dicembre
                    </p>
                </div>
                <div class="ml-auto flex items-end gap-3">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Anno</label>
                        <select v-model="annoSel" @change="cambia" class="border rounded px-2 py-1">
                            <option v-for="a in anni_disponibili" :key="a" :value="a">{{ a }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Scadenza e minimo -->
            <div class="bg-amber-50 border border-amber-200 rounded p-4 flex items-center gap-4">
                <div class="text-amber-700">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-amber-800">
                        Scadenza versamento: {{ prospetto?.scadenza }} — entro il 27 dicembre
                    </div>
                    <div class="text-sm text-amber-700 mt-0.5">
                        Acconto minimo raccomandato:
                        <span class="font-bold text-lg ml-1">€ {{ fmt(prospetto?.minimo_raccomandato) }}</span>
                    </div>
                </div>
            </div>

            <!-- 3 metodi -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div
                    v-for="m in metodi" :key="m.id"
                    class="bg-white shadow rounded p-4 flex flex-col"
                    :class="m.consigliato ? 'ring-2 ring-blue-500' : ''"
                >
                    <div class="flex items-start justify-between mb-2">
                        <span class="font-semibold">{{ m.label }}</span>
                        <span v-if="m.consigliato"
                              class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded">
                            Consigliato
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">{{ m.descr }}</p>
                    <div class="mt-auto space-y-1">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Base di calcolo</span>
                            <span>€ {{ fmt(m.base) }}</span>
                        </div>
                        <div class="flex justify-between text-base font-bold border-t pt-1">
                            <span>Acconto</span>
                            <span class="text-emerald-700">€ {{ fmt(m.acconto) }}</span>
                        </div>
                    </div>
                    <div class="text-xs text-gray-400 mt-2 italic">{{ m.fonte }}</div>
                </div>
            </div>

            <!-- Export LIPE per trimestre -->
            <div class="bg-white shadow rounded p-4">
                <h2 class="font-semibold mb-3">Export LIPE XML — Comunicazione Liquidazioni IVA</h2>
                <p class="text-sm text-gray-500 mb-3">
                    Scarica il file XML LIPE per anno {{ anno }} da importare in Entratel/Desktop Telematico.
                </p>
                <div class="flex gap-2 flex-wrap">
                    <button
                        v-for="t in [1,2,3,4]" :key="t"
                        @click="scaricaLipe(t)"
                        class="bg-slate-600 hover:bg-slate-700 text-white text-sm px-3 py-1.5 rounded"
                    >
                        Q{{ t }} {{ anno }}
                    </button>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
