<script setup>
import {
    PlusIcon,
    FunnelIcon,
    EyeIcon,
    PencilSquareIcon,
    TrashIcon,
    ArrowLeftIcon,
    ArrowRightIcon,
    CheckCircleIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    suppliers: Object,
    filters: Object,
    categorie: Object,
});

const form = reactive({
    search:      props.filters?.search      ?? '',
    categoria:   props.filters?.categoria   ?? '',
    solo_attivi: props.filters?.solo_attivi ?? true,
});

const cerca = () => router.get(route('suppliers.index'), form, { preserveScroll: false });

function destroy(supplier) {
    if (!confirm(`Eliminare il fornitore "${supplier.nome_completo ?? supplier.name}"?`)) return;
    router.delete(route('suppliers.destroy', supplier.id));
}
</script>

<template>
    <AppLayout title="Fornitori">
        <Head title="Fornitori" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-2">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Fornitori</h2>
                <Link :href="route('suppliers.create')">
                    <PrimaryButton>
                        <PlusIcon class="size-4 me-2" aria-hidden="true" />Nuovo fornitore
                    </PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- Filtri -->
                <form @submit.prevent="cerca" class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-48">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cerca</label>
                        <input
                            v-model="form.search"
                            type="text"
                            placeholder="Nome, P.IVA, città…"
                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Categoria</label>
                        <select v-model="form.categoria" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                            <option value="">Tutte</option>
                            <option v-for="(label, key) in categorie" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2 pb-0.5">
                        <input id="solo_attivi" v-model="form.solo_attivi" type="checkbox" class="rounded border-gray-300 dark:border-gray-700" />
                        <label for="solo_attivi" class="text-sm text-gray-700 dark:text-gray-300">Solo attivi</label>
                    </div>
                    <PrimaryButton type="submit">
                        <FunnelIcon class="size-4 me-2" aria-hidden="true" />Filtra
                    </PrimaryButton>
                </form>

                <!-- Tabella -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fornitore</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">P.IVA</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Città</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Categoria</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Attivo</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fatture</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="s in suppliers.data"
                                :key="s.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                :class="{ 'opacity-60': !s.attivo }"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ s.ragione_sociale || s.name }}
                                    </div>
                                    <div v-if="s.ragione_sociale && s.name !== s.ragione_sociale" class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ s.name }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono text-gray-600 dark:text-gray-400">
                                    {{ s.partita_iva || '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                    <template v-if="s.citta">{{ s.citta }}<span v-if="s.provincia"> ({{ s.provincia }})</span></template>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        {{ categorie[s.categoria] ?? s.categoria }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <CheckCircleIcon v-if="s.attivo" class="size-5 text-green-500 mx-auto" aria-label="Attivo" />
                                    <XCircleIcon v-else class="size-5 text-gray-400 mx-auto" aria-label="Inattivo" />
                                </td>
                                <td class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">
                                    {{ s.fatture_passive_count ?? 0 }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <Link
                                            :href="route('suppliers.show', s.id)"
                                            class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline"
                                        >
                                            <EyeIcon class="size-4" aria-hidden="true" />
                                        </Link>
                                        <Link
                                            :href="route('suppliers.edit', s.id)"
                                            class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400 hover:underline"
                                        >
                                            <PencilSquareIcon class="size-4" aria-hidden="true" />
                                        </Link>
                                        <button
                                            type="button"
                                            @click="destroy(s)"
                                            class="inline-flex items-center gap-1 text-red-600 dark:text-red-400 hover:underline"
                                        >
                                            <TrashIcon class="size-4" aria-hidden="true" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="!suppliers.data?.length" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                        Nessun fornitore trovato.
                    </p>

                    <!-- Paginazione -->
                    <div v-if="suppliers.prev_page_url || suppliers.next_page_url" class="px-4 py-3 border-t dark:border-gray-700 flex justify-between items-center text-sm">
                        <Link
                            v-if="suppliers.prev_page_url"
                            :href="suppliers.prev_page_url"
                            class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            <ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro
                        </Link>
                        <span v-else />
                        <span class="text-gray-500 dark:text-gray-400">
                            Pagina {{ suppliers.current_page }} di {{ suppliers.last_page }}
                        </span>
                        <Link
                            v-if="suppliers.next_page_url"
                            :href="suppliers.next_page_url"
                            class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            Avanti<ArrowRightIcon class="size-4" aria-hidden="true" />
                        </Link>
                        <span v-else />
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
