<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    conti:       { type: Array, default: () => [] },
    categories:  { type: Array, default: () => [] },
    defaultDate: { type: String, default: '' },
});

const form = useForm({
    data:                  props.defaultDate,
    tipo:                  'entrata',
    importo:               '',
    descrizione:           '',
    category_id:           '',
    conto_id:              '',
    conto_destinazione_id: '',
    riferimento:           '',
    note:                  '',
});

// Categorie compatibili con il tipo selezionato
const categorieCompatibili = computed(() =>
    props.categories.filter(c => c.tipo === form.tipo || c.tipo === 'qualsiasi')
);

// Reset categoria se non compatibile dopo cambio tipo
const onTipoChange = () => {
    if (form.category_id) {
        const cat = props.categories.find(c => c.id == form.category_id);
        if (cat && cat.tipo !== 'qualsiasi' && cat.tipo !== form.tipo) {
            form.category_id = '';
        }
    }
};

const submit = () => form.post(route('movimenti-amministrativi.store'));
</script>

<template>
    <AppLayout title="Nuovo Movimento">
        <Head title="Nuovo Movimento Amministrativo" />
        <template #header>
            <div>
                <div class="mb-1">
                    <Link :href="route('movimenti-amministrativi.index')"
                        class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                        ← Movimenti Amministrativi
                    </Link>
                </div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Nuovo Movimento
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-5">

                    <!-- Tipo -->
                    <div>
                        <InputLabel value="Tipo movimento *" />
                        <div class="mt-1.5 flex gap-2">
                            <button v-for="opt in [
                                { value: 'entrata',   label: '↑ Entrata',   cls: 'green' },
                                { value: 'uscita',    label: '↓ Uscita',    cls: 'red' },
                                { value: 'giroconto', label: '⇄ Giroconto', cls: 'blue' },
                            ]" :key="opt.value"
                                type="button"
                                @click="form.tipo = opt.value; onTipoChange()"
                                :class="[
                                    'flex-1 py-2 px-3 rounded-lg text-sm font-medium border-2 transition',
                                    form.tipo === opt.value
                                        ? opt.cls === 'green' ? 'border-green-500 bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400 dark:border-green-600'
                                          : opt.cls === 'red' ? 'border-red-500 bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400 dark:border-red-600'
                                          : 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-600'
                                        : 'border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:border-gray-300 dark:hover:border-gray-500'
                                ]">
                                {{ opt.label }}
                            </button>
                        </div>
                        <InputError :message="form.errors.tipo" class="mt-1" />
                    </div>

                    <!-- Data + Importo -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="data" value="Data *" />
                            <TextInput id="data" v-model="form.data" type="date" class="mt-1 block w-full" required />
                            <InputError :message="form.errors.data" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="importo" value="Importo (€) *" />
                            <TextInput id="importo" v-model="form.importo" type="number" step="0.01" min="0.01"
                                class="mt-1 block w-full" placeholder="0,00" required />
                            <InputError :message="form.errors.importo" class="mt-1" />
                        </div>
                    </div>

                    <!-- Descrizione -->
                    <div>
                        <InputLabel for="descrizione" value="Descrizione *" />
                        <TextInput id="descrizione" v-model="form.descrizione" type="text"
                            class="mt-1 block w-full" placeholder="Es. Versamento quota associativa 2024" required />
                        <InputError :message="form.errors.descrizione" class="mt-1" />
                    </div>

                    <!-- Conto (sorgente) -->
                    <div v-if="form.tipo !== 'giroconto'">
                        <InputLabel for="conto_id" value="Conto" />
                        <select id="conto_id" v-model="form.conto_id"
                            class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200 text-sm px-3 py-2">
                            <option value="">— Nessun conto —</option>
                            <option v-for="c in conti" :key="c.id" :value="c.id">
                                {{ c.name }} ({{ c.type }})
                            </option>
                        </select>
                        <InputError :message="form.errors.conto_id" class="mt-1" />
                    </div>

                    <!-- Giroconto: conto da → conto a -->
                    <div v-if="form.tipo === 'giroconto'" class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="conto_id" value="Da conto *" />
                            <select id="conto_id" v-model="form.conto_id"
                                class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200 text-sm px-3 py-2">
                                <option value="">Seleziona...</option>
                                <option v-for="c in conti" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <InputError :message="form.errors.conto_id" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="conto_destinazione_id" value="A conto *" />
                            <select id="conto_destinazione_id" v-model="form.conto_destinazione_id"
                                class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200 text-sm px-3 py-2">
                                <option value="">Seleziona...</option>
                                <option v-for="c in conti.filter(c => c.id != form.conto_id)" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <InputError :message="form.errors.conto_destinazione_id" class="mt-1" />
                        </div>
                    </div>

                    <!-- Categoria -->
                    <div v-if="form.tipo !== 'giroconto'">
                        <InputLabel for="category_id" value="Categoria" />
                        <select id="category_id" v-model="form.category_id"
                            class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200 text-sm px-3 py-2">
                            <option value="">— Nessuna categoria —</option>
                            <option v-for="cat in categorieCompatibili" :key="cat.id" :value="cat.id">
                                {{ cat.icona }} {{ cat.nome }}
                            </option>
                        </select>
                        <InputError :message="form.errors.category_id" class="mt-1" />
                    </div>

                    <!-- Riferimento -->
                    <div>
                        <InputLabel for="riferimento" value="Riferimento (n. fattura, ricevuta, ecc.)" />
                        <TextInput id="riferimento" v-model="form.riferimento" type="text"
                            class="mt-1 block w-full" placeholder="Es. FT-2024-001" />
                        <InputError :message="form.errors.riferimento" class="mt-1" />
                    </div>

                    <!-- Note -->
                    <div>
                        <InputLabel for="note" value="Note" />
                        <textarea id="note" v-model="form.note" rows="2"
                            class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200 text-sm px-3 py-2"
                            placeholder="Note aggiuntive..." />
                        <InputError :message="form.errors.note" class="mt-1" />
                    </div>

                    <!-- Azioni -->
                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <Link :href="route('movimenti-amministrativi.index')"
                            class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                            Annulla
                        </Link>
                        <PrimaryButton type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Salvataggio...' : 'Registra movimento' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
