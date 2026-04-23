<script setup>
import { watch, ref, computed } from 'vue';
import { CheckIcon, ArrowLeftIcon, DocumentDuplicateIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    tipoOptions: Array,
    prossimoNumeroConsiglio: Number,
    prossimoNumeroAssemblea: Number,
    annoSuggerito: Number,
    templates: { type: Array, default: () => [] },
});

const form = useForm({
    tipo: 'consiglio_direttivo',
    data_verbale: '',
    titolo: '',
    contenuto: '',
    numero: props.prossimoNumeroConsiglio ?? '',
});

const showTemplateModal = ref(false);

const templatesFiltered = computed(() => {
    if (!props.templates?.length) return [];
    return props.templates.filter((t) => !t.tipo || t.tipo === form.tipo);
});

function annoDaData(dataStr) {
    if (!dataStr) return props.annoSuggerito || new Date().getFullYear();
    return new Date(dataStr).getFullYear();
}

function aggiornaProssimoNumero() {
    const anno = annoDaData(form.data_verbale);
    const tipo = form.tipo;
    axios.get(route('verbali.prossimo-numero'), { params: { tipo, anno } })
        .then(({ data }) => { form.numero = data.prossimo_numero; })
        .catch(() => {});
}

function tipoLabel(tipo) {
    if (!tipo) return 'Tutti';
    return tipo === 'consiglio_direttivo' ? 'Consiglio direttivo' : 'Assemblea';
}

function useTemplate(template) {
    form.titolo = template.nome ?? '';
    form.contenuto = template.contenuto ?? '';
    showTemplateModal.value = false;
}

watch(() => form.tipo, () => aggiornaProssimoNumero());
watch(() => form.data_verbale, () => aggiornaProssimoNumero());
</script>

<template>
    <AppLayout title="Nuovo verbale">
        <Head title="Nuovo verbale" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Nuovo verbale</h2>
                <Link :href="route('verbali.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Elenco</Link>
            </div>
        </template>

        <div class="py-6 max-w-3xl mx-auto sm:px-6">
            <form @submit.prevent="form.transform(({ data_verbale, ...rest }) => ({ ...rest, data: data_verbale })).post(route('verbali.store'))" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div>
                    <InputLabel for="tipo" value="Tipo *" />
                    <select id="tipo" v-model="form.tipo" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                        <option v-for="opt in tipoOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.tipo" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="data" value="Data *" />
                        <TextInput id="data" v-model="form.data_verbale" type="date" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="form.errors.data || form.errors.data_verbale" />
                    </div>
                    <div>
                        <InputLabel for="numero" value="Numero (progressivo per tipo e anno)" />
                        <TextInput id="numero" v-model="form.numero" type="number" min="1" class="mt-1 block w-full" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">La numerazione è separata: Consiglio direttivo e Assemblea hanno ciascuno la propria progressione per anno.</p>
                        <InputError class="mt-1" :message="form.errors.numero" />
                    </div>
                </div>
                <div>
                    <InputLabel for="titolo" value="Titolo *" />
                    <TextInput id="titolo" v-model="form.titolo" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.titolo" />
                </div>
                <div>
                    <InputLabel for="contenuto" value="Contenuto" />
                    <RichTextEditor
                        v-model="form.contenuto"
                        placeholder="Testo del verbale..."
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
                    <Link :href="route('verbali.index')" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"><ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla</Link>
                </div>
            </form>
        </div>

        <Modal :show="showTemplateModal" max-width="md" @close="showTemplateModal = false">
            <div class="px-6 py-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Usa template</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Scegli un template per inserire il contenuto nel verbale (sostituirà il testo attuale).</p>
                <ul class="mt-4 divide-y divide-gray-200 dark:divide-gray-700 max-h-80 overflow-y-auto">
                    <li v-for="t in templatesFiltered" :key="t.id" class="py-2 first:pt-0">
                        <button
                            type="button"
                            class="w-full text-left px-3 py-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center justify-between gap-2"
                            @click="useTemplate(t)"
                        >
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ t.nome }}</span>
                            <span v-if="t.tipo" class="text-xs px-2 py-0.5 rounded bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300">{{ tipoLabel(t.tipo) }}</span>
                        </button>
                    </li>
                </ul>
                <p v-if="!templatesFiltered.length" class="py-4 text-sm text-gray-500 dark:text-gray-400">Nessun template disponibile per questo tipo. Crea template dalla sezione Template.</p>
            </div>
            <div class="flex justify-end gap-2 px-6 py-4 bg-gray-100 dark:bg-gray-800">
                <SecondaryButton type="button" @click="showTemplateModal = false">Chiudi</SecondaryButton>
            </div>
        </Modal>
    </AppLayout>
</template>
