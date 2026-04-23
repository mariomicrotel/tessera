<script setup>
import { ref, watch, computed } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { TableCellsIcon, ChevronLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    categorie: Array,
    suppliers: Array,
    conti:     Array,
    metodi:    Object,
});

const form = useForm({
    name:                     '',
    code:                     '',
    matricola:                '',
    asset_category_id:        null,
    supplier_id:              null,
    costo_storico:            '',
    data_inizio_ammortamento: '',
    purchase_date:            '',
    aliquota_custom:          '',
    metodo_ammortamento:      'ordinario',
    primo_anno_ridotto:       true,
    percentuale_deducibilita: 100,
    conto_bene_id:            null,
    conto_fondo_id:           null,
    notes:                    '',
    note_fiscali:             '',
});

// Quando si seleziona una categoria, precompila i campi con i default
watch(() => form.asset_category_id, (id) => {
    if (!id) return;
    const cat = props.categorie.find(c => c.id === Number(id));
    if (!cat) return;
    if (!form.aliquota_custom)          form.aliquota_custom          = '';
    if (form.percentuale_deducibilita === 100) form.percentuale_deducibilita = cat.percentuale_deducibilita_default ?? 100;
    if (form.primo_anno_ridotto === true)      form.primo_anno_ridotto       = cat.primo_anno_ridotto_default ?? true;
    if (!form.conto_bene_id)  form.conto_bene_id  = cat.conto_bene_default_id  ?? null;
    if (!form.conto_fondo_id) form.conto_fondo_id = cat.conto_fondo_default_id ?? null;
});

// Aliquota effettiva mostrata nel form
const aliquotaEffettiva = computed(() => {
    if (form.aliquota_custom) return Number(form.aliquota_custom);
    const cat = props.categorie.find(c => c.id === Number(form.asset_category_id));
    return cat?.coefficiente_ministeriale ?? null;
});

function submit() {
    form.post(route('cespiti.store'));
}
</script>

<template>
    <AppLayout title="Nuovo Cespite">
        <Head title="Nuovo Cespite" />

        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('cespiti.index')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <ChevronLeftIcon class="size-5" />
                </Link>
                <TableCellsIcon class="size-6 text-gray-500" />
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Nuovo Cespite</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">

                    <!-- Dati anagrafici -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Dati anagrafici</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descrizione *</label>
                                <input v-model="form.name" type="text" required
                                       class="w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                       :class="form.errors.name ? 'border-red-500' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700'" />
                                <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Codice</label>
                                <input v-model="form.code" type="text"
                                       class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Matricola / N° serie</label>
                                <input v-model="form.matricola" type="text"
                                       class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fornitore</label>
                                <select v-model="form.supplier_id"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                    <option :value="null">— nessuno —</option>
                                    <option v-for="s in suppliers" :key="s.id" :value="s.id">
                                        {{ s.ragione_sociale || s.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data acquisto</label>
                                <input v-model="form.purchase_date" type="date"
                                       class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Note</label>
                                <textarea v-model="form.notes" rows="2"
                                          class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                            </div>
                        </div>
                    </div>

                    <!-- Dati fiscali -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Dati fiscali e ammortamento</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Categoria fiscale</label>
                                <select v-model="form.asset_category_id"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                    <option :value="null">— seleziona —</option>
                                    <option v-for="cat in categorie" :key="cat.id" :value="cat.id">
                                        {{ cat.codice }} — {{ cat.descrizione }} ({{ cat.coefficiente_ministeriale }}%)
                                    </option>
                                </select>
                                <p v-if="form.errors.asset_category_id" class="mt-1 text-xs text-red-500">{{ form.errors.asset_category_id }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Costo storico *</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">€</span>
                                    <input v-model="form.costo_storico" type="number" step="0.01" min="0.01" required
                                           class="w-full pl-7 pr-3 py-2 rounded-lg border text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                           :class="form.errors.costo_storico ? 'border-red-500' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700'" />
                                </div>
                                <p v-if="form.errors.costo_storico" class="mt-1 text-xs text-red-500">{{ form.errors.costo_storico }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data inizio ammortamento *</label>
                                <input v-model="form.data_inizio_ammortamento" type="date" required
                                       class="w-full rounded-lg border text-sm px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                       :class="form.errors.data_inizio_ammortamento ? 'border-red-500' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700'" />
                                <p v-if="form.errors.data_inizio_ammortamento" class="mt-1 text-xs text-red-500">{{ form.errors.data_inizio_ammortamento }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Metodo ammortamento *</label>
                                <select v-model="form.metodo_ammortamento"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                    <option v-for="(label, key) in metodi" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Aliquota custom
                                    <span v-if="aliquotaEffettiva" class="ml-1 text-xs text-gray-400">(categoria: {{ aliquotaEffettiva }}%)</span>
                                </label>
                                <div class="relative">
                                    <input v-model="form.aliquota_custom" type="number" step="0.01" min="0" max="100"
                                           placeholder="Lascia vuoto per usare categoria"
                                           class="w-full pr-8 pl-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deducibilità fiscale %</label>
                                <div class="relative">
                                    <input v-model="form.percentuale_deducibilita" type="number" step="0.01" min="0" max="100"
                                           class="w-full pr-8 pl-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 mt-1">
                                <input id="primo_anno_ridotto" v-model="form.primo_anno_ridotto" type="checkbox"
                                       class="size-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                <label for="primo_anno_ridotto" class="text-sm text-gray-700 dark:text-gray-300">
                                    Primo anno ridotto al 50% (DM 31/12/1988)
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Conti contabili -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">Conti contabili</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Se non impostati, verranno usati i default della categoria.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Conto bene (cespite)</label>
                                <select v-model="form.conto_bene_id"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                    <option :value="null">— default categoria —</option>
                                    <option v-for="c in conti" :key="c.id" :value="c.id">{{ c.codice }} — {{ c.nome }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Conto fondo ammortamento</label>
                                <select v-model="form.conto_fondo_id"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                    <option :value="null">— default categoria —</option>
                                    <option v-for="c in conti" :key="c.id" :value="c.id">{{ c.codice }} — {{ c.nome }}</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Note fiscali</label>
                                <textarea v-model="form.note_fiscali" rows="2"
                                          class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                            </div>
                        </div>
                    </div>

                    <!-- Azioni -->
                    <div class="flex justify-end gap-3">
                        <Link :href="route('cespiti.index')"
                              class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            Annulla
                        </Link>
                        <button type="submit" :disabled="form.processing"
                                class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition">
                            {{ form.processing ? 'Salvataggio…' : 'Crea cespite' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
