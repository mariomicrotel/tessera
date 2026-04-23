<script setup>
import { ArrowLeftIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AttachmentsPanel from '@/Components/AttachmentsPanel.vue';

const props = defineProps({
    incasso: Object,
    uploadMaxFileSizeHuman: { type: String, default: '10 MB' },
});

const page = usePage();

const backHref = () => {
    if (props.incasso.type === 'donazione') return route('donazioni.index');
    if (props.incasso.type === 'altro') return route('incassi-generici.index');
    return route('quote-sociali.index');
};

const receiptSent = props.incasso.receipt?.sent_at != null;

const elimina = () => {
    if (confirm('Eliminare questo incasso? Verranno eliminati anche la prima nota e la ricevuta collegata. Questa operazione non è reversibile.')) {
        router.delete(route('incassi.destroy', props.incasso.id));
    }
};

function removeAttachment(attachment) {
    if (!confirm('Rimuovere questo allegato?')) return;
    router.delete(route('incassi.attachments.destroy', [props.incasso.id, attachment.id]));
}

const attachmentError = computed(() => {
    const err = page.props.errors?.file;
    if (!err) return null;
    return Array.isArray(err) ? err[0] : err;
});
</script>

<template>
    <AppLayout title="Dettaglio incasso">
        <Head title="Dettaglio incasso" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Incasso #{{ incasso.id }}</h2>
                <Link :href="backHref()" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />
                    {{ incasso.type === 'donazione' ? 'Elenco erogazioni liberali' : (incasso.type === 'altro' ? 'Incassi generici' : 'Elenco quote sociali') }}
                </Link>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto sm:px-6 space-y-4">
            <!-- Avviso ricevuta inviata -->
            <div v-if="receiptSent" class="p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg text-sm text-yellow-700 dark:text-yellow-400">
                La ricevuta è stata inviata per email il {{ new Date(incasso.receipt.sent_at).toLocaleString('it-IT') }}.
                La modifica di questo incasso non è consentita.
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Tipo</dt>
                        <dd>
                            <span v-if="incasso.type === 'quota'" class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">Quota</span>
                            <span v-else-if="incasso.type === 'donazione'" class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">Donazione</span>
                            <span v-else class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Generico</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">{{ incasso.type === 'donazione' ? 'Donatore' : 'Socio' }}</dt>
                        <dd>
                            <Link v-if="incasso.member" :href="route('members.show', incasso.member.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ incasso.member.cognome }} {{ incasso.member.nome }}</Link>
                            <template v-else>{{ incasso.donor_name || (incasso.type === 'donazione' ? 'Anonimo' : '—') }}</template>
                        </dd>
                    </div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Importo</dt><dd class="font-medium">€ {{ Number(incasso.amount).toFixed(2) }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Data</dt><dd>{{ incasso.paid_at ? new Date(incasso.paid_at).toLocaleDateString('it-IT') : '—' }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Conto di destinazione</dt><dd>{{ incasso.conto?.name ?? '—' }}</dd></div>
                    <div v-if="incasso.type === 'quota'"><dt class="text-sm text-gray-500 dark:text-gray-400">Iscrizione</dt><dd>{{ incasso.subscription ? 'Anno ' + incasso.subscription.year + (incasso.subscription.ends_at ? ' (fino al ' + new Date(incasso.subscription.ends_at).toLocaleDateString('it-IT') + ')' : '') : '—' }}</dd></div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Ricevuta</dt>
                        <dd>
                            <template v-if="incasso.receipt">
                                <Link :href="route('receipts.show', incasso.receipt.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ incasso.receipt.number }}</Link>
                                <a :href="route('receipts.download', incasso.receipt.id)" target="_blank" class="ml-2 text-sm text-gray-500 hover:underline">Scarica PDF</a>
                                <span v-if="incasso.receipt.sent_at" class="ml-2 text-xs text-green-600 dark:text-green-400">✓ Inviata</span>
                            </template>
                            <template v-else>—</template>
                        </dd>
                    </div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Prima nota</dt><dd><template v-if="incasso.prima_nota_entry">Sì ({{ incasso.prima_nota_entry.rendiconto_label || incasso.prima_nota_entry.rendiconto_code }})</template><template v-else>No</template></dd></div>
                    <div v-if="incasso.description" class="sm:col-span-2"><dt class="text-sm text-gray-500 dark:text-gray-400">Causale</dt><dd>{{ incasso.description }}</dd></div>
                </dl>
            </div>

            <!-- Azioni -->
            <div class="flex gap-2">
                <Link
                    :href="route('incassi.edit', incasso.id)"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                    :class="{ 'opacity-50 pointer-events-none': receiptSent }"
                >
                    <PencilIcon class="size-4" aria-hidden="true" />Modifica
                </Link>
                <button
                    @click="elimina"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-red-300 dark:border-red-700 rounded-md text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
                >
                    <TrashIcon class="size-4" aria-hidden="true" />Annulla incasso
                </button>
            </div>

            <!-- Allegati -->
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
