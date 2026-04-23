<script setup>
import { ArrowLeftIcon, EnvelopeIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';
import { ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({ receipt: Object, suggestedEmail: String });

const showEmailModal = ref(false);
const emailForm = useForm({ email: props.suggestedEmail ?? '' });
const regenerateForm = useForm({});

watch(showEmailModal, (open) => {
    if (open) {
        emailForm.email = props.suggestedEmail ?? '';
        emailForm.clearErrors();
    }
});

function submitSendEmail() {
    emailForm.post(route('receipts.send-email', props.receipt.id), {
        preserveScroll: true,
        onSuccess: () => { showEmailModal.value = false; },
    });
}

function submitRegenerate() {
    if (!confirm('Rigenerare il PDF della ricevuta? Il file esistente sarà sostituito con una nuova versione (stesso numero e dati).')) return;
    regenerateForm.post(route('receipts.regenerate', props.receipt.id), { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Ricevuta">
        <Head :title="'Ricevuta ' + receipt.number" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Ricevuta {{ receipt.number }}</h2>
                <div class="flex gap-2">
                    <a v-if="receipt.file_path" :href="route('receipts.download', receipt.id)" target="_blank" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-800 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white">Scarica PDF</a>
                    <button v-if="receipt.file_path" type="button" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs uppercase tracking-widest text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600" @click="showEmailModal = true">
                        <EnvelopeIcon class="size-4" aria-hidden="true" />Invia per email
                    </button>
                    <button type="button" class="inline-flex items-center gap-1 px-4 py-2 border border-amber-500/50 dark:border-amber-400/50 rounded-md font-semibold text-xs uppercase tracking-widest text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 hover:bg-amber-100 dark:hover:bg-amber-900/40" :class="{ 'opacity-50 cursor-not-allowed': regenerateForm.processing }" :disabled="regenerateForm.processing" @click="submitRegenerate()">
                        <ArrowPathIcon class="size-4" :class="{ 'animate-spin': regenerateForm.processing }" aria-hidden="true" />Rigenera ricevuta
                    </button>
                    <Link :href="route('receipts.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Elenco ricevute</Link>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto sm:px-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Socio</dt><dd><Link v-if="receipt.member" :href="route('members.show', receipt.member.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ receipt.member.cognome }} {{ receipt.member.nome }}</Link><template v-else>—</template></dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Data emissione</dt><dd>{{ receipt.issued_at ? new Date(receipt.issued_at).toLocaleDateString('it-IT') : '—' }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Tipo</dt><dd>{{ receipt.type }}</dd></div>
                </dl>
            </div>

            <div v-if="receipt.file_path" class="mt-6 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Anteprima</h3>
                <iframe
                    :src="route('receipts.download', receipt.id) + '?inline=1'"
                    class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"
                    style="min-height: 60vh;"
                    title="Anteprima PDF ricevuta"
                />
            </div>
        </div>

        <DialogModal :show="showEmailModal" @close="showEmailModal = false">
            <template #title>Invia ricevuta per email</template>
            <template #content>
                <p class="text-gray-600 dark:text-gray-400 mb-4">La ricevuta n. {{ receipt.number }} verrà inviata in allegato (PDF) all'indirizzo indicato.</p>
                <InputLabel for="email" value="Indirizzo email destinatario" />
                <TextInput id="email" v-model="emailForm.email" type="email" class="mt-1 block w-full" placeholder="esempio@email.it" required autofocus />
                <InputError :message="emailForm.errors.email" class="mt-1" />
            </template>
            <template #footer>
                <SecondaryButton @click="showEmailModal = false">Annulla</SecondaryButton>
                <PrimaryButton class="ml-2" :class="{ 'opacity-25': emailForm.processing }" :disabled="emailForm.processing" @click="submitSendEmail">Invia</PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
