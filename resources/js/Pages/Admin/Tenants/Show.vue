<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    tenant: Object,
    users: Array,
});

const editing = ref(false);

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

const coopTypeLabel = (value) => cooperativeTypes.find(t => t.value === value)?.label ?? value ?? '—';

const form = useForm({
    name:              props.tenant.name,
    plan:              props.tenant.plan,
    is_active:         props.tenant.is_active,
    plan_expires_at:   props.tenant.plan_expires_at?.substring(0, 10) || '',
    organization_type: props.tenant.organization_type || 'ets',
    cooperative_type:  props.tenant.cooperative_type  || '',
});

const save = () => {
    form.put(route('admin.tenants.update', props.tenant.slug), {
        onSuccess: () => { editing.value = false; },
    });
};

const toggleActive = () => {
    router.post(route('admin.tenants.toggle-active', props.tenant.slug), {}, { preserveScroll: true });
};

const seedTenant = () => {
    if (confirm('Eseguire il seed dei dati iniziali? Eventuali dati duplicati non verranno ricreati.')) {
        router.post(route('admin.tenants.seed', props.tenant.slug), {}, { preserveScroll: true });
    }
};

const deleteTenant = () => {
    if (confirm(`Eliminare definitivamente "${props.tenant.name}"?`)) {
        router.delete(route('admin.tenants.destroy', props.tenant.slug));
    }
};

const planLabels = { free: 'Free', basic: 'Basic', pro: 'Pro', enterprise: 'Enterprise' };
const planColors = {
    free: 'bg-gray-100 text-gray-800',
    basic: 'bg-blue-100 text-blue-800',
    pro: 'bg-purple-100 text-purple-800',
    enterprise: 'bg-amber-100 text-amber-800',
};
</script>

<template>
    <AdminLayout :title="tenant.name">
        <div class="max-w-4xl">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <Link :href="route('admin.tenants')" class="text-sm text-gray-500 hover:text-gray-700">&larr; Tutti i tenant</Link>
                    <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ tenant.name }}</h1>
                </div>
                <div class="flex space-x-2">
                    <a
                        :href="`/app/${tenant.slug}/dashboard`"
                        target="_blank"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Apri Area Tenant
                    </a>
                    <button
                        @click="editing = !editing"
                        class="inline-flex items-center px-3 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700"
                    >
                        {{ editing ? 'Annulla' : 'Modifica' }}
                    </button>
                </div>
            </div>

            <!-- Info / Edit form -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <form v-if="editing" @submit.prevent="save" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome</label>
                            <input v-model="form.name" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Piano</label>
                            <select v-model="form.plan" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option v-for="(label, key) in planLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tipo organizzazione</label>
                            <select v-model="form.organization_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="ets">ETS / ODV / APS</option>
                                <option value="cooperative">Cooperativa</option>
                            </select>
                        </div>
                        <div v-show="form.organization_type === 'cooperative'">
                            <label class="block text-sm font-medium text-gray-700">Tipo cooperativa</label>
                            <select v-model="form.cooperative_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="" disabled>— Seleziona —</option>
                                <option v-for="ct in cooperativeTypes" :key="ct.value" :value="ct.value">{{ ct.label }}</option>
                            </select>
                            <p v-if="form.errors.cooperative_type" class="mt-1 text-sm text-red-600">{{ form.errors.cooperative_type }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Scadenza piano</label>
                            <input v-model="form.plan_expires_at" type="date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" />
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                <span class="text-sm font-medium text-gray-700">Attivo</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                            Salva modifiche
                        </button>
                    </div>
                </form>

                <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div>
                        <p class="text-sm text-gray-500">Slug</p>
                        <p class="font-mono text-gray-900">{{ tenant.slug }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Piano</p>
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full" :class="planColors[tenant.plan]">
                            {{ planLabels[tenant.plan] || tenant.plan }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Tipo organizzazione</p>
                        <span
                            class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                            :class="tenant.organization_type === 'cooperative' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'"
                        >
                            {{ tenant.organization_type === 'cooperative' ? '🤝 Cooperativa' : '🏛️ ETS / ODV / APS' }}
                        </span>
                    </div>
                    <div v-if="tenant.organization_type === 'cooperative'">
                        <p class="text-sm text-gray-500">Tipo cooperativa</p>
                        <p class="text-gray-900">{{ coopTypeLabel(tenant.cooperative_type) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Stato</p>
                        <span
                            class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                            :class="tenant.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                        >
                            {{ tenant.is_active ? 'Attivo' : 'Inattivo' }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Utenti</p>
                        <p class="text-gray-900 font-semibold">{{ tenant.users_count }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Creato il</p>
                        <p class="text-gray-900">{{ new Date(tenant.created_at).toLocaleDateString('it-IT') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Scadenza piano</p>
                        <p class="text-gray-900">{{ tenant.plan_expires_at ? new Date(tenant.plan_expires_at).toLocaleDateString('it-IT') : 'Nessuna' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">ID</p>
                        <p class="font-mono text-xs text-gray-500 break-all">{{ tenant.id }}</p>
                    </div>
                </div>
            </div>

            <!-- Users -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Utenti ({{ users.length }})</h2>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ruolo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">{{ user.name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ user.email }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded bg-blue-100 text-blue-800">
                                    {{ user.pivot?.role || '-' }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="users.length === 0">
                            <td colspan="3" class="px-4 py-6 text-center text-gray-500 text-sm">Nessun utente associato.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Danger zone -->
            <div class="bg-white shadow rounded-lg p-6 border border-red-200">
                <h2 class="text-lg font-semibold text-red-700 mb-4">Zona pericolosa</h2>
                <div class="flex flex-wrap gap-3">
                    <button
                        @click="toggleActive"
                        class="px-4 py-2 border rounded-md text-sm font-medium"
                        :class="tenant.is_active ? 'border-yellow-300 text-yellow-700 hover:bg-yellow-50' : 'border-green-300 text-green-700 hover:bg-green-50'"
                    >
                        {{ tenant.is_active ? 'Disattiva Tenant' : 'Riattiva Tenant' }}
                    </button>
                    <button
                        @click="seedTenant"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50"
                    >
                        Ri-esegui Seed
                    </button>
                    <button
                        @click="deleteTenant"
                        class="px-4 py-2 border border-red-300 text-red-700 rounded-md text-sm font-medium hover:bg-red-50"
                    >
                        Elimina Tenant
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
