<script setup>
import { computed } from 'vue';
import { CheckIcon } from '@heroicons/vue/24/outline';
import { Head, useForm } from '@inertiajs/vue3';
import MinimalLayout from '@/Layouts/MinimalLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const PRIVACY_FALLBACK = 'I dati forniti saranno trattati dal titolare secondo la normativa sulla privacy (GDPR) esclusivamente per la gestione della domanda di ammissione.';

const props = defineProps({
    token: String,
    email: { type: String, default: '' },
    memberTypes: { type: Array, default: () => [] },
    appName: { type: String, default: '' },
    invalid: { type: Boolean, default: false },
    submitted: { type: Boolean, default: false },
    privacy_informativa: { type: String, default: '' },
});

const privacyText = computed(() => {
    const t = (props.privacy_informativa || '').trim();
    return t || PRIVACY_FALLBACK;
});

const privacyIsHtml = computed(() => (props.privacy_informativa || '').trim().length > 0);

const form = useForm({
    member_type_id: '',
    nome: '',
    cognome: '',
    email: props.email,
    codice_fiscale: '',
    data_nascita: '',
    indirizzo: '',
    telefono: '',
    note: '',
    privacy_accepted: false,
});
</script>

<template>
    <MinimalLayout :app-name="appName" :title="submitted ? 'Domanda inviata' : invalid ? 'Link non valido' : 'Domanda di ammissione'">
        <Head :title="submitted ? 'Domanda inviata' : invalid ? 'Link non valido' : 'Domanda di ammissione'" />
        <div class="max-w-xl mx-auto">
            <div v-if="invalid" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 text-center">
                <p class="text-gray-700 dark:text-gray-300">
                    Questo link non è valido, è scaduto o è già stato utilizzato. Se hai bisogno di presentare domanda di ammissione, contatta la segreteria.
                </p>
            </div>

            <div v-else-if="submitted" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-100 dark:bg-green-900/40 text-green-600 dark:text-green-400 mb-4">
                    <CheckIcon class="size-6" aria-hidden="true" />
                </div>
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2">Domanda inviata</h2>
                <p class="text-gray-600 dark:text-gray-400">
                    La tua domanda di ammissione è stata inviata. La segreteria la esaminerà e ti contatterà.
                </p>
            </div>

            <form
                v-else
                @submit.prevent="form.post(route('members.admission-request.store', { token }))"
                class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6"
            >
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Domanda di ammissione come socio</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Compila i campi qui sotto. I campi contrassegnati con * sono obbligatori.</p>

                <div>
                    <InputLabel for="member_type_id" value="Tipologia *" />
                    <select
                        id="member_type_id"
                        v-model="form.member_type_id"
                        required
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm"
                    >
                        <option value="">Seleziona</option>
                        <option v-for="t in memberTypes" :key="t.id" :value="t.id">{{ t.display_name || t.name }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.member_type_id" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="cognome" value="Cognome *" />
                        <TextInput id="cognome" v-model="form.cognome" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.cognome" />
                    </div>
                    <div>
                        <InputLabel for="nome" value="Nome *" />
                        <TextInput id="nome" v-model="form.nome" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.nome" />
                    </div>
                </div>
                <div>
                    <InputLabel for="email" value="Email *" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full bg-gray-100 dark:bg-gray-700"
                        required
                        readonly
                    />
                    <InputError class="mt-1" :message="form.errors.email" />
                </div>
                <div>
                    <InputLabel for="codice_fiscale" value="Codice fiscale" />
                    <TextInput id="codice_fiscale" v-model="form.codice_fiscale" class="mt-1 block w-full" maxlength="64" />
                    <InputError class="mt-1" :message="form.errors.codice_fiscale" />
                </div>
                <div>
                    <InputLabel for="data_nascita" value="Data di nascita" />
                    <TextInput id="data_nascita" v-model="form.data_nascita" type="date" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.data_nascita" />
                </div>
                <div>
                    <InputLabel for="indirizzo" value="Indirizzo" />
                    <TextInput id="indirizzo" v-model="form.indirizzo" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.indirizzo" />
                </div>
                <div>
                    <InputLabel for="telefono" value="Telefono" />
                    <TextInput id="telefono" v-model="form.telefono" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.telefono" />
                </div>
                <div>
                    <InputLabel for="note" value="Note" />
                    <textarea
                        id="note"
                        v-model="form.note"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm"
                    ></textarea>
                    <InputError class="mt-1" :message="form.errors.note" />
                </div>
                <div>
                    <InputLabel value="Informativa sulla privacy" />
                    <div
                        class="mt-1 p-4 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 text-sm text-gray-700 dark:text-gray-300 max-h-[200px] overflow-y-auto"
                        role="document"
                        aria-label="Testo informativa privacy"
                    >
                        <div v-if="privacyIsHtml" class="prose prose-sm dark:prose-invert max-w-none" v-html="privacyText" />
                        <span v-else class="whitespace-pre-wrap">{{ privacyText }}</span>
                    </div>
                    <label class="mt-3 flex items-start gap-3 cursor-pointer">
                        <input
                            v-model="form.privacy_accepted"
                            type="checkbox"
                            class="mt-1 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Ho letto e accetto l'informativa sulla privacy (GDPR) *</span>
                    </label>
                    <InputError class="mt-1" :message="form.errors.privacy_accepted" />
                </div>
                <div>
                    <PrimaryButton type="submit" :disabled="form.processing">
                        <CheckIcon class="size-4 me-2" aria-hidden="true" />
                        Invia domanda
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </MinimalLayout>
</template>
