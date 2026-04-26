<script setup>
import {
    DocumentTextIcon,
    InformationCircleIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    anno:            Number,
    anniRange:       Array,
    variabili:       Object,
    sezioniDefault:  Array,
    statiLabel:      Object,
    esistente:       [Number, null],
});

const page   = usePage();
const tenant = page.props.tenant;

const annoScelto = ref(props.anno);

// When anno changes, reload to refresh variabili
watch(annoScelto, (val) => {
    router.get(route('relazione-missione.create', tenant), { anno: val }, { preserveState: false });
});

const form = useForm({
    anno:               props.anno,
    organo_approvante:  'Assemblea dei soci',
    data_approvazione:  '',
    luogo_approvazione: '',
    note_interne:       '',
});

const submit = () => {
    form.post(route('relazione-missione.store', tenant));
};

const fmt = (v) => Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtN = (v) => Number(v ?? 0).toLocaleString('it-IT');
</script>

<template>
    <AppLayout title="Nuova Relazione di Missione">
        <Head title="Nuova Relazione di Missione" />
        <template #header>
            <div class="flex items-center gap-2">
                <DocumentTextIcon class="w-6 h-6 text-blue-600" />
                <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">Nuova Relazione di Missione</h2>
            </div>
        </template>

        <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Warning: already exists -->
            <div v-if="esistente"
                 class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-xl p-4">
                <ExclamationTriangleIcon class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" />
                <div>
                    <p class="font-semibold text-amber-800">Relazione già presente per l'anno {{ anno }}</p>
                    <p class="text-sm text-amber-700 mt-1">
                        Esiste già una relazione di missione per quest'anno.
                        <Link :href="route('relazione-missione.edit', [tenant, esistente])"
                              class="font-bold underline">Modificala</Link>
                        oppure seleziona un anno diverso.
                    </p>
                </div>
            </div>

            <!-- Anno selector -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-4">Anno di riferimento</h3>
                <div class="flex flex-wrap gap-3">
                    <button v-for="a in anniRange" :key="a"
                            @click="annoScelto = a"
                            :class="['px-5 py-2 rounded-lg border-2 font-bold text-sm transition',
                                     annoScelto === a
                                       ? 'border-blue-600 bg-blue-50 text-blue-700'
                                       : 'border-gray-200 bg-white text-gray-600 hover:border-blue-300']">
                        {{ a }}
                    </button>
                </div>
            </div>

            <!-- Dati automatici snapshot -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-6">
                <div class="flex items-center gap-2 mb-4">
                    <InformationCircleIcon class="w-5 h-5 text-blue-600" />
                    <h3 class="font-semibold text-blue-800 dark:text-blue-200">
                        Dati rilevati automaticamente — Anno {{ annoScelto }}
                    </h3>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                        <div class="text-xs text-gray-500 uppercase">Soci attivi</div>
                        <div class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ fmtN(variabili.totale_soci) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                        <div class="text-xs text-gray-500 uppercase">Nuovi soci</div>
                        <div class="text-xl font-bold text-green-700">+{{ fmtN(variabili.nuovi_soci) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                        <div class="text-xs text-gray-500 uppercase">Totale entrate</div>
                        <div class="text-xl font-bold text-gray-900 dark:text-gray-100">€ {{ fmt(variabili.totale_entrate) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                        <div class="text-xs text-gray-500 uppercase">Risultato</div>
                        <div :class="['text-xl font-bold', variabili.risultato_esercizio >= 0 ? 'text-green-700' : 'text-red-600']">
                            € {{ fmt(variabili.risultato_esercizio) }}
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                        <div class="text-xs text-gray-500 uppercase">Totale uscite</div>
                        <div class="text-xl font-bold text-gray-900 dark:text-gray-100">€ {{ fmt(variabili.totale_uscite) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                        <div class="text-xs text-gray-500 uppercase">Quote assoc.</div>
                        <div class="text-xl font-bold text-gray-900 dark:text-gray-100">€ {{ fmt(variabili.quote_associative) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                        <div class="text-xs text-gray-500 uppercase">Donazioni</div>
                        <div class="text-xl font-bold text-gray-900 dark:text-gray-100">€ {{ fmt(variabili.donazioni_ricevute) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                        <div class="text-xs text-gray-500 uppercase">N. eventi</div>
                        <div class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ fmtN(variabili.numero_eventi) }}</div>
                    </div>
                </div>
                <p class="text-xs text-blue-600 mt-3">
                    I dati vengono incorporati automaticamente nel testo delle sezioni tramite segnaposto <code>{{ '{{variabile}}' }}</code>.
                    Potrai modificarli nella fase successiva.
                </p>
            </div>

            <!-- Metadati -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-4">Metadati assemblea (opzionali)</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Organo approvante</label>
                        <input v-model="form.organo_approvante" type="text"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                        <p v-if="form.errors.organo_approvante" class="text-red-600 text-xs mt-1">{{ form.errors.organo_approvante }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Luogo approvazione</label>
                        <input v-model="form.luogo_approvazione" type="text"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data approvazione</label>
                        <input v-model="form.data_approvazione" type="date"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Note interne</label>
                        <input v-model="form.note_interne" type="text"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                    </div>
                </div>
            </div>

            <!-- Sezioni preview -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Struttura sezioni</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Le seguenti sezioni verranno create con testi pre-compilati. Potrai modificarle liberamente nella schermata successiva.
                </p>
                <ul class="space-y-2">
                    <li v-for="sez in sezioniDefault" :key="sez.id"
                        class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <span class="w-2 h-2 bg-blue-400 rounded-full flex-shrink-0"></span>
                        {{ sez.titolo }}
                    </li>
                </ul>
            </div>

            <!-- Actions -->
            <div class="flex justify-between items-center">
                <Link :href="route('relazione-missione.index', tenant)"
                      class="text-sm text-gray-500 hover:text-gray-700">
                    ← Annulla
                </Link>
                <PrimaryButton @click="submit" :disabled="form.processing || !!esistente">
                    Crea relazione e compila sezioni →
                </PrimaryButton>
            </div>

        </div>
    </AppLayout>
</template>
