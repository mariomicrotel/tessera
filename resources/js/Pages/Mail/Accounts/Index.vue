<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    PlusIcon, PencilSquareIcon, TrashIcon,
    CheckCircleIcon, XCircleIcon, InboxArrowDownIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
});

function destroy(account) {
    if (confirm(`Eliminare la casella "${account.name}" e tutti i messaggi associati?`)) {
        router.delete(route('mail.accounts.destroy', account.id));
    }
}

function fmtDate(iso) {
    if (!iso) return 'Mai';
    return new Date(iso).toLocaleString('it-IT', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}
</script>

<template>
    <AppLayout title="Caselle email">
        <Head title="Caselle email" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Caselle email
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Configurazione account IMAP</p>
                </div>
                <a
                    :href="route('mail.accounts.create')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"
                >
                    <PlusIcon class="size-4" /> Aggiungi casella
                </a>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

                <div v-if="accounts.length === 0" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-12 text-center">
                    <InboxArrowDownIcon class="size-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
                    <p class="text-gray-500 dark:text-gray-400">Nessuna casella configurata.</p>
                    <a :href="route('mail.accounts.create')" class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <PlusIcon class="size-4" /> Aggiungi la prima casella
                    </a>
                </div>

                <div v-else class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Nome / Email</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400 hidden md:table-cell">Server IMAP</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400 hidden lg:table-cell">Ultima sync</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500 dark:text-gray-400">Messaggi</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500 dark:text-gray-400">Stato</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="acc in accounts" :key="acc.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ acc.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ acc.email }}</p>
                                </td>
                                <td class="px-4 py-3 hidden md:table-cell text-gray-600 dark:text-gray-400">
                                    {{ acc.imap_host }}:{{ acc.imap_port }}
                                    <span class="ml-1 px-1.5 py-0.5 text-xs bg-gray-100 dark:bg-gray-700 rounded">{{ acc.imap_encryption }}</span>
                                </td>
                                <td class="px-4 py-3 hidden lg:table-cell text-gray-500 dark:text-gray-400 text-xs">
                                    {{ fmtDate(acc.last_synced_at) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-gray-700 dark:text-gray-300">{{ acc.messages_count }}</span>
                                    <span v-if="acc.unread_count > 0" class="ml-1 text-xs text-indigo-600 dark:text-indigo-400 font-semibold">
                                        ({{ acc.unread_count }} non letti)
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <CheckCircleIcon v-if="acc.is_active" class="size-5 text-emerald-500 mx-auto" />
                                    <XCircleIcon v-else class="size-5 text-gray-400 mx-auto" />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <a :href="route('mail.accounts.edit', acc.id)" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 hover:text-indigo-600">
                                            <PencilSquareIcon class="size-4" />
                                        </a>
                                        <button @click="destroy(acc)" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 hover:text-red-500">
                                            <TrashIcon class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <a :href="route('mail.index')" class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                        ← Torna alla posta in arrivo
                    </a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
