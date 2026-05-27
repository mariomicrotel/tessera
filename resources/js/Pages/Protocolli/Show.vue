<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AttachmentsPanel from '@/Components/AttachmentsPanel.vue';
import {
    ArrowLeftIcon, PencilIcon, TrashIcon,
    InboxArrowDownIcon, PaperAirplaneIcon, DocumentTextIcon,
    ArrowTopRightOnSquareIcon, ClockIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    protocollo:            { type: Object, required: true },
    uploadMaxFileSizeHuman:{ type: String, default: '10 MB' },
});

const page = usePage();

const tipoBadge = props.protocollo.tipo === 'entrata'
    ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
    : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300';

const fmt    = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';
const fmtDt  = (d) => d ? new Date(d).toLocaleString('it-IT') : '—';

const linkedLabel = computed(() => {
    const t = props.protocollo.linked_type;
    if (!t) return null;
    if (t.endsWith('Receipt')) return 'Ricevuta';
    if (t.endsWith('Incasso')) return 'Incasso';
    if (t.endsWith('FatturaAttiva')) return 'Fattura attiva';
    if (t.endsWith('MailMessage')) return 'Email';
    return 'Documento';
});

const linkedHref = computed(() => {
    const p = props.protocollo;
    if (!p.linked_type || !p.linked_id) return null;
    if (p.linked_type.endsWith('Receipt')) return route('receipts.show', p.linked_id);
    if (p.linked_type.endsWith('Incasso')) return route('incassi.show', p.linked_id);
    if (p.linked_type.endsWith('MailMessage')) return route('mail.show', p.linked_id);
    return null;
});

const linkedNumber = computed(() => {
    const l = props.protocollo.linked;
    if (!l) return null;
    // Per le email mostriamo l'oggetto invece di un numero
    if (props.protocollo.linked_type?.endsWith('MailMessage')) {
        return l.subject ?? '(nessun oggetto)';
    }
    return l.number ?? l.id;
});

function elimina() {
    if (!confirm('Eliminare questa voce di protocollo? L\'operazione non è reversibile.')) return;
    router.delete(route('protocolli.destroy', props.protocollo.id));
}

function removeAttachment(att) {
    if (!confirm('Rimuovere questo allegato?')) return;
    router.delete(route('protocolli.attachments.destroy', [props.protocollo.id, att.id]));
}

const attachmentError = computed(() => {
    const err = page.props.errors?.file;
    return Array.isArray(err) ? err[0] : (err ?? null);
});
</script>

<template>
    <AppLayout :title="`Protocollo ${protocollo.numero_formattato}`">
        <Head :title="`Protocollo ${protocollo.numero_formattato}`" />

        <template #header>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-3">
                    <Link :href="route('protocolli.index')"
                        class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                        <ArrowLeftIcon class="size-4" /> Protocollo
                    </Link>
                    <span class="text-gray-300 dark:text-gray-600">/</span>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                        {{ protocollo.numero_formattato }}
                    </h2>
                    <span :class="['inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium', tipoBadge]">
                        <InboxArrowDownIcon v-if="protocollo.tipo === 'entrata'" class="size-3.5" />
                        <PaperAirplaneIcon  v-else class="size-3.5" />
                        {{ protocollo.tipo === 'entrata' ? 'Entrata' : 'Uscita' }}
                    </span>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('protocolli.edit', protocollo.id)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <PencilIcon class="size-4" /> Modifica
                    </Link>
                    <button @click="elimina"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-red-300 dark:border-red-700 bg-white dark:bg-gray-800 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                        <TrashIcon class="size-4" /> Elimina
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto sm:px-6 space-y-4">

            <!-- Dati principali -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
                    <DocumentTextIcon class="size-4 text-indigo-500" />
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Dati protocollo</h3>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 px-5 py-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Numero protocollo</dt>
                        <dd class="mt-0.5 font-mono text-lg font-bold text-gray-900 dark:text-gray-100">
                            {{ protocollo.numero_formattato }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Data registrazione</dt>
                        <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">
                            {{ fmt(protocollo.data_registrazione) }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Oggetto</dt>
                        <dd class="mt-0.5 text-gray-900 dark:text-gray-100 font-medium">
                            {{ protocollo.oggetto }}
                        </dd>
                    </div>
                    <div v-if="protocollo.mittente || protocollo.tipo === 'entrata'">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Mittente</dt>
                        <dd class="mt-0.5 text-gray-900 dark:text-gray-100">
                            {{ protocollo.mittente || '—' }}
                        </dd>
                    </div>
                    <div v-if="protocollo.destinatario || protocollo.tipo === 'uscita'">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Destinatario</dt>
                        <dd class="mt-0.5 text-gray-900 dark:text-gray-100">
                            {{ protocollo.destinatario || '—' }}
                        </dd>
                    </div>
                    <div v-if="protocollo.note" class="sm:col-span-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Note</dt>
                        <dd class="mt-0.5 text-gray-700 dark:text-gray-300 whitespace-pre-line">
                            {{ protocollo.note }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                            <ClockIcon class="size-3.5" /> Registrato il
                        </dt>
                        <dd class="mt-0.5 text-gray-500 dark:text-gray-400 text-xs">
                            {{ fmtDt(protocollo.created_at) }}
                            <template v-if="protocollo.created_by">
                                da {{ protocollo.created_by.name }}
                            </template>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Documento collegato (se presente) -->
            <div v-if="linkedLabel"
                class="bg-white dark:bg-gray-800 rounded-xl border border-indigo-200 dark:border-indigo-700 overflow-hidden">
                <div class="px-5 py-3 border-b border-indigo-100 dark:border-indigo-800 flex items-center gap-2 bg-indigo-50 dark:bg-indigo-900/20">
                    <DocumentTextIcon class="size-4 text-indigo-500" />
                    <h3 class="text-sm font-semibold text-indigo-900 dark:text-indigo-200">
                        {{ linkedLabel }} collegat{{ (linkedLabel === 'Ricevuta' || linkedLabel === 'Email') ? 'a' : 'o' }}
                    </h3>
                </div>
                <div class="px-5 py-4 flex items-center justify-between gap-3">
                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        <template v-if="linkedNumber">
                            <span class="font-mono font-semibold text-indigo-700 dark:text-indigo-300">
                                {{ linkedNumber }}
                            </span>
                            <template v-if="protocollo.linked?.issued_at || protocollo.linked?.paid_at">
                                <span class="mx-2 text-gray-300">·</span>
                                {{ fmt(protocollo.linked.issued_at || protocollo.linked.paid_at) }}
                            </template>
                            <template v-if="protocollo.linked?.member_name || protocollo.linked?.recipient_name">
                                <span class="mx-2 text-gray-300">·</span>
                                {{ protocollo.linked.member_name || protocollo.linked.recipient_name }}
                            </template>
                        </template>
                        <template v-else>
                            {{ linkedLabel }} n° {{ protocollo.linked_id }}
                        </template>
                    </div>
                    <Link v-if="linkedHref" :href="linkedHref"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-indigo-300 dark:border-indigo-600 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/40">
                        Apri <ArrowTopRightOnSquareIcon class="size-3.5" />
                    </Link>
                </div>
            </div>

            <!-- Allegati -->
            <AttachmentsPanel
                :attachments="protocollo.attachments ?? []"
                :can-edit="true"
                :store-action="route('protocolli.attachments.store', protocollo.id)"
                :upload-max-file-size-human="uploadMaxFileSizeHuman"
                :error="attachmentError"
                @remove="removeAttachment"
            />

        </div>
    </AppLayout>
</template>
