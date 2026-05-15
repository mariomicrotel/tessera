<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    fatture: Array,
    totale_imponibile: Number,
    totale_iva: Number,
    totale_documenti: Number,
    numero_fatture: Number,
    per_aliquota: Array,
    filters: Object,
});

const page = usePage();
const tenant = computed(() => page.props.auth?.currentTenantSlug ?? page.props.tenant?.slug ?? '');

const from = ref(props.filters?.from ?? '');
const to = ref(props.filters?.to ?? '');

const fmt = (v) =>
    new Intl.NumberFormat('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v ?? 0);

const filtra = () => {
    router.get(route('reports.registro-vendite', { tenant: tenant.value, from: from.value, to: to.value }));
};

const esporta = () => {
    window.location.href = route('reports.registro-vendite.export', {
        tenant: tenant.value,
        from: from.value,
        to: to.value,
    });
};

const stampaPdf = () => {
    window.location.href = route('reports.registro-vendite.pdf', {
        tenant: tenant.value,
        from: from.value,
        to: to.value,
    });
};
</script>

<template>
    <AppLayout title="Registro Vendite">
        <Head title="Registro Vendite" />

        <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow rounded p-4 mb-4">
                <h1 class="text-xl font-semibold mb-3">Registro Vendite (IVA a debito)</h1>
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

            <div class="grid grid-cols-4 gap-3 mb-4">
                <div class="bg-white shadow rounded p-3">
                    <div class="text-xs text-gray-500">Fatture</div>
                    <div class="text-2xl font-bold">{{ numero_fatture }}</div>
                </div>
                <div class="bg-white shadow rounded p-3">
                    <div class="text-xs text-gray-500">Imponibile</div>
                    <div class="text-2xl font-bold text-slate-700">€ {{ fmt(totale_imponibile) }}</div>
                </div>
                <div class="bg-white shadow rounded p-3">
                    <div class="text-xs text-gray-500">IVA</div>
                    <div class="text-2xl font-bold text-orange-700">€ {{ fmt(totale_iva) }}</div>
                </div>
                <div class="bg-white shadow rounded p-3">
                    <div class="text-xs text-gray-500">Totale Documenti</div>
                    <div class="text-2xl font-bold text-emerald-700">€ {{ fmt(totale_documenti) }}</div>
                </div>
            </div>

            <div v-if="per_aliquota?.length" class="bg-white shadow rounded p-4 mb-4">
                <h2 class="font-semibold mb-2">Riepilogo per aliquota</h2>
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-1 text-left">Codice</th>
                            <th class="px-3 py-1 text-left">Descrizione</th>
                            <th class="px-3 py-1 text-right">%</th>
                            <th class="px-3 py-1 text-right">Imponibile</th>
                            <th class="px-3 py-1 text-right">IVA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in per_aliquota" :key="a.codice" class="border-t">
                            <td class="px-3 py-1">{{ a.codice }}</td>
                            <td class="px-3 py-1">{{ a.descrizione }}</td>
                            <td class="px-3 py-1 text-right">{{ a.percentuale }}%</td>
                            <td class="px-3 py-1 text-right">€ {{ fmt(a.imponibile) }}</td>
                            <td class="px-3 py-1 text-right">€ {{ fmt(a.iva) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="bg-white shadow rounded overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 text-left">Numero</th>
                            <th class="px-3 py-2 text-left">Data</th>
                            <th class="px-3 py-2 text-left">Tipo</th>
                            <th class="px-3 py-2 text-left">Cliente</th>
                            <th class="px-3 py-2 text-right">Imponibile</th>
                            <th class="px-3 py-2 text-right">IVA</th>
                            <th class="px-3 py-2 text-right">Totale</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="f in fatture" :key="f.id" class="border-t">
                            <td class="px-3 py-1">{{ f.numero_fattura }}</td>
                            <td class="px-3 py-1">{{ f.data_fattura }}</td>
                            <td class="px-3 py-1">{{ f.tipo_documento }}</td>
                            <td class="px-3 py-1">#{{ f.cliente_id ?? '-' }}</td>
                            <td class="px-3 py-1 text-right">€ {{ fmt(f.imponibile_totale) }}</td>
                            <td class="px-3 py-1 text-right">€ {{ fmt(f.iva_totale) }}</td>
                            <td class="px-3 py-1 text-right font-semibold">€ {{ fmt(f.totale_documento) }}</td>
                        </tr>
                        <tr v-if="!fatture.length">
                            <td colspan="7" class="px-3 py-6 text-center text-gray-400">
                                Nessuna fattura emessa nel periodo selezionato.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50 font-semibold">
                        <tr>
                            <td colspan="4" class="px-3 py-2 text-right">Totali</td>
                            <td class="px-3 py-2 text-right">€ {{ fmt(totale_imponibile) }}</td>
                            <td class="px-3 py-2 text-right text-orange-700">€ {{ fmt(totale_iva) }}</td>
                            <td class="px-3 py-2 text-right text-emerald-700">€ {{ fmt(totale_documenti) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
