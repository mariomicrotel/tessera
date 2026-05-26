<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeftIcon, CheckIcon } from '@heroicons/vue/24/outline';
import Form from './Form.vue';

const props = defineProps({
    protocollo: { type: Object, required: true },
    receipts:   { type: Array,  default: () => [] },
});

const form = useForm({
    tipo:               props.protocollo.tipo,
    data_registrazione: props.protocollo.data_registrazione,
    oggetto:            props.protocollo.oggetto,
    mittente:           props.protocollo.mittente ?? '',
    destinatario:       props.protocollo.destinatario ?? '',
    receipt_id:         props.protocollo.linked_type?.endsWith('Receipt')
                            ? (props.protocollo.linked_id ?? '')
                            : '',
    note:               props.protocollo.note ?? '',
});

function submit() {
    form.put(route('protocolli.update', props.protocollo.id));
}
</script>

<template>
    <AppLayout :title="`Modifica protocollo ${protocollo.numero_formattato}`">
        <Head :title="`Modifica ${protocollo.numero_formattato}`" />

        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Modifica protocollo {{ protocollo.numero_formattato }}
                </h2>
                <Link :href="route('protocolli.show', protocollo.id)"
                    class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                    <ArrowLeftIcon class="size-4" /> Dettaglio
                </Link>
            </div>
        </template>

        <div class="py-6 max-w-2xl mx-auto sm:px-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                <form @submit.prevent="submit">
                    <Form :form="form" :is-edit="true" :receipts="receipts">
                        <template #actions>
                            <button type="submit" :disabled="form.processing"
                                class="inline-flex items-center gap-2 px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                <CheckIcon class="size-4" />
                                {{ form.processing ? 'Salvataggio…' : 'Salva modifiche' }}
                            </button>
                            <Link :href="route('protocolli.show', protocollo.id)"
                                class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                Annulla
                            </Link>
                        </template>
                    </Form>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
