<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    EnvelopeIcon, EnvelopeOpenIcon, InboxArrowDownIcon,
    MagnifyingGlassIcon, ArrowPathIcon, BookmarkIcon,
    BookmarkSlashIcon, TrashIcon, StarIcon, Cog6ToothIcon,
    ChevronLeftIcon, ChevronRightIcon, FunnelIcon,
    PencilSquareIcon, PaperAirplaneIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid, BookmarkIcon as BookmarkSolid } from '@heroicons/vue/24/solid';

const props = defineProps({
    accounts:     { type: Array,  default: () => [] },
    messages:     { type: Object, required: true },
    total_unread: { type: Number, default: 0 },
    sent_count:   { type: Number, default: 0 },
    filters:      { type: Object, default: () => ({}) },
    has_accounts: { type: Boolean, default: false },
});

const search  = ref(props.filters.search  ?? '');
const filter  = ref(props.filters.filter  ?? 'all');
const account = ref(props.filters.account ?? '');
const box     = ref(props.filters.box     ?? 'received');

let debounce = null;
function applyFilters() {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('mail.index'), {
            search:  search.value  || undefined,
            filter:  filter.value  !== 'all' ? filter.value : undefined,
            account: account.value || undefined,
            box:     box.value !== 'received' ? box.value : undefined,
        }, { preserveScroll: true, replace: true });
    }, 300);
}

function switchBox(newBox) {
    box.value = newBox;
    applyFilters();
}

const isSentBox = computed(() => box.value === 'sent');

function goPage(url) {
    if (url) router.get(url, {}, { preserveScroll: true });
}

const syncForm = useForm({});
function triggerSync(accountId = null) {
    syncForm.post(route('mail.sync'), {
        data: accountId ? { account_id: accountId } : {},
        preserveScroll: true,
    });
}

function markRead(msg) {
    router.patch(route('mail.read', msg.id), {}, { preserveScroll: true });
}
function markUnread(msg) {
    router.patch(route('mail.unread', msg.id), {}, { preserveScroll: true });
}
function toggleFlag(msg) {
    router.patch(route('mail.flag', msg.id), {}, { preserveScroll: true });
}
function destroy(msg) {
    if (confirm('Eliminare questo messaggio dall\'archivio locale?')) {
        router.delete(route('mail.destroy', msg.id));
    }
}

function fmtDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    const now = new Date();
    if (d.toDateString() === now.toDateString()) {
        return d.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
    }
    return d.toLocaleDateString('it-IT', { day: '2-digit', month: 'short' });
}

const filterOptions = [
    { value: 'all',     label: 'Tutti' },
    { value: 'unread',  label: 'Non letti' },
    { value: 'flagged', label: 'Contrassegnati' },
];
</script>

<template>
    <AppLayout title="Posta in arrivo">
        <Head title="Posta in arrivo" />

        <template #header>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-3">
                    <InboxArrowDownIcon v-if="!isSentBox" class="size-6 text-indigo-500" />
                    <PaperAirplaneIcon v-else class="size-6 text-indigo-500" />
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                            {{ isSentBox ? 'Posta inviata' : 'Posta in arrivo' }}
                        </h2>
                        <p v-if="!isSentBox && total_unread > 0" class="text-xs text-indigo-600 dark:text-indigo-400 mt-0.5">
                            {{ total_unread }} non letti
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        v-if="has_accounts"
                        @click="triggerSync()"
                        :disabled="syncForm.processing"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
                    >
                        <ArrowPathIcon class="size-4" :class="{ 'animate-spin': syncForm.processing }" />
                        Sincronizza
                    </button>
                    <a
                        v-if="$page.props.userRoles?.includes('admin')"
                        :href="route('settings.index') + '?tab=posta'"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        <Cog6ToothIcon class="size-4" />
                        Impostazioni
                    </a>
                    <a
                        :href="route('mail.compose')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition-colors"
                    >
                        <PencilSquareIcon class="size-4" />
                        Scrivi
                    </a>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Nessuna casella configurata -->
                <div v-if="!has_accounts" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-12 text-center">
                    <InboxArrowDownIcon class="size-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
                    <p class="text-lg font-medium text-gray-700 dark:text-gray-300 mb-2">Nessuna casella email configurata</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Aggiungi una casella IMAP per iniziare a ricevere messaggi.</p>
                    <a
                        v-if="$page.props.userRoles?.includes('admin')"
                        :href="route('mail.accounts.create')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"
                    >
                        Aggiungi casella
                    </a>
                </div>

                <div v-else class="flex gap-4">

                    <!-- Sidebar account -->
                    <aside class="hidden lg:block w-52 shrink-0 space-y-3">
                        <!-- Selettore Ricevuti / Inviati -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <nav class="p-2 space-y-0.5">
                                <button
                                    @click="switchBox('received')"
                                    :class="[
                                        'w-full flex items-center justify-between gap-2 px-2 py-1.5 text-sm rounded-md text-left',
                                        !isSentBox ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-medium' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'
                                    ]"
                                >
                                    <span class="flex items-center gap-1.5 truncate">
                                        <InboxArrowDownIcon class="size-4 shrink-0" />
                                        Ricevuti
                                    </span>
                                    <span v-if="total_unread > 0" class="text-xs bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 font-semibold px-1.5 py-0.5 rounded-full">
                                        {{ total_unread }}
                                    </span>
                                </button>
                                <button
                                    @click="switchBox('sent')"
                                    :class="[
                                        'w-full flex items-center justify-between gap-2 px-2 py-1.5 text-sm rounded-md text-left',
                                        isSentBox ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-medium' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'
                                    ]"
                                >
                                    <span class="flex items-center gap-1.5 truncate">
                                        <PaperAirplaneIcon class="size-4 shrink-0" />
                                        Inviati
                                    </span>
                                    <span v-if="sent_count > 0" class="text-xs text-gray-400 dark:text-gray-500 font-medium px-1.5 py-0.5">
                                        {{ sent_count }}
                                    </span>
                                </button>
                            </nav>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <div class="px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Caselle</p>
                            </div>
                            <nav class="p-2 space-y-0.5">
                                <button
                                    @click="account = ''; applyFilters()"
                                    :class="[
                                        'w-full flex items-center justify-between gap-2 px-2 py-1.5 text-sm rounded-md text-left',
                                        !account ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-medium' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'
                                    ]"
                                >
                                    <span class="flex items-center gap-1.5 truncate">
                                        <InboxArrowDownIcon class="size-4 shrink-0" />
                                        Tutte
                                    </span>
                                    <span v-if="total_unread > 0" class="text-xs bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 font-semibold px-1.5 py-0.5 rounded-full">
                                        {{ total_unread }}
                                    </span>
                                </button>
                                <button
                                    v-for="acc in accounts"
                                    :key="acc.id"
                                    @click="account = acc.id; applyFilters()"
                                    :class="[
                                        'w-full flex items-center justify-between gap-2 px-2 py-1.5 text-sm rounded-md text-left',
                                        account == acc.id ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-medium' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'
                                    ]"
                                >
                                    <span class="flex items-center gap-1.5 truncate">
                                        <EnvelopeIcon class="size-4 shrink-0" />
                                        <span class="truncate">{{ acc.name }}</span>
                                    </span>
                                    <span v-if="acc.unread_count > 0" class="text-xs bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 font-semibold px-1.5 py-0.5 rounded-full shrink-0">
                                        {{ acc.unread_count }}
                                    </span>
                                </button>
                            </nav>
                        </div>
                    </aside>

                    <!-- Lista messaggi -->
                    <div class="flex-1 min-w-0">

                        <!-- Filtri -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 mb-3 flex flex-wrap gap-2 items-center">
                            <!-- Ricerca -->
                            <div class="flex-1 min-w-48 relative">
                                <MagnifyingGlassIcon class="absolute left-2.5 top-1/2 -translate-y-1/2 size-4 text-gray-400" />
                                <input
                                    v-model="search"
                                    @input="applyFilters"
                                    type="text"
                                    placeholder="Cerca mittente, oggetto…"
                                    class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                            <!-- Filtro letti/non letti -->
                            <div class="flex gap-1">
                                <button
                                    v-for="opt in filterOptions"
                                    :key="opt.value"
                                    @click="filter = opt.value; applyFilters()"
                                    :class="[
                                        'px-2.5 py-1.5 text-xs font-medium rounded-lg border transition-colors',
                                        filter === opt.value
                                            ? 'bg-indigo-600 text-white border-indigo-600'
                                            : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-300 dark:border-gray-600 hover:border-indigo-400'
                                    ]"
                                >
                                    {{ opt.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Tabella messaggi -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <div v-if="messages.data.length === 0" class="p-12 text-center">
                                <EnvelopeOpenIcon class="size-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
                                <p class="text-sm text-gray-500 dark:text-gray-400">Nessun messaggio trovato.</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                    Prova a sincronizzare la casella o cambia i filtri.
                                </p>
                            </div>

                            <ul v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                                <li
                                    v-for="msg in messages.data"
                                    :key="msg.id"
                                    :class="[
                                        'group flex items-start gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors cursor-pointer',
                                        !msg.is_read ? 'bg-indigo-50/40 dark:bg-indigo-950/20' : ''
                                    ]"
                                    @click="router.get(route('mail.show', msg.id))"
                                >
                                    <!-- Indicatore letto/non letto -->
                                    <div class="mt-1 shrink-0">
                                        <div :class="['size-2 rounded-full mt-1', !msg.is_read ? 'bg-indigo-500' : 'bg-transparent']" />
                                    </div>

                                    <!-- Corpo -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-baseline justify-between gap-2">
                                            <p :class="['text-sm truncate', !msg.is_read ? 'font-semibold text-gray-900 dark:text-gray-100' : 'font-medium text-gray-700 dark:text-gray-300']">
                                                <span v-if="msg.is_sent" class="text-gray-400 dark:text-gray-500 font-normal">A: </span>{{ msg.is_sent ? (msg.to_label || '(destinatario sconosciuto)') : (msg.from_name || msg.from_email || '(mittente sconosciuto)') }}
                                            </p>
                                            <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">{{ fmtDate(msg.sent_at) }}</span>
                                        </div>
                                        <p :class="['text-sm truncate', !msg.is_read ? 'font-medium text-gray-800 dark:text-gray-200' : 'text-gray-600 dark:text-gray-400']">
                                            {{ msg.subject }}
                                        </p>
                                        <p class="text-xs text-gray-400 dark:text-gray-500 truncate mt-0.5">
                                            {{ msg.snippet }}
                                        </p>
                                    </div>

                                    <!-- Badge + azioni (visibili on hover) -->
                                    <div class="shrink-0 flex items-center gap-1">
                                        <span v-if="msg.has_attachments" class="text-gray-400 dark:text-gray-500" title="Ha allegati">📎</span>

                                        <div class="hidden group-hover:flex items-center gap-1">
                                            <button
                                                @click.stop="msg.is_read ? markUnread(msg) : markRead(msg)"
                                                class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-400 hover:text-indigo-600"
                                                :title="msg.is_read ? 'Segna come non letto' : 'Segna come letto'"
                                            >
                                                <EnvelopeIcon v-if="msg.is_read" class="size-4" />
                                                <EnvelopeOpenIcon v-else class="size-4" />
                                            </button>
                                            <button
                                                @click.stop="toggleFlag(msg)"
                                                class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-600"
                                                :class="msg.is_flagged ? 'text-amber-500' : 'text-gray-400 hover:text-amber-500'"
                                                title="Contrassegna"
                                            >
                                                <StarSolid v-if="msg.is_flagged" class="size-4" />
                                                <StarIcon v-else class="size-4" />
                                            </button>
                                            <button
                                                @click.stop="destroy(msg)"
                                                class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-400 hover:text-red-500"
                                                title="Elimina"
                                            >
                                                <TrashIcon class="size-4" />
                                            </button>
                                        </div>
                                    </div>
                                </li>
                            </ul>

                            <!-- Paginazione -->
                            <div v-if="messages.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ messages.from }}–{{ messages.to }} di {{ messages.total }}
                                </p>
                                <div class="flex gap-1">
                                    <button
                                        @click="goPage(messages.prev_page_url)"
                                        :disabled="!messages.prev_page_url"
                                        class="p-1.5 rounded border border-gray-300 dark:border-gray-600 disabled:opacity-40 hover:bg-gray-50 dark:hover:bg-gray-700"
                                    >
                                        <ChevronLeftIcon class="size-4" />
                                    </button>
                                    <button
                                        @click="goPage(messages.next_page_url)"
                                        :disabled="!messages.next_page_url"
                                        class="p-1.5 rounded border border-gray-300 dark:border-gray-600 disabled:opacity-40 hover:bg-gray-50 dark:hover:bg-gray-700"
                                    >
                                        <ChevronRightIcon class="size-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
