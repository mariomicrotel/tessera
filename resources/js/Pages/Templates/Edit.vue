<script setup>
import { ref, watch } from 'vue';
import { CheckIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({ template: Object, tipoOptions: Array });

const form = useForm({
    nome: props.template.nome ?? '',
    categoria: props.template.categoria ?? 'documento',
    tipo_verbale: props.template.tipo_verbale ?? '',
    contenuto: props.template.contenuto ?? '',
    _updated_at: props.template?.updated_at ?? '',
});

const showStaleModal = ref(false);

watch(
    () => form.errors.stale,
    (val) => {
        if (val) showStaleModal.value = true;
    },
    { immediate: true },
);

function closeStaleModal() {
    showStaleModal.value = false;
    form.clearErrors('stale');
}

function submitForceOverwrite() {
    showStaleModal.value = false;
    form.clearErrors('stale');
    router.put(route('templates.update', props.template.id), {
        nome: form.nome,
        categoria: form.categoria,
        tipo_verbale: form.tipo_verbale,
        contenuto: form.contenuto,
        _updated_at: form._updated_at,
        force_overwrite: 1,
    });
}
</script>

<template>
    <AppLayout title="Modifica template">
        <Head title="Modifica template" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Modifica template</h2>
                <Link :href="route('templates.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Elenco</Link>
            </div>
        </template>

        <div class="py-6 max-w-3xl mx-auto sm:px-6">
            <form @submit.prevent="form.put(route('templates.update', template.id))" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
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
                    <TextInput id="nome" v-model="form.nome" class="mt-1 block w-full" placeholder="es. Lettera tipo A / Verbale Consiglio tipo A" />
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

        <Modal :show="showStaleModal" max-width="md" @close="closeStaleModal">
            <div class="px-6 py-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Modifiche concorrenti</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ form.errors.stale }}</p>
            </div>
            <div class="flex justify-end gap-2 px-6 py-4 bg-gray-100 dark:bg-gray-800">
                <SecondaryButton type="button" @click="closeStaleModal">Annulla</SecondaryButton>
                <PrimaryButton type="button" @click="submitForceOverwrite">Sovrascrivi</PrimaryButton>
            </div>
        </Modal>
    </AppLayout>
</template>
