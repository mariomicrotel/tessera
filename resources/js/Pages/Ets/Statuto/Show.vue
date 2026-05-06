<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import {
    ArrowLeftIcon, PencilSquareIcon, CheckCircleIcon,
    ArchiveBoxIcon, PaperClipIcon, TrashIcon, ArrowUpTrayIcon,
    CheckIcon, XMarkIcon, MinusIcon,
} from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps({ statuto: Object });

const statoStyle = (stato) => ({
    approvato:  'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    archiviato: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    bozza:      'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
}[stato] ?? '');

const formatDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const approva = () => {
    if (!confirm('Approvare questo statuto?')) return;
    router.post(route('ets.statuto.approva', props.statuto.id));
};
const archivia = () => {
    if (!confirm('Archiviare questo statuto?')) return;
    router.post(route('ets.statuto.archivia', props.statuto.id));
};

const uploadForm = useForm({ file: null });
const fileInput = ref(null);
const uploadFile = () => {
    if (!uploadForm.file) return;
    uploadForm.post(route('ets.statuto.attachments.store', props.statuto.id), {
        onSuccess: () => { uploadForm.reset(); fileInput.value && (fileInput.value.value = ''); },
    });
};

const distruggiAllegato = (attachmentId) => {
    if (!confirm('Eliminare questo allegato?')) return;
    router.delete(route('ets.statuto.attachments.destroy', [props.statuto.id, attachmentId]));
};

const complianceIcon = (ok) => ok === true ? CheckIcon : ok === false ? XMarkIcon : MinusIcon;
const complianceClass = (ok) => ok === true ? 'text-green-500' : ok === false ? 'text-red-500' : 'text-gray-400';
</script>

<template>
    <AppLayout :title="statuto.titolo">
        <Head :title="statuto.titolo" />
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('ets.statuto.index')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <ArrowLeftIcon class="size-5" />
                </Link>
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">{{ statuto.titolo }}</h2>
                        <span :class="statoStyle(statuto.stato)" class="text-xs font-medium px-2 py-1 rounded-full">{{ statuto.stato_label }}</span>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Versione {{ statuto.versione }}</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('ets.statuto.edit', statuto.id)">
                        <SecondaryButton><PencilSquareIcon class="size-4 me-1" />Modifica</SecondaryButton>
                    </Link>
                    <PrimaryButton v-if="statuto.stato === 'bozza'" @click="approva">
                        <CheckCircleIcon class="size-4 me-1" />Approva
                    </PrimaryButton>
                    <SecondaryButton v-if="statuto.stato === 'approvato'" @click="archivia">
                        <ArchiveBoxIcon class="size-4 me-1" />Archivia
                    </SecondaryButton>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Metadati -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg px-6 py-4">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                        <div>
                            <div class="text-gray-500 dark:text-gray-400">Data approvazione</div>
                            <div class="font-medium text-gray-800 dark:text-gray-200">{{ formatDate(statuto.data_approvazione) }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500 dark:text-gray-400">Data deposito</div>
                            <div class="font-medium text-gray-800 dark:text-gray-200">{{ formatDate(statuto.data_deposito) }}</div>
                        </div>
                        <div v-if="statuto.note">
                            <div class="text-gray-500 dark:text-gray-400">Note</div>
                            <div class="font-medium text-gray-800 dark:text-gray-200">{{ statuto.note }}</div>
                        </div>
                    </div>
                </div>

                <!-- Articoli / Clausole -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <h3 class="font-medium text-gray-900 dark:text-gray-100">Articoli ({{ statuto.clausole?.length ?? 0 }})</h3>
                    </div>
                    <div v-if="!statuto.clausole?.length" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                        Nessun articolo. Modifica lo statuto per aggiungere gli articoli.
                    </div>
                    <div v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                        <div v-for="c in statuto.clausole" :key="c.id" class="px-6 py-4">
                            <div class="flex items-start gap-3">
                                <component
                                    :is="complianceIcon(c.compliance_ok)"
                                    :class="complianceClass(c.compliance_ok)"
                                    class="size-4 mt-1 shrink-0"
                                    :title="c.compliance_label"
                                />
                                <div class="flex-1">
                                    <div class="font-medium text-sm text-gray-900 dark:text-gray-100">
                                        Art. {{ c.numero_articolo }}<span v-if="c.titolo"> — {{ c.titolo }}</span>
                                        <span v-if="c.articolo_cts" class="ml-2 text-xs text-indigo-600 dark:text-indigo-400 font-normal">[{{ c.articolo_cts }}]</span>
                                    </div>
                                    <div class="text-sm text-gray-700 dark:text-gray-300 mt-1 whitespace-pre-wrap">{{ c.testo }}</div>
                                    <div v-if="c.note_compliance && c.compliance_ok === false" class="text-xs text-red-600 dark:text-red-400 mt-1 italic">
                                        {{ c.note_compliance }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Allegati -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <PaperClipIcon class="size-4" />Allegati ({{ statuto.attachments?.length ?? 0 }})
                        </h3>
                    </div>
                    <div class="px-6 py-4">
                        <div v-if="statuto.attachments?.length" class="mb-4 space-y-2">
                            <div v-for="a in statuto.attachments" :key="a.id" class="flex items-center justify-between text-sm">
                                <a :href="route('attachments.show', a.id)" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-2">
                                    <PaperClipIcon class="size-4" />{{ a.original_name }}
                                </a>
                                <button @click="distruggiAllegato(a.id)" class="text-red-500 hover:text-red-700 ml-4">
                                    <TrashIcon class="size-4" />
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <input ref="fileInput" type="file" accept=".pdf,.doc,.docx,.odt" @change="e => uploadForm.file = e.target.files[0]" class="text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                            <PrimaryButton :disabled="!uploadForm.file || uploadForm.processing" @click="uploadFile">
                                <ArrowUpTrayIcon class="size-4 me-1" />Carica
                            </PrimaryButton>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
