<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { BuildingOffice2Icon, ChevronRightIcon } from '@heroicons/vue/24/outline';

defineProps({
    entities: { type: Array, default: () => [] },
});
</script>

<template>
    <AppLayout title="I miei enti">
        <Head title="Enti assegnati — Consulente" />
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Enti assegnati
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Organizzazioni per cui sei consulente
                    </p>
                </div>
                <Link :href="route('consultant.dashboard')"
                    class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">
                    ← Dashboard
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

                <div v-if="entities.length === 0"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-6 py-16 text-center">
                    <BuildingOffice2Icon class="size-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Nessun ente assegnato.</p>
                    <p class="text-gray-400 dark:text-gray-500 text-xs mt-1">
                        Contatta l'amministratore della piattaforma per richiedere l'assegnazione.
                    </p>
                </div>

                <div v-else class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li v-for="e in entities" :key="e.id">
                            <Link :href="route('consultant.entities.show', e.slug)"
                                class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition group">

                                <!-- Avatar ente -->
                                <div class="size-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                                    <span class="text-sm font-bold text-blue-600 dark:text-blue-400">
                                        {{ e.name.charAt(0).toUpperCase() }}
                                    </span>
                                </div>

                                <!-- Info ente -->
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-gray-100 text-sm">{{ e.name }}</p>
                                    <div class="flex items-center gap-3 mt-0.5">
                                        <span class="text-xs text-gray-500 dark:text-gray-400 capitalize">
                                            Ruolo: {{ e.ruolo }}
                                        </span>
                                        <span v-if="e.started_at" class="text-xs text-gray-400 dark:text-gray-500">
                                            Dal {{ new Date(e.started_at).toLocaleDateString('it-IT') }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Plan badge -->
                                <span v-if="e.plan"
                                    class="flex-shrink-0 text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-full px-2 py-0.5 font-medium capitalize">
                                    {{ e.plan }}
                                </span>

                                <ChevronRightIcon class="size-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 flex-shrink-0" />
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
