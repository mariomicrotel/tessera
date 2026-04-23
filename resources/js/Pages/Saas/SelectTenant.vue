<script setup>
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    tenants: Array,
});

const switchTo = (slug) => {
    router.post(route('saas.switch-tenant', { tenant: slug }));
};
</script>

<template>
    <Head title="Seleziona Organizzazione" />

    <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
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

            <div class="space-y-3">
                <button
                    v-for="tenant in tenants"
                    :key="tenant.id"
                    @click="switchTo(tenant.slug)"
                    class="w-full flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200 hover:border-blue-500 hover:shadow-md transition cursor-pointer"
                >
                    <div class="text-left">
                        <p class="font-semibold text-gray-900">{{ tenant.name }}</p>
                        <p class="text-sm text-gray-500">{{ tenant.role }} • Piano {{ tenant.plan }}</p>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>
