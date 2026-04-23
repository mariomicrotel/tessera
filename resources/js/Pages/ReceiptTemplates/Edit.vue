<script setup>
import { computed, ref } from 'vue';
import { CheckIcon, ArrowLeftIcon, CodeBracketIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { receiptEditorPlaceholders } from '@/constants/receiptEditorPlaceholders';

const props = defineProps({
    template: Object,
    typeLabel: String,
    placeholders: Array,
    preview_samples: Object,
});

const form = useForm({
    body_text: props.template?.body_text ?? '',
});

const bodyEditMode = ref('editor');

function escapeHtml(s) {
    if (s == null || s === '') return '';
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
}

const previewHtml = computed(() => {
    let html = form.body_text || '';
    const samples = props.preview_samples || {};
    for (const [key, value] of Object.entries(samples)) {
        html = html.split('{{' + key + '}}').join(escapeHtml(String(value)));
    }
    return html;
});

function placeholderLabel(key) {
    return '\u007B\u007B' + key + '\u007D\u007D';
}
</script>

<template>
    <AppLayout title="Modifica template ricevuta">
        <Head :title="'Template ricevuta: ' + (typeLabel || '')" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Modifica template ricevuta - {{ typeLabel }}</h2>
                <Link :href="route('receipt-templates.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />Elenco
                </Link>
            </div>
        </template>

        <div class="py-6 max-w-6xl mx-auto sm:px-6">
            <form @submit.prevent="form.put(route('receipt-templates.update', template.tipo))" class="space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <InputLabel for="body_text" value="Corpo ricevuta (HTML)" />
                                <div class="flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                                    <button
                                        type="button"
                                        :class="[
                                            'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium',
                                            bodyEditMode === 'editor'
                                                ? 'bg-gray-200 dark:bg-gray-600 text-gray-900 dark:text-white'
                                                : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'
                                        ]"
                                        @click="bodyEditMode = 'editor'"
                                    >
                                        <PencilSquareIcon class="size-3.5" /> Editor
                                    </button>
                                    <button
                                        type="button"
                                        :class="[
                                            'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium',
                                            bodyEditMode === 'raw'
                                                ? 'bg-gray-200 dark:bg-gray-600 text-gray-900 dark:text-white'
                                                : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'
                                        ]"
                                        @click="bodyEditMode = 'raw'"
                                    >
                                        <CodeBracketIcon class="size-3.5" /> HTML raw
                                    </button>
                                </div>
                            </div>
                            <RichTextEditor
                                v-if="bodyEditMode === 'editor'"
                                :key="'receipt-body-editor'"
                                id="body_text"
                                v-model="form.body_text"
                                placeholder="Contenuto ricevuta in HTML..."
                                min-height="280px"
                                :placeholder-items="receiptEditorPlaceholders"
                                enable-table
                            />
                            <textarea
                                v-if="bodyEditMode === 'raw'"
                                id="body_text_raw"
                                v-model="form.body_text"
                                rows="14"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"
                                placeholder="<p>Inserisci il contenuto ricevuta...</p>"
                            />
                            <InputError class="mt-1" :message="form.errors.body_text" />
                        </div>
                        <div v-if="placeholders?.length" class="rounded-md bg-gray-50 dark:bg-gray-900/50 p-4">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Placeholder disponibili</p>
                            <ul class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                                <li v-for="p in placeholders" :key="p.key">
                                    <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{ placeholderLabel(p.key) }}</code>
                                    - {{ p.description }}
                                </li>
                            </ul>
                        </div>
                        <div class="flex gap-2">
                            <PrimaryButton type="submit" :disabled="form.processing">
                                <CheckIcon class="size-4 me-2" aria-hidden="true" />Salva
                            </PrimaryButton>
                            <Link :href="route('receipt-templates.index')" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                                <ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla
                            </Link>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Anteprima</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">I placeholder sono sostituiti con valori di esempio. Anteprima HTML in tempo reale.</p>
                        <div class="border border-gray-200 dark:border-gray-600 rounded-md overflow-hidden bg-white dark:bg-gray-900 min-h-[320px]">
                            <div class="p-4 prose prose-sm max-w-none dark:prose-invert min-h-[280px]" v-html="previewHtml" />
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
