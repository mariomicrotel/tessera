<script setup>
import { computed } from 'vue';
import { PencilSquareIcon, ArrowLeftIcon, TrashIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AttachmentsPanel from '@/Components/AttachmentsPanel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    document: Object,
    uploadMaxFileSizeHuman: { type: String, default: '10 MB' },
});

const documentId = computed(() => props.document?.id);

const deleteDocument = () => {
    if (!confirm('Eliminare questo documento?')) return;
    if (!documentId.value) return;
    router.delete(route('documents.destroy', { document: documentId.value }), { onSuccess: () => router.visit(route('documents.index')) });
};

const removeAttachment = (attachment) => {
    if (!confirm('Rimuovere questo allegato?')) return;
    router.delete(route('documents.attachments.destroy', { document: documentId.value, attachment: attachment.id }));
};

const showAttachmentsSection = () =>
    (props.document.attachments && props.document.attachments.length > 0) || true;

const page = usePage();
const attachmentError = computed(() => {
    const err = page.props.errors?.file;
    if (!err) return null;
    return Array.isArray(err) ? err[0] : err;
});
</script>

<template>
    <AppLayout title="Documento">
        <Head :title="document.titolo || 'Documento'" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ document.titolo || 'Documento' }}</h2>
                <div class="flex items-center gap-2 flex-wrap">
                    <Link :href="route('documents.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Elenco</Link>
                    <a v-if="documentId" :href="route('documents.pdf', { document: documentId })" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm">
                        <ArrowDownTrayIcon class="size-4" aria-hidden="true" />Scarica PDF
                    </a>
                    <Link v-if="documentId" :href="route('documents.edit', { document: documentId })">
                        <SecondaryButton><PencilSquareIcon class="size-4 me-2" aria-hidden="true" />Modifica</SecondaryButton>
                    </Link>
                    <DangerButton type="button" @click="deleteDocument"><TrashIcon class="size-4 me-2" aria-hidden="true" />Elimina</DangerButton>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto sm:px-6 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <dt class="text-gray-500 dark:text-gray-400">Data</dt>
                    <dd>{{ document.data ? new Date(document.data).toLocaleDateString('it-IT') : '—' }}</dd>
                    <dt class="text-gray-500 dark:text-gray-400">Titolo</dt>
                    <dd class="font-medium">{{ document.titolo }}</dd>
                </dl>
                <div v-if="document.contenuto" class="pt-4 border-t border-gray-200 dark:border-gray-600">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Contenuto</h3>
                    <div class="prose prose-sm dark:prose-invert max-w-none text-gray-800 dark:text-gray-200" v-html="document.contenuto"></div>
                </div>
                <p v-else class="pt-4 border-t border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 text-sm">Nessun contenuto.</p>
            </div>

            <AttachmentsPanel
                :attachments="document.attachments ?? []"
                :can-edit="true"
                :store-action="route('documents.attachments.store', { document: document.id })"
                :upload-max-file-size-human="uploadMaxFileSizeHuman"
                :error="attachmentError"
                @remove="removeAttachment"
            />
        </div>
    </AppLayout>
</template>
