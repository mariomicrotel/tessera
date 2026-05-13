<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ClipboardDocumentListIcon, PlusIcon, ClockIcon, PaperClipIcon } from '@heroicons/vue/24/outline';

defineProps({
    entity:    { type: Object, required: true },
    richieste: { type: Object, required: true }, // LengthAwarePaginator
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
    <AppLayout :title="`Richieste — ${entity.name}`">
        <Head :title="`Richieste — ${entity.name}`" />
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-1 mb-1">
                        <Link :href="route('consultant.entities.show', entity.slug)"
                            class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                            ← {{ entity.name }}
                        </Link>
                    </div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Richieste documenti
                    </h2>
                </div>
                <Link :href="route('consultant.requests.create', entity.slug)">
                    <PrimaryButton>
                        <PlusIcon class="size-4 me-1.5" />Nuova richiesta
                    </PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

                <div v-if="richieste.data.length === 0"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-6 py-16 text-center">
                    <ClipboardDocumentListIcon class="size-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Nessuna richiesta per questo ente.</p>
                    <Link :href="route('consultant.requests.create', entity.slug)"
                        class="mt-3 inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">
                        <PlusIcon class="size-4" />Crea la prima richiesta
                    </Link>
                </div>

                <div v-else class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li v-for="r in richieste.data" :key="r.id">
                            <Link :href="route('consultant.requests.show', [entity.slug, r.id])"
                                class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition group">

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                                        <span :class="['text-xs font-semibold px-2 py-0.5 rounded', badgeClass(r.priorita_color)]">
                                            {{ prioritaLabel(r.priorita) }}
                                        </span>
                                        <span :class="['text-xs px-2 py-0.5 rounded', badgeClass(r.badge_color)]">
                                            {{ statoLabel(r.stato) }}
                                        </span>
                                    </div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ r.titolo }}</p>
                                </div>

                                <div class="flex items-center gap-4 flex-shrink-0">
                                    <div v-if="r.documenti_count > 0" class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                        <PaperClipIcon class="size-3.5" />
                                        {{ r.documenti_count }}
                                    </div>
                                    <div v-if="r.data_scadenza" class="flex items-center gap-1 text-xs"
                                        :class="isScaduta(r.data_scadenza) ? 'text-red-500' : 'text-gray-400 dark:text-gray-500'">
                                        <ClockIcon class="size-3.5" />
                                        {{ new Date(r.data_scadenza).toLocaleDateString('it-IT') }}
                                    </div>
                                </div>
                            </Link>
                        </li>
                    </ul>

                    <!-- Paginazione -->
                    <div v-if="richieste.last_page > 1"
                        class="px-5 py-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>Pagina {{ richieste.current_page }} di {{ richieste.last_page }}</span>
                        <div class="flex gap-2">
                            <Link v-if="richieste.prev_page_url" :href="richieste.prev_page_url"
                                class="px-3 py-1 rounded border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                                ← Prec
                            </Link>
                            <Link v-if="richieste.next_page_url" :href="richieste.next_page_url"
                                class="px-3 py-1 rounded border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                                Succ →
                            </Link>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
