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
    conti: Array,
    rendicontoVociUscita: Array,
    macroAreasUscita: Array,
    oldInput: Object,
});

const o = props.oldInput || {};
const form = useForm({
    date: o.date ?? new Date().toISOString().slice(0, 10),
    amount: o.amount ?? '',
    description: o.description ?? '',
    conto_id: o.conto_id ?? (props.conti?.length ? props.conti[0].id : ''),
    genera_prima_nota: o.genera_prima_nota !== undefined ? !!o.genera_prima_nota : true,
    rendiconto_code: o.rendiconto_code ?? '',
    gestione: o.gestione ?? 'istituzionale',
    competenza_cassa: o.competenza_cassa !== undefined ? (o.competenza_cassa !== false && o.competenza_cassa !== '0') : true,
});

const selectedMacroCode = ref('');

watch(selectedMacroCode, (newMacroCode) => {
    const macro = props.macroAreasUscita?.find((m) => m.code === newMacroCode);
    const voiceInMacro = macro?.children?.some((c) => c.code === form.rendiconto_code);
    if (!voiceInMacro) form.rendiconto_code = '';
});

onMounted(() => {
    if (form.rendiconto_code && props.macroAreasUscita?.length) {
        const macro = props.macroAreasUscita.find((m) => m.children?.some((c) => c.code === form.rendiconto_code));
        if (macro) selectedMacroCode.value = macro.code;
    }
});

const selectedMacro = computed(() =>
    props.macroAreasUscita?.find((m) => m.code === selectedMacroCode.value) ?? null,
);

const childrenOfSelectedMacro = computed(() => selectedMacro.value?.children ?? []);

const canSubmit = computed(() => {
    if (!form.date || !form.amount || parseFloat(form.amount) < 0.01 || !form.conto_id) return false;
    if (form.genera_prima_nota && !form.rendiconto_code) return false;
    return true;
});
</script>

<template>
    <AppLayout title="Nuova spesa">
        <Head title="Nuova spesa" />
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Nuova spesa</h2>
        </template>

        <div class="py-6 max-w-2xl mx-auto sm:px-6">
            <form @submit.prevent="form.post(route('spese.store'))" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="date" value="Data *" />
                        <TextInput id="date" v-model="form.date" type="date" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.date" />
                    </div>
                    <div>
                        <InputLabel for="amount" value="Importo (€) *" />
                        <TextInput id="amount" v-model="form.amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.amount" />
                    </div>
                </div>
                <div>
                    <InputLabel for="conto_id" value="Conto *" />
                    <select
                        id="conto_id"
                        v-model="form.conto_id"
                        required
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm"
                    >
                        <option value="">Seleziona conto</option>
                        <option v-for="c in conti" :key="c.id" :value="c.id">{{ c.name }}{{ c.code ? ' (' + c.code + ')' : '' }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.conto_id" />
                </div>
                <div>
                    <InputLabel for="description" value="Descrizione" />
                    <TextInput id="description" v-model="form.description" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.description" />
                </div>
                <div class="flex items-center">
                    <input id="genera_prima_nota" v-model="form.genera_prima_nota" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 shadow-sm" />
                    <label for="genera_prima_nota" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Registra movimento in prima nota</label>
                </div>
                <template v-if="form.genera_prima_nota">
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
                    <div>
                        <InputLabel for="rendiconto_code" value="Voce di rendiconto *" />
                        <select
                            id="rendiconto_code"
                            v-model="form.rendiconto_code"
                            :required="form.genera_prima_nota"
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
                </template>
                <div class="flex gap-2">
                    <PrimaryButton type="submit" :disabled="form.processing || !canSubmit">
                        <CheckIcon class="size-4 me-2" aria-hidden="true" />Registra spesa
                    </PrimaryButton>
                    <Link :href="route('spese.index')" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"><ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
