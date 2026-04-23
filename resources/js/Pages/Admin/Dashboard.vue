<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
});

const planLabels = { free: 'Free', basic: 'Basic', pro: 'Pro', enterprise: 'Enterprise' };
</script>

<template>
    <AdminLayout title="Dashboard">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Dashboard Piattaforma</h1>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm font-medium text-gray-500">Tenant totali</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ stats.total_tenants }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm font-medium text-gray-500">Tenant attivi</p>
                <p class="text-3xl font-bold text-green-600 mt-1">{{ stats.active_tenants }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm font-medium text-gray-500">Utenti totali</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ stats.total_users }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm font-medium text-gray-500">Tenant inattivi</p>
                <p class="text-3xl font-bold text-red-500 mt-1">{{ stats.total_tenants - stats.active_tenants }}</p>
            </div>
        </div>

        <!-- Per piano -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Distribuzione per piano</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div v-for="(label, key) in planLabels" :key="key" class="text-center p-4 bg-gray-50 rounded-lg">
                    <p class="text-2xl font-bold text-gray-900">{{ stats.tenants_by_plan?.[key] || 0 }}</p>
                    <p class="text-sm text-gray-500 mt-1">{{ label }}</p>
                </div>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Azioni rapide</h2>
            <div class="flex flex-wrap gap-3">
                <Link
                    :href="route('admin.tenants.create')"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700"
                >
                    + Nuovo Tenant
                </Link>
                <Link
                    :href="route('admin.tenants')"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50"
                >
                    Gestisci Tenant
                </Link>
            </div>
        </div>
    </AdminLayout>
</template>
