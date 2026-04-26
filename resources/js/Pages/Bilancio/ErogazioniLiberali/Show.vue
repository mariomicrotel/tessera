<script setup>
import {
    HeartIcon,
    PencilSquareIcon,
    TrashIcon,
    CheckCircleIcon,
    XCircleIcon,
    LinkIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    erogazione:  Object,
    modalita:    Object,
    tipiDonante: Object,
});

const page   = usePage();
const tenant = page.props.tenant;

const showDeleteModal = ref(false);
const doDelete = () => {
    router.delete(route('erogazioni-liberali.destroy', [tenant, props.erogazione.id]));
};

const fmt     = (n) => Number(n ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const isPF = computed(() => props.erogazione.donante_tipo === 'persona_fisica');
const importoDetrazione = computed(() => {
    const aliq = props.erogazione.aliquota_detrazione;
    if (!aliq || !props.erogazione.is_detraibile) return 0;
    return ((parseFloat(props.erogazione.importo) || 0) * aliq / 100).toFixed(2);
});

const nomeCompleto = computed(() => {
    if (isPF.value) {
        return [props.erogazione.donante_cognome, props.erogazione.donante_nome]
            .filter(Boolean).join(' ') || props.erogazione.donante_cf;
    }
    return props.erogazione.donante_ragione_sociale || props.erogazione.donante_cf;
});
</script>

<template>
    <AppLayout title="Erogazione Liberale">
        <Head title="Erogazione Liberale" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-3">
                    <HeartIcon class="w-6 h-6 text-rose-500" />
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">
                        Erogazione Liberale — {{ erogazione.anno }}
                    </h2>
                    <span :class="['inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold',
                                   erogazione.is_detraibile ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700']">
                        <CheckCircleIcon v-if="erogazione.is_detraibile" class="w-3.5 h-3.5" />
                        <XCircleIcon v-else class="w-3.5 h-3.5" />
                        {{ erogazione.is_detraibile ? 'Detraibile' : 'Non detraibile' }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('erogazioni-liberali.edit', [tenant, erogazione.id])"
                          class="inline-flex items-center gap-1 text-sm bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg px-3 py-1.5">
                        <PencilSquareIcon class="w-4 h-4" />
                        Modifica
                    </Link>
                    <button @click="showDeleteModal = true"
                            class="inline-flex items-center gap-1 text-sm text-red-600 hover:text-red-800 border border-red-200 rounded-lg px-3 py-1.5">
                        <TrashIcon class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Detrazione box -->
            <div v-if="erogazione.is_detraibile"
                 class="flex flex-wrap gap-6 bg-green-50 border border-green-200 rounded-xl p-5">
                <div>
                    <div class="text-xs text-green-700 uppercase font-semibold">Importo donato</div>
                    <div class="text-2xl font-bold text-gray-900">€ {{ fmt(erogazione.importo) }}</div>
                </div>
                <div>
                    <div class="text-xs text-green-700 uppercase font-semibold">
                        Detrazione {{ erogazione.aliquota_detrazione }}%
                        ({{ tipiDonante[erogazione.donante_tipo] }})
                    </div>
                    <div class="text-2xl font-bold text-green-700">€ {{ importoDetrazione }}</div>
                </div>
                <div>
                    <div class="text-xs text-green-700 uppercase font-semibold">Riferimento normativo</div>
                    <div class="text-sm font-medium text-green-800 mt-1">
                        Art. 83 c.{{ erogazione.donante_tipo === 'persona_fisica' ? '1' : '2' }} D.Lgs. 117/2017
                    </div>
                </div>
            </div>

            <div v-else
                 class="bg-red-50 border border-red-200 rounded-xl p-5 text-sm text-red-700">
                <strong>Donazione non detraibile</strong> — Il pagamento in contante non dà diritto
                alla detrazione ex art. 83 CTS. L'erogazione è registrata solo a fini contabili.
            </div>

            <!-- Dati donante -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-5">
                <h3 class="font-semibold text-gray-700 dark:text-gray-200 mb-4">Dati donante</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <div class="text-xs text-gray-400 uppercase">Tipo</div>
                        <div class="font-medium text-gray-900 dark:text-gray-100 mt-0.5">
                            {{ tipiDonante[erogazione.donante_tipo] ?? erogazione.donante_tipo }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 uppercase">Codice fiscale</div>
                        <div class="font-mono font-bold text-gray-900 dark:text-gray-100 mt-0.5">
                            {{ erogazione.donante_cf || '—' }}
                        </div>
                    </div>
                    <div v-if="erogazione.donante_piva">
                        <div class="text-xs text-gray-400 uppercase">Partita IVA</div>
                        <div class="font-mono font-medium text-gray-900 dark:text-gray-100 mt-0.5">
                            {{ erogazione.donante_piva }}
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <div class="text-xs text-gray-400 uppercase">Nome / Ragione sociale</div>
                        <div class="font-semibold text-gray-900 dark:text-gray-100 mt-0.5">{{ nomeCompleto }}</div>
                    </div>
                    <div v-if="erogazione.donante_comune">
                        <div class="text-xs text-gray-400 uppercase">Comune</div>
                        <div class="font-medium text-gray-900 dark:text-gray-100 mt-0.5">
                            {{ erogazione.donante_comune }} ({{ erogazione.donante_provincia }})
                        </div>
                    </div>
                    <div v-if="erogazione.donante_indirizzo" class="sm:col-span-3">
                        <div class="text-xs text-gray-400 uppercase">Indirizzo</div>
                        <div class="font-medium text-gray-600 dark:text-gray-300 mt-0.5">
                            {{ erogazione.donante_indirizzo }}{{ erogazione.donante_cap ? ', ' + erogazione.donante_cap : '' }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dati donazione -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-5">
                <h3 class="font-semibold text-gray-700 dark:text-gray-200 mb-4">Dati donazione</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <div class="text-xs text-gray-400 uppercase">Data erogazione</div>
                        <div class="font-semibold text-gray-900 dark:text-gray-100 mt-0.5">
                            {{ fmtDate(erogazione.data_erogazione) }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 uppercase">Modalità pagamento</div>
                        <div class="font-medium text-gray-900 dark:text-gray-100 mt-0.5">
                            {{ modalita[erogazione.modalita_pagamento] ?? erogazione.modalita_pagamento }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 uppercase">Codice AdE</div>
                        <div class="font-mono text-gray-600 dark:text-gray-300 mt-0.5">
                            <!-- Shown as placeholder from model -->
                            {{ erogazione.modalita_pagamento === 'bonifico' ? 'BO'
                             : erogazione.modalita_pagamento === 'assegno_circolare' ? 'AC'
                             : erogazione.modalita_pagamento === 'carta_credito' ? 'CC'
                             : erogazione.modalita_pagamento === 'carta_debito' ? 'CD'
                             : erogazione.modalita_pagamento === 'altro_tracciabile' ? 'AT'
                             : 'CN' }}
                        </div>
                    </div>
                    <div v-if="erogazione.note" class="sm:col-span-3">
                        <div class="text-xs text-gray-400 uppercase">Note</div>
                        <div class="text-gray-600 dark:text-gray-300 mt-0.5">{{ erogazione.note }}</div>
                    </div>
                </div>
            </div>

            <!-- Incasso collegato -->
            <div v-if="erogazione.incasso"
                 class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-center gap-3 text-sm">
                <LinkIcon class="w-5 h-5 text-blue-500 flex-shrink-0" />
                <div>
                    <span class="font-semibold text-blue-800">Collegato a incasso del
                        {{ fmtDate(erogazione.incasso.paid_at) }}</span>
                    — € {{ fmt(erogazione.incasso.amount) }}
                </div>
            </div>

            <!-- Back -->
            <div>
                <Link :href="route('erogazioni-liberali.index', [tenant, { anno: erogazione.anno }])"
                      class="text-sm text-gray-500 hover:text-gray-700">← Elenco erogazioni {{ erogazione.anno }}</Link>
            </div>
        </div>

        <!-- Delete modal -->
        <Teleport to="body">
            <div v-if="showDeleteModal"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm p-6">
                    <h3 class="font-bold text-lg text-gray-800 dark:text-gray-100 mb-2">Eliminare questa erogazione?</h3>
                    <p class="text-sm text-gray-500 mb-6">
                        Stai eliminando la donazione di € {{ fmt(erogazione.importo) }}
                        da {{ nomeCompleto }}. L'operazione è irreversibile.
                    </p>
                    <div class="flex justify-end gap-3">
                        <button @click="showDeleteModal = false"
                                class="text-sm text-gray-500 hover:text-gray-700 px-4 py-2">Annulla</button>
                        <DangerButton @click="doDelete">Elimina</DangerButton>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
