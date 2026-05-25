<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ArrowLeftIcon, PaperClipIcon, XMarkIcon, DocumentArrowUpIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    entity: { type: Object, required: true },
    tipi:   { type: Array,  default: () => [] },
});

const form = useForm({
    titolo: '',
    descrizione: '',
    tipo: 'comunicazione',
    files: [],
    consegna_subito: true,
});

const fileInput = ref(null);

const onFileSelect = (event) => {
    const newFiles = Array.from(event.target.files);
    form.files = [...form.files, ...newFiles];
    event.target.value = ''; // reset per consentire re-selezione stesso file
};

const removeFile = (i) => {
    form.files = form.files.filter((_, idx) => idx !== i);
};

const submit = () => {
    form.post(route('consultant.deliveries.store', props.entity.slug), {
        forceFormData: true, // necessario per upload file
    });
};

const totalSize = () => {
    const s = form.files.reduce((sum, f) => sum + f.size, 0);
    if (s < 1024) return `${s} B`;
    if (s < 1024 * 1024) return `${(s / 1024).toFixed(1)} KB`;
    return `${(s / 1024 / 1024).toFixed(1)} MB`;
};
</script>

<template>
    <AppLayout :title="`Nuova consegna — ${entity.name}`">
        <Head :title="`Nuova consegna — ${entity.name}`" />

        <template #header>
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <Link :href="route('consultant.deliveries.index', entity.slug)"
                        class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1">
                        <ArrowLeftIcon class="size-3" /> Consegne
                    </Link>
                </div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Nuova consegna — {{ entity.name }}
                </h2>
            </div>
        </template>

        <div class="py-6">
            <form @submit.prevent="submit" class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4">
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Titolo *</span>
                        <input v-model="form.titolo" type="text" required maxlength="200"
                            placeholder="Es: F24 di novembre da versare"
                            class="mt-1 w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700" />
                        <p v-if="form.errors.titolo" class="text-xs text-red-500 mt-1">{{ form.errors.titolo }}</p>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Tipo *</span>
                        <select v-model="form.tipo" required
                            class="mt-1 w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                            <option v-for="t in tipi" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Descrizione</span>
                        <textarea v-model="form.descrizione" rows="4" maxlength="5000"
                            placeholder="Note opzionali per l'ente..."
                            class="mt-1 w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700"></textarea>
                    </label>
                </div>

                <!-- File upload -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Allegati</h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            Max 10 file · 20MB per file
                        </span>
                    </div>

                    <input ref="fileInput" type="file" multiple @change="onFileSelect" class="hidden" />
                    <button type="button" @click="$refs.fileInput.click()"
                        class="w-full py-6 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-blue-400 dark:hover:border-blue-500 hover:bg-blue-50/30 dark:hover:bg-blue-900/10 transition-colors">
                        <PaperClipIcon class="size-6 mx-auto text-gray-400 mb-1" />
                        <p class="text-sm text-gray-600 dark:text-gray-400">Click per aggiungere file</p>
                    </button>

                    <ul v-if="form.files.length > 0" class="mt-3 space-y-1">
                        <li v-for="(f, i) in form.files" :key="i"
                            class="flex items-center justify-between text-sm py-1.5 px-3 bg-gray-50 dark:bg-gray-900/30 rounded">
                            <span class="text-gray-700 dark:text-gray-300 truncate">{{ f.name }}</span>
                            <button type="button" @click="removeFile(i)" class="text-red-500 hover:text-red-700">
                                <XMarkIcon class="size-4" />
                            </button>
                        </li>
                        <li class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            {{ form.files.length }} file selezionati · {{ totalSize() }}
                        </li>
                    </ul>
                    <p v-if="form.errors.files" class="text-xs text-red-500 mt-1">{{ form.errors.files }}</p>
                </div>

                <!-- Consegna subito -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input v-model="form.consegna_subito" type="checkbox"
                            class="mt-0.5 rounded text-blue-600" />
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Consegna immediata</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                                Se attivo, l'ente verrà notificato subito via email e potrà vedere/scaricare i documenti.
                                Altrimenti rimane in bozza (solo tu la vedi) finché non clicchi "Consegna" dal dettaglio.
                            </p>
                        </div>
                    </label>
                </div>

                <div class="flex justify-end gap-2">
                    <Link :href="route('consultant.deliveries.index', entity.slug)">
                        <SecondaryButton type="button" class="text-sm">Annulla</SecondaryButton>
                    </Link>
                    <PrimaryButton type="submit"
                        :disabled="!form.titolo || form.files.length === 0 || form.processing"
                        class="text-sm">
                        <DocumentArrowUpIcon class="size-4 me-1.5" />
                        {{ form.processing ? 'Invio…' : (form.consegna_subito ? 'Crea e consegna' : 'Salva bozza') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
