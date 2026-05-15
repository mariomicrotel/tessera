<script setup>
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowUpTrayIcon, DocumentCheckIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const fileInput   = ref(null);
const selectedFile = ref(null);
const formato     = ref('csv');
const banca       = ref('');
const iban        = ref('');
const previewing  = ref(false);
const importing   = ref(false);
const previewData = ref(null);
const previewError = ref('');

const fmt     = (v) => Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2 });
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const onFileChange = (e) => {
    selectedFile.value = e.target.files[0] ?? null;
    previewData.value  = null;
    previewError.value = '';
    // Auto-detect formato
    const nome = selectedFile.value?.name?.toLowerCase() ?? '';
    if (nome.endsWith('.sta') || nome.endsWith('.mt940') || nome.endsWith('.940')) {
        formato.value = 'mt940';
    } else {
        formato.value = 'csv';
    }
};

const doPreview = async () => {
    if (!selectedFile.value) return;
    previewing.value   = true;
    previewError.value = '';
    previewData.value  = null;

    const fd = new FormData();
    fd.append('file', selectedFile.value);
    fd.append('formato', formato.value);

    try {
        const res = await fetch(route('riconciliazione.preview'), {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' },
        });
        const json = await res.json();
        if (json.ok) {
            previewData.value = json.movimenti;
        } else {
            previewError.value = json.error ?? 'Errore durante la lettura del file.';
        }
    } catch {
        previewError.value = 'Errore di rete.';
    } finally {
        previewing.value = false;
    }
};

const doImport = () => {
    if (!selectedFile.value) return;
    importing.value = true;

    const fd = new FormData();
    fd.append('file', selectedFile.value);
    fd.append('formato', formato.value);
    if (banca.value) fd.append('banca', banca.value);
    if (iban.value)  fd.append('iban',  iban.value);

    router.post(route('riconciliazione.store'), fd, {
        forceFormData: true,
        onFinish: () => { importing.value = false; },
    });
};
</script>

<template>
    <AppLayout title="Carica estratto conto">
        <Head title="Carica estratto conto" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Carica Estratto Conto
                </h2>
                <Link :href="route('riconciliazione.index')">
                    <SecondaryButton>← Torna all'elenco</SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 rounded-lg p-4 text-sm text-blue-800 dark:text-blue-200">
                    Carica un estratto conto in formato <strong>CSV</strong> (separatore punto-virgola o virgola) oppure
                    <strong>MT940</strong> (SWIFT). Il sistema riconoscerà automaticamente le colonne e importerà i movimenti.
                </div>

                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-5">
                    <!-- File -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">File estratto conto</label>
                        <input ref="fileInput" type="file" accept=".csv,.sta,.mt940,.940,.txt"
                               class="hidden" @change="onFileChange" />
                        <button @click="fileInput.click()"
                                class="flex items-center gap-2 px-4 py-2 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-indigo-400 text-gray-500 dark:text-gray-400 hover:text-indigo-600 transition">
                            <ArrowUpTrayIcon class="size-5" />
                            {{ selectedFile ? selectedFile.name : 'Scegli file…' }}
                        </button>
                    </div>

                    <!-- Formato -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Formato</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" v-model="formato" value="csv"
                                       class="rounded border-gray-300" />
                                CSV (Excel)
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" v-model="formato" value="mt940"
                                       class="rounded border-gray-300" />
                                MT940 (SWIFT)
                            </label>
                        </div>
                    </div>

                    <!-- Banca / IBAN -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome banca (opzionale)</label>
                            <input v-model="banca" type="text"
                                   class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IBAN conto (opzionale)</label>
                            <input v-model="iban" type="text" placeholder="IT…"
                                   class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <SecondaryButton :disabled="!selectedFile || previewing" @click="doPreview">
                            <DocumentCheckIcon class="size-4 mr-1" />
                            {{ previewing ? 'Lettura…' : 'Anteprima' }}
                        </SecondaryButton>
                        <PrimaryButton :disabled="!selectedFile || importing" @click="doImport">
                            <ArrowUpTrayIcon class="size-4 mr-1" />
                            {{ importing ? 'Importazione…' : 'Importa' }}
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Errore -->
                <div v-if="previewError" class="bg-red-50 border border-red-200 rounded-lg p-4 flex gap-3 items-start">
                    <ExclamationCircleIcon class="size-5 text-red-500 flex-shrink-0" />
                    <p class="text-sm text-red-700">{{ previewError }}</p>
                </div>

                <!-- Anteprima movimenti -->
                <div v-if="previewData" class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b">
                        <span class="font-semibold text-gray-700 dark:text-gray-300">
                            Anteprima — {{ previewData.length }} movimenti trovati
                        </span>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-100 dark:bg-gray-600 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2 text-left">Data valuta</th>
                                    <th class="px-3 py-2 text-left">Descrizione</th>
                                    <th class="px-3 py-2 text-right">Importo</th>
                                    <th class="px-3 py-2 text-left">Segno</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(m, i) in previewData" :key="i"
                                    :class="['border-t border-gray-100 dark:border-gray-700',
                                             m.importo >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400']">
                                    <td class="px-3 py-1.5 text-gray-700 dark:text-gray-300">{{ fmtDate(m.data_valuta) }}</td>
                                    <td class="px-3 py-1.5 text-gray-700 dark:text-gray-300 max-w-xs truncate">{{ m.descrizione }}</td>
                                    <td class="px-3 py-1.5 text-right font-semibold">€ {{ fmt(Math.abs(m.importo)) }}</td>
                                    <td class="px-3 py-1.5 text-xs uppercase font-semibold">
                                        {{ m.importo >= 0 ? 'Avere (+)' : 'Dare (−)' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-3 border-t flex justify-end">
                        <PrimaryButton :disabled="importing" @click="doImport">
                            <ArrowUpTrayIcon class="size-4 mr-1" />
                            {{ importing ? 'Importazione in corso…' : 'Conferma e importa' }}
                        </PrimaryButton>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
