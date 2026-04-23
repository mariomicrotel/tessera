<script setup>
import { CheckIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';

const props = defineProps({ tipoOptions: Array });

const form = useForm({
    nome: '',
    categoria: 'documento',
    tipo_verbale: '',
    contenuto: '',
});
</script>

<template>
    <AppLayout title="Nuovo template">
        <Head title="Nuovo template" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Nuovo template</h2>
                <Link :href="route('templates.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Elenco</Link>
            </div>
        </template>

        <div class="py-6 max-w-3xl mx-auto sm:px-6">
            <form @submit.prevent="form.post(route('templates.store'))" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div>
                    <InputLabel for="categoria" value="Categoria *" />
                    <select id="categoria" v-model="form.categoria" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                        <option value="documento">Documento</option>
                        <option value="verbale">Verbale</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.categoria" />
                </div>
                <div v-if="form.categoria === 'verbale'">
                    <InputLabel for="tipo_verbale" value="Tipo (opzionale)" />
                    <select id="tipo_verbale" v-model="form.tipo_verbale" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                        <option v-for="opt in tipoOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se vuoto, il template è disponibile per entrambi i tipi di verbale.</p>
                    <InputError class="mt-1" :message="form.errors.tipo_verbale" />
                </div>
                <div>
                    <InputLabel for="nome" value="Nome *" />
                    <TextInput id="nome" v-model="form.nome" class="mt-1 block w-full" placeholder="es. Lettera tipo A" />
                    <InputError class="mt-1" :message="form.errors.nome" />
                </div>
                <div>
                    <InputLabel for="contenuto" value="Contenuto" />
                    <RichTextEditor v-model="form.contenuto" placeholder="Testo del template..." min-height="280px" />
                    <InputError class="mt-1" :message="form.errors.contenuto" />
                </div>
                <div class="flex gap-2">
                    <PrimaryButton type="submit" :disabled="form.processing"><CheckIcon class="size-4 me-2" aria-hidden="true" />Salva</PrimaryButton>
                    <Link :href="route('templates.index')" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"><ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
