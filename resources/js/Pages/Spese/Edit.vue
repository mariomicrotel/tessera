<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { CheckIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    spesa: Object,
    conti: Array,
    rendicontoVociUscita: Array,
    macroAreasUscita: Array,
});

const form = useForm({
    date: props.spesa.date ? props.spesa.date.slice(0, 10) : '',
    amount: String(Number(props.spesa.amount).toFixed(2)),
    description: props.spesa.description ?? '',
    conto_id: props.spesa.conto_id ?? '',
    rendiconto_code: props.spesa.rendiconto_code ?? '',
    gestione: props.spesa.gestione ?? 'istituzionale',
    competenza_cassa: props.spesa.competenza_cassa ?? true,
});

// Doppio select macro area → voce (identico a Create.vue)
const selectedMacroCode = ref('');

watch(selectedMacroCode, (newMacroCode) => {
    const macro = props.macroAreasUscita?.find((m) => m.code === newMacroCode);
    const voiceInMacro = macro?.children?.some((c) => c.code === form.rendiconto_code);
    if (!voiceInMacro) form.rendiconto_code = '';
});

onMounted(() => {
    if (form.rendiconto_code && props.macroAreasUscita?.length) {
        const macro = props.macroAreasUscita.find((m) =>
            m.children?.some((c) => c.code === form.rendiconto_code),
        );
        if (macro) selectedMacroCode.value = macro.code;
    }
});

const selectedMacro = computed(() =>
    props.macroAreasUscita?.find((m) => m.code === selectedMacroCode.value) ?? null,
);
const childrenOfSelectedMacro = computed(() => selectedMacro.value?.children ?? []);

const canSubmit = computed(() => {
    if (!form.date || !form.amount || parseFloat(form.amount) < 0.01 || !form.conto_id) return false;
    if (props.spesa.genera_prima_nota && !form.rendiconto_code) return false;
    return true;
});

const hasPrimaNota = props.spesa.genera_prima_nota;
</script>

<template>
    <AppLayout title="Modifica spesa">
        <Head title="Modifica spesa" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Modifica spesa #{{ spesa.id }}
                </h2>
                <Link
                    :href="route('spese.show', spesa.id)"
                    class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"
                >
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro
                </Link>
            </div>
        </template>

        <div class="py-6 max-w-2xl mx-auto sm:px-6">
            <!-- Info prima nota -->
            <div v-if="hasPrimaNota" class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg text-sm text-blue-700 dark:text-blue-400">
                Questa spesa ha un movimento in prima nota collegato che verrà aggiornato automaticamente.
            </div>

            <form
                @submit.prevent="form.put(route('spese.update', spesa.id))"
                class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6"
            >
                <!-- Data e Importo -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="date" value="Data *" />
                        <TextInput
                            id="date"
                            v-model="form.date"
                            type="date"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.date" />
                    </div>
                    <div>
                        <InputLabel for="amount" value="Importo (€) *" />
                        <TextInput
                            id="amount"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.amount" />
                    </div>
                </div>

                <!-- Conto -->
                <div>
                    <InputLabel for="conto_id" value="Conto *" />
                    <select
                        id="conto_id"
                        v-model="form.conto_id"
                        required
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm"
                    >
                        <option value="">Seleziona conto</option>
                        <option v-for="c in conti" :key="c.id" :value="c.id">
                            {{ c.name }}{{ c.code ? ' (' + c.code + ')' : '' }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.conto_id" />
                </div>

                <!-- Descrizione -->
                <div>
                    <InputLabel for="description" value="Descrizione" />
                    <TextInput id="description" v-model="form.description" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.description" />
                </div>

                <!-- Prima nota: read-only flag + campi modificabili -->
                <div>
                    <InputLabel value="Prima nota" />
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        {{ hasPrimaNota ? 'Sì — movimento collegato' : 'No' }}
                    </p>
                </div>

                <template v-if="hasPrimaNota">
                    <!-- Macro area -->
                    <div>
                        <InputLabel for="macro_area" value="Sezione / Area *" />
                        <select
                            id="macro_area"
                            v-model="selectedMacroCode"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm"
                        >
                            <option value="">Seleziona sezione</option>
                            <option v-for="m in macroAreasUscita" :key="m.code" :value="m.code">
                                {{ m.area ? m.area + ' – ' + m.name : m.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Voce rendiconto -->
                    <div>
                        <InputLabel for="rendiconto_code" value="Voce di rendiconto *" />
                        <select
                            id="rendiconto_code"
                            v-model="form.rendiconto_code"
                            :required="hasPrimaNota"
                            :disabled="!selectedMacroCode"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm disabled:opacity-50"
                        >
                            <option value="">Seleziona voce</option>
                            <option v-for="c in childrenOfSelectedMacro" :key="c.code" :value="c.code">
                                {{ c.ministerial_code }} – {{ c.name }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.rendiconto_code" />
                    </div>

                    <!-- Gestione -->
                    <div>
                        <InputLabel for="gestione" value="Gestione" />
                        <select
                            id="gestione"
                            v-model="form.gestione"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm"
                        >
                            <option value="istituzionale">Istituzionale</option>
                            <option value="commerciale">Commerciale</option>
                        </select>
                    </div>

                    <!-- Competenza cassa -->
                    <div class="flex items-center">
                        <input
                            id="competenza_cassa"
                            v-model="form.competenza_cassa"
                            type="checkbox"
                            class="rounded border-gray-300 dark:border-gray-700 shadow-sm"
                        />
                        <label for="competenza_cassa" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                            Competenza cassa
                        </label>
                    </div>
                </template>

                <!-- Azioni -->
                <div class="flex gap-2 pt-2">
                    <PrimaryButton type="submit" :disabled="form.processing || !canSubmit">
                        <CheckIcon class="size-4 me-2" aria-hidden="true" />Salva modifiche
                    </PrimaryButton>
                    <Link
                        :href="route('spese.show', spesa.id)"
                        class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        <ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
