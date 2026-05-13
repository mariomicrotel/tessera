<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps({
    title: { type: String, default: 'Admin' },
});

const page = usePage();
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <Head :title="`${title} - Admin`" />

        <!-- Navbar -->
        <nav class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center space-x-8">
                        <Link href="/admin" class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                                <span class="text-white font-bold text-sm">I</span>
                            </div>
                            <span class="font-bold text-gray-900">Super Admin</span>
                        </Link>

                        <div class="hidden sm:flex space-x-4">
                            <Link
                                :href="route('admin.dashboard')"
                                class="px-3 py-2 rounded-md text-sm font-medium"
                                :class="route().current('admin.dashboard') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'"
                            >
                                Dashboard
                            </Link>
                            <Link
                                :href="route('admin.tenants')"
                                class="px-3 py-2 rounded-md text-sm font-medium"
                                :class="route().current('admin.tenants*') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'"
                            >
                                Tenant
                            </Link>
                            <Link
                                :href="route('admin.consultants.index')"
                                class="px-3 py-2 rounded-md text-sm font-medium"
                                :class="route().current('admin.consultants*') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'"
                            >
                                Consulenti
                            </Link>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500">{{ page.props.auth?.user?.name }}</span>
                        <Link :href="route('saas.select-tenant')" class="text-sm text-blue-600 hover:text-blue-800">
                            Area Tenant
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Flash messages -->
        <div v-if="page.props.flash" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div
                class="rounded-md p-4"
                :class="{
                    'bg-green-50 text-green-800': page.props.flash.type === 'success',
                    'bg-red-50 text-red-800': page.props.flash.type === 'error',
                    'bg-yellow-50 text-yellow-800': page.props.flash.type === 'warning',
                }"
            >
                {{ page.props.flash.message }}
            </div>
        </div>

        <!-- Content -->
        <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <slot />
        </main>
    </div>
</template>
