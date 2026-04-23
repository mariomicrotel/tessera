<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ApiTokenManager from '@/Pages/API/Partials/ApiTokenManager.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ActionSection from '@/Components/ActionSection.vue';
import InputLabel from '@/Components/InputLabel.vue';

defineProps({
    tokens: Array,
    availablePermissions: Array,
    defaultPermissions: Array,
});

const apiVersions = computed(() => usePage().props.apiVersions ?? ['v1']);
const selectedVersion = ref(apiVersions.value[0] ?? 'v1');

const apiBaseUrl = computed(() => {
    if (typeof window === 'undefined') return '';
    return window.location.origin + '/api/' + selectedVersion.value;
});

const endpoints = [
    {
        method: 'GET',
        path: '/user',
        description: 'Restituisce i dati dell\'utente autenticato.',
        permission: 'read',
        responseExample: '{"id":1,"name":"...","email":"..."}',
    },
];
</script>

<template>
    <AppLayout title="Token API">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Token API
            </h2>
        </template>

        <div>
            <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-10">
                <!-- Documentazione delle chiamate API -->
                <ActionSection>
                    <template #title>
                        Documentazione delle chiamate API
                    </template>

                    <template #description>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Usa il token creato qui sotto per autenticarti alle API. In ogni richiesta invia l'header
                            <code class="px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-700 font-mono text-xs">Authorization: Bearer &lt;il_tuo_token&gt;</code>.
                        </p>
                        <div class="mt-3">
                            <InputLabel for="api-version" value="Versione API" />
                            <select
                                id="api-version"
                                v-model="selectedVersion"
                                class="mt-1 block w-full max-w-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            >
                                <option v-for="v in apiVersions" :key="v" :value="v">{{ v }}</option>
                            </select>
                        </div>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            Base URL: <code class="px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-700 font-mono text-xs break-all">{{ apiBaseUrl }}</code>
                        </p>
                    </template>

                    <template #content>
                        <div class="space-y-4">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Metodo
                                            </th>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Path
                                            </th>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Descrizione
                                            </th>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Permesso
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-600">
                                        <tr v-for="(ep, i) in endpoints" :key="i">
                                            <td class="px-4 py-2 whitespace-nowrap">
                                                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                                    {{ ep.method }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 font-mono text-sm text-gray-800 dark:text-gray-200">
                                                {{ ep.path }}
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">
                                                {{ ep.description }}
                                            </td>
                                            <td class="px-4 py-2">
                                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ ep.permission }}</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div v-for="(ep, i) in endpoints.filter(e => e.responseExample)" :key="'ex-' + i" class="mt-4">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Esempio risposta ({{ ep.method }} {{ ep.path }}):</p>
                                <pre class="p-3 rounded bg-gray-100 dark:bg-gray-900 text-xs text-gray-800 dark:text-gray-200 overflow-x-auto">{{ ep.responseExample }}</pre>
                            </div>

                            <div class="pt-2">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Esempio cURL:</p>
                                <pre class="p-3 rounded bg-gray-100 dark:bg-gray-900 text-xs text-gray-800 dark:text-gray-200 overflow-x-auto break-all">curl -H "Authorization: Bearer IL_TUO_TOKEN" "{{ apiBaseUrl }}/user"</pre>
                            </div>
                        </div>
                    </template>
                </ActionSection>

                <ApiTokenManager
                    :tokens="tokens"
                    :available-permissions="availablePermissions"
                    :default-permissions="defaultPermissions"
                />
            </div>
        </div>
    </AppLayout>
</template>
