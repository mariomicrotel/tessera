<script setup>
import {
    ArrowLeftIcon, PencilIcon, TrashIcon,
    DocumentTextIcon, EnvelopeIcon, ArrowPathIcon,
    ArrowDownTrayIcon, ChevronDownIcon, ChevronUpIcon,
    CheckCircleIcon, CodeBracketIcon, PencilSquareIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AttachmentsPanel from '@/Components/AttachmentsPanel.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { receiptEditorPlaceholders } from '@/constants/receiptEditorPlaceholders';

const props = defineProps({
    incasso:                 { type: Object, required: true },
    uploadMaxFileSizeHuman:  { type: String, default: '10 MB' },
    receiptTemplateText:     { type: String, default: null },
    memberEmail:             { type: String, default: null },
});

const page = usePage();

const backHref = () => {
    if (props.incasso.type === 'donazione') return route('donazioni.index');
    if (props.incasso.type === 'altro')     return route('incassi-generici.index');
    return route('quote-sociali.index');
};

const backLabel = () => {
    if (props.incasso.type === 'donazione') return 'Elenco erogazioni liberali';
    if (props.incasso.type === 'altro')     return 'Incassi generici';
    return 'Elenco quote sociali';
};

const receiptSent = computed(() => !!props.incasso.receipt?.sent_at);

const fmt         = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';
const fmtDatetime = (d) => d ? new Date(d).toLocaleString('it-IT') : '—';

/* ── Emissione ricevuta ─────────────────────────────────── */
const showIssueForm  = ref(false);
const bodyEditMode   = ref('editor');

const issueForm = useForm({
    receipt_text_override: props.receiptTemplateText ?? '',
});

/** Anteprima: sostituisce i placeholder con valori reali dall'incasso. */
const receiptPreviewHtml = computed(() => {
    let html = issueForm.receipt_text_override || '';
    const yr   = String(new Date().getFullYear());
    const paid = props.incasso.paid_at
        ? new Date(props.incasso.paid_at).toLocaleDateString('it-IT')
        : new Date().toLocaleDateString('it-IT');
    const memberName = props.incasso.member
        ? `${props.incasso.member.cognome} ${props.incasso.member.nome}`
        : (props.incasso.donor_name || 'Destinatario');
    const samples = {
        'nome-associazione': 'La mia Associazione ETS',
        'receipt_number':    yr + '/0001',
        'data':              paid,
        'amount':            props.incasso.amount
            ? Number(props.incasso.amount).toFixed(2).replace('.', ',')
            : '0,00',
        'causale':           props.incasso.description
            || (props.incasso.type === 'donazione' ? 'Erogazione liberale' : 'Quota associativa'),
        'recipient_name':    memberName,
        'recipient_cf':      '',
        'iban':              '',
        'anno':              yr,
        'presidente':        'Mario Bianchi',
        'sede':              'Via Roma 1, 20100 Milano',
        'sede-legale':       'Via Roma 1, 20100 Milano',
        'sede-operativa':    'Via Roma 1, 20100 Milano',
    };
    for (const [key, val] of Object.entries(samples)) {
        html = html.split('{{' + key + '}}').join(String(val));
    }
    return html;
});

function issueReceipt() {
    issueForm.post(route('incassi.issue-receipt', props.incasso.id), {
        preserveScroll: true,
        onSuccess: () => { showIssueForm.value = false; bodyEditMode.value = 'editor'; },
    });
}

/* ── Invio email ────────────────────────────────────────── */
const showEmailModal = ref(false);

const emailForm = useForm({
    email: props.memberEmail ?? '',
});

function sendEmail() {
    emailForm.post(route('incassi.send-receipt-email', props.incasso.id), {
        preserveScroll: true,
        onSuccess: () => { showEmailModal.value = false; },
    });
}

/* ── Rigenera PDF ───────────────────────────────────────── */
function regenerate() {
    if (!confirm('Rigenerare il PDF della ricevuta? Il contenuto verrà aggiornato dal template attuale.')) return;
    router.post(route('receipts.regenerate', props.incasso.receipt.id), {}, { preserveScroll: true });
}

/* ── Elimina incasso ────────────────────────────────────── */
const elimina = () => {
    if (confirm('Eliminare questo incasso? Verranno eliminati anche la prima nota e la ricevuta collegata. Questa operazione non è reversibile.')) {
        router.delete(route('incassi.destroy', props.incasso.id));
    }
};

/* ── Allegati ───────────────────────────────────────────── */
function removeAttachment(attachment) {
    if (!confirm('Rimuovere questo allegato?')) return;
    router.delete(route('incassi.attachments.destroy', [props.incasso.id, attachment.id]));
}

const attachmentError = computed(() => {
    const err = page.props.errors?.file;
    if (!err) return null;
    return Array.isArray(err) ? err[0] : err;
});

const canIssueReceipt = computed(() =>
    !props.incasso.receipt && (props.incasso.member_id || props.incasso.donor_name)
);
</script>

<template>
    <AppLayout title="Dettaglio incasso">
        <Head title="Dettaglio incasso" />

        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Incasso #{{ incasso.id }}
                </h2>
                <Link :href="backHref()"
                    class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300 text-sm">
                    <ArrowLeftIcon class="size-4" />
                    {{ backLabel() }}
                </Link>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto sm:px-6 space-y-4">

            <!-- Banner ricevuta già inviata -->
            <div v-if="receiptSent"
                class="flex items-center gap-2 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg text-sm text-amber-700 dark:text-amber-300">
                <CheckCircleIcon class="size-4 shrink-0" />
                La ricevuta è stata inviata per email il {{ fmtDatetime(incasso.receipt.sent_at) }}.
                La modifica di questo incasso non è consentita.
            </div>

            <!-- ── Dati incasso ── -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Tipo</dt>
                        <dd class="mt-0.5">
                            <span v-if="incasso.type === 'quota'"
                                class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                Quota
                            </span>
                            <span v-else-if="incasso.type === 'donazione'"
                                class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                Donazione
                            </span>
                            <span v-else
                                class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                Generico
                            </span>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ incasso.type === 'donazione' ? 'Donatore' : 'Socio' }}
                        </dt>
                        <dd class="mt-0.5">
                            <Link v-if="incasso.member"
                                :href="route('members.show', incasso.member.id)"
                                class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ incasso.member.cognome }} {{ incasso.member.nome }}
                            </Link>
                            <template v-else>
                                {{ incasso.donor_name || (incasso.type === 'donazione' ? 'Anonimo' : '—') }}
                            </template>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Importo</dt>
                        <dd class="mt-0.5 font-semibold text-gray-900 dark:text-gray-100">
                            € {{ Number(incasso.amount).toFixed(2) }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Data pagamento</dt>
                        <dd class="mt-0.5">{{ fmt(incasso.paid_at) }}</dd>
                    </div>

                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Conto di destinazione</dt>
                        <dd class="mt-0.5">{{ incasso.conto?.name ?? '—' }}</dd>
                    </div>

                    <div v-if="incasso.type === 'quota'">
                        <dt class="text-gray-500 dark:text-gray-400">Iscrizione</dt>
                        <dd class="mt-0.5">
                            {{ incasso.subscription
                                ? 'Anno ' + incasso.subscription.year
                                    + (incasso.subscription.ends_at
                                        ? ' (fino al ' + fmt(incasso.subscription.ends_at) + ')'
                                        : '')
                                : '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Prima nota</dt>
                        <dd class="mt-0.5">
                            <template v-if="incasso.prima_nota_entry">
                                Sì ({{ incasso.prima_nota_entry.rendiconto_label || incasso.prima_nota_entry.rendiconto_code }})
                            </template>
                            <template v-else>No</template>
                        </dd>
                    </div>

                    <div v-if="incasso.description" class="sm:col-span-2">
                        <dt class="text-gray-500 dark:text-gray-400">Causale</dt>
                        <dd class="mt-0.5">{{ incasso.description }}</dd>
                    </div>
                </dl>
            </div>

            <!-- ══════════════════════════════════════════════════════
                 SEZIONE RICEVUTA
                 ══════════════════════════════════════════════════════ -->

            <!-- Ricevuta già emessa -->
            <div v-if="incasso.receipt"
                class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
                    <DocumentTextIcon class="size-4 text-indigo-500" />
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Ricevuta emessa</h3>
                    <span v-if="receiptSent"
                        class="ml-auto inline-flex items-center gap-1 text-xs font-medium text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/20 px-2 py-0.5 rounded-full">
                        <CheckCircleIcon class="size-3" /> Inviata per email
                    </span>
                    <span v-else
                        class="ml-auto text-xs text-gray-400">Non ancora inviata</span>
                </div>

                <div class="px-5 py-4 space-y-4">
                    <!-- Metadati ricevuta -->
                    <dl class="grid grid-cols-3 gap-3 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Numero</dt>
                            <dd class="mt-0.5 font-semibold text-gray-900 dark:text-gray-100">
                                {{ incasso.receipt.number }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Data emissione</dt>
                            <dd class="mt-0.5">{{ fmt(incasso.receipt.issued_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Inviata il</dt>
                            <dd class="mt-0.5">
                                {{ incasso.receipt.sent_at ? fmtDatetime(incasso.receipt.sent_at) : '—' }}
                            </dd>
                        </div>
                    </dl>

                    <!-- Azioni ricevuta -->
                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('receipts.show', incasso.receipt.id)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <DocumentTextIcon class="size-3.5" /> Visualizza
                        </Link>

                        <a :href="route('receipts.download', incasso.receipt.id)" target="_blank"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded border border-indigo-300 dark:border-indigo-600 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/40">
                            <ArrowDownTrayIcon class="size-3.5" /> Scarica PDF
                        </a>

                        <button
                            v-if="!receiptSent"
                            @click="showEmailModal = !showEmailModal"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded border border-emerald-300 dark:border-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/40">
                            <EnvelopeIcon class="size-3.5" /> Invia per email
                        </button>

                        <button @click="regenerate"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <ArrowPathIcon class="size-3.5" /> Rigenera PDF
                        </button>
                    </div>

                    <!-- Form invio email inline -->
                    <div v-if="showEmailModal && !receiptSent"
                        class="border border-emerald-200 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-4">
                        <p class="text-xs font-semibold text-emerald-800 dark:text-emerald-200 mb-3">
                            Invia ricevuta n° {{ incasso.receipt.number }} per email
                        </p>
                        <form @submit.prevent="sendEmail" class="flex gap-2 flex-wrap">
                            <input
                                v-model="emailForm.email"
                                type="email"
                                required
                                placeholder="Indirizzo email destinatario"
                                class="flex-1 min-w-[200px] text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                            <button type="submit"
                                :disabled="emailForm.processing"
                                class="px-4 py-2 text-xs font-medium rounded-md bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50">
                                {{ emailForm.processing ? 'Invio in corso…' : 'Invia' }}
                            </button>
                            <button type="button" @click="showEmailModal = false"
                                class="px-3 py-2 text-xs font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                Annulla
                            </button>
                        </form>
                        <p v-if="emailForm.errors.email" class="mt-1.5 text-xs text-red-600 dark:text-red-400">
                            {{ emailForm.errors.email }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Nessuna ricevuta — emetti -->
            <div v-else-if="canIssueReceipt"
                class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <DocumentTextIcon class="size-4 text-gray-400" />
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Ricevuta</h3>
                        <span class="text-xs text-gray-400">— non ancora emessa</span>
                    </div>
                    <button @click="showIssueForm = !showIssueForm"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                        <DocumentTextIcon class="size-3.5" />
                        Emetti ricevuta
                        <ChevronUpIcon v-if="showIssueForm" class="size-3.5" />
                        <ChevronDownIcon v-else class="size-3.5" />
                    </button>
                </div>

                <!-- Form emissione -->
                <div v-if="showIssueForm" class="px-5 py-4 space-y-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Verrà generata una ricevuta PDF. Puoi modificare il testo con l'editor
                        o passare alla modalità HTML raw per interventi avanzati.
                        L'anteprima in basso mostra il risultato con i dati reali dell'incasso.
                    </p>

                    <form @submit.prevent="issueReceipt" class="space-y-4">

                        <!-- Toggle editor / HTML raw -->
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <label class="text-xs font-medium text-gray-700 dark:text-gray-300">
                                    Testo ricevuta
                                </label>
                                <div class="flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden shrink-0">
                                    <button
                                        type="button"
                                        :class="bodyEditMode === 'editor'
                                            ? 'bg-gray-200 dark:bg-gray-600 text-gray-900 dark:text-white'
                                            : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium"
                                        @click="bodyEditMode = 'editor'"
                                    >
                                        <PencilSquareIcon class="size-3.5" /> Editor
                                    </button>
                                    <button
                                        type="button"
                                        :class="bodyEditMode === 'raw'
                                            ? 'bg-gray-200 dark:bg-gray-600 text-gray-900 dark:text-white'
                                            : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium"
                                        @click="bodyEditMode = 'raw'"
                                    >
                                        <CodeBracketIcon class="size-3.5" /> HTML
                                    </button>
                                </div>
                            </div>

                            <!-- Editor visuale TipTap -->
                            <RichTextEditor
                                v-if="bodyEditMode === 'editor'"
                                v-model="issueForm.receipt_text_override"
                                placeholder="Testo ricevuta (HTML)..."
                                min-height="220px"
                                :placeholder-items="receiptEditorPlaceholders"
                                enable-table
                            />
                            <!-- Textarea HTML raw -->
                            <textarea
                                v-else
                                v-model="issueForm.receipt_text_override"
                                rows="10"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-xs"
                                placeholder="<p>Inserisci il testo HTML della ricevuta...</p>"
                            />
                            <p v-if="issueForm.errors.receipt_text_override"
                                class="mt-1 text-xs text-red-600 dark:text-red-400">
                                {{ issueForm.errors.receipt_text_override }}
                            </p>
                        </div>

                        <!-- Anteprima HTML in tempo reale -->
                        <div class="rounded-md border border-gray-200 dark:border-gray-600 overflow-hidden">
                            <div class="flex items-center justify-between px-3 py-2 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                                <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">Anteprima ricevuta</span>
                                <span class="text-xs text-gray-400 dark:text-gray-500">
                                    Placeholder sostituiti con i dati reali dell'incasso.
                                </span>
                            </div>
                            <div
                                class="p-4 prose prose-sm max-w-none dark:prose-invert min-h-[100px] bg-white dark:bg-gray-900"
                                v-html="receiptPreviewHtml"
                            />
                        </div>

                        <!-- Pulsanti -->
                        <div class="flex gap-2 pt-1">
                            <button type="submit"
                                :disabled="issueForm.processing"
                                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-md bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50">
                                <DocumentTextIcon class="size-4" />
                                {{ issueForm.processing ? 'Generazione in corso…' : 'Emetti ricevuta' }}
                            </button>
                            <button type="button" @click="showIssueForm = false"
                                class="px-4 py-2 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                Annulla
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Incasso anonimo senza possibilità di ricevuta -->
            <div v-else-if="!incasso.receipt && incasso.type === 'donazione'"
                class="px-4 py-3 bg-gray-50 dark:bg-gray-900/30 border border-gray-200 dark:border-gray-700 rounded-lg text-xs text-gray-500 dark:text-gray-400 flex items-center gap-2">
                <DocumentTextIcon class="size-4 shrink-0" />
                Donazione anonima — non è possibile emettere ricevuta senza un socio o un nome donatore.
            </div>

            <!-- ── Azioni principali ── -->
            <div class="flex gap-2">
                <Link
                    :href="route('incassi.edit', incasso.id)"
                    :class="['inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                        receiptSent ? 'opacity-50 pointer-events-none' : '']">
                    <PencilIcon class="size-4" /> Modifica
                </Link>
                <button @click="elimina"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-red-300 dark:border-red-700 rounded-md text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                    <TrashIcon class="size-4" /> Annulla incasso
                </button>
            </div>

            <!-- ── Allegati ── -->
            <AttachmentsPanel
                :attachments="incasso.attachments ?? []"
                :can-edit="true"
                :store-action="route('incassi.attachments.store', incasso.id)"
                :upload-max-file-size-human="uploadMaxFileSizeHuman"
                :error="attachmentError"
                @remove="removeAttachment"
            />
        </div>
    </AppLayout>
</template>
