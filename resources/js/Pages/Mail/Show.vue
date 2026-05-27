<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ArrowLeftIcon, EnvelopeIcon,
    TrashIcon, StarIcon, PaperClipIcon,
    ArrowDownTrayIcon, ArrowUturnLeftIcon,
    ChevronLeftIcon, ChevronRightIcon,
    ChevronDownIcon, ChevronUpIcon,
    ClipboardDocumentListIcon,
    CheckBadgeIcon, ChatBubbleLeftRightIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid';

const props = defineProps({
    message:    { type: Object,  required: true },
    thread:     { type: Array,   default: () => [] },
    prev_id:    { type: Number,  default: null },
    next_id:    { type: Number,  default: null },
    protocollo: { type: Object,  default: null },
});

// Stato espansione per ogni messaggio del thread: il messaggio aperto è espanso
const expanded = ref({});
props.thread.forEach((m) => {
    expanded.value[m.id] = (m.id === props.message.id);
});
// Se c'è un solo messaggio, è sempre espanso
const isSingle = props.thread.length <= 1;

function toggle(id) {
    if (isSingle) return;
    expanded.value[id] = !expanded.value[id];
}

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

function protocolla() {
    if (props.protocollo) return;
    router.post(route('mail.protocolla', props.message.id));
}

function fmtDateFull(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('it-IT', {
        weekday: 'long', year: 'numeric', month: 'long',
        day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function fmtDateShort(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('it-IT', {
        day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
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

function snippet(m) {
    const t = m.body_text || (m.body_html ? m.body_html.replace(/<[^>]*>/g, ' ') : '');
    return t.replace(/\s+/g, ' ').trim().slice(0, 120);
}

const isSent = props.message.folder === 'Sent';
</script>

<template>
    <AppLayout :title="message.subject">
        <Head :title="message.subject" />

        <template #header>
            <div class="flex items-center gap-2 min-w-0">
                <button @click="goBack" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 shrink-0">
                    <ArrowLeftIcon class="size-5" />
                </button>
                <button
                    v-if="prev_id"
                    @click="router.get(route('mail.show', prev_id))"
                    class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-600 shrink-0"
                    title="Messaggio precedente"
                >
                    <ChevronLeftIcon class="size-4" />
                </button>
                <button
                    v-if="next_id"
                    @click="router.get(route('mail.show', next_id))"
                    class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-600 shrink-0"
                    title="Messaggio successivo"
                >
                    <ChevronRightIcon class="size-4" />
                </button>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight truncate">
                    {{ message.subject }}
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-3">

                <!-- Barra azioni conversazione -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-3 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <ChatBubbleLeftRightIcon v-if="!isSingle" class="size-5 text-indigo-500 shrink-0" />
                        <span class="text-sm text-gray-600 dark:text-gray-300 truncate">
                            <template v-if="!isSingle">Conversazione · {{ thread.length }} messaggi</template>
                            <template v-else>{{ isSent ? 'Messaggio inviato' : 'Messaggio' }}</template>
                        </span>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <a
                            v-if="!isSent"
                            :href="route('mail.compose') + '?reply_to=' + message.id"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium transition-colors"
                        >
                            <ArrowUturnLeftIcon class="size-4" />
                            Rispondi
                        </a>
                        <button @click="markUnread" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 hover:text-indigo-600" title="Segna come non letto">
                            <EnvelopeIcon class="size-5" />
                        </button>
                        <button
                            @click="toggleFlag"
                            :class="['p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700', message.is_flagged ? 'text-amber-500' : 'text-gray-500 hover:text-amber-500']"
                            title="Contrassegna"
                        >
                            <StarSolid v-if="message.is_flagged" class="size-5" />
                            <StarIcon v-else class="size-5" />
                        </button>
                        <a
                            v-if="protocollo"
                            :href="route('protocolli.show', protocollo.id)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-300 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-xs font-medium hover:bg-emerald-100 dark:hover:bg-emerald-900/40 transition-colors"
                        >
                            <CheckBadgeIcon class="size-4" />
                            Prot. {{ protocollo.numero_formattato }}
                        </a>
                        <button
                            v-else
                            @click="protocolla"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 text-xs font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                            title="Registra nel protocollo"
                        >
                            <ClipboardDocumentListIcon class="size-4" />
                            Protocolla
                        </button>
                        <button @click="destroy" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 hover:text-red-500" title="Elimina">
                            <TrashIcon class="size-5" />
                        </button>
                    </div>
                </div>

                <!-- Messaggi del thread -->
                <div
                    v-for="m in thread"
                    :key="m.id"
                    :class="[
                        'bg-white dark:bg-gray-800 rounded-xl border overflow-hidden',
                        m.id === message.id ? 'border-indigo-300 dark:border-indigo-700' : 'border-gray-200 dark:border-gray-700'
                    ]"
                >
                    <!-- Intestazione messaggio (cliccabile per espandere/comprimere) -->
                    <div
                        :class="['px-5 py-3 flex items-start justify-between gap-3', !isSingle ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/40' : '']"
                        @click="toggle(m.id)"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span v-if="m.is_sent" class="px-1.5 py-0.5 text-[10px] font-medium bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded border border-blue-200 dark:border-blue-700">Inviata</span>
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{{ m.from }}</span>
                            </div>
                            <!-- Dettagli quando espanso -->
                            <dl v-if="expanded[m.id]" class="mt-2 space-y-1 text-xs">
                                <div class="flex gap-2">
                                    <dt class="w-12 shrink-0 text-gray-400 dark:text-gray-500">A</dt>
                                    <dd class="text-gray-600 dark:text-gray-400">{{ addrList(m.to_addresses) }}</dd>
                                </div>
                                <div v-if="m.cc_addresses?.length" class="flex gap-2">
                                    <dt class="w-12 shrink-0 text-gray-400 dark:text-gray-500">Cc</dt>
                                    <dd class="text-gray-600 dark:text-gray-400">{{ addrList(m.cc_addresses) }}</dd>
                                </div>
                            </dl>
                            <!-- Snippet quando compresso -->
                            <p v-else class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">{{ snippet(m) }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span v-if="m.has_attachments" class="text-gray-400" title="Ha allegati"><PaperClipIcon class="size-4" /></span>
                            <span class="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ expanded[m.id] ? fmtDateFull(m.sent_at) : fmtDateShort(m.sent_at) }}</span>
                            <component
                                v-if="!isSingle"
                                :is="expanded[m.id] ? ChevronUpIcon : ChevronDownIcon"
                                class="size-4 text-gray-400"
                            />
                        </div>
                    </div>

                    <!-- Corpo + allegati (solo se espanso) -->
                    <div v-if="expanded[m.id]" class="border-t border-gray-100 dark:border-gray-700">
                        <div class="px-5 py-4">
                            <div v-if="m.body_html">
                                <iframe
                                    :srcdoc="m.body_html"
                                    sandbox="allow-same-origin"
                                    class="w-full border-0 rounded-lg bg-white"
                                    style="height: 500px; resize: vertical; overflow: auto;"
                                    title="Corpo email"
                                />
                            </div>
                            <div v-else-if="m.body_text">
                                <pre class="whitespace-pre-wrap font-sans text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ m.body_text }}</pre>
                            </div>
                            <div v-else class="text-sm text-gray-400 dark:text-gray-500 italic py-6 text-center">
                                Nessun contenuto disponibile.
                            </div>
                        </div>

                        <!-- Allegati -->
                        <div v-if="m.attachments && m.attachments.length" class="px-5 py-3 border-t border-gray-100 dark:border-gray-700">
                            <ul class="space-y-2">
                                <li
                                    v-for="att in m.attachments"
                                    :key="att.id"
                                    class="flex items-center gap-3 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/40"
                                >
                                    <PaperClipIcon class="size-4 shrink-0 text-gray-400" />
                                    <span class="flex-1 min-w-0 text-sm text-gray-800 dark:text-gray-200 truncate">{{ att.original_name }}</span>
                                    <span class="text-xs text-gray-400 shrink-0">{{ fmtSize(att.size) }}</span>
                                    <a
                                        :href="att.download_url"
                                        class="shrink-0 inline-flex items-center gap-1 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800"
                                        target="_blank" rel="noopener noreferrer"
                                    >
                                        <ArrowDownTrayIcon class="size-4" />
                                        Scarica
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- Rispondi a questo messaggio -->
                        <div v-if="!m.is_sent" class="px-5 py-3 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
                            <a
                                :href="route('mail.compose') + '?reply_to=' + m.id"
                                class="inline-flex items-center gap-1.5 text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800"
                            >
                                <ArrowUturnLeftIcon class="size-4" />
                                Rispondi
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Footer navigazione -->
                <div class="flex items-center justify-between px-1">
                    <button @click="goBack" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                        <ArrowLeftIcon class="size-4" />
                        Torna alla lista
                    </button>
                    <div class="flex items-center gap-2">
                        <button v-if="prev_id" @click="router.get(route('mail.show', prev_id))" class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-indigo-600">
                            <ChevronLeftIcon class="size-3.5" /> Precedente
                        </button>
                        <button v-if="next_id" @click="router.get(route('mail.show', next_id))" class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-indigo-600">
                            Successivo <ChevronRightIcon class="size-3.5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
