<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    BuildingOffice2Icon,
    ClipboardDocumentListIcon,
    ExclamationCircleIcon,
    ClockIcon,
} from '@heroicons/vue/24/outline';

defineProps({
    entities:         { type: Array, default: () => [] },
    richieste_aperte: { type: Array, default: () => [] },
});

const prioritaLabel = (p) => ({ urgente: 'Urgente', alta: 'Alta', normale: 'Normale', bassa: 'Bassa' })[p] ?? p;
const statoLabel = (s) => ({
    aperta:               'Aperta',
    in_attesa_risposta:   'In attesa',
    risposta_ricevuta:    'Risposta ricevuta',
    chiusa:               'Chiusa',
    annullata:            'Annullata',
})[s] ?? s;

const badgeClass = (color) => ({
    blue:   'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    green:  'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    gray:   'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    red:    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    orange: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
})[color] ?? 'bg-gray-100 text-gray-600';

const isScaduta = (d) => d && new Date(d) < new Date();
</script>

<template>
    <AppLayout title="Area Consulente">
        <Head title="Dashboard Consulente" />
        <template #header>
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Area Consulente
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    Panoramica degli enti gestiti e delle richieste in corso
                </p>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- KPI cards -->
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-3">
                        <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                            <BuildingOffice2Icon class="size-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ entities.length }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Enti assegnati</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-3">
                        <div class="p-2 bg-yellow-50 dark:bg-yellow-900/30 rounded-lg">
                            <ClipboardDocumentListIcon class="size-6 text-yellow-600 dark:text-yellow-400" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ richieste_aperte.length }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Richieste aperte</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-3">
                        <div class="p-2 bg-red-50 dark:bg-red-900/30 rounded-lg">
                            <ExclamationCircleIcon class="size-6 text-red-500 dark:text-red-400" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {{ richieste_aperte.filter(r => r.priorita === 'urgente' || r.priorita === 'alta').length }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Alta priorità</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

                    <!-- Enti assegnati -->
                    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100 text-sm">Enti assegnati</h3>
                            <Link :href="route('consultant.entities.index')"
                                class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                Vedi tutti
                            </Link>
                        </div>
                        <div v-if="entities.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                            Nessun ente assegnato
                        </div>
                        <ul v-else class="divide-y divide-gray-50 dark:divide-gray-700">
                            <li v-for="e in entities" :key="e.id"
                                class="px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="size-9 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                                            <span class="text-sm font-bold text-blue-600 dark:text-blue-400">
                                                {{ e.name.charAt(0).toUpperCase() }}
                                            </span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ e.name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 capitalize">{{ e.ruolo }}</p>
                                        </div>
                                        <span v-if="e.richieste_aperte > 0"
                                            class="flex-shrink-0 text-xs bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 rounded-full px-2 py-0.5 font-medium">
                                            {{ e.richieste_aperte }}
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-2 flex items-center gap-2">
                                    <a :href="`/app/${e.slug}/dashboard`"
                                        class="flex-1 text-center text-xs font-medium px-2 py-1.5 rounded-md bg-blue-600 hover:bg-blue-700 text-white transition">
                                        Entra nell'ente →
                                    </a>
                                    <Link :href="route('consultant.requests.create', e.slug)"
                                        class="flex items-center gap-1 text-xs px-2 py-1.5 rounded-md border border-blue-200 dark:border-blue-700 text-blue-700 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30"
                                        title="Crea una nuova richiesta documenti per questo ente">
                                        <ClipboardDocumentListIcon class="size-4" />
                                        Nuova richiesta
                                    </Link>
                                    <Link :href="route('consultant.entities.show', e.slug)"
                                        class="text-xs px-2 py-1.5 rounded-md border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                        Riepilogo
                                    </Link>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Richieste in coda -->
                    <div class="lg:col-span-3 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100 text-sm">Richieste aperte (per priorità)</h3>
                        </div>
                        <div v-if="richieste_aperte.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                            Nessuna richiesta aperta
                        </div>
                        <ul v-else class="divide-y divide-gray-50 dark:divide-gray-700">
                            <li v-for="r in richieste_aperte" :key="r.id"
                                class="px-5 py-3 flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 mb-0.5">
                                        <span :class="['text-xs font-medium px-1.5 py-0.5 rounded', badgeClass(r.priorita_color)]">
                                            {{ prioritaLabel(r.priorita) }}
                                        </span>
                                        <span :class="['text-xs px-1.5 py-0.5 rounded', badgeClass(r.badge_color)]">
                                            {{ statoLabel(r.stato) }}
                                        </span>
                                    </div>
                                    <Link :href="route('consultant.requests.show', [r.tenant?.slug, r.id])"
                                        class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400 line-clamp-1">
                                        {{ r.titolo }}
                                    </Link>
                                    <p v-if="r.tenant" class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ r.tenant.name }}
                                    </p>
                                </div>
                                <div v-if="r.data_scadenza" class="flex-shrink-0 flex items-center gap-1 text-xs"
                                    :class="isScaduta(r.data_scadenza) ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400'">
                                    <ClockIcon class="size-3.5" />
                                    {{ new Date(r.data_scadenza).toLocaleDateString('it-IT') }}
                                </div>
                            </li>
                        </ul>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>
