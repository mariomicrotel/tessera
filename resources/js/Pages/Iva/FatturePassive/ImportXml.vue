<script setup>
import { ref, reactive } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ArrowUpTrayIcon, DocumentCheckIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const page = usePage();

const fileInput   = ref(null);
const selectedFile = ref(null);
const previewing  = ref(false);
const importing   = ref(false);
const previewData = ref(null);
const previewError = ref('');
const skipDuplicates = ref(true);

const fmt = (v) => Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2 });

const onFileChange = (e) => {
    selectedFile.value = e.target.files[0] ?? null;
    previewData.value  = null;
    previewError.value = '';
};

const doPreview = async () => {
    if (!selectedFile.value) return;
    previewing.value = true;
    previewError.value = '';
    previewData.value  = null;

    const fd = new FormData();
    fd.append('xml', selectedFile.value);
    fd.append('_token', page.props.csrf_token ?? document.querySelector('meta[name=csrf-token]')?.content ?? '');

    try {
        const res = await fetch(route('iva.fatture-passive.xml-preview'), {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' },
        });
        const json = await res.json();
        if (json.ok) {
            previewData.value = json.fatture;
        } else {
            previewError.value = json.error ?? 'Errore durante l\'anteprima.';
        }
    } catch {
        previewError.value = 'Errore di rete durante l\'anteprima.';
    } finally {
        previewing.value = false;
    }
};

const doImport = () => {
    if (!selectedFile.value) return;
    importing.value = true;

    const fd = new FormData();
    fd.append('xml', selectedFile.value);
    fd.append('skip_duplicates', skipDuplicates.value ? '1' : '0');

    router.post(route('iva.fatture-passive.xml-store'), fd, {
        forceFormData: true,
        onFinish: () => { importing.value = false; },
    });
};
</script>

<template>
    <AppLayout title="Importa XML fatture">
        <Head title="Importa XML fatture (AdE)" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Importa Fatture Passive — XML AdE (FatturaPA)
                </h2>
                <a :href="route('iva.fatture-passive.index')" class="text-sm text-indigo-600 hover:underline">
                    ← Torna alle fatture
                </a>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Istruzioni -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4 text-sm text-blue-800 dark:text-blue-200">
                    <strong>Come funziona:</strong> carica un file XML in formato FatturaPA 1.3.2
                    scaricato dal portale AdE (fatture e corrispettivi). Il sistema leggerà fornitore,
                    righe e aliquote IVA, creerà automaticamente il fornitore se non presente,
                    e registrerà la fattura passiva pronta per la liquidazione IVA.
                </div>

                <!-- Form upload -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                    <h3 class="font-semibold text-gray-700 dark:text-gray-300">1. Seleziona il file XML</h3>

                    <div class="flex items-center gap-4">
                        <input ref="fileInput" type="file" accept=".xml,text/xml,application/xml"
                               class="hidden" @change="onFileChange" />
                        <button @click="fileInput.click()"
                                class="flex items-center gap-2 px-4 py-2 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-indigo-400 text-gray-500 dark:text-gray-400 hover:text-indigo-600 transition">
                            <ArrowUpTrayIcon class="size-5" />
                            {{ selectedFile ? selectedFile.name : 'Scegli file XML…' }}
                        </button>
                        <span v-if="selectedFile" class="text-xs text-gray-400">
                            {{ (selectedFile.size / 1024).toFixed(1) }} KB
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <input id="skip" type="checkbox" v-model="skipDuplicates"
                               class="rounded border-gray-300 dark:border-gray-700" />
                        <label for="skip" class="text-sm text-gray-600 dark:text-gray-400">
                            Salta fatture già presenti (stessa P.IVA + numero + data)
                        </label>
                    </div>

                    <div class="flex gap-3">
                        <SecondaryButton :disabled="!selectedFile || previewing" @click="doPreview">
                            <DocumentCheckIcon class="size-4 mr-1" />
                            {{ previewing ? 'Analisi…' : 'Anteprima' }}
                        </SecondaryButton>
                        <PrimaryButton :disabled="!selectedFile || importing" @click="doImport">
                            <ArrowUpTrayIcon class="size-4 mr-1" />
                            {{ importing ? 'Importazione…' : 'Importa' }}
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Errore anteprima -->
                <div v-if="previewError"
                     class="bg-red-50 dark:bg-red-900/20 border border-red-200 rounded-lg p-4 flex gap-3 items-start">
                    <ExclamationCircleIcon class="size-5 text-red-500 flex-shrink-0 mt-0.5" />
                    <p class="text-sm text-red-700 dark:text-red-300">{{ previewError }}</p>
                </div>

                <!-- Anteprima fatture -->
                <template v-if="previewData">
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-4">
                            2. Anteprima — {{ previewData.length }} fattura/e trovata/e
                        </h3>

                        <div v-for="(f, i) in previewData" :key="i"
                             class="mb-6 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                            <!-- Intestazione fattura -->
                            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 flex flex-wrap gap-4 text-sm">
                                <div>
                                    <span class="text-xs text-gray-500 block">Fornitore</span>
                                    <span class="font-semibold">{{ f.cedente.nome }}</span>
                                    <span v-if="f.cedente.piva" class="ml-2 text-xs text-gray-400">P.IVA {{ f.cedente.piva }}</span>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500 block">N° Fattura</span>
                                    <span class="font-mono">{{ f.numero_fattura }}</span>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500 block">Data</span>
                                    {{ f.data_fattura }}
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500 block">Tipo</span>
                                    {{ f.tipo_documento }}
                                </div>
                                <div v-if="f.data_scadenza">
                                    <span class="text-xs text-gray-500 block">Scadenza</span>
                                    {{ f.data_scadenza }}
                                </div>
                                <div class="ml-auto text-right">
                                    <span class="text-xs text-gray-500 block">Imponibile</span>
                                    <span class="font-semibold">€ {{ fmt(f.imponibile_totale) }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs text-gray-500 block">IVA</span>
                                    <span class="text-orange-600 font-semibold">€ {{ fmt(f.iva_totale) }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs text-gray-500 block">Totale</span>
                                    <span class="text-green-700 font-bold">€ {{ fmt(f.totale_documento) }}</span>
                                </div>
                            </div>

                            <!-- Righe -->
                            <table class="min-w-full text-xs divide-y divide-gray-100 dark:divide-gray-700">
                                <thead class="bg-gray-100 dark:bg-gray-600">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Descrizione</th>
                                        <th class="px-3 py-2 text-right">Qtà</th>
                                        <th class="px-3 py-2 text-right">P.Unitario</th>
                                        <th class="px-3 py-2 text-right">Imponibile</th>
                                        <th class="px-3 py-2 text-right">IVA %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(r, ri) in f.righe" :key="ri" class="border-t border-gray-100 dark:border-gray-700">
                                        <td class="px-3 py-1.5">{{ r.descrizione }}</td>
                                        <td class="px-3 py-1.5 text-right">{{ r.quantita }}</td>
                                        <td class="px-3 py-1.5 text-right">€ {{ fmt(r.prezzo_unitario) }}</td>
                                        <td class="px-3 py-1.5 text-right">€ {{ fmt(r.imponibile) }}</td>
                                        <td class="px-3 py-1.5 text-right">
                                            {{ r.aliquota_iva }}%
                                            <span v-if="r.natura" class="text-gray-400"> ({{ r.natura }})</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <PrimaryButton :disabled="importing" @click="doImport">
                                <ArrowUpTrayIcon class="size-4 mr-1" />
                                {{ importing ? 'Importazione in corso…' : 'Conferma e importa' }}
                            </PrimaryButton>
                        </div>
                    </div>
                </template>

            </div>
        </div>
    </AppLayout>
</template>
