<script setup>
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ArrowLeftIcon, EnvelopeIcon, EnvelopeOpenIcon,
    TrashIcon, StarIcon, FlagIcon, PaperClipIcon,
    ArrowDownTrayIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid';

const props = defineProps({
    message: { type: Object, required: true },
});

function goBack() {
    router.get(route('mail.index'));
}

function markUnread() {
    router.patch(route('mail.unread', props.message.id), {}, { preserveScroll: true });
}

function toggleFlag() {
    router.patch(route('mail.flag', props.message.id), {}, { preserveScroll: true });
}

function destroy() {
    if (confirm('Eliminare questo messaggio dall\'archivio locale?')) {
        router.delete(route('mail.destroy', props.message.id));
    }
}

function fmtDateFull(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('it-IT', {
        weekday: 'long', year: 'numeric', month: 'long',
        day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function addrList(list) {
    if (!list || !list.length) return '—';
    return list.map(a => a.name ? `${a.name} <${a.email}>` : a.email).join(', ');
}

function fmtSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}
</script>

<template>
    <AppLayout :title="message.subject">
        <Head :title="message.subject" />

        <template #header>
            <div class="flex items-center gap-3">
                <button @click="goBack" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500">
                    <ArrowLeftIcon class="size-5" />
                </button>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight truncate max-w-2xl">
                    {{ message.subject }}
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">

                    <!-- Header messaggio -->
                    <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-start justify-between gap-4">
                            <!-- Mittente + subject -->
                            <div class="flex-1 min-w-0">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3">
                                    {{ message.subject }}
                                </h3>
                                <dl class="space-y-1.5 text-sm">
                                    <div class="flex gap-2">
                                        <dt class="w-16 shrink-0 text-gray-500 dark:text-gray-400">Da</dt>
                                        <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ message.from }}</dd>
                                    </div>
                                    <div class="flex gap-2">
                                        <dt class="w-16 shrink-0 text-gray-500 dark:text-gray-400">A</dt>
                                        <dd class="text-gray-700 dark:text-gray-300">{{ addrList(message.to_addresses) }}</dd>
                                    </div>
                                    <div v-if="message.cc_addresses?.length" class="flex gap-2">
                                        <dt class="w-16 shrink-0 text-gray-500 dark:text-gray-400">Cc</dt>
                                        <dd class="text-gray-700 dark:text-gray-300">{{ addrList(message.cc_addresses) }}</dd>
                                    </div>
                                    <div class="flex gap-2">
                                        <dt class="w-16 shrink-0 text-gray-500 dark:text-gray-400">Data</dt>
                                        <dd class="text-gray-700 dark:text-gray-300">{{ fmtDateFull(message.sent_at) }}</dd>
                                    </div>
                                    <div v-if="message.account" class="flex gap-2">
                                        <dt class="w-16 shrink-0 text-gray-500 dark:text-gray-400">Casella</dt>
                                        <dd class="text-gray-700 dark:text-gray-300">{{ message.account.name }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Azioni -->
                            <div class="flex items-center gap-1 shrink-0">
                                <button
                                    @click="markUnread"
                                    class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 hover:text-indigo-600"
                                    title="Segna come non letto"
                                >
                                    <EnvelopeIcon class="size-5" />
                                </button>
                                <button
                                    @click="toggleFlag"
                                    :class="[
                                        'p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700',
                                        message.is_flagged ? 'text-amber-500' : 'text-gray-500 hover:text-amber-500'
                                    ]"
                                    title="Contrassegna"
                                >
                                    <StarSolid v-if="message.is_flagged" class="size-5" />
                                    <StarIcon v-else class="size-5" />
                                </button>
                                <button
                                    @click="destroy"
                                    class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 hover:text-red-500"
                                    title="Elimina"
                                >
                                    <TrashIcon class="size-5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Corpo email -->
                    <div class="px-6 py-5">
                        <!-- HTML body in iframe sandboxed -->
                        <div v-if="message.body_html">
                            <iframe
                                :srcdoc="message.body_html"
                                sandbox="allow-same-origin"
                                class="w-full min-h-64 border-0 rounded-lg bg-white"
                                style="height: 600px; resize: vertical; overflow: auto;"
                                title="Corpo email"
                            />
                        </div>
                        <!-- Fallback testo plain -->
                        <div v-else-if="message.body_text">
                            <pre class="whitespace-pre-wrap font-sans text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ message.body_text }}</pre>
                        </div>
                        <div v-else class="text-sm text-gray-400 dark:text-gray-500 italic py-8 text-center">
                            Nessun contenuto disponibile.
                        </div>
                    </div>

                    <!-- Allegati -->
                    <div v-if="message.attachments && message.attachments.length" class="px-6 py-4 border-t border-gray-100 dark:border-gray-700">
                        <h4 class="flex items-center gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                            <PaperClipIcon class="size-4" />
                            Allegati ({{ message.attachments.length }})
                        </h4>
                        <ul class="space-y-2">
                            <li
                                v-for="att in message.attachments"
                                :key="att.id"
                                class="flex items-center gap-3 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/40 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                            >
                                <PaperClipIcon class="size-4 shrink-0 text-gray-400" />
                                <span class="flex-1 min-w-0 text-sm text-gray-800 dark:text-gray-200 truncate">{{ att.original_name }}</span>
                                <span class="text-xs text-gray-400 shrink-0">{{ fmtSize(att.size) }}</span>
                                <a
                                    :href="att.download_url"
                                    class="shrink-0 inline-flex items-center gap-1 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <ArrowDownTrayIcon class="size-4" />
                                    Scarica
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-3 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30 flex items-center justify-between">
                        <button @click="goBack" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                            <ArrowLeftIcon class="size-4" />
                            Torna alla lista
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
