<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    tenants: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
let searchTimeout = null;

watch(search, (val) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('admin.tenants'), { search: val || undefined }, { preserveState: true, replace: true });
    }, 300);
});

const toggleActive = (tenant) => {
    router.post(route('admin.tenants.toggle-active', tenant.slug), {}, { preserveScroll: true });
};

const deleteTenant = (tenant) => {
    if (confirm(`Eliminare definitivamente "${tenant.name}"? Questa azione non è reversibile.`)) {
        router.delete(route('admin.tenants.destroy', tenant.slug));
    }
};

const planColors = {
    free: 'bg-gray-100 text-gray-800',
    basic: 'bg-blue-100 text-blue-800',
    pro: 'bg-purple-100 text-purple-800',
    enterprise: 'bg-amber-100 text-amber-800',
};
</script>

<template>
    <AdminLayout title="Tenant">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Gestione Tenant</h1>
            <Link
                :href="route('admin.tenants.create')"
                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700"
            >
                + Nuovo Tenant
            </Link>
        </div>

        <!-- Search -->
        <div class="mb-4">
            <input
                v-model="search"
                type="text"
                placeholder="Cerca per nome..."
                class="w-full max-w-md px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
            />
        </div>

        <!-- Table -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slug</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Piano</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Utenti</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stato</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Creato il</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Azioni</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="tenant in tenants.data" :key="tenant.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <Link :href="route('admin.tenants.show', tenant.slug)" class="text-blue-600 hover:text-blue-800 font-medium">
                                {{ tenant.name }}
                            </Link>
                            <div v-if="tenant.wizard_pendente" class="mt-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">
                                    ⚙️ Wizard da completare
                                </span>
                            </div>
                            <div v-else-if="tenant.forma_giuridica_label && tenant.forma_giuridica_label !== '—'" class="mt-1 text-xs text-gray-500">
                                {{ tenant.forma_giuridica_label }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                            {{ tenant.slug }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full" :class="planColors[tenant.plan] || 'bg-gray-100 text-gray-800'">
                                {{ tenant.plan }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ tenant.users_count }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button
                                @click="toggleActive(tenant)"
                                class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full cursor-pointer"
                                :class="tenant.is_active ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-red-100 text-red-800 hover:bg-red-200'"
                            >
                                {{ tenant.is_active ? 'Attivo' : 'Inattivo' }}
                            </button>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ new Date(tenant.created_at).toLocaleDateString('it-IT') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-3">
                            <Link :href="route('admin.tenants.wizard', tenant.slug)"
                                class="text-amber-600 hover:text-amber-800"
                                :class="{ 'font-semibold': tenant.wizard_pendente }">
                                {{ tenant.wizard_pendente ? '⚙️ Configura' : 'Configura' }}
                            </Link>
                            <Link :href="route('admin.tenants.show', tenant.slug)" class="text-blue-600 hover:text-blue-800">
                                Dettagli
                            </Link>
                            <button @click="deleteTenant(tenant)" class="text-red-600 hover:text-red-800">
                                Elimina
                            </button>
                        </td>
                    </tr>
                    <tr v-if="tenants.data.length === 0">
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">Nessun tenant trovato.</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="tenants.last_page > 1" class="px-6 py-3 border-t border-gray-200 flex justify-between items-center">
                <p class="text-sm text-gray-500">{{ tenants.total }} risultati</p>
                <div class="flex space-x-1">
                    <template v-for="link in tenants.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="px-3 py-1 text-sm rounded"
                            :class="link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border hover:bg-gray-50'"
                            v-html="link.label"
                        />
                        <span v-else class="px-3 py-1 text-sm text-gray-400" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
