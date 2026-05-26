<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    account: { type: Object, default: null },
});

const isEdit = !!props.account;

const form = useForm({
    name:             props.account?.name             ?? '',
    email:            props.account?.email            ?? '',
    imap_host:        props.account?.imap_host        ?? '',
    imap_port:        props.account?.imap_port        ?? 993,
    imap_encryption:  props.account?.imap_encryption  ?? 'ssl',
    imap_username:    props.account?.imap_username    ?? '',
    imap_password:    '',
    imap_folder:      props.account?.imap_folder      ?? 'INBOX',
    sync_days:        props.account?.sync_days        ?? 30,
    is_active:        props.account?.is_active        ?? true,
});

function submit() {
    if (isEdit) {
        form.put(route('mail.accounts.update', props.account.id), {
            onSuccess: () => router.get(route('mail.accounts.index')),
        });
    } else {
        form.post(route('mail.accounts.store'), {
            onSuccess: () => router.get(route('mail.accounts.index')),
        });
    }
}

// Test connessione
const testStatus = ref(null); // null | 'ok' | 'error'
const testMessage = ref('');
const testLoading = ref(false);

async function testConnection() {
    testLoading.value = true;
    testStatus.value  = null;
    testMessage.value = '';

    try {
        const res = await fetch(route('mail.accounts.test'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                imap_host:       form.imap_host,
                imap_port:       form.imap_port,
                imap_encryption: form.imap_encryption,
                imap_username:   form.imap_username,
                imap_password:   form.imap_password,
            }),
        });
        const data = await res.json();
        testStatus.value  = data.ok ? 'ok' : 'error';
        testMessage.value = data.message;
    } catch (e) {
        testStatus.value  = 'error';
        testMessage.value = 'Errore di rete.';
    } finally {
        testLoading.value = false;
    }
}

const encryptionOptions = ['ssl', 'tls', 'starttls', 'none'];
</script>

<template>
    <AppLayout :title="isEdit ? 'Modifica casella' : 'Nuova casella email'">
        <Head :title="isEdit ? 'Modifica casella' : 'Nuova casella email'" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ isEdit ? 'Modifica casella' : 'Nuova casella email' }}
            </h2>
        </template>

        <div class="py-6">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">

                    <!-- Sezione: Identità -->
                    <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Identità</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome visualizzato <span class="text-red-500">*</span></label>
                                <input v-model="form.name" type="text" required maxlength="100"
                                    placeholder="es. Casella principale"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-100"
                                    :class="{ 'border-red-400': form.errors.name }"
                                />
                                <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Indirizzo email <span class="text-red-500">*</span></label>
                                <input v-model="form.email" type="email" required
                                    placeholder="info@cooperativa.it"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-100"
                                    :class="{ 'border-red-400': form.errors.email }"
                                />
                                <p v-if="form.errors.email" class="text-xs text-red-500 mt-1">{{ form.errors.email }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Sezione: IMAP -->
                    <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Impostazioni IMAP</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Server IMAP <span class="text-red-500">*</span></label>
                                <input v-model="form.imap_host" type="text" required
                                    placeholder="imap.gmail.com"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-100"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Porta</label>
                                <input v-model.number="form.imap_port" type="number" min="1" max="65535"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-100"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cifratura</label>
                                <select v-model="form.imap_encryption"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-100"
                                >
                                    <option v-for="enc in encryptionOptions" :key="enc" :value="enc">{{ enc.toUpperCase() }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cartella</label>
                                <input v-model="form.imap_folder" type="text"
                                    placeholder="INBOX"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-100"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username <span class="text-red-500">*</span></label>
                                <input v-model="form.imap_username" type="text" required autocomplete="off"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-100"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Password {{ isEdit ? '(lascia vuoto per non cambiare)' : '' }} <span v-if="!isEdit" class="text-red-500">*</span>
                                </label>
                                <input v-model="form.imap_password" type="password" autocomplete="new-password"
                                    :required="!isEdit"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-100"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Sezione: Opzioni -->
                    <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Opzioni sync</h3>
                        <div class="flex flex-wrap gap-6 items-start">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Giorni da scaricare al primo sync</label>
                                <input v-model.number="form.sync_days" type="number" min="1" max="365"
                                    class="w-24 px-3 py-2 text-sm border rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-100"
                                />
                            </div>
                            <div class="flex items-center gap-2 pt-6">
                                <input v-model="form.is_active" type="checkbox" id="is_active"
                                    class="rounded text-indigo-600 focus:ring-indigo-500"
                                />
                                <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300">Casella attiva</label>
                            </div>
                        </div>
                    </div>

                    <!-- Test connessione -->
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
                        <div class="flex items-center gap-3 flex-wrap">
                            <button
                                type="button"
                                @click="testConnection"
                                :disabled="testLoading || !form.imap_host || !form.imap_username || (!form.imap_password && !isEdit)"
                                class="px-3 py-1.5 text-sm font-medium border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-white dark:hover:bg-gray-700 disabled:opacity-40"
                            >
                                {{ testLoading ? 'Test in corso…' : 'Testa connessione' }}
                            </button>
                            <div v-if="testStatus" class="flex items-center gap-1.5 text-sm">
                                <CheckCircleIcon v-if="testStatus === 'ok'" class="size-5 text-emerald-500" />
                                <XCircleIcon v-else class="size-5 text-red-500" />
                                <span :class="testStatus === 'ok' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                    {{ testMessage }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer azioni -->
                    <div class="px-6 py-4 flex items-center justify-between gap-3">
                        <a :href="route('mail.accounts.index')" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                            Annulla
                        </a>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {{ isEdit ? 'Salva modifiche' : 'Aggiungi casella' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
