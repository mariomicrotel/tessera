<script setup>
import { ref } from 'vue';
import { PaperClipIcon, ArrowDownTrayIcon, TrashIcon, DocumentIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';
import { usePage } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    attachments: { type: Array, required: true },
    canEdit: { type: Boolean, default: false },
    storeAction: { type: String, required: true },
    uploadMaxFileSizeHuman: { type: String, default: '10 MB' },
    accept: { type: String, default: '.pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.xls,.xlsx' },
    error: { type: String, default: '' },
});

const emit = defineEmits(['remove']);

const page = usePage();
const selectedAttachment = ref(null);

function formatSize(bytes) {
    if (bytes == null || bytes < 1024) return (bytes ?? 0) + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function previewUrl(attachment) {
    return route('attachments.show', attachment.id);
}

function isImage(attachment) {
    const mime = attachment.mime_type || '';
    return mime.startsWith('image/');
}

function isPdf(attachment) {
    return (attachment.mime_type || '') === 'application/pdf';
}

function selectAttachment(attachment) {
    selectedAttachment.value = attachment;
}

function onRemove(attachment) {
    emit('remove', attachment);
}
</script>

<template>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
            <PaperClipIcon class="size-5" aria-hidden="true" />
            Allegati
        </h3>

        <slot name="hint" />

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
                <ul v-if="attachments?.length" class="space-y-2 mb-4">
                    <li
                        v-for="a in attachments"
                        :key="a.id"
                        class="flex items-center justify-between gap-2 py-2 border-b border-gray-200 dark:border-gray-600 last:border-0"
                        :class="{ 'bg-gray-50 dark:bg-gray-700/50 rounded px-2': selectedAttachment?.id === a.id }"
                    >
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 text-indigo-600 dark:text-indigo-400 hover:underline text-left flex-1 min-w-0"
                            @click="selectAttachment(a)"
                        >
                            <ArrowDownTrayIcon class="size-4 shrink-0" aria-hidden="true" />
                            <span class="truncate" :title="a.original_name">{{ a.original_name }}</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400 shrink-0">{{ formatSize(a.size) }}</span>
                        </button>
                        <div class="flex items-center gap-1 shrink-0">
                            <a
                                :href="previewUrl(a)"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="p-1.5 text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 rounded"
                                title="Apri in nuova scheda"
                            >
                                <ArrowTopRightOnSquareIcon class="size-4" aria-hidden="true" />
                            </a>
                            <button
                                v-if="canEdit"
                                type="button"
                                class="p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"
                                title="Elimina"
                                @click="onRemove(a)"
                            >
                                <TrashIcon class="size-4" aria-hidden="true" />
                            </button>
                        </div>
                    </li>
                </ul>
                <p v-else class="text-sm text-gray-500 dark:text-gray-400 mb-4">Nessun allegato.</p>
                <form
                    v-if="canEdit"
                    :action="storeAction"
                    method="post"
                    enctype="multipart/form-data"
                    class="space-y-2"
                >
                    <input type="hidden" name="_token" :value="page.props.csrf_token" />
                    <div class="flex items-center gap-2">
                        <input
                            type="file"
                            name="file"
                            :accept="accept"
                            required
                            class="text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-gray-100 file:text-gray-700 dark:file:bg-gray-700 dark:file:text-gray-300"
                        />
                        <PrimaryButton type="submit">Carica allegato</PrimaryButton>
                    </div>
                    <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Max {{ uploadMaxFileSizeHuman }}. Formati: PDF, immagini, Word, Excel.</p>
                </form>
            </div>

            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 min-h-[320px] lg:min-h-[70vh] flex flex-col bg-gray-50 dark:bg-gray-900/30">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3 shrink-0">Anteprima</h4>
                <div v-if="!selectedAttachment" class="flex-1 flex items-center justify-center text-sm text-gray-500 dark:text-gray-400">
                    Clicca su un allegato nell'elenco per vedere l'anteprima qui.
                </div>
                <template v-else>
                    <div v-if="isImage(selectedAttachment)" class="flex-1 flex items-start justify-center overflow-auto min-h-0">
                        <img
                            :src="previewUrl(selectedAttachment)"
                            :alt="selectedAttachment.original_name"
                            class="max-w-full max-h-[70vh] object-contain rounded"
                        />
                    </div>
                    <div v-else-if="isPdf(selectedAttachment)" class="flex-1 min-h-0 flex flex-col rounded border border-gray-200 dark:border-gray-600 overflow-hidden">
                        <iframe
                            :src="previewUrl(selectedAttachment)"
                            class="w-full flex-1 min-h-[50vh] lg:min-h-[65vh] rounded"
                            title="Anteprima PDF"
                        />
                    </div>
                    <div v-else class="flex-1 flex flex-col items-center justify-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <DocumentIcon class="size-12 text-gray-400 dark:text-gray-500" aria-hidden="true" />
                        <span>Anteprima non disponibile.</span>
                        <a
                            :href="previewUrl(selectedAttachment)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            Scarica file
                        </a>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
