<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { FolderIcon, PlusIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    categorie: Array,
    conti:     Array,
});

// ── Modal crea / modifica ──
const showModal    = ref(false);
const editingCat   = ref(null);

const form = useForm({
    codice:                           '',
    descrizione:                      '',
    coefficiente_ministeriale:        '',
    percentuale_deducibilita_default: 100,
    primo_anno_ridotto_default:       true,
    conto_bene_default_id:            null,
    conto_fondo_default_id:           null,
    conto_ammortamento_default_id:    null,
    attivo:                           true,
});

function openCreate() {
    editingCat.value = null;
    form.reset();
    form.primo_anno_ridotto_default = true;
    form.percentuale_deducibilita_default = 100;
    form.attivo = true;
    showModal.value = true;
}

function openEdit(cat) {
    editingCat.value = cat;
    form.codice                           = cat.codice;
    form.descrizione                      = cat.descrizione;
    form.coefficiente_ministeriale        = cat.coefficiente_ministeriale;
    form.percentuale_deducibilita_default = cat.percentuale_deducibilita_default;
    form.primo_anno_ridotto_default       = cat.primo_anno_ridotto_default;
    form.conto_bene_default_id            = cat.conto_bene_default_id ?? null;
    form.conto_fondo_default_id           = cat.conto_fondo_default_id ?? null;
    form.conto_ammortamento_default_id    = cat.conto_ammortamento_default_id ?? null;
    form.attivo                           = cat.attivo;
    showModal.value = true;
}

function submit() {
    if (editingCat.value) {
        form.put(route('cespiti.categorie.update', editingCat.value.id), {
            onSuccess: () => { showModal.value = false; },
        });
    } else {
        form.post(route('cespiti.categorie.store'), {
            onSuccess: () => { showModal.value = false; },
        });
    }
}
</script>

<template>
    <AppLayout title="Categorie Cespiti">
        <Head title="Categorie Cespiti" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <FolderIcon class="size-6 text-gray-500" />
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Categorie Fiscali Cespiti</h2>
                </div>
                <button @click="openCreate"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                    <PlusIcon class="size-4" />
                    Nuova categoria
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Avviso categorie di sistema -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg px-4 py-3 text-sm text-blue-700 dark:text-blue-300">
                    Le categorie contrassegnate come <strong>Sistema</strong> sono condivise tra tutti i tenant e non possono essere modificate.
                    Puoi creare categorie personalizzate per aggiungere coefficienti specifici.
                </div>

                <!-- Tabella categorie -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Codice</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Descrizione</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Coeff. %</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Deducibilità %</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Primo anno 50%</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Cespiti</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Tipo</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Stato</th>
                                <th class="relative px-4 py-3"><span class="sr-only">Azioni</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="categorie.length === 0">
                                <td colspan="9" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500 text-sm">
                                    Nessuna categoria. Esegui il seeder <code>AssetCategoriesSeeder</code> per caricare le categorie ministeriali.
                                </td>
                            </tr>
                            <tr v-for="cat in categorie" :key="cat.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition"
                                :class="!cat.attivo ? 'opacity-50' : ''">
                                <td class="px-4 py-2.5 font-mono font-medium text-gray-900 dark:text-gray-100">{{ cat.codice }}</td>
                                <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ cat.descrizione }}</td>
                                <td class="px-4 py-2.5 text-right font-medium text-gray-900 dark:text-gray-100">{{ cat.coefficiente_ministeriale }}%</td>
                                <td class="px-4 py-2.5 text-right text-gray-600 dark:text-gray-400">{{ cat.percentuale_deducibilita_default }}%</td>
                                <td class="px-4 py-2.5 text-center">
                                    <span v-if="cat.primo_anno_ridotto_default" class="text-green-500">✓</span>
                                    <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                                </td>
                                <td class="px-4 py-2.5 text-center text-gray-500 dark:text-gray-400">{{ cat.assets_count ?? 0 }}</td>
                                <td class="px-4 py-2.5 text-center">
                                    <span v-if="cat.di_sistema"
                                          class="px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">
                                        Sistema
                                    </span>
                                    <span v-else
                                          class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                        Personalizzata
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <span v-if="cat.attivo"
                                          class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                        Attiva
                                    </span>
                                    <span v-else
                                          class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                        Disattiva
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <button v-if="!cat.di_sistema" @click="openEdit(cat)"
                                            class="inline-flex items-center gap-1 text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                        <PencilSquareIcon class="size-4" />
                                        Modifica
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

        <!-- Modal crea/modifica -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-2xl p-6 my-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-5">
                    {{ editingCat ? 'Modifica categoria' : 'Nuova categoria' }}
                </h3>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Codice *</label>
                            <input v-model="form.codice" type="text" required maxlength="20"
                                   :disabled="!!editingCat"
                                   class="w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none disabled:opacity-60"
                                   :class="form.errors.codice ? 'border-red-500' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700'" />
                            <p v-if="form.errors.codice" class="mt-1 text-xs text-red-500">{{ form.errors.codice }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Coefficiente ministeriale % *</label>
                            <div class="relative">
                                <input v-model="form.coefficiente_ministeriale" type="number" step="0.01" min="0" max="100" required
                                       class="w-full pr-8 pl-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descrizione *</label>
                            <input v-model="form.descrizione" type="text" required
                                   class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deducibilità default %</label>
                            <div class="relative">
                                <input v-model="form.percentuale_deducibilita_default" type="number" step="0.01" min="0" max="100"
                                       class="w-full pr-8 pl-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 mt-4">
                            <input id="primo_anno" v-model="form.primo_anno_ridotto_default" type="checkbox"
                                   class="size-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                            <label for="primo_anno" class="text-sm text-gray-700 dark:text-gray-300">Primo anno ridotto 50%</label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Conto bene default</label>
                            <select v-model="form.conto_bene_default_id"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                <option :value="null">— nessuno —</option>
                                <option v-for="c in conti" :key="c.id" :value="c.id">{{ c.codice }} — {{ c.nome }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Conto fondo default</label>
                            <select v-model="form.conto_fondo_default_id"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                <option :value="null">— nessuno —</option>
                                <option v-for="c in conti" :key="c.id" :value="c.id">{{ c.codice }} — {{ c.nome }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Conto ammortamento default</label>
                            <select v-model="form.conto_ammortamento_default_id"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                <option :value="null">— nessuno —</option>
                                <option v-for="c in conti" :key="c.id" :value="c.id">{{ c.codice }} — {{ c.nome }}</option>
                            </select>
                        </div>
                        <div v-if="editingCat" class="flex items-center gap-3 mt-4">
                            <input id="cat_attivo" v-model="form.attivo" type="checkbox"
                                   class="size-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                            <label for="cat_attivo" class="text-sm text-gray-700 dark:text-gray-300">Attiva</label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2 border-t border-gray-200 dark:border-gray-700 mt-4">
                        <button type="button" @click="showModal = false"
                                class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            Annulla
                        </button>
                        <button type="submit" :disabled="form.processing"
                                class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition">
                            {{ form.processing ? 'Salvataggio…' : (editingCat ? 'Aggiorna' : 'Crea categoria') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
