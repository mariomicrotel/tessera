<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { PencilSquareIcon, PlusIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({ property: Object });

const assetForm = useForm({
    name: '',
    code: '',
    purchase_date: '',
    value: '',
    notes: '',
});

function addAsset() {
    if (!assetForm.name?.trim()) return;
    const payload = {
        name: assetForm.name.trim(),
        code: assetForm.code?.trim() || '',
        purchase_date: assetForm.purchase_date || null,
        value: assetForm.value !== '' && assetForm.value != null ? Number(assetForm.value) : null,
        notes: assetForm.notes?.trim() || '',
    };
    assetForm.transform(() => payload).post(route('properties.assets.store', props.property.id), {
        preserveScroll: true,
        onSuccess: () => assetForm.reset(),
    });
}

function formatValue(val) {
    if (val == null || val === '') return '—';
    const n = Number(val);
    return Number.isFinite(n) ? n.toFixed(2) : '—';
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString('it-IT');
    } catch {
        return dateStr;
    }
}

function eliminaCespite(assetId) {
    if (!confirm('Eliminare questo cespite?')) return;
    router.delete(route('properties.assets.destroy', [props.property.id, assetId]), {
        preserveScroll: true,
    });
}

function eliminaImmobile() {
    if (!confirm('Eliminare l\'immobile e tutti i cespiti?')) return;
    router.delete(route('properties.destroy', props.property.id));
}
</script>

<template>
    <AppLayout title="Immobile">
        <Head :title="property.name" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ property.name }}</h2>
                <div class="flex items-center gap-2">
                    <Link :href="route('properties.edit', property.id)" class="inline-flex items-center gap-1 px-3 py-2 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <PencilSquareIcon class="size-4" aria-hidden="true" />Modifica
                    </Link>
                    <button
                        type="button"
                        @click="eliminaImmobile"
                        class="inline-flex items-center gap-1 px-3 py-2 rounded-md text-sm font-medium text-red-700 dark:text-red-300 bg-white dark:bg-gray-800 border border-red-300 dark:border-red-700 hover:bg-red-50 dark:hover:bg-red-900/20"
                    >
                        <TrashIcon class="size-4" aria-hidden="true" />Elimina immobile
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto sm:px-6 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Nome</dt><dd>{{ property.name }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Indirizzo</dt><dd>{{ property.address || '—' }}</dd></div>
                    <div v-if="property.notes" class="sm:col-span-2"><dt class="text-sm text-gray-500 dark:text-gray-400">Note</dt><dd class="whitespace-pre-wrap">{{ property.notes }}</dd></div>
                </dl>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-medium mb-4">Cespiti</h3>
                <form @submit.prevent="addAsset" class="mb-4 p-4 rounded-lg border border-gray-200 dark:border-gray-600 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <InputLabel for="asset_name" value="Nome *" />
                            <TextInput id="asset_name" v-model="assetForm.name" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="assetForm.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="asset_code" value="Codice" />
                            <TextInput id="asset_code" v-model="assetForm.code" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="assetForm.errors.code" />
                        </div>
                        <div>
                            <InputLabel for="asset_purchase_date" value="Data acquisto" />
                            <TextInput id="asset_purchase_date" v-model="assetForm.purchase_date" type="date" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="assetForm.errors.purchase_date" />
                        </div>
                        <div>
                            <InputLabel for="asset_value" value="Valore" />
                            <TextInput id="asset_value" v-model="assetForm.value" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="assetForm.errors.value" />
                        </div>
                    </div>
                    <div>
                        <InputLabel for="asset_notes" value="Note" />
                        <TextInput id="asset_notes" v-model="assetForm.notes" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="assetForm.errors.notes" />
                    </div>
                    <PrimaryButton type="submit" :disabled="assetForm.processing || !assetForm.name?.trim()">
                        <PlusIcon class="size-4 me-2" aria-hidden="true" />Aggiungi cespite
                    </PrimaryButton>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Nome</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Codice</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data acquisto</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Valore</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Note</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="a in property.assets" :key="a.id">
                                <td class="px-4 py-2">{{ a.name }}</td>
                                <td class="px-4 py-2">{{ a.code || '—' }}</td>
                                <td class="px-4 py-2">{{ formatDate(a.purchase_date) }}</td>
                                <td class="px-4 py-2">{{ formatValue(a.value) }}</td>
                                <td class="px-4 py-2">{{ a.notes || '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <button
                                        type="button"
                                        @click="eliminaCespite(a.id)"
                                        class="p-1.5 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                                        title="Elimina cespite"
                                    >
                                        <XMarkIcon class="size-4" aria-hidden="true" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="!property.assets?.length" class="py-4 text-sm text-gray-500 dark:text-gray-400">Nessun cespite.</p>
            </div>
        </div>
    </AppLayout>
</template>
