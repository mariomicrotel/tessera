<script setup>
import {
    BanknotesIcon,
    ArrowLeftIcon,
    PlusIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    versamenti: Array,
    anno:       Number,
    anni:       Array,
});

const annoFiltro = ref(props.anno);

watch(annoFiltro, (a) => {
    router.get(route('compensi-terzi.versamenti'), { anno: a }, { preserveState: true, replace: true });
});

/* ── Form versamento ─────────────────────────────────────────────────────── */
const showForm = ref(false);
const form = useForm({
    mese:            '',
    anno:            props.anno,
    data_versamento: '',
    codice_tributo:  '1040',
    codice_ufficio:  '',
    codice_atto:     '',
    note:            '',
});

const mesi = [
    { v: 1, l: 'Gennaio' }, { v: 2, l: 'Febbraio' }, { v: 3, l: 'Marzo' },
    { v: 4, l: 'Aprile' },  { v: 5, l: 'Maggio' },   { v: 6, l: 'Giugno' },
    { v: 7, l: 'Luglio' },  { v: 8, l: 'Agosto' },   { v: 9, l: 'Settembre' },
    { v: 10, l: 'Ottobre' },{ v: 11, l: 'Novembre' },{ v: 12, l: 'Dicembre' },
];

const submit = () => form.post(route('compensi-terzi.versa'), {
    onSuccess: () => { showForm.value = false; form.reset(); },
});

const fmt     = (n) => Number(n ?? 0).toFixed(2);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';
</script>

<template>
    <AppLayout title="Versamenti F24 — Ritenute">
        <Head title="Versamenti F24 ritenute" />
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <BanknotesIcon class="size-5 text-gray-500" />
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Versamenti F24 — {{ anno }}
                    </h2>
                </div>
                <div class="flex gap-2 items-center">
                    <select v-model="annoFiltro"
                        class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                        <option v-for="a in anni" :key="a" :value="a">{{ a }}</option>
                    </select>
                    <Link :href="route('compensi-terzi.index')"
                        class="inline-flex items-center gap-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                        <ArrowLeftIcon class="size-4" />Compensi
                    </Link>
                    <button @click="showForm = true"
                        class="inline-flex items-center gap-1 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md font-medium text-xs uppercase tracking-widest">
                        <PlusIcon class="size-4" />Registra versamento
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-5xl mx-auto sm:px-6 space-y-4">

            <!-- Lista versamenti -->
            <div v-if="versamenti?.length" class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Mese</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data versamento</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Codice tributo</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Importo</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Compensi</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Scadenza legale</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="v in versamenti" :key="v.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100 capitalize">
                                {{ v.mese_riferimento }}/{{ v.anno_riferimento }}
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ fmtDate(v.data_versamento) }}</td>
                            <td class="px-4 py-3 font-mono text-gray-700 dark:text-gray-300">{{ v.codice_tributo }}</td>
                            <td class="px-4 py-3 text-right font-bold font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(v.importo_totale) }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center justify-center size-6 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-xs font-bold text-indigo-700 dark:text-indigo-300">
                                    {{ v.compensi_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                <!-- Calculated on server; show mese+1/16 heuristic -->
                                16/{{ v.mese_riferimento === 12 ? `01/${v.anno_riferimento + 1}` : `${String(v.mese_riferimento + 1).padStart(2,'0')}/${v.anno_riferimento}` }}
                            </td>
                            <td class="px-4 py-3">
                                <Link :href="route('compensi-terzi.versamento.show', v.id)"
                                      class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                    Dettaglio
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-else class="py-12 text-center text-gray-500 dark:text-gray-400">
                Nessun versamento registrato per il {{ anno }}.
            </p>

        </div>

        <!-- ── Modal: Registra versamento ──────────────────────────────────── -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6 max-w-lg w-full mx-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                    <CheckCircleIcon class="size-5 text-green-600" />
                    Registra versamento F24
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Versa tutte le ritenute <em>da versare</em> relative al mese selezionato.
                </p>

                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Mese <span class="text-red-500">*</span>
                            </label>
                            <select v-model.number="form.mese" required
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                                <option value="">— seleziona —</option>
                                <option v-for="m in mesi" :key="m.v" :value="m.v">{{ m.l }}</option>
                            </select>
                            <InputError :message="form.errors.mese" class="mt-1" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Anno <span class="text-red-500">*</span>
                            </label>
                            <input v-model.number="form.anno" type="number" required min="2000" max="2100"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Data versamento <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.data_versamento" type="date" required
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                            <InputError :message="form.errors.data_versamento" class="mt-1" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Codice tributo
                            </label>
                            <input v-model="form.codice_tributo" type="text" maxlength="10"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm font-mono shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Codice ufficio</label>
                            <input v-model="form.codice_ufficio" type="text" maxlength="4"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm font-mono shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Codice atto</label>
                            <input v-model="form.codice_atto" type="text" maxlength="10"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm font-mono shadow-sm" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Note</label>
                        <textarea v-model="form.note" maxlength="500" rows="2"
                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm"></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showForm = false; form.reset()"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                            Annulla
                        </button>
                        <PrimaryButton :disabled="form.processing">
                            {{ form.processing ? 'Registrazione…' : 'Registra versamento' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
