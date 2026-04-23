<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { CheckIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import HelpTooltip from '@/Components/HelpTooltip.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({ entry: Object, rendicontoVoci: Array, macroAreas: Array, conti: Array });
const page = usePage();
const showConfirmAnnoPrecedenteModal = ref(false);

const selectedMacroCode = ref('');

const form = useForm({
    conto_id: props.entry?.conto_id ?? '',
    rendiconto_code: props.entry?.rendiconto_code ?? '',
    date: props.entry?.date ? (typeof props.entry.date === 'string' ? props.entry.date.slice(0, 10) : props.entry.date) : new Date().toISOString().slice(0, 10),
    amount: props.entry?.amount ?? '',
    description: props.entry?.description ?? '',
    gestione: props.entry?.gestione ?? 'istituzionale',
    competenza_cassa: props.entry?.competenza_cassa ?? true,
    confirm_anno_precedente: false,
});

watch(selectedMacroCode, (newMacroCode) => {
    const macro = props.macroAreas?.find((m) => m.code === newMacroCode);
    const voiceInMacro = macro?.children?.some((c) => c.code === form.rendiconto_code);
    if (!voiceInMacro) form.rendiconto_code = '';
});

onMounted(() => {
    if (form.rendiconto_code && props.macroAreas?.length) {
        const macro = props.macroAreas.find((m) => m.children?.some((c) => c.code === form.rendiconto_code));
        if (macro) selectedMacroCode.value = macro.code;
    }
});

const selectedMacro = computed(() =>
    props.macroAreas?.find((m) => m.code === selectedMacroCode.value) ?? null,
);

const childrenOfSelectedMacro = computed(() => selectedMacro.value?.children ?? []);

const selectedVoiceInfo = computed(() => {
    if (!form.rendiconto_code || !props.rendicontoVoci) return null;
    return props.rendicontoVoci.find((v) => v.code === form.rendiconto_code) ?? null;
});

const selectedVoiceType = computed(() => selectedVoiceInfo.value?.tipo ?? null);

const amountLabel = computed(() => {
    const t = selectedVoiceType.value;
    if (t === 'entrata') return 'Importo * (solo positivo – entrata)';
    if (t === 'uscita') return 'Importo * (solo negativo – uscita)';
    return 'Importo * (selezionare prima la voce)';
});

const isAmountSignValid = computed(() => {
    const amount = parseFloat(form.amount);
    if (Number.isNaN(amount)) return false;
    const t = selectedVoiceType.value;
    if (t === 'entrata') return amount >= 0;
    if (t === 'uscita') return amount <= 0;
    return false;
});

const canSubmit = computed(() => {
    return form.conto_id && form.rendiconto_code && form.date && form.amount !== '' && isAmountSignValid.value;
});

/** Messaggio visibile quando voce e importo sono compilati ma il segno non è coerente (spiega perché Salva è disabilitato). */
const amountSignHint = computed(() => {
    const amount = parseFloat(form.amount);
    if (form.amount === '' || Number.isNaN(amount)) return null;
    const t = selectedVoiceType.value;
    if (!t) return null;
    if (t === 'entrata' && amount < 0) {
        return 'Per una voce di entrata l\'importo deve essere positivo. Inserire un numero positivo.';
    }
    if (t === 'uscita' && amount > 0) {
        return 'Per una voce di uscita l\'importo deve essere negativo. Inserire un numero negativo.';
    }
    return null;
});

const submitError = ref('');

watch(() => page.props.flash, (flash) => {
    if (flash?.type === 'confirm_anno_precedente_required') {
        showConfirmAnnoPrecedenteModal.value = true;
    }
}, { immediate: true });

function handleSubmit(withConfirm = false) {
    const isConfirm = withConfirm === true;
    submitError.value = '';
    if (!isConfirm) form.confirm_anno_precedente = false;
    if (!canSubmit.value && !isConfirm) {
        if (selectedVoiceType.value === 'entrata' && parseFloat(form.amount) < 0) {
            submitError.value = 'Per una voce di entrata l\'importo deve essere positivo.';
        } else if (selectedVoiceType.value === 'uscita' && parseFloat(form.amount) > 0) {
            submitError.value = 'Per una voce di uscita l\'importo deve essere negativo.';
        } else {
            submitError.value = 'Compilare tutti i campi obbligatori e rispettare il segno dell\'importo.';
        }
        return;
    }
    if (isConfirm) form.confirm_anno_precedente = true;
    form.put(route('prima-nota.update', props.entry.id), { preserveScroll: true });
}

function confirmAnnoPrecedenteProceed() {
    showConfirmAnnoPrecedenteModal.value = false;
    handleSubmit(true);
}
</script>

<template>
    <AppLayout title="Modifica movimento">
        <Head title="Modifica movimento prima nota" />
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Modifica movimento</h2>
        </template>

        <div class="py-6 max-w-2xl mx-auto sm:px-6">
            <form @submit.prevent="() => handleSubmit()" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
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
                    <InputLabel for="macro_area" value="Sezione / Area *" />
                    <select
                        id="macro_area"
                        v-model="selectedMacroCode"
                        required
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm"
                    >
                        <option value="">Seleziona sezione</option>
                        <option v-for="m in macroAreas" :key="m.code" :value="m.code">
                            {{ m.area ? m.area + ' – ' + m.name : m.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <InputLabel for="rendiconto_code" value="Voce *" />
                    <select
                        id="rendiconto_code"
                        v-model="form.rendiconto_code"
                        required
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
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="date" value="Data *" />
                        <TextInput id="date" v-model="form.date" type="date" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.date" />
                    </div>
                    <div>
                        <InputLabel for="amount" :value="amountLabel" />
                        <TextInput id="amount" v-model="form.amount" type="number" step="0.01" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.amount" />
                        <p v-if="amountSignHint" class="mt-1 text-sm font-medium text-amber-600 dark:text-amber-400">
                            {{ amountSignHint }}
                        </p>
                        <p v-if="submitError" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ submitError }}</p>
                    </div>
                </div>
                <div>
                    <InputLabel for="description" value="Descrizione" />
                    <TextInput id="description" v-model="form.description" class="mt-1 block w-full" />
                </div>
                <div class="flex items-center gap-1">
                    <input id="competenza_cassa" v-model="form.competenza_cassa" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 shadow-sm" />
                    <label for="competenza_cassa" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Competenza cassa</label>
                    <HelpTooltip
                        text="Se attivo, il movimento entra nel rendiconto per cassa (Modello D). Disattivalo per ratei o accantonamenti esclusi dal rendiconto di cassa."
                        width-class="w-80"
                    />
                </div>
                <div class="flex gap-2">
                    <PrimaryButton type="submit" :disabled="form.processing || !canSubmit">
                        <CheckIcon class="size-4 me-2" aria-hidden="true" />Salva
                    </PrimaryButton>
                    <Link :href="route('prima-nota.index')" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"><ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla</Link>
                </div>
            </form>

            <Teleport to="body">
                <div v-if="showConfirmAnnoPrecedenteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
                        <p class="text-gray-700 dark:text-gray-300">{{ page.props.flash?.message || 'Operazioni su anni precedenti possono alterare i rendiconti già generati. Vuoi procedere?' }}</p>
                        <div class="mt-4 flex justify-end gap-2">
                            <SecondaryButton @click="showConfirmAnnoPrecedenteModal = false">Annulla</SecondaryButton>
                            <PrimaryButton @click="confirmAnnoPrecedenteProceed">Procedi</PrimaryButton>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>
    </AppLayout>
</template>
