<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    plans: Array,
});

const form = useForm({
    name: '',
    slug: '',
    plan: 'basic',
    admin_name: '',
    admin_email: '',
    admin_password: '',
    organization_type: 'ets',
    cooperative_type: '',
});

const cooperativeTypes = [
    { value: 'lavoro',      label: 'Cooperativa di Lavoro' },
    { value: 'sociale_a',   label: 'Cooperativa Sociale (Tipo A)' },
    { value: 'sociale_b',   label: 'Cooperativa Sociale (Tipo B)' },
    { value: 'agricola',    label: 'Cooperativa Agricola' },
    { value: 'comunita',    label: 'Cooperativa di Comunità' },
    { value: 'consumo',     label: 'Cooperativa di Consumo' },
    { value: 'abitazione',  label: 'Cooperativa di Abitazione' },
    { value: 'consortile',  label: 'Cooperativa Consortile' },
];

const autoSlug = () => {
    form.slug = form.name
        .toLowerCase()
        .replace(/[àáâã]/g, 'a')
        .replace(/[èéêë]/g, 'e')
        .replace(/[ìíîï]/g, 'i')
        .replace(/[òóôõ]/g, 'o')
        .replace(/[ùúûü]/g, 'u')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
};

const submit = () => {
    form.post(route('admin.tenants.store'));
};

const planLabels = { free: 'Free', basic: 'Basic', pro: 'Pro', enterprise: 'Enterprise' };
</script>

<template>
    <AdminLayout title="Nuovo Tenant">
        <div class="max-w-2xl">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Nuovo Tenant</h1>
                <Link :href="route('admin.tenants')" class="text-sm text-gray-600 hover:text-gray-900">
                    &larr; Torna alla lista
                </Link>
            </div>

            <form @submit.prevent="submit" class="bg-white shadow rounded-lg p-6 space-y-6">
                <!-- Organizzazione -->
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Dati Organizzazione</h2>

                    <div class="space-y-4">
                        <!-- Tipo organizzazione -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo di organizzazione *</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label
                                    :class="[
                                        'relative flex cursor-pointer rounded-lg border p-3 transition-colors',
                                        form.organization_type === 'ets'
                                            ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-600'
                                            : 'border-gray-300 bg-white hover:bg-gray-50',
                                    ]"
                                >
                                    <input type="radio" v-model="form.organization_type" value="ets" class="sr-only" />
                                    <div class="flex items-center gap-2 w-full">
                                        <span class="text-xl">🏛️</span>
                                        <div>
                                            <span :class="['text-sm font-semibold block', form.organization_type === 'ets' ? 'text-blue-700' : 'text-gray-800']">ETS / ODV / APS</span>
                                            <span class="text-xs text-gray-500">Ente del Terzo Settore</span>
                                        </div>
                                    </div>
                                </label>
                                <label
                                    :class="[
                                        'relative flex cursor-pointer rounded-lg border p-3 transition-colors',
                                        form.organization_type === 'cooperative'
                                            ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-600'
                                            : 'border-gray-300 bg-white hover:bg-gray-50',
                                    ]"
                                >
                                    <input type="radio" v-model="form.organization_type" value="cooperative" class="sr-only" />
                                    <div class="flex items-center gap-2 w-full">
                                        <span class="text-xl">🤝</span>
                                        <div>
                                            <span :class="['text-sm font-semibold block', form.organization_type === 'cooperative' ? 'text-blue-700' : 'text-gray-800']">Cooperativa</span>
                                            <span class="text-xs text-gray-500">Società mutualistica</span>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <p v-if="form.errors.organization_type" class="mt-1 text-sm text-red-600">{{ form.errors.organization_type }}</p>
                        </div>

                        <!-- Tipo cooperativa -->
                        <div v-show="form.organization_type === 'cooperative'">
                            <label class="block text-sm font-medium text-gray-700">Tipo di cooperativa *</label>
                            <select
                                v-model="form.cooperative_type"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="" disabled>— Seleziona il tipo —</option>
                                <option v-for="ct in cooperativeTypes" :key="ct.value" :value="ct.value">
                                    {{ ct.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.cooperative_type" class="mt-1 text-sm text-red-600">{{ form.errors.cooperative_type }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome Organizzazione *</label>
                            <input
                                v-model="form.name"
                                @input="autoSlug"
                                type="text"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Associazione XYZ"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Slug (URL) *</label>
                            <div class="mt-1 flex rounded-md shadow-sm">
                                <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-sm text-gray-500">
                                    /app/
                                </span>
                                <input
                                    v-model="form.slug"
                                    type="text"
                                    class="flex-1 block w-full border-gray-300 rounded-none rounded-r-md focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="associazione-xyz"
                                />
                            </div>
                            <p v-if="form.errors.slug" class="mt-1 text-sm text-red-600">{{ form.errors.slug }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Piano *</label>
                            <select
                                v-model="form.plan"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option v-for="plan in plans" :key="plan" :value="plan">
                                    {{ planLabels[plan] || plan }}
                                </option>
                            </select>
                            <p v-if="form.errors.plan" class="mt-1 text-sm text-red-600">{{ form.errors.plan }}</p>
                        </div>
                    </div>
                </div>

                <!-- Admin -->
                <div class="border-t pt-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Utente Amministratore</h2>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome *</label>
                            <input
                                v-model="form.admin_name"
                                type="text"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Mario Rossi"
                            />
                            <p v-if="form.errors.admin_name" class="mt-1 text-sm text-red-600">{{ form.errors.admin_name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email *</label>
                            <input
                                v-model="form.admin_email"
                                type="email"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                placeholder="admin@organizzazione.it"
                            />
                            <p v-if="form.errors.admin_email" class="mt-1 text-sm text-red-600">{{ form.errors.admin_email }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Password *</label>
                            <input
                                v-model="form.admin_password"
                                type="password"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Minimo 8 caratteri"
                            />
                            <p v-if="form.errors.admin_password" class="mt-1 text-sm text-red-600">{{ form.errors.admin_password }}</p>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex items-center justify-end space-x-3 pt-4 border-t">
                    <Link :href="route('admin.tenants')" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Annulla
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
                    >
                        <span v-if="form.processing">Creazione in corso...</span>
                        <span v-else>Crea Tenant</span>
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
