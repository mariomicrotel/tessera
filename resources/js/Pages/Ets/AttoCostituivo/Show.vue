<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ArrowLeftIcon, PencilSquareIcon, CheckCircleIcon, PaperClipIcon, TrashIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps({ atto: Object });

const formatDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const registra = () => {
    if (!confirm('Segnare questo atto come registrato all\'Agenzia Entrate?')) return;
    router.post(route('ets.atto-costitutivo.registra', props.atto.id));
};

const uploadForm = useForm({ file: null });
const fileInput = ref(null);
const uploadFile = () => {
    if (!uploadForm.file) return;
    uploadForm.post(route('ets.atto-costitutivo.attachments.store', props.atto.id), {
        onSuccess: () => { uploadForm.reset(); if (fileInput.value) fileInput.value.value = ''; },
    });
};

const distruggiAllegato = (id) => {
    if (!confirm('Eliminare questo allegato?')) return;
    router.delete(route('ets.atto-costitutivo.attachments.destroy', [props.atto.id, id]));
};
</script>

<template>
    <AppLayout title="Atto Costitutivo">
        <Head title="Atto Costitutivo" />
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('ets.atto-costitutivo.index')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <ArrowLeftIcon class="size-5" />
                </Link>
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Atto Costitutivo</h2>
                        <span
                            :class="atto.stato === 'registrato' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'"
                            class="text-xs font-medium px-2 py-1 rounded-full"
                        >{{ atto.stato_label }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('ets.atto-costitutivo.edit', atto.id)">
                        <SecondaryButton><PencilSquareIcon class="size-4 me-1" />Modifica</SecondaryButton>
                    </Link>
                    <PrimaryButton v-if="atto.stato === 'bozza'" @click="registra">
                        <CheckCircleIcon class="size-4 me-1" />Segna registrato
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Dati principali -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg px-6 py-6">
                    <h3 class="font-medium text-gray-900 dark:text-gray-100 mb-4">Dati notarili e registrazione</h3>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Notaio</dt>
                            <dd class="font-medium text-gray-800 dark:text-gray-200 mt-0.5">{{ atto.notaio || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">N. Repertorio</dt>
                            <dd class="font-medium text-gray-800 dark:text-gray-200 font-mono mt-0.5">{{ atto.repertorio || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Data atto</dt>
                            <dd class="font-medium text-gray-800 dark:text-gray-200 mt-0.5">{{ formatDate(atto.data_atto) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Registrazione Ag. Entrate</dt>
                            <dd class="font-medium text-gray-800 dark:text-gray-200 mt-0.5">{{ formatDate(atto.data_registrazione_ae) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Ufficio Registro</dt>
                            <dd class="font-medium text-gray-800 dark:text-gray-200 mt-0.5">{{ atto.ufficio_registro || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Numero registro</dt>
                            <dd class="font-medium text-gray-800 dark:text-gray-200 font-mono mt-0.5">{{ atto.numero_registro || '—' }}</dd>
                        </div>
                    </dl>
                    <div v-if="atto.note" class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <dt class="text-xs text-gray-500 dark:text-gray-400 mb-1">Note</dt>
                        <dd class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ atto.note }}</dd>
                    </div>
                </div>

                <!-- Allegati -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <PaperClipIcon class="size-4" />Allegati ({{ atto.attachments?.length ?? 0 }})
                        </h3>
                    </div>
                    <div class="px-6 py-4">
                        <div v-if="atto.attachments?.length" class="mb-4 space-y-2">
                            <div v-for="a in atto.attachments" :key="a.id" class="flex items-center justify-between text-sm">
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
