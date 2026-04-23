<script setup>
import { ref } from 'vue';
import { CheckIcon, ArrowLeftIcon, DocumentDuplicateIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    templates: { type: Array, default: () => [] },
});

const form = useForm({
    titolo: '',
    data_document: '',
    contenuto: '',
});

const showTemplateModal = ref(false);

function useTemplate(template) {
    form.titolo = template.nome ?? '';
    form.contenuto = template.contenuto ?? '';
    showTemplateModal.value = false;
}
</script>

<template>
    <AppLayout title="Nuovo documento">
        <Head title="Nuovo documento" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Nuovo documento</h2>
                <Link :href="route('documents.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Elenco</Link>
            </div>
        </template>

        <div class="py-6 max-w-3xl mx-auto sm:px-6">
            <form @submit.prevent="form.transform(({ data_document, ...rest }) => ({ ...rest, data: data_document })).post(route('documents.store'))" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div>
                    <InputLabel for="titolo" value="Titolo *" />
                    <TextInput id="titolo" v-model="form.titolo" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.titolo" />
                </div>
                <div>
                    <InputLabel for="data" value="Data *" />
                    <TextInput id="data" v-model="form.data_document" type="date" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.data || form.errors.data_document" />
                </div>
                <div>
                    <InputLabel for="contenuto" value="Contenuto" />
                    <RichTextEditor
                        v-model="form.contenuto"
                        placeholder="Testo del documento..."
                        min-height="280px"
                    >
                        <template #toolbar-actions>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 px-2 py-1.5 rounded text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30"
                                title="Inserisci contenuto da un template"
                                @click="showTemplateModal = true"
                            >
                                <DocumentDuplicateIcon class="size-4" aria-hidden="true" />
                                Usa template
                            </button>
                        </template>
                    </RichTextEditor>
                    <InputError class="mt-1" :message="form.errors.contenuto" />
                </div>
                <div class="flex gap-2">
                    <PrimaryButton type="submit" :disabled="form.processing"><CheckIcon class="size-4 me-2" aria-hidden="true" />Salva</PrimaryButton>
                    <Link :href="route('documents.index')" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"><ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla</Link>
                </div>
            </form>
        </div>

        <Modal :show="showTemplateModal" max-width="md" @close="showTemplateModal = false">
            <div class="px-6 py-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Usa template</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Scegli un template per inserire il contenuto nel documento (sostituirà il testo attuale).</p>
                <ul class="mt-4 divide-y divide-gray-200 dark:divide-gray-700 max-h-80 overflow-y-auto">
                    <li v-for="t in templates" :key="t.id" class="py-2 first:pt-0">
                        <button
                            type="button"
                            class="w-full text-left px-3 py-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 font-medium text-gray-900 dark:text-gray-100"
                            @click="useTemplate(t)"
                        >
                            {{ t.nome }}
                        </button>
                    </li>
                </ul>
                <p v-if="!templates?.length" class="py-4 text-sm text-gray-500 dark:text-gray-400">Nessun template. Crea template dalla sezione Template.</p>
            </div>
            <div class="flex justify-end gap-2 px-6 py-4 bg-gray-100 dark:bg-gray-800">
                <SecondaryButton type="button" @click="showTemplateModal = false">Chiudi</SecondaryButton>
            </div>
        </Modal>
    </AppLayout>
</template>
