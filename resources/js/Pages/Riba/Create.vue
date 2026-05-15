<script setup>
import { useForm } from '@inertiajs/vue3';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    fatture:    Array,
    codiceSia:  String,
});

const fmt = (v) => Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2 });

const form = useForm({
    fattura_attiva_id:  null,
    numero_riba:        '',
    nome_debitore:      '',
    cf_piva_debitore:   '',
    iban_debitore:      '',
    importo:            '',
    data_scadenza:      '',
    data_emissione:     new Date().toISOString().slice(0, 10),
    banca_presentatrice: '',
    note:               '',
});

const onFatturaSelect = () => {
    const f = props.fatture.find(x => x.id == form.fattura_attiva_id);
    if (!f) return;
    form.importo       = f.totale_documento;
    form.numero_riba   = f.numero_fattura;
};

const submit = () => form.post(route('riba.store'));
</script>

<template>
    <AppLayout title="Nuova RI.BA">
        <Head title="Nuova RI.BA" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Nuova RI.BA</h2>
                <Link :href="route('riba.index')">
                    <SecondaryButton>← Torna all'elenco</SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit"
                      class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-5">

                    <!-- Collega fattura -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Fattura attiva collegata (opzionale)
                        </label>
                        <select v-model="form.fattura_attiva_id" @change="onFatturaSelect"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                            <option :value="null">— nessuna —</option>
                            <option v-for="f in fatture" :key="f.id" :value="f.id">
                                {{ f.numero_fattura }} — {{ f.data_fattura }} — € {{ fmt(f.totale_documento) }}
                            </option>
                        </select>
                    </div>

                    <!-- Nome debitore -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Nome debitore <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.nome_debitore" type="text" required
                               class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                        <p v-if="form.errors.nome_debitore" class="mt-1 text-xs text-red-600">{{ form.errors.nome_debitore }}</p>
                    </div>

                    <!-- CF/P.IVA + IBAN -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CF / P.IVA</label>
                            <input v-model="form.cf_piva_debitore" type="text"
                                   class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IBAN debitore</label>
                            <input v-model="form.iban_debitore" type="text" placeholder="IT..."
                                   class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                        </div>
                    </div>

                    <!-- Importo + N° RI.BA -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Importo (€) <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.importo" type="number" step="0.01" min="0.01" required
                                   class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                            <p v-if="form.errors.importo" class="mt-1 text-xs text-red-600">{{ form.errors.importo }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Numero RI.BA</label>
                            <input v-model="form.numero_riba" type="text" maxlength="10"
                                   class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                        </div>
                    </div>

                    <!-- Date -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Data emissione <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.data_emissione" type="date" required
                                   class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Data scadenza <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.data_scadenza" type="date" required
                                   class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                            <p v-if="form.errors.data_scadenza" class="mt-1 text-xs text-red-600">{{ form.errors.data_scadenza }}</p>
                        </div>
                    </div>

                    <!-- Banca presentatrice -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Banca presentatrice</label>
                        <input v-model="form.banca_presentatrice" type="text"
                               class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                    </div>

                    <!-- Note -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Note (max 255)</label>
                        <textarea v-model="form.note" rows="2" maxlength="255"
                                  class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm"></textarea>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('riba.index')">
                            <SecondaryButton type="button">Annulla</SecondaryButton>
                        </Link>
                        <PrimaryButton type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Salvataggio…' : 'Crea RI.BA' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
