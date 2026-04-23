<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { PlusIcon, TrashIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    members:      Array,
    verbali:      Array,
    annoDefault:  Number,
});

const form = useForm({
    anno:                      props.annoDefault ?? new Date().getFullYear() - 1,
    importo_totale_deliberato: 0,
    aliquota_ritenuta:         0.30,
    data_delibera_assemblea:   new Date().toISOString().slice(0, 10),
    verbale_id:                null,
    note:                      '',
    entries: [
        { member_id: null, importo_lordo: 0 },
    ],
});

function addEntry() {
    form.entries.push({ member_id: null, importo_lordo: 0 });
}
function removeEntry(i) {
    if (form.entries.length > 1) form.entries.splice(i, 1);
}

// Distribuisci il totale in parti uguali fra le entries
function distribuisciEqualmente() {
    const n = form.entries.length;
    if (n === 0) return;
    const tot = Number(form.importo_totale_deliberato) || 0;
    const quota = Math.floor((tot / n) * 100) / 100;
    const resto = Number((tot - quota * n).toFixed(2));
    form.entries.forEach((e, i) => {
        e.importo_lordo = i === 0 ? Number((quota + resto).toFixed(2)) : quota;
    });
}

const sumLordo = computed(() =>
    form.entries.reduce((s, e) => s + (Number(e.importo_lordo) || 0), 0)
);
const differenza = computed(() =>
    Number((sumLordo.value - Number(form.importo_totale_deliberato)).toFixed(2))
);
const quadra = computed(() => Math.abs(differenza.value) < 0.01);

const fmt = (v) => new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(Number(v) || 0);

const memberLabel = (m) => m.ragione_sociale || `${m.cognome ?? ''} ${m.nome ?? ''}`.trim();

function submit() {
    form.post(route('ristorni.store'));
}
</script>

<template>
    <Head title="Nuovo ristorno" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Nuovo ristorno ai soci</h2>
                <Link :href="route('ristorni.index')" class="text-sm text-gray-600 dark:text-gray-300 hover:underline">
                    <ArrowLeftIcon class="w-4 h-4 inline" /> Torna all'elenco
                </Link>
            </div>
        </template>

        <form @submit.prevent="submit" class="p-6 space-y-6 max-w-5xl mx-auto">
            <!-- Dati delibera -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Delibera assembleare</h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Anno di riferimento</label>
                        <input v-model.number="form.anno" type="number" min="2000" max="2100" required
                               class="mt-1 w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                        <div v-if="form.errors.anno" class="text-xs text-red-600 mt-1">{{ form.errors.anno }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Data delibera</label>
                        <input v-model="form.data_delibera_assemblea" type="date" required
                               class="mt-1 w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                        <div v-if="form.errors.data_delibera_assemblea" class="text-xs text-red-600 mt-1">{{ form.errors.data_delibera_assemblea }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Verbale (opzionale)</label>
                        <select v-model="form.verbale_id"
                                class="mt-1 w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                            <option :value="null">— nessuno —</option>
                            <option v-for="v in verbali" :key="v.id" :value="v.id">
                                {{ new Date(v.data).toLocaleDateString('it-IT') }} — {{ v.titolo }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Importo totale deliberato (€)</label>
                        <input v-model.number="form.importo_totale_deliberato" type="number" step="0.01" min="0.01" required
                               class="mt-1 w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                        <div v-if="form.errors.importo_totale_deliberato" class="text-xs text-red-600 mt-1">{{ form.errors.importo_totale_deliberato }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Aliquota ritenuta fiscale</label>
                        <div class="flex items-center gap-2 mt-1">
                            <input v-model.number="form.aliquota_ritenuta" type="number" step="0.0001" min="0" max="1" required
                                   class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                            <span class="text-sm text-gray-500">= {{ (form.aliquota_ritenuta * 100).toFixed(2) }}%</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Default: 30% (art. 27 DPR 600/73)</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Note</label>
                    <textarea v-model="form.note" rows="2"
                              class="mt-1 w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"></textarea>
                </div>
            </div>

            <!-- Entries per socio -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100">Ripartizione per socio</h3>
                    <div class="flex gap-2">
                        <button type="button" @click="distribuisciEqualmente"
                                class="text-sm px-3 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/40 dark:text-blue-300">
                            Distribuisci equamente
                        </button>
                        <button type="button" @click="addEntry"
                                class="text-sm px-3 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/40 dark:text-green-300">
                            <PlusIcon class="w-4 h-4 inline" /> Aggiungi socio
                        </button>
                    </div>
                </div>

                <table class="min-w-full">
                    <thead>
                        <tr class="text-xs uppercase text-gray-500 dark:text-gray-400 border-b dark:border-gray-700">
                            <th class="py-2 text-left">Socio</th>
                            <th class="py-2 text-right">Lordo (€)</th>
                            <th class="py-2 text-right">Ritenuta</th>
                            <th class="py-2 text-right">Netto</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(e, i) in form.entries" :key="i" class="border-b dark:border-gray-700">
                            <td class="py-2 pr-2">
                                <select v-model="e.member_id" required
                                        class="w-full border rounded px-2 py-1 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                                    <option :value="null">— seleziona socio —</option>
                                    <option v-for="m in members" :key="m.id" :value="m.id">{{ memberLabel(m) }}</option>
                                </select>
                            </td>
                            <td class="py-2 px-2">
                                <input v-model.number="e.importo_lordo" type="number" step="0.01" min="0.01" required
                                       class="w-full border rounded px-2 py-1 text-right dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                            </td>
                            <td class="py-2 px-2 text-right text-gray-600 dark:text-gray-400">
                                {{ fmt((Number(e.importo_lordo) || 0) * Number(form.aliquota_ritenuta)) }}
                            </td>
                            <td class="py-2 px-2 text-right text-gray-900 dark:text-gray-100 font-medium">
                                {{ fmt((Number(e.importo_lordo) || 0) * (1 - Number(form.aliquota_ritenuta))) }}
                            </td>
                            <td class="py-2 text-right">
                                <button type="button" @click="removeEntry(i)" :disabled="form.entries.length === 1"
                                        class="text-red-600 hover:text-red-800 disabled:opacity-30">
                                    <TrashIcon class="w-5 h-5" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="font-semibold border-t-2 dark:border-gray-600">
                            <td class="py-2">Totale ripartito</td>
                            <td class="py-2 px-2 text-right">{{ fmt(sumLordo) }}</td>
                            <td colspan="3" class="py-2 px-2 text-right"
                                :class="quadra ? 'text-green-600' : 'text-red-600'">
                                <span v-if="quadra">✓ Quadra col totale deliberato</span>
                                <span v-else>Differenza: {{ fmt(differenza) }}</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <div v-if="form.errors.entries" class="text-sm text-red-600">{{ form.errors.entries }}</div>
            </div>

            <div class="flex justify-end gap-3">
                <Link :href="route('ristorni.index')"
                      class="px-4 py-2 rounded border text-gray-700 dark:text-gray-200 dark:border-gray-600">
                    Annulla
                </Link>
                <PrimaryButton type="submit" :disabled="form.processing || !quadra">
                    Delibera ristorno
                </PrimaryButton>
            </div>
        </form>
    </AppLayout>
</template>
