<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {
    ClipboardDocumentListIcon,
    PencilSquareIcon,
    ClockIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    entity:       { type: Object, required: true },
    richieste:    { type: Array,  default: () => [] },
    note_fissate: { type: Array,  default: () => [] },
    kpi:          { type: Object, default: () => ({}) },
});

const badgeClass = (color) => ({
    blue:   'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    green:  'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    gray:   'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    red:    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    orange: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
})[color] ?? 'bg-gray-100 text-gray-600';

const statoLabel = (s) => ({
    aperta:               'Aperta',
    in_attesa_risposta:   'In attesa',
    risposta_ricevuta:    'Risposta ricevuta',
    chiusa:               'Chiusa',
    annullata:            'Annullata',
})[s] ?? s;

const prioritaLabel = (p) => ({ urgente: 'Urgente', alta: 'Alta', normale: 'Normale', bassa: 'Bassa' })[p] ?? p;

const isScaduta = (d) => d && new Date(d) < new Date();
</script>

<template>
    <AppLayout :title="entity.name">
        <Head :title="`${entity.name} — Consulente`" />
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <Link :href="route('consultant.entities.index')"
                            class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                            ← Enti
                        </Link>
                    </div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ entity.name }}
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 capitalize">
                        {{ entity.organization_type ?? 'Ente' }} · {{ entity.plan ?? '—' }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('consultant.notes.index', entity.slug)">
                        <PrimaryButton class="text-sm">
                            <PencilSquareIcon class="size-4 me-1.5" />Note
                        </PrimaryButton>
                    </Link>
                    <Link :href="route('consultant.requests.create', entity.slug)">
                        <PrimaryButton class="text-sm">
                            <ClipboardDocumentListIcon class="size-4 me-1.5" />Nuova richiesta
                        </PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- KPI -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-3">
                        <div class="p-2 bg-green-50 dark:bg-green-900/30 rounded-lg">
                            <UsersIcon class="size-5 text-green-600 dark:text-green-400" />
                        </div>
                        <div>
                            <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ kpi.membri_attivi ?? '—' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Soci attivi</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-3">
                        <div class="p-2 bg-yellow-50 dark:bg-yellow-900/30 rounded-lg">
                            <ClipboardDocumentListIcon class="size-5 text-yellow-600 dark:text-yellow-400" />
                        </div>
                        <div>
                            <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ richieste.length }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Richieste aperte</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-3">
                        <div class="p-2 bg-purple-50 dark:bg-purple-900/30 rounded-lg">
                            <PencilSquareIcon class="size-5 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div>
                            <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ note_fissate.length }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Note in evidenza</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- Richieste aperte -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Richieste aperte</h3>
                            <Link :href="route('consultant.requests.index', entity.slug)"
                                class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                Vedi tutte
                            </Link>
                        </div>
                        <div v-if="richieste.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                            Nessuna richiesta aperta
                        </div>
                        <ul v-else class="divide-y divide-gray-50 dark:divide-gray-700">
                            <li v-for="r in richieste" :key="r.id"
                                class="px-5 py-3 flex items-center justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span :class="['text-xs font-medium px-1.5 py-0.5 rounded', badgeClass(r.priorita_color)]">
                                            {{ prioritaLabel(r.priorita) }}
                                        </span>
                                    </div>
                                    <Link :href="route('consultant.requests.show', [entity.slug, r.id])"
                                        class="text-sm text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400 line-clamp-1 font-medium">
                                        {{ r.titolo }}
                                    </Link>
                                </div>
                                <div v-if="r.data_scadenza" class="flex-shrink-0 flex items-center gap-1 text-xs"
                                    :class="isScaduta(r.data_scadenza) ? 'text-red-500' : 'text-gray-400 dark:text-gray-500'">
                                    <ClockIcon class="size-3.5" />
                                    {{ new Date(r.data_scadenza).toLocaleDateString('it-IT') }}
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Note fissate -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Note in evidenza</h3>
                            <Link :href="route('consultant.notes.index', entity.slug)"
                                class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                Tutte le note
                            </Link>
                        </div>
                        <div v-if="note_fissate.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                            Nessuna nota in evidenza
                        </div>
                        <ul v-else class="divide-y divide-gray-50 dark:divide-gray-700 px-5 py-3 space-y-2">
                            <li v-for="n in note_fissate" :key="n.id"
                                class="py-2">
                                <div class="flex items-start gap-2">
                                    <span class="mt-0.5 text-yellow-500 text-xs">★</span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-gray-800 dark:text-gray-200 line-clamp-2">{{ n.testo }}</p>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span :class="[
                                                'text-xs px-1.5 py-0.5 rounded',
                                                n.visibilita === 'condivisa'
                                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                    : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
                                            ]">
                                                {{ n.visibilita === 'condivisa' ? 'Condivisa' : 'Interna' }}
                                            </span>
                                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ n.updated_at }}</span>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>
