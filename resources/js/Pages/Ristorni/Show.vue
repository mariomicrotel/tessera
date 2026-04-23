<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    ArrowLeftIcon, ArrowDownTrayIcon, CheckCircleIcon, XCircleIcon, BanknotesIcon,
} from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    ristorno: Object,
    totali:   Object,
});

const fmt = (v) => new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(Number(v) || 0);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const statusConfig = {
    deliberato:   { label: 'Deliberato',   cls: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' },
    in_pagamento: { label: 'In pagamento', cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' },
    pagato:       { label: 'Pagato',       cls: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' },
    annullato:    { label: 'Annullato',    cls: 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400' },
};
const badgeFor = (s) => statusConfig[s] ?? { label: s, cls: 'bg-gray-100 text-gray-600' };

const memberLabel = (m) => {
    if (!m) return '—';
    return m.ragione_sociale || `${m.cognome ?? ''} ${m.nome ?? ''}`.trim() || `Socio #${m.id}`;
};

// ── Liquidazione (markPagato) ─────────────────────────────────────────────────
const showPayModal = ref(false);
const payForm = useForm({
    data_pagamento: new Date().toISOString().slice(0, 10),
    conto_id: null,
});

// Contesto: conti caricati separatamente — per semplicità useremo un prompt.
// In un'implementazione completa, il controller passerebbe l'elenco conti.
const contoIdManual = ref('');

function pay() {
    const cid = Number(contoIdManual.value);
    if (!cid) {
        alert('Inserire un ID conto tesoreria valido.');
        return;
    }
    payForm.conto_id = cid;
    payForm.post(route('ristorni.mark-pagato', props.ristorno.id), {
        onSuccess: () => { showPayModal.value = false; },
    });
}

function annulla() {
    if (!confirm('Annullare definitivamente questo ristorno?')) return;
    router.post(route('ristorni.annulla', props.ristorno.id));
}

const canPay = computed(() => props.ristorno.status === 'deliberato');
const canAnnulla = computed(() => props.ristorno.status !== 'pagato' && props.ristorno.status !== 'annullato');
</script>

<template>
    <Head :title="`Ristorno ${ristorno.anno}`" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                        Ristorno anno {{ ristorno.anno }}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Delibera del {{ fmtDate(ristorno.data_delibera_assemblea) }}
                    </p>
                </div>
                <Link :href="route('ristorni.index')" class="text-sm text-gray-600 dark:text-gray-300 hover:underline">
                    <ArrowLeftIcon class="w-4 h-4 inline" /> Elenco
                </Link>
            </div>
        </template>

        <div class="p-6 space-y-4 max-w-5xl mx-auto">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <span class="inline-flex px-3 py-1 text-sm font-medium rounded-full" :class="badgeFor(ristorno.status).cls">
                        {{ badgeFor(ristorno.status).label }}
                    </span>

                    <div class="flex gap-2">
                        <a :href="route('ristorni.export', ristorno.id)"
                           class="px-3 py-1 rounded border text-sm text-gray-700 dark:text-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <ArrowDownTrayIcon class="w-4 h-4 inline" /> CSV
                        </a>
                        <button v-if="canPay" type="button" @click="showPayModal = true"
                                class="px-3 py-1 rounded bg-green-600 text-white hover:bg-green-700 text-sm">
                            <BanknotesIcon class="w-4 h-4 inline" /> Liquida
                        </button>
                        <button v-if="canAnnulla" type="button" @click="annulla"
                                class="px-3 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300 text-sm">
                            <XCircleIcon class="w-4 h-4 inline" /> Annulla
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                    <div>
                        <div class="text-xs text-gray-500">Deliberato</div>
                        <div class="text-lg font-semibold">{{ fmt(ristorno.importo_totale_deliberato) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Totale lordo</div>
                        <div class="text-lg font-semibold">{{ fmt(totali.lordo) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Ritenuta ({{ (ristorno.aliquota_ritenuta * 100).toFixed(2) }}%)</div>
                        <div class="text-lg font-semibold text-red-600">- {{ fmt(totali.ritenuta) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Totale netto ai soci</div>
                        <div class="text-lg font-semibold text-green-600">{{ fmt(totali.netto) }}</div>
                    </div>
                </div>

                <div v-if="ristorno.data_pagamento" class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                    <CheckCircleIcon class="w-4 h-4 inline text-green-600" />
                    Liquidato il {{ fmtDate(ristorno.data_pagamento) }}
                </div>

                <div v-if="ristorno.note" class="mt-4 p-3 bg-gray-50 dark:bg-gray-700 rounded text-sm text-gray-700 dark:text-gray-300">
                    {{ ristorno.note }}
                </div>
            </div>

            <!-- Entries -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Socio</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">C.F.</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Lordo</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Ritenuta</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Netto</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Stato</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="e in ristorno.entries" :key="e.id">
                            <td class="px-4 py-2 text-sm">{{ memberLabel(e.member) }}</td>
                            <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400 font-mono">{{ e.member?.codice_fiscale || '—' }}</td>
                            <td class="px-4 py-2 text-sm text-right">{{ fmt(e.importo_lordo) }}</td>
                            <td class="px-4 py-2 text-sm text-right text-red-600">- {{ fmt(e.importo_ritenuta) }}</td>
                            <td class="px-4 py-2 text-sm text-right font-medium">{{ fmt(e.importo_netto) }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full"
                                      :class="e.status === 'pagato' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'">
                                    {{ e.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal liquidazione -->
        <div v-if="showPayModal"
             class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
             @click.self="showPayModal = false">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Liquidazione ristorno</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Registra il pagamento del ristorno. Sarà creata una voce in prima nota (uscita B07 "Ristorni ai soci")
                    per l'importo lordo di <b>{{ fmt(totali.lordo) }}</b>.
                </p>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Data pagamento</label>
                    <input v-model="payForm.data_pagamento" type="date" required
                           class="mt-1 w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">ID Conto tesoreria</label>
                    <input v-model="contoIdManual" type="number" required
                           placeholder="Es: 1"
                           class="mt-1 w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                    <p class="text-xs text-gray-500 mt-1">Consulta l'elenco conti per l'ID esatto.</p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showPayModal = false"
                            class="px-4 py-2 rounded border text-gray-700 dark:text-gray-200 dark:border-gray-600">
                        Annulla
                    </button>
                    <PrimaryButton type="button" @click="pay" :disabled="payForm.processing">
                        Conferma liquidazione
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
