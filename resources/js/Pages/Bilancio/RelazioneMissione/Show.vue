<script setup>
import {
    DocumentTextIcon,
    ArrowDownTrayIcon,
    PencilSquareIcon,
    CheckCircleIcon,
    TrashIcon,
    XMarkIcon,
    CheckIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, usePage, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    relazione:          Object,
    sezioniInterpolate: Array,
    variabili:          Object,
    statiLabel:         Object,
});

const page   = usePage();
const tenant = page.props.tenant;

// Approva modal
const showApprovaModal = ref(false);
const approvaForm = useForm({
    data_approvazione:  '',
    organo_approvante:  props.relazione.organo_approvante ?? 'Assemblea dei soci',
    luogo_approvazione: props.relazione.luogo_approvazione ?? '',
});

const submitApprova = () => {
    approvaForm.post(route('relazione-missione.approva', [tenant, props.relazione.id]), {
        onSuccess: () => { showApprovaModal.value = false; },
    });
};

// Delete modal
const showDeleteModal = ref(false);
const deleteRelazione = () => {
    router.delete(route('relazione-missione.destroy', [tenant, props.relazione.id]));
};

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';
const fmt     = (v) => Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtN    = (v) => Number(v ?? 0).toLocaleString('it-IT');

const statoBadgeClass = computed(() => ({
    bozza:      'bg-yellow-100 text-yellow-800',
    definitiva: 'bg-blue-100 text-blue-800',
    approvata:  'bg-green-100 text-green-800',
}[props.relazione.stato] ?? 'bg-gray-100 text-gray-700'));

const compiledCount = computed(() =>
    (props.relazione.sezioni ?? []).filter(s => s.testo?.trim()).length
);
</script>

<template>
    <AppLayout :title="`Relazione di Missione ${relazione.anno}`">
        <Head :title="`Relazione di Missione ${relazione.anno}`" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-3">
                    <DocumentTextIcon class="w-6 h-6 text-blue-600" />
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">
                        Relazione di Missione {{ relazione.anno }}
                    </h2>
                    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold', statoBadgeClass]">
                        {{ statiLabel[relazione.stato] ?? relazione.stato }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Edit (only if not approvata) -->
                    <Link v-if="relazione.stato !== 'approvata'"
                          :href="route('relazione-missione.edit', [tenant, relazione.id])"
                          class="inline-flex items-center gap-1 text-sm bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg px-3 py-1.5">
                        <PencilSquareIcon class="w-4 h-4" />
                        Modifica
                    </Link>
                    <!-- Approva (only if not approvata) -->
                    <button v-if="relazione.stato !== 'approvata'"
                            @click="showApprovaModal = true"
                            class="inline-flex items-center gap-1 text-sm bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 rounded-lg px-3 py-1.5">
                        <CheckCircleIcon class="w-4 h-4" />
                        Approva
                    </button>
                    <!-- PDF download -->
                    <a :href="route('relazione-missione.pdf', [tenant, relazione.id])"
                       target="_blank"
                       class="inline-flex items-center gap-1 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-200 rounded-lg px-3 py-1.5">
                        <ArrowDownTrayIcon class="w-4 h-4" />
                        PDF
                    </a>
                    <!-- Delete -->
                    <button v-if="relazione.stato !== 'approvata'"
                            @click="showDeleteModal = true"
                            class="inline-flex items-center gap-1 text-sm text-red-600 hover:text-red-800 border border-red-200 rounded-lg px-3 py-1.5">
                        <TrashIcon class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Info box -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-5">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <div class="text-xs text-gray-400 uppercase">Approvata da</div>
                        <div class="font-semibold text-gray-800 dark:text-gray-100 mt-0.5">{{ relazione.organo_approvante ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 uppercase">Data approvazione</div>
                        <div class="font-semibold text-gray-800 dark:text-gray-100 mt-0.5">{{ fmtDate(relazione.data_approvazione) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 uppercase">Luogo</div>
                        <div class="font-semibold text-gray-800 dark:text-gray-100 mt-0.5">{{ relazione.luogo_approvazione ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 uppercase">Sezioni</div>
                        <div class="font-semibold text-gray-800 dark:text-gray-100 mt-0.5">
                            {{ compiledCount }} / {{ (relazione.sezioni ?? []).length }} compilate
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dati sintesi -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 rounded-xl p-5">
                <h3 class="font-semibold text-blue-800 dark:text-blue-200 text-sm mb-3">
                    Dati di sintesi — Anno {{ relazione.anno }}
                </h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-2.5">
                        <div class="text-xs text-gray-400">Soci attivi</div>
                        <div class="font-bold text-gray-900 dark:text-gray-100">{{ fmtN(variabili.totale_soci) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-2.5">
                        <div class="text-xs text-gray-400">Nuovi soci</div>
                        <div class="font-bold text-green-700">+{{ fmtN(variabili.nuovi_soci) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-2.5">
                        <div class="text-xs text-gray-400">Totale entrate</div>
                        <div class="font-bold text-gray-900 dark:text-gray-100">€ {{ fmt(variabili.totale_entrate) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-2.5">
                        <div class="text-xs text-gray-400">Risultato esercizio</div>
                        <div :class="['font-bold', variabili.risultato_esercizio >= 0 ? 'text-green-700' : 'text-red-600']">
                            € {{ fmt(variabili.risultato_esercizio) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sezioni -->
            <div v-if="sezioniInterpolate.length === 0"
                 class="bg-white dark:bg-gray-800 rounded-xl shadow p-10 text-center text-gray-400">
                Nessuna sezione compilata. <Link :href="route('relazione-missione.edit', [tenant, relazione.id])"
                      class="text-indigo-600 font-medium underline ml-1">Inizia a compilare</Link>
            </div>

            <div v-for="sez in sezioniInterpolate" :key="sez.id"
                 class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                <div class="bg-blue-900 text-white px-5 py-3">
                    <span class="font-semibold text-sm">{{ sez.titolo }}</span>
                </div>
                <div class="p-5">
                    <div v-if="sez.testo_interpolato?.trim()"
                         class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap leading-relaxed">
                        {{ sez.testo_interpolato }}
                    </div>
                    <div v-else class="text-sm italic text-gray-400">
                        Sezione non ancora compilata.
                        <Link v-if="relazione.stato !== 'approvata'"
                              :href="route('relazione-missione.edit', [tenant, relazione.id])"
                              class="text-indigo-600 underline ml-1">Compila</Link>
                    </div>
                </div>
            </div>

            <!-- Note interne -->
            <div v-if="relazione.note_interne"
                 class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-sm text-amber-800">
                <strong>Note interne:</strong> {{ relazione.note_interne }}
            </div>

            <!-- Back -->
            <div>
                <Link :href="route('relazione-missione.index', tenant)"
                      class="text-sm text-gray-500 hover:text-gray-700">
                    ← Tutte le relazioni
                </Link>
            </div>

        </div>

        <!-- ── Approva Modal ─────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showApprovaModal"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-lg text-gray-800 dark:text-gray-100 flex items-center gap-2">
                            <CheckCircleIcon class="w-5 h-5 text-green-600" />
                            Approva relazione {{ relazione.anno }}
                        </h3>
                        <button @click="showApprovaModal = false">
                            <XMarkIcon class="w-5 h-5 text-gray-400 hover:text-gray-600" />
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Data approvazione <span class="text-red-500">*</span>
                            </label>
                            <input v-model="approvaForm.data_approvazione" type="date" required
                                   class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                            <p v-if="approvaForm.errors.data_approvazione" class="text-red-600 text-xs mt-1">
                                {{ approvaForm.errors.data_approvazione }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Organo approvante <span class="text-red-500">*</span>
                            </label>
                            <input v-model="approvaForm.organo_approvante" type="text" required
                                   class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                            <p v-if="approvaForm.errors.organo_approvante" class="text-red-600 text-xs mt-1">
                                {{ approvaForm.errors.organo_approvante }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Luogo</label>
                            <input v-model="approvaForm.luogo_approvazione" type="text"
                                   class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button @click="showApprovaModal = false"
                                class="text-sm text-gray-500 hover:text-gray-700 px-4 py-2">
                            Annulla
                        </button>
                        <PrimaryButton @click="submitApprova" :disabled="approvaForm.processing" class="flex items-center gap-2">
                            <CheckIcon class="w-4 h-4" />
                            Approva definitivamente
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Delete Modal ──────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showDeleteModal"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm p-6">
                    <h3 class="font-bold text-lg text-gray-800 dark:text-gray-100 mb-2">Eliminare la relazione?</h3>
                    <p class="text-sm text-gray-500 mb-6">
                        Stai per eliminare la Relazione di Missione {{ relazione.anno }}.
                        L'operazione è irreversibile.
                    </p>
                    <div class="flex justify-end gap-3">
                        <button @click="showDeleteModal = false"
                                class="text-sm text-gray-500 hover:text-gray-700 px-4 py-2">
                            Annulla
                        </button>
                        <DangerButton @click="deleteRelazione">Elimina</DangerButton>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>
