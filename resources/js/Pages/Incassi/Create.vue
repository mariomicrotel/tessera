<script setup>
import { CheckIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SearchableMemberSelect from '@/Components/SearchableMemberSelect.vue';
import TextInput from '@/Components/TextInput.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { receiptEditorPlaceholders } from '@/constants/receiptEditorPlaceholders';

const props = defineProps({
    members: Array,
    conti: Array,
    preselectedType: { type: String, default: 'quota' },
    preselectedMember: Object,
    preselectedSubscriptionId: Number,
    preselectedAmount: String,
    preselectedDescription: String,
    quota_annuale:  [Number, String],
    quota_mensile:  [Number, String],
    quota_ingresso: [Number, String],
    causale_default_quota: { type: String, default: 'Quota associativa' },
    causale_default_donazione: { type: String, default: 'Erogazione liberale' },
    rendicontoVociEntrata: { type: Array, default: () => [] },
    receiptTemplateTexts: { type: Object, default: () => ({}) },
});

const page = usePage();
const showConfirmAnnoPrecedenteModal = ref(false);

function normalizeTemplateToHtml(input) {
    const value = (input || '').trim();
    if (!value) return '';
    if (value.includes('<') && value.includes('>')) return value;

    return value
        .split(/\n{2,}/)
        .map((chunk) => `<p>${chunk.replace(/\n/g, '<br>')}</p>`)
        .join('');
}

const form = useForm({
    type: props.preselectedType,
    donor_mode: (props.preselectedType === 'donazione' || props.preselectedType === 'altro') ? (props.preselectedMember?.id ? 'member' : 'manual') : 'member',
    member_id: props.preselectedMember?.id ?? (props.preselectedType === 'donazione' || props.preselectedType === 'altro' ? '' : ''),
    donor_name: '',
    subscription_id: (props.preselectedType === 'donazione' || props.preselectedType === 'altro') ? '' : (props.preselectedSubscriptionId ?? ''),
    amount: props.preselectedAmount ?? (props.preselectedType === 'quota' && props.quota_annuale != null ? String(Number(props.quota_annuale).toFixed(2)) : ''),
    paid_at: new Date().toISOString().slice(0, 10),
    conto_id: props.conti?.length ? props.conti[0].id : '',
    description: props.preselectedType === 'altro' ? '' : (props.preselectedType === 'donazione' ? (props.causale_default_donazione ?? 'Erogazione liberale') : (props.preselectedDescription ?? props.causale_default_quota ?? '')),
    rendiconto_code: '',
    issue_receipt: true,
    genera_prima_nota: true,
    confirm_anno_precedente: false,
    receipt_text_override: normalizeTemplateToHtml(props.receiptTemplateTexts?.[props.preselectedType] ?? ''),
});

// Tipo quota selezionato (annuale / mensile / ingresso) — non inviato al server, serve solo per auto-fill importo
const quotaTipo = ref('annuale');

const quotaAmounts = computed(() => ({
    annuale:  props.quota_annuale  != null ? Number(props.quota_annuale).toFixed(2)  : '0.00',
    mensile:  props.quota_mensile  != null ? Number(props.quota_mensile).toFixed(2)  : '0.00',
    ingresso: props.quota_ingresso != null ? Number(props.quota_ingresso).toFixed(2) : '0.00',
}));

// Quando cambia il tipo quota, aggiorna l'importo (solo se non preimpostato manualmente)
watch(quotaTipo, (tipo) => {
    if (form.type === 'quota') {
        form.amount = quotaAmounts.value[tipo] ?? quotaAmounts.value.annuale;
    }
});

const subscriptionsForMember = computed(() => {
    if (!form.member_id) return [];
    const m = props.members.find(m => m.id == form.member_id);
    return m?.subscriptions ?? [];
});

/** Al cambio tipo incasso imposta la causale di default da settings (per quota/donazione); per altro lascia description. */
watch(() => form.type, (newType) => {
    if (newType === 'altro') {
        form.description = form.description || '';
    } else if (newType === 'capitale') {
        form.description = 'Versamento quota capitale sociale';
    } else if (newType === 'prestito_sociale') {
        form.description = 'Deposito in libretto prestito sociale';
    } else {
        form.description = newType === 'donazione'
            ? (props.causale_default_donazione ?? 'Erogazione liberale')
            : (props.causale_default_quota ?? 'Quota associativa');
    }
    form.receipt_text_override = normalizeTemplateToHtml(props.receiptTemplateTexts?.[newType] ?? '');
});

watch(() => page.props.flash, (flash) => {
    if (flash?.type === 'confirm_anno_precedente_required') showConfirmAnnoPrecedenteModal.value = true;
}, { immediate: true });

const pageTitle = computed(() => {
    return {
        donazione: 'Nuova donazione',
        altro: 'Incasso generico',
        capitale: 'Versamento quota capitale',
        prestito_sociale: 'Deposito prestito sociale',
    }[form.type] ?? 'Nuovo incasso';
});

const submitLabel = computed(() => {
    return {
        donazione: 'Registra donazione',
        altro: 'Registra incasso generico',
        capitale: 'Registra versamento',
        prestito_sociale: 'Registra deposito',
    }[form.type] ?? 'Registra incasso';
});

const cancelHref = computed(() => {
    if (props.preselectedType === 'altro' || form.type === 'altro') return route('incassi-generici.index');
    if (props.preselectedType === 'donazione') return route('donazioni.index');
    return route('quote-sociali.index');
});

function onSubmit(withConfirm = false) {
    const isConfirm = withConfirm === true;
    if (form.type === 'donazione' || form.type === 'altro') {
        if (form.donor_mode === 'manual') form.member_id = '';
        else form.donor_name = '';
    }
    form.confirm_anno_precedente = isConfirm;
    form.post(route('incassi.store'));
}

function confirmAnnoPrecedenteProceed() {
    showConfirmAnnoPrecedenteModal.value = false;
    onSubmit(true);
}
</script>

<template>
    <AppLayout :title="pageTitle">
        <Head :title="pageTitle" />
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ pageTitle }}</h2>
        </template>

        <div class="py-6 max-w-2xl mx-auto sm:px-6">
            <form @submit.prevent="() => onSubmit()" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div>
                    <InputLabel for="type" value="Tipo" />
                    <select id="type" v-model="form.type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                        <option value="quota">Quota</option>
                        <option value="donazione">Donazione</option>
                        <option value="altro">Altro</option>
                        <option v-if="page.props.is_cooperativa" value="capitale">Versamento quota capitale</option>
                        <option v-if="page.props.is_cooperativa" value="prestito_sociale">Deposito prestito sociale</option>
                    </select>
                </div>
                <!-- Selettore tipo quota (solo quando type = quota) -->
                <div v-if="form.type === 'quota'" class="rounded-md border border-gray-200 dark:border-gray-700 p-3 bg-gray-50 dark:bg-gray-700/30">
                    <InputLabel value="Tipo di quota" class="mb-2" />
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="opt in [
                                { value: 'annuale',  label: 'Annuale',    amount: quotaAmounts.annuale },
                                { value: 'mensile',  label: 'Mensile',    amount: quotaAmounts.mensile },
                                { value: 'ingresso', label: 'Ingresso',   amount: quotaAmounts.ingresso },
                            ]"
                            :key="opt.value"
                            class="flex items-center gap-2 px-3 py-1.5 rounded-md border cursor-pointer text-sm transition-colors"
                            :class="quotaTipo === opt.value
                                ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-medium'
                                : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-gray-400'"
                        >
                            <input type="radio" v-model="quotaTipo" :value="opt.value" class="sr-only" />
                            <span>{{ opt.label }}</span>
                            <span class="text-xs opacity-70">€&nbsp;{{ opt.amount }}</span>
                        </label>
                    </div>
                </div>

                <template v-if="form.type === 'quota'">
                    <div>
                        <InputLabel for="member_id" value="Socio *" />
                        <SearchableMemberSelect
                            id="member_id"
                            v-model="form.member_id"
                            :members="members"
                            placeholder="Seleziona socio"
                        />
                        <InputError class="mt-1" :message="form.errors.member_id" />
                    </div>
                </template>
                <template v-else-if="form.type === 'donazione'">
                    <div>
                        <InputLabel value="Donatore" />
                        <div class="mt-1 flex gap-4 items-center">
                            <label class="inline-flex items-center">
                                <input v-model="form.donor_mode" type="radio" value="member" class="rounded border-gray-300 dark:border-gray-700" />
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Da anagrafica</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input v-model="form.donor_mode" type="radio" value="manual" class="rounded border-gray-300 dark:border-gray-700" />
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Inserisci a mano</span>
                            </label>
                        </div>
                        <div v-if="form.donor_mode === 'member'" class="mt-2">
                            <SearchableMemberSelect
                                id="member_id"
                                v-model="form.member_id"
                                :members="members"
                                placeholder="Seleziona donatore"
                                empty-option="Anonimo"
                            />
                            <InputError class="mt-1" :message="form.errors.member_id" />
                        </div>
                        <div v-else class="mt-2">
                            <TextInput
                                id="donor_name"
                                v-model="form.donor_name"
                                type="text"
                                class="block w-full"
                                placeholder="Nome e cognome del donatore (opzionale)"
                            />
                            <InputError class="mt-1" :message="form.errors.donor_name" />
                        </div>
                    </div>
                </template>
                <template v-else-if="form.type === 'altro'">
                    <div>
                        <InputLabel for="rendiconto_code" value="Voce di rendiconto *" />
                        <select id="rendiconto_code" v-model="form.rendiconto_code" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm" required>
                            <option value="">Seleziona voce</option>
                            <option v-for="v in rendicontoVociEntrata" :key="v.code" :value="v.code">{{ v.label }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.rendiconto_code" />
                    </div>
                    <div>
                        <InputLabel for="description_altro" value="Contenuto ricevuta / Causale *" />
                        <textarea id="description_altro" v-model="form.description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm" placeholder="Testo che apparirà sulla ricevuta" required></textarea>
                        <InputError class="mt-1" :message="form.errors.description" />
                    </div>
                    <div>
                        <InputLabel value="Destinatario (opzionale)" />
                        <div class="mt-1 flex gap-4 items-center">
                            <label class="inline-flex items-center">
                                <input v-model="form.donor_mode" type="radio" value="member" class="rounded border-gray-300 dark:border-gray-700" />
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Da anagrafica</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input v-model="form.donor_mode" type="radio" value="manual" class="rounded border-gray-300 dark:border-gray-700" />
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Inserisci a mano</span>
                            </label>
                        </div>
                        <div v-if="form.donor_mode === 'member'" class="mt-2">
                            <SearchableMemberSelect
                                id="member_id_altro"
                                v-model="form.member_id"
                                :members="members"
                                placeholder="Seleziona socio/destinatario"
                                empty-option="Nessuno"
                            />
                            <InputError class="mt-1" :message="form.errors.member_id" />
                        </div>
                        <div v-else class="mt-2">
                            <TextInput
                                id="donor_name_altro"
                                v-model="form.donor_name"
                                type="text"
                                class="block w-full"
                                placeholder="Nome e cognome del destinatario (opzionale)"
                            />
                            <InputError class="mt-1" :message="form.errors.donor_name" />
                        </div>
                    </div>
                </template>
                <template v-else-if="form.type === 'capitale'">
                    <div>
                        <InputLabel for="member_id_capitale" value="Socio *" />
                        <SearchableMemberSelect
                            id="member_id_capitale"
                            v-model="form.member_id"
                            :members="members"
                            placeholder="Seleziona socio"
                        />
                        <InputError class="mt-1" :message="form.errors.member_id" />
                    </div>
                </template>
                <template v-else-if="form.type === 'prestito_sociale'">
                    <div>
                        <InputLabel for="member_id_prestito" value="Socio *" />
                        <SearchableMemberSelect
                            id="member_id_prestito"
                            v-model="form.member_id"
                            :members="members"
                            placeholder="Seleziona socio"
                        />
                        <InputError class="mt-1" :message="form.errors.member_id" />
                    </div>
                </template>
                <div v-if="form.type === 'quota' && subscriptionsForMember.length" class="subscription-block">
                    <InputLabel for="subscription_id" value="Iscrizione (opzionale)" />
                    <select id="subscription_id" v-model="form.subscription_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                        <option value="">Nessuna</option>
                        <option v-for="s in subscriptionsForMember" :key="s.id" :value="s.id">Anno {{ s.year }} (scad. {{ s.ends_at ? new Date(s.ends_at).toLocaleDateString('it-IT') : '' }})</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="amount" value="Importo (€) *" />
                        <TextInput id="amount" v-model="form.amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.amount" />
                    </div>
                    <div>
                        <InputLabel for="paid_at" value="Data *" />
                        <TextInput id="paid_at" v-model="form.paid_at" type="date" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.paid_at" />
                    </div>
                </div>
                <div>
                    <InputLabel for="conto_id" value="Conto di destinazione *" />
                    <select id="conto_id" v-model="form.conto_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm" required>
                        <option v-for="c in conti" :key="c.id" :value="c.id">{{ c.name }}{{ c.code ? ' (' + c.code + ')' : '' }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.conto_id" />
                </div>
                <div v-if="form.type !== 'altro'">
                    <InputLabel for="description" value="Causale / Descrizione" />
                    <TextInput id="description" v-model="form.description" class="mt-1 block w-full" :placeholder="form.type === 'donazione' ? (causale_default_donazione || 'Erogazione liberale') : (causale_default_quota || 'Quota associativa')" />
                </div>
                <div class="flex items-center">
                    <input id="genera_prima_nota" v-model="form.genera_prima_nota" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 shadow-sm" />
                    <label for="genera_prima_nota" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Genera movimento in prima nota</label>
                </div>
                <div class="flex items-center">
                    <input id="issue_receipt" v-model="form.issue_receipt" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 shadow-sm" />
                    <label for="issue_receipt" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Emetti ricevuta</label>
                </div>
                <div v-if="form.issue_receipt">
                    <InputLabel for="receipt_text_override" value="Testo ricevuta (modificabile)" />
                    <RichTextEditor
                        id="receipt_text_override"
                        v-model="form.receipt_text_override"
                        placeholder="Testo ricevuta personalizzato (HTML)"
                        min-height="220px"
                        :placeholder-items="receiptEditorPlaceholders"
                        enable-table
                    />
                    <InputError class="mt-1" :message="form.errors.receipt_text_override" />
                </div>
                <div class="flex gap-2">
                    <PrimaryButton type="submit" :disabled="form.processing"><CheckIcon class="size-4 me-2" aria-hidden="true" />{{ submitLabel }}</PrimaryButton>
                    <Link :href="cancelHref" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"><ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla</Link>
                </div>
            </form>
        </div>

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
    </AppLayout>
</template>
