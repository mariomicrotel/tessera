<script setup>
import {
    FunnelIcon,
    XMarkIcon,
    LockClosedIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
    BanknotesIcon,
} from '@heroicons/vue/24/outline';
import { reactive, ref, computed } from 'vue';
import { Head, router, usePage, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    saldi: Object,
    storico: Array,
    liquidazioneCorrente: Object,
    filters: Object,
    errore: String,
});

const page = usePage();
const tenant = computed(() => page.props.currentTenant);

// ── Filtri ────────────────────────────────────────────────────────────────
const annoCorrente = new Date().getFullYear();
const anni = Array.from({ length: 7 }, (_, i) => annoCorrente - 3 + i);

const mesiLabel = [
    'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
    'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre',
];
const trimestriLabel = ['Q1 (Gen–Mar)', 'Q2 (Apr–Giu)', 'Q3 (Lug–Set)', 'Q4 (Ott–Dic)'];

const form = reactive({
    anno:         props.filters?.anno         ?? annoCorrente,
    periodo:      props.filters?.periodo      ?? new Date().getMonth() + 1,
    tipo_periodo: props.filters?.tipoPeriodo  ?? 'mensile',
});

const periodOptions = computed(() => {
    if (form.tipo_periodo === 'mensile')     return mesiLabel.map((label, i) => ({ value: i + 1, label }));
    if (form.tipo_periodo === 'trimestrale') return trimestriLabel.map((label, i) => ({ value: i + 1, label }));
    return [{ value: 1, label: 'Anno completo' }];
});

function applyFilters() {
    router.get(
        route('iva.liquidazione', { tenant: tenant.value?.slug }),
        { anno: form.anno, periodo: form.periodo, tipo_periodo: form.tipo_periodo },
        { preserveState: false },
    );
}

function onTipoPeriodoChange() {
    form.periodo = 1;
    applyFilters();
}

// ── Modale conferma chiusura ──────────────────────────────────────────────
const showChiudiModal = ref(false);

const chiudiForm = useForm({
    anno:         form.anno,
    periodo:      form.periodo,
    tipo_periodo: form.tipo_periodo,
});

function openChiudiModal() {
    chiudiForm.anno         = form.anno;
    chiudiForm.periodo      = form.periodo;
    chiudiForm.tipo_periodo = form.tipo_periodo;
    showChiudiModal.value   = true;
}

function confermaChiudi() {
    chiudiForm.post(
        route('iva.liquidazione.chiudi', { tenant: tenant.value?.slug }),
        {
            onSuccess: () => { showChiudiModal.value = false; },
            onError:   () => { showChiudiModal.value = false; },
        },
    );
}

// ── Stato liquidazione corrente ───────────────────────────────────────────
const giaChiusa = computed(() =>
    props.liquidazioneCorrente && props.liquidazioneCorrente.status !== 'bozza',
);

// ── Saldo: debito o credito ───────────────────────────────────────────────
const saldoFinale   = computed(() => Number(props.saldi?.saldo_finale ?? 0));
const isDebito      = computed(() => saldoFinale.value > 0);
const isCredito     = computed(() => saldoFinale.value < 0);
const isPareggio    = computed(() => saldoFinale.value === 0);

// ── Formattazione ─────────────────────────────────────────────────────────
function fmtEur(val) {
    return '€\u00a0' + Math.abs(Number(val ?? 0)).toFixed(2);
}
function fmtData(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('it-IT');
}

// ── Storico badge ─────────────────────────────────────────────────────────
const statusBadge = {
    bozza:      'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
    definitiva: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    versata:    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
};
const statusLabel = {
    bozza:      'Bozza',
    definitiva: 'Definitiva',
    versata:    'Versata',
};
</script>

<template>
    <AppLayout title="Liquidazione IVA">
        <Head title="Liquidazione IVA" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Liquidazione IVA Periodica
            </h2>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Errore periodo non valido -->
                <div v-if="errore" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 text-red-700 dark:text-red-400 text-sm flex items-start gap-2">
                    <ExclamationTriangleIcon class="size-5 flex-shrink-0 mt-0.5" />
                    {{ errore }}
                </div>

                <!-- ── Selezione periodo ────────────────────────────────────────── -->
                <form @submit.prevent="applyFilters" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Seleziona periodo</h3>
                    <div class="flex flex-wrap gap-3 items-end">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Anno</label>
                            <select
                                v-model.number="form.anno"
                                @change="applyFilters"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                            >
                                <option v-for="a in anni" :key="a" :value="a">{{ a }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Tipo</label>
                            <select
                                v-model="form.tipo_periodo"
                                @change="onTipoPeriodoChange"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                            >
                                <option value="mensile">Mensile</option>
                                <option value="trimestrale">Trimestrale</option>
                                <option value="annuale">Annuale</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Periodo</label>
                            <select
                                v-model.number="form.periodo"
                                @change="applyFilters"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                            >
                                <option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                        <PrimaryButton type="submit">
                            <FunnelIcon class="size-4 me-1" />Calcola
                        </PrimaryButton>
                    </div>
                </form>

                <!-- ── Riepilogo saldi (solo se dati disponibili) ──────────────── -->
                <div v-if="saldi && !errore">

                    <!-- Banner liquidazione già chiusa -->
                    <div
                        v-if="giaChiusa"
                        class="mb-4 flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20 px-4 py-3 text-sm text-blue-700 dark:text-blue-300"
                    >
                        <CheckCircleIcon class="size-5 flex-shrink-0" />
                        Questo periodo è già stato chiuso in modo
                        <strong>{{ statusLabel[liquidazioneCorrente.status] }}</strong>
                        il {{ fmtData(liquidazioneCorrente.data_chiusura) }}.
                    </div>

                    <!-- Card riepilogativa -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
                            <BanknotesIcon class="size-5 text-gray-500" />
                            <h3 class="font-semibold text-gray-800 dark:text-gray-200">
                                Riepilogo IVA —
                                {{ fmtData(saldi.data_inizio) }} / {{ fmtData(saldi.data_fine) }}
                            </h3>
                        </div>

                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            <!-- IVA a debito (fatture attive) -->
                            <div class="flex items-center justify-between px-6 py-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">IVA a debito</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Da fatture vendita emesse nel periodo</p>
                                </div>
                                <p class="text-lg font-semibold text-red-600 dark:text-red-400">
                                    + {{ fmtEur(saldi.iva_debito) }}
                                </p>
                            </div>

                            <!-- IVA a credito (fatture passive) -->
                            <div class="flex items-center justify-between px-6 py-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">IVA a credito</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Da fatture acquisto registrate nel periodo (quota detraibile)</p>
                                </div>
                                <p class="text-lg font-semibold text-green-600 dark:text-green-400">
                                    − {{ fmtEur(saldi.iva_credito) }}
                                </p>
                            </div>

                            <!-- Saldo periodo -->
                            <div class="flex items-center justify-between px-6 py-4 bg-gray-50 dark:bg-gray-700/30">
                                <div>
                                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Saldo periodo</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">IVA debito − IVA credito</p>
                                </div>
                                <p
                                    class="text-lg font-bold"
                                    :class="Number(saldi.saldo_periodo) >= 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'"
                                >
                                    {{ Number(saldi.saldo_periodo) >= 0 ? '+' : '−' }} {{ fmtEur(saldi.saldo_periodo) }}
                                </p>
                            </div>

                            <!-- Credito periodo precedente -->
                            <div v-if="Number(saldi.credito_periodo_precedente) > 0" class="flex items-center justify-between px-6 py-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Credito periodo precedente</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Riportato dalla liquidazione precedente</p>
                                </div>
                                <p class="text-lg font-semibold text-green-600 dark:text-green-400">
                                    − {{ fmtEur(saldi.credito_periodo_precedente) }}
                                </p>
                            </div>

                            <!-- SALDO FINALE -->
                            <div
                                class="flex items-center justify-between px-6 py-5"
                                :class="{
                                    'bg-red-50 dark:bg-red-900/20':   isDebito,
                                    'bg-green-50 dark:bg-green-900/20': isCredito,
                                    'bg-gray-50 dark:bg-gray-700/20':  isPareggio,
                                }"
                            >
                                <div>
                                    <p class="text-base font-bold text-gray-900 dark:text-gray-100">SALDO FINALE</p>
                                    <p
                                        class="text-xs font-medium mt-0.5"
                                        :class="{
                                            'text-red-600 dark:text-red-400':   isDebito,
                                            'text-green-600 dark:text-green-400': isCredito,
                                            'text-gray-500 dark:text-gray-400':  isPareggio,
                                        }"
                                    >
                                        <span v-if="isDebito">IVA da versare all'Erario</span>
                                        <span v-else-if="isCredito">Credito da riportare al prossimo periodo</span>
                                        <span v-else>Periodo in pareggio</span>
                                    </p>
                                </div>
                                <p
                                    class="text-2xl font-black"
                                    :class="{
                                        'text-red-600 dark:text-red-400':   isDebito,
                                        'text-green-600 dark:text-green-400': isCredito,
                                        'text-gray-700 dark:text-gray-300':  isPareggio,
                                    }"
                                >
                                    {{ isDebito ? '+' : isCredito ? '−' : '' }} {{ fmtEur(saldi.saldo_finale) }}
                                </p>
                            </div>
                        </div>

                        <!-- Azioni -->
                        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between gap-4">
                            <div class="flex items-start gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <InformationCircleIcon class="size-4 flex-shrink-0 mt-0.5" />
                                <span>La chiusura è <strong>irreversibile</strong> e aggancia le fatture del periodo a questa liquidazione.</span>
                            </div>
                            <button
                                v-if="!giaChiusa"
                                type="button"
                                @click="openChiudiModal"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 whitespace-nowrap"
                            >
                                <LockClosedIcon class="size-4" />Chiudi periodo
                            </button>
                            <div v-else class="inline-flex items-center gap-2 px-3 py-2 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 text-sm">
                                <CheckCircleIcon class="size-4" />Già chiusa
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Placeholder se nessun dato -->
                <div v-if="!saldi && !errore" class="bg-white dark:bg-gray-800 shadow rounded-lg p-8 text-center text-gray-500 dark:text-gray-400">
                    <BanknotesIcon class="size-12 mx-auto mb-3 opacity-30" />
                    <p class="text-sm">Seleziona il periodo e premi <strong>Calcola</strong> per visualizzare i saldi IVA.</p>
                </div>

                <!-- ── Storico liquidazioni ────────────────────────────────────── -->
                <div v-if="storico?.length" class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200">Storico liquidazioni chiuse</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Periodo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tipo</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">IVA Debito</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">IVA Credito</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Saldo Finale</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Chiusa il</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Stato</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="liq in storico"
                                :key="liq.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/40"
                            >
                                <td class="px-4 py-2 text-sm font-medium">{{ liq.periodo_label }} {{ liq.anno }}</td>
                                <td class="px-4 py-2 text-sm capitalize text-gray-500 dark:text-gray-400">{{ liq.tipo_periodo }}</td>
                                <td class="px-4 py-2 text-sm text-right text-red-600 dark:text-red-400">{{ fmtEur(liq.iva_debito) }}</td>
                                <td class="px-4 py-2 text-sm text-right text-green-600 dark:text-green-400">{{ fmtEur(liq.iva_credito) }}</td>
                                <td
                                    class="px-4 py-2 text-sm text-right font-semibold"
                                    :class="Number(liq.saldo_finale) >= 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'"
                                >
                                    {{ Number(liq.saldo_finale) >= 0 ? '+' : '−' }} {{ fmtEur(liq.saldo_finale) }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ fmtData(liq.data_chiusura) }}</td>
                                <td class="px-4 py-2">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                        :class="statusBadge[liq.status] ?? 'bg-gray-100 text-gray-700'"
                                    >
                                        {{ statusLabel[liq.status] ?? liq.status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

        <!-- ── Modale conferma chiusura ───────────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="showChiudiModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
                @click.self="showChiudiModal = false"
            >
                <div
                    class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="flex items-center gap-3 mb-4">
                        <LockClosedIcon class="size-6 text-indigo-600 dark:text-indigo-400 flex-shrink-0" />
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Chiudi liquidazione IVA
                        </h3>
                    </div>

                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                        Stai per chiudere la liquidazione IVA del periodo:
                    </p>
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-4">
                        {{ periodOptions.find(o => o.value === form.periodo)?.label ?? form.periodo }}
                        {{ form.anno }}
                        <span class="font-normal text-gray-500">({{ form.tipo_periodo }})</span>
                    </p>

                    <!-- Riepilogo saldo finale nel modal -->
                    <div
                        v-if="saldi"
                        class="rounded-lg p-3 mb-4 text-sm"
                        :class="{
                            'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300':   isDebito,
                            'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300': isCredito,
                            'bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-400':  isPareggio,
                        }"
                    >
                        Saldo finale:
                        <strong>{{ isDebito ? '+' : isCredito ? '−' : '' }} {{ fmtEur(saldi.saldo_finale) }}</strong>
                        <span v-if="isDebito"> da versare</span>
                        <span v-else-if="isCredito"> da riportare</span>
                    </div>

                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3 mb-4 flex items-start gap-2 text-xs text-yellow-700 dark:text-yellow-300">
                        <ExclamationTriangleIcon class="size-4 flex-shrink-0 mt-0.5" />
                        Questa operazione è <strong>irreversibile</strong>. Le fatture del periodo verranno agganciate a questa liquidazione e non potranno più essere modificate.
                    </div>

                    <div class="flex justify-end gap-2">
                        <SecondaryButton @click="showChiudiModal = false" :disabled="chiudiForm.processing">
                            Annulla
                        </SecondaryButton>
                        <button
                            type="button"
                            @click="confermaChiudi"
                            :disabled="chiudiForm.processing"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                        >
                            <LockClosedIcon class="size-4" />
                            {{ chiudiForm.processing ? 'Chiusura in corso…' : 'Conferma chiusura' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
