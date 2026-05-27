<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { ArrowLeftIcon, PaperAirplaneIcon, PaperClipIcon, XMarkIcon, DocumentCheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    accounts: { type: Array, default: () => [] },  // [{id, label}]
    reply_to: { type: Object, default: null },       // messaggio originale se risposta
    draft:    { type: Object, default: null },       // bozza da modificare
});

const isReply = !!props.reply_to;
const isDraft = !!props.draft;

// Escape HTML per il testo plain citato
function escapeHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// Pre-compila subject con "Re: " se risposta
const defaultSubject = isDraft
    ? (props.draft.subject ?? '')
    : (isReply
        ? (props.reply_to.subject?.startsWith('Re:') ? props.reply_to.subject : 'Re: ' + props.reply_to.subject)
        : '');

const defaultTo      = isDraft ? (props.draft.to ?? '')      : (isReply ? (props.reply_to.from_email ?? '') : '');
const defaultToName  = isDraft ? (props.draft.to_name ?? '') : (isReply ? (props.reply_to.from_name  ?? '') : '');
const defaultCc      = isDraft ? (props.draft.cc ?? '')      : '';

// Corpo in HTML (il body è ora rich text)
function buildReplyQuote() {
    const author = props.reply_to.from_name || props.reply_to.from_email;
    const quoted = props.reply_to.body_html
        || (props.reply_to.body_text ? '<p>' + escapeHtml(props.reply_to.body_text).replace(/\n/g, '<br>') + '</p>' : '');
    return '<p></p><p></p>'
        + '<blockquote style="border-left:3px solid #d1d5db;margin:0;padding-left:12px;color:#6b7280">'
        + '<p>In data ' + escapeHtml(fmtDate(props.reply_to.sent_at)) + ', ' + escapeHtml(author) + ' ha scritto:</p>'
        + quoted
        + '</blockquote>';
}

const defaultBody = isDraft
    ? (props.draft.body ?? '')
    : (isReply ? buildReplyQuote() : '');

const defaultAccount = isDraft && props.draft.account_id
    ? props.draft.account_id
    : (isReply && props.reply_to.account_id
        ? props.reply_to.account_id
        : (props.accounts[0]?.id ?? null));

const form = useForm({
    account_id:           defaultAccount,
    to:                   defaultTo,
    to_name:              defaultToName,
    cc:                   defaultCc,
    bcc:                  '',
    subject:              defaultSubject,
    body:                 defaultBody,
    reply_to_message_id:  isReply ? props.reply_to.id : null,
    draft_id:             isDraft ? props.draft.id : null,
    attachments:          [],
});

const savingDraft = ref(false);
function saveDraft() {
    savingDraft.value = true;
    router.post(route('mail.draft'), {
        draft_id:   form.draft_id,
        account_id: form.account_id,
        to:         form.to,
        to_name:    form.to_name,
        cc:         form.cc,
        subject:    form.subject,
        body:       form.body,
    }, {
        onFinish: () => { savingDraft.value = false; },
    });
}

// Allegati — gestione lato UI
const fileInputRef = ref(null);

function onFileChange(e) {
    const files = Array.from(e.target.files || []);
    form.attachments = [...form.attachments, ...files];
    // Reset input per permettere di ri-selezionare lo stesso file
    if (fileInputRef.value) fileInputRef.value.value = '';
}

function removeAttachment(index) {
    form.attachments = form.attachments.filter((_, i) => i !== index);
}

function fmtSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function fmtDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('it-IT', {
        day: '2-digit', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function submit() {
    form.post(route('mail.send'));
}

const pageTitle = isDraft ? 'Modifica bozza' : (isReply ? 'Rispondi' : 'Nuova email');
const hasAccounts = props.accounts.length > 0;
</script>

<template>
    <AppLayout :title="pageTitle">
        <Head :title="pageTitle" />

        <template #header>
            <div class="flex items-center gap-3">
                <button @click="router.get(route('mail.index'))" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500">
                    <ArrowLeftIcon class="size-5" />
                </button>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ pageTitle }}
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

                <!-- Nessun account SMTP configurato -->
                <div v-if="!hasAccounts" class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-6 text-center">
                    <p class="text-amber-700 dark:text-amber-300 font-medium mb-2">Nessuna casella con SMTP configurato.</p>
                    <p class="text-sm text-amber-600 dark:text-amber-400 mb-4">
                        Per inviare email devi prima configurare i parametri SMTP di almeno una casella.
                    </p>
                    <a :href="route('settings.index') + '?tab=posta'"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700">
                        Vai alle impostazioni posta
                    </a>
                </div>

                <form v-else @submit.prevent="submit"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">

                    <!-- Casella mittente -->
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center gap-3">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 w-16 shrink-0">Da</span>
                        <select v-model="form.account_id"
                            class="flex-1 text-sm border-0 bg-transparent focus:ring-0 text-gray-800 dark:text-gray-100 py-1"
                        >
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.label }}</option>
                        </select>
                        <p v-if="form.errors.account_id" class="text-xs text-red-500">{{ form.errors.account_id }}</p>
                    </div>

                    <!-- A -->
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center gap-3">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 w-16 shrink-0">A</span>
                        <div class="flex-1 flex items-center gap-2">
                            <input v-model="form.to" type="email" required placeholder="destinatario@email.it"
                                class="flex-1 text-sm border-0 bg-transparent focus:ring-0 text-gray-800 dark:text-gray-100 placeholder-gray-400 py-1"
                                :class="{ 'border-b border-red-400': form.errors.to }"
                            />
                            <input v-model="form.to_name" type="text" placeholder="Nome (opzionale)"
                                class="w-40 text-sm border-0 bg-transparent focus:ring-0 text-gray-500 dark:text-gray-400 placeholder-gray-400 py-1"
                            />
                        </div>
                        <p v-if="form.errors.to" class="text-xs text-red-500">{{ form.errors.to }}</p>
                    </div>

                    <!-- CC -->
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center gap-3">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 w-16 shrink-0">CC</span>
                        <input v-model="form.cc" type="text" placeholder="email1@esempio.it, email2@esempio.it"
                            class="flex-1 text-sm border-0 bg-transparent focus:ring-0 text-gray-800 dark:text-gray-100 placeholder-gray-400 py-1"
                        />
                    </div>

                    <!-- Oggetto -->
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center gap-3">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 w-16 shrink-0">Oggetto</span>
                        <input v-model="form.subject" type="text" required placeholder="Oggetto del messaggio"
                            class="flex-1 text-sm font-medium border-0 bg-transparent focus:ring-0 text-gray-800 dark:text-gray-100 placeholder-gray-400 py-1"
                            :class="{ 'border-b border-red-400': form.errors.subject }"
                        />
                        <p v-if="form.errors.subject" class="text-xs text-red-500">{{ form.errors.subject }}</p>
                    </div>

                    <!-- Corpo (rich text) -->
                    <div class="px-4 py-3">
                        <RichTextEditor
                            v-model="form.body"
                            :hide-placeholders="true"
                            min-height="300px"
                            placeholder="Scrivi il tuo messaggio…"
                        />
                        <p v-if="form.errors.body" class="text-xs text-red-500 pt-2">{{ form.errors.body }}</p>
                    </div>

                    <!-- Allegati selezionati -->
                    <div v-if="form.attachments.length" class="px-4 py-3 border-t border-gray-100 dark:border-gray-700 space-y-1.5">
                        <div
                            v-for="(file, i) in form.attachments"
                            :key="i"
                            class="flex items-center gap-2 text-sm"
                        >
                            <PaperClipIcon class="size-4 text-gray-400 shrink-0" />
                            <span class="flex-1 truncate text-gray-700 dark:text-gray-300">{{ file.name }}</span>
                            <span class="text-xs text-gray-400 shrink-0">{{ fmtSize(file.size) }}</span>
                            <button type="button" @click="removeAttachment(i)" class="p-0.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-red-500">
                                <XMarkIcon class="size-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                @click="router.get(route('mail.index'))"
                                class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                            >
                                Annulla
                            </button>
                            <!-- Pulsante allega file -->
                            <label class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 cursor-pointer">
                                <PaperClipIcon class="size-4" />
                                Allega
                                <input
                                    ref="fileInputRef"
                                    type="file"
                                    multiple
                                    class="hidden"
                                    @change="onFileChange"
                                    accept="*/*"
                                />
                            </label>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="saveDraft"
                                :disabled="savingDraft || form.processing"
                                class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 transition-colors"
                            >
                                <DocumentCheckIcon class="size-4" />
                                {{ savingDraft ? 'Salvataggio…' : 'Salva bozza' }}
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-2 px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                            >
                                <PaperAirplaneIcon class="size-4" />
                                {{ form.processing ? 'Invio in corso…' : 'Invia' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
