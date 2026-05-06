<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const form = useForm({
    notaio:                '',
    repertorio:            '',
    data_atto:             '',
    data_registrazione_ae: '',
    ufficio_registro:      '',
    numero_registro:       '',
    stato:                 'bozza',
    note:                  '',
});

const submit = () => form.post(route('ets.atto-costitutivo.store'));
</script>

<template>
    <AppLayout title="Nuovo Atto Costitutivo">
        <Head title="Nuovo Atto Costitutivo" />
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('ets.atto-costitutivo.index')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <ArrowLeftIcon class="size-5" />
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Nuovo Atto Costitutivo</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit">
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg px-6 py-6 space-y-4">
                        <h3 class="font-medium text-gray-900 dark:text-gray-100">Dati notarili e registrazione</h3>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notaio</label>
                                <input v-model="form.notaio" type="text" maxlength="255" placeholder="Es. Dott. Mario Rossi" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                                <InputError :message="form.errors.notaio" class="mt-1" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">N. Repertorio</label>
                                <input v-model="form.repertorio" type="text" maxlength="50" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data atto</label>
                                <input v-model="form.data_atto" type="date" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Registrazione Agenzia Entrate</label>
                                <input v-model="form.data_registrazione_ae" type="date" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ufficio Registro</label>
                                <input v-model="form.ufficio_registro" type="text" maxlength="100" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Numero registro</label>
                                <input v-model="form.numero_registro" type="text" maxlength="50" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stato *</label>
                            <select v-model="form.stato" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="bozza">Bozza / In lavorazione</option>
                                <option value="registrato">Registrato all'Agenzia Entrate</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Note</label>
                            <textarea v-model="form.note" rows="3" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <Link :href="route('ets.atto-costitutivo.index')">
                                <SecondaryButton type="button">Annulla</SecondaryButton>
                            </Link>
                            <PrimaryButton type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Salvataggio…' : 'Crea atto costitutivo' }}
                            </PrimaryButton>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
