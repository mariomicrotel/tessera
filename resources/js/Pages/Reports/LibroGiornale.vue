<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    movimenti: Array,
    totale_dare: Number,
    totale_avere: Number,
    numero_movimenti: Number,
    filters: Object,
});

const page = usePage();
const tenant = computed(() => page.props.auth?.currentTenantSlug ?? page.props.tenant?.slug ?? '');

const from = ref(props.filters?.from ?? '');
const to = ref(props.filters?.to ?? '');

const fmt = (v) =>
    new Intl.NumberFormat('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v ?? 0);

const filtra = () => {
    router.get(route('reports.libro-giornale', { tenant: tenant.value, from: from.value, to: to.value }));
};

const esporta = () => {
    window.location.href = route('reports.libro-giornale.export', {
        tenant: tenant.value,
        from: from.value,
        to: to.value,
    });
};

const stampaPdf = () => {
    window.location.href = route('reports.libro-giornale.pdf', {
        tenant: tenant.value,
        from: from.value,
        to: to.value,
    });
};
</script>

<template>
    <AppLayout title="Libro Giornale">
        <Head title="Libro Giornale" />

        <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow rounded p-4 mb-4">
                <h1 class="text-xl font-semibold mb-3">Libro Giornale</h1>
                <div class="flex gap-3 items-end">
                    <div>
                        <label class="block text-xs text-gray-600">Dal</label>
                        <input type="date" v-model="from" class="border rounded px-2 py-1" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Al</label>
                        <input type="date" v-model="to" class="border rounded px-2 py-1" />
                    </div>
                    <button @click="filtra" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded">
                        Filtra
                    </button>
                    <button @click="esporta" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-1.5 rounded">
                        Export CSV
                    </button>
                    <button @click="stampaPdf" class="bg-red-600 hover:bg-red-700 text-white px-4 py-1.5 rounded">
                        Stampa PDF
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="bg-white shadow rounded p-3">
                    <div class="text-xs text-gray-500">Movimenti</div>
                    <div class="text-2xl font-bold">{{ numero_movimenti }}</div>
                </div>
                <div class="bg-white shadow rounded p-3">
                    <div class="text-xs text-gray-500">Totale Dare</div>
                    <div class="text-2xl font-bold text-blue-700">€ {{ fmt(totale_dare) }}</div>
                </div>
                <div class="bg-white shadow rounded p-3">
                    <div class="text-xs text-gray-500">Totale Avere</div>
                    <div class="text-2xl font-bold text-orange-700">€ {{ fmt(totale_avere) }}</div>
                </div>
            </div>

            <div class="bg-white shadow rounded overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 text-left">N°</th>
                            <th class="px-3 py-2 text-left">Data</th>
                            <th class="px-3 py-2 text-left">Causale / Documento</th>
                            <th class="px-3 py-2 text-left">Conto</th>
                            <th class="px-3 py-2 text-left">Descrizione</th>
                            <th class="px-3 py-2 text-right">Dare</th>
                            <th class="px-3 py-2 text-right">Avere</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="m in movimenti" :key="m.id">
                            <tr v-for="(r, i) in m.righe" :key="`${m.id}-${i}`" class="border-t">
                                <td class="px-3 py-1 align-top">{{ i === 0 ? m.numero : '' }}</td>
                                <td class="px-3 py-1 align-top">{{ i === 0 ? m.data_registrazione : '' }}</td>
                                <td class="px-3 py-1 align-top">
                                    <span v-if="i === 0">
                                        {{ m.causale }}
                                        <div v-if="m.numero_documento" class="text-xs text-gray-500">
                                            Doc: {{ m.numero_documento }}
                                        </div>
                                    </span>
                                </td>
                                <td class="px-3 py-1 align-top">{{ r.conto }}</td>
                                <td class="px-3 py-1 align-top text-gray-700">{{ r.descrizione || m.descrizione }}</td>
                                <td class="px-3 py-1 text-right align-top">
                                    <span v-if="r.importo_dare > 0">€ {{ fmt(r.importo_dare) }}</span>
                                </td>
                                <td class="px-3 py-1 text-right align-top">
                                    <span v-if="r.importo_avere > 0">€ {{ fmt(r.importo_avere) }}</span>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!movimenti.length">
                            <td colspan="7" class="px-3 py-6 text-center text-gray-400">
                                Nessun movimento nel periodo selezionato.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50 font-semibold">
                        <tr>
                            <td colspan="5" class="px-3 py-2 text-right">Totali</td>
                            <td class="px-3 py-2 text-right text-blue-700">€ {{ fmt(totale_dare) }}</td>
                            <td class="px-3 py-2 text-right text-orange-700">€ {{ fmt(totale_avere) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
