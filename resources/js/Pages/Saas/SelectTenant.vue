<script setup>
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    tenants:      Array,
    isSuperAdmin: Boolean,
    adminUrl:     String,
});

const switchTo = (slug) => {
    router.post(route('saas.switch-tenant', { tenant: slug }));
};
</script>

<template>
    <Head title="Seleziona Organizzazione" />

    <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-lg">

            <!-- Logo -->
            <div class="flex justify-center mb-6">
                <div class="flex items-center space-x-2">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-lg">I</span>
                    </div>
                    <span class="text-xl font-bold text-gray-900">Infotel Sistemi</span>
                </div>
            </div>

            <h2 class="text-center text-2xl font-bold text-gray-900 mb-2">Seleziona Organizzazione</h2>
            <p class="text-center text-sm text-gray-600 mb-8">Scegli l'organizzazione su cui vuoi lavorare</p>

            <!-- Banner superadmin -->
            <div v-if="isSuperAdmin" class="mb-6 flex items-center justify-between bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3">
                <div class="flex items-center gap-2 text-sm text-indigo-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span class="font-medium">Superadmin</span>
                    <span class="text-indigo-500">— stai visualizzando tutte le organizzazioni</span>
                </div>
                <Link :href="adminUrl"
                    class="text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 px-3 py-1.5 rounded-md transition">
                    Pannello Admin
                </Link>
            </div>

            <!-- Lista tenant -->
            <div class="space-y-3">
                <button
                    v-for="tenant in tenants"
                    :key="tenant.id"
                    @click="switchTo(tenant.slug)"
                    class="w-full flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200 hover:border-blue-500 hover:shadow-md transition cursor-pointer text-left"
                >
                    <div>
                        <p class="font-semibold text-gray-900">{{ tenant.name }}</p>
                        <p class="text-sm text-gray-500 capitalize">{{ tenant.role }} • Piano {{ tenant.plan }}</p>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <!-- Nessuna organizzazione -->
                <p v-if="!tenants?.length" class="text-center text-sm text-gray-500 py-8">
                    Nessuna organizzazione trovata.
                </p>
            </div>

            <!-- Link pannello admin (in fondo, solo superadmin) -->
            <div v-if="isSuperAdmin" class="mt-8 text-center">
                <Link :href="adminUrl" class="text-sm text-indigo-600 hover:underline">
                    → Gestisci organizzazioni dal pannello admin
                </Link>
            </div>

        </div>
    </div>
</template>
