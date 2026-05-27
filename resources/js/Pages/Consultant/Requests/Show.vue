<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { PaperClipIcon, ClockIcon, ArrowDownTrayIcon, InboxArrowDownIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    entity:    { type: Object, required: true },
    richiesta: { type: Object, required: true },
});

const form = useForm({
    stato:        props.richiesta.stato,
    priorita:     props.richiesta.priorita,
    descrizione:  props.richiesta.descrizione ?? '',
    data_scadenza: props.richiesta.data_scadenza ?? '',
});

const aggiornaStato = () => {
    form.put(route('consultant.requests.update', [props.entity.slug, props.richiesta.id]));
};

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

const isScaduta = (d) => d && new Date(d) < new Date();

const mimeIcon = (mime) => {
    if (!mime) return '📄';
    if (mime.includes('pdf')) return '📕';
    if (mime.includes('image')) return '🖼️';
    if (mime.includes('sheet') || mime.includes('excel') || mime.includes('csv')) return '📊';
    if (mime.includes('word') || mime.includes('document')) return '📝';
    return '📄';
};
</script>

<template>
    <AppLayout :title="richiesta.titolo">
        <Head :title="`${richiesta.titolo} — ${entity.name}`" />
        <template #header>
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-1 mb-1">
                        <Link :href="route('consultant.requests.index', entity.slug)"
                            class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                            ← {{ entity.name }} / Richieste
                        </Link>
                    </div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight truncate">
                        {{ richiesta.titolo }}
                    </h2>
                    <div class="flex items-center gap-2 mt-1">
                        <span :class="['text-xs font-semibold px-2 py-0.5 rounded', badgeClass(richiesta.priorita_color)]">
                            {{ richiesta.priorita.charAt(0).toUpperCase() + richiesta.priorita.slice(1) }}
                        </span>
                        <span :class="['text-xs px-2 py-0.5 rounded', badgeClass(richiesta.badge_color)]">
                            {{ statoLabel(richiesta.stato) }}
                        </span>
                        <span v-if="richiesta.data_scadenza" class="flex items-center gap-1 text-xs"
                            :class="isScaduta(richiesta.data_scadenza) ? 'text-red-500' : 'text-gray-500 dark:text-gray-400'">
                            <ClockIcon class="size-3.5" />
                            Scadenza: {{ new Date(richiesta.data_scadenza).toLocaleDateString('it-IT') }}
                        </span>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Descrizione -->
                <div v-if="richiesta.descrizione"
                    class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl px-5 py-4">
                    <p class="text-sm font-medium text-blue-800 dark:text-blue-300 mb-1">Istruzioni al cliente</p>
                    <p class="text-sm text-blue-700 dark:text-blue-400 whitespace-pre-line">{{ richiesta.descrizione }}</p>
                </div>

                <!-- Documenti allegati -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
                        <InboxArrowDownIcon class="size-4 text-gray-500 dark:text-gray-400" />
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            Risposte e documenti ({{ richiesta.documenti.length }})
                        </h3>
                    </div>
                    <div v-if="richiesta.documenti.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                        Nessuna risposta ricevuta. In attesa che l'ente carichi i documenti.
                    </div>
                    <ul v-else class="divide-y divide-gray-50 dark:divide-gray-700">
                        <li v-for="d in richiesta.documenti" :key="d.id"
                            class="flex items-center gap-3 px-5 py-3">
                            <span class="text-xl">{{ mimeIcon(d.mime_type) }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate flex items-center gap-2">
                                    {{ d.filename_originale }}
                                    <span v-if="d.uploaded_as_response"
                                        class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                        Risposta del cliente
                                    </span>
                                </p>
                                <div class="flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                    <span>{{ d.size_human }}</span>
                                    <span>·</span>
                                    <span>{{ d.created_at }}</span>
                                    <span v-if="d.uploaded_by">· da {{ d.uploaded_by }}</span>
                                </div>
                                <p v-if="d.note" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 italic">{{ d.note }}</p>
                            </div>
                            <a v-if="d.download_url" :href="d.download_url"
                                class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                                target="_blank" rel="noopener noreferrer">
                                <ArrowDownTrayIcon class="size-4" />
                                Scarica
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Aggiorna stato -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Aggiorna richiesta</h3>
                    <form @submit.prevent="aggiornaStato" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stato</label>
                                <select v-model="form.stato"
                                    class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200 text-sm px-3 py-2">
                                    <option value="aperta">Aperta</option>
                                    <option value="in_attesa_risposta">In attesa risposta</option>
                                    <option value="risposta_ricevuta">Risposta ricevuta</option>
                                    <option value="chiusa">Chiusa</option>
                                    <option value="annullata">Annullata</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priorità</label>
                                <select v-model="form.priorita"
                                    class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200 text-sm px-3 py-2">
                                    <option value="bassa">Bassa</option>
                                    <option value="normale">Normale</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data scadenza</label>
                            <input type="date" v-model="form.data_scadenza"
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200 text-sm px-3 py-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descrizione / istruzioni</label>
                            <textarea v-model="form.descrizione" rows="3"
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200 text-sm px-3 py-2" />
                        </div>
                        <div class="flex justify-end">
                            <PrimaryButton type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Salvataggio...' : 'Salva modifiche' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
