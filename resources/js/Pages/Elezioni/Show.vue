<script setup>
import { ref } from 'vue';
import { PlusIcon, PencilSquareIcon, TrashIcon, ArrowLeftIcon, CheckIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({ elezione: Object, membersForCandidati: { type: Array, default: () => [] }, aventiDirittoCount: { type: Number, default: 0 } });
const showAddCandidato = ref(false);
const newCandidatoId = ref('');
const showInvalidaForm = ref(false);
const formInvalida = useForm({ motivazione: '' });

const isApertaOggi = () => {
    if (props.elezione.stato !== 'aperta') return false;
    const today = new Date().toISOString().slice(0, 10);
    return props.elezione.data_elezione === today;
};

function addCandidato() {
    if (!newCandidatoId.value) return;
    router.post(route('elezioni.candidati.store', props.elezione.id), { member_id: newCandidatoId.value }, { preserveScroll: true });
    showAddCandidato.value = false;
    newCandidatoId.value = '';
}
function removeCandidato(c) {
    if (!confirm('Rimuovere questo candidato?')) return;
    router.delete(route('elezioni.candidati.destroy', { elezione: props.elezione.id, candidatura: c.id }), { preserveScroll: true });
}
function submitInvalida() {
    formInvalida.post(route('elezioni.invalida', props.elezione.id), {
        preserveScroll: true,
        onSuccess: () => { showInvalidaForm.value = false; formInvalida.reset(); },
    });
}
</script>

<template>
    <AppLayout :title="elezione.titolo">
        <Head :title="elezione.titolo" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ elezione.titolo }}</h2>
                <div class="flex gap-2">
                    <Link v-if="elezione.stato === 'aperta' && isApertaOggi()" :href="route('elezioni.vota', elezione.id)">
                        <PrimaryButton>Vota</PrimaryButton>
                    </Link>
                    <Link :href="route('elezioni.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Elenco</Link>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto sm:px-6 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Organo</dt><dd>{{ elezione.organo?.nome ?? '—' }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Data</dt><dd>{{ elezione.data_elezione ? new Date(elezione.data_elezione).toLocaleDateString('it-IT') : '' }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Stato</dt><dd><span class="px-2 py-0.5 rounded text-xs" :class="{ 'bg-gray-100 text-gray-800': elezione.stato === 'bozza', 'bg-green-100 text-green-800': elezione.stato === 'aperta', 'bg-blue-100 text-blue-800': elezione.stato === 'chiusa' && !elezione.invalidata_at, 'bg-red-100 text-red-800': elezione.invalidata_at }">{{ elezione.invalidata_at ? 'invalidata' : elezione.stato }}</span></dd></div>
                    <div v-if="elezione.stato === 'aperta' || elezione.stato === 'chiusa'"><dt class="text-sm text-gray-500 dark:text-gray-400">Votanti</dt><dd>{{ elezione.partecipazioni_count ?? 0 }} / {{ aventiDirittoCount }} (aventi diritto al voto)</dd></div>
                    <div v-if="elezione.stato === 'chiusa'"><dt class="text-sm text-gray-500 dark:text-gray-400">Risultati</dt><dd><Link :href="route('elezioni.risultati', elezione.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">Vedi risultati</Link></dd></div>
                </dl>
                <div v-if="elezione.stato === 'bozza'" class="mt-4 flex gap-2">
                    <Link :href="route('elezioni.edit', elezione.id)" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md text-sm border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <PencilSquareIcon class="size-4" aria-hidden="true" />Modifica
                    </Link>
                    <form @submit.prevent="router.post(route('elezioni.open', elezione.id))" class="inline">
                        <PrimaryButton type="submit" class="!py-1.5">Apri votazione</PrimaryButton>
                    </form>
                </div>
                <div v-if="elezione.stato === 'aperta'" class="mt-4">
                    <form @submit.prevent="router.post(route('elezioni.close', elezione.id))" class="inline">
                        <SecondaryButton type="submit">Chiudi votazione</SecondaryButton>
                    </form>
                </div>
                <div v-if="elezione.invalidata_at" class="mt-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                    <p class="font-medium text-red-800 dark:text-red-200 flex items-center gap-2"><ExclamationTriangleIcon class="size-5" aria-hidden="true" />Votazione invalidata</p>
                    <p class="mt-2 text-sm text-red-700 dark:text-red-300 whitespace-pre-wrap">{{ elezione.motivazione_invalidazione }}</p>
                </div>
                <div v-else-if="elezione.stato === 'chiusa' && !showInvalidaForm" class="mt-4">
                    <SecondaryButton type="button" @click="showInvalidaForm = true">Invalida votazione</SecondaryButton>
                </div>
                <form v-else-if="elezione.stato === 'chiusa' && showInvalidaForm" @submit.prevent="submitInvalida" class="mt-4 p-4 rounded-lg border border-gray-200 dark:border-gray-600 space-y-3">
                    <InputLabel for="motivazione" value="Motivazione dell'invalidazione *" />
                    <textarea id="motivazione" v-model="formInvalida.motivazione" rows="4" class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm" placeholder="Indica il motivo per cui la votazione viene invalidata..." required></textarea>
                    <InputError :message="formInvalida.errors.motivazione" />
                    <div class="flex gap-2">
                        <PrimaryButton type="submit" :disabled="formInvalida.processing">Conferma invalidazione</PrimaryButton>
                        <SecondaryButton type="button" @click="showInvalidaForm = false; formInvalida.reset()">Annulla</SecondaryButton>
                    </div>
                </form>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <h3 class="px-4 py-3 text-lg font-medium text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700">Candidati</h3>
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Nome</th>
                            <th v-if="elezione.stato === 'bozza'" class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="c in elezione.candidature" :key="c.id">
                            <td class="px-4 py-2">{{ c.member?.cognome }} {{ c.member?.nome }}</td>
                            <td v-if="elezione.stato === 'bozza'" class="px-4 py-2 text-right">
                                <button type="button" class="text-sm text-red-600 dark:text-red-400 hover:underline" @click="removeCandidato(c)">Rimuovi</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!elezione.candidature?.length" class="px-4 py-4 text-gray-500 text-sm">Nessun candidato.</div>
                <div v-if="elezione.stato === 'bozza'" class="px-4 py-4 border-t">
                    <template v-if="!showAddCandidato">
                        <SecondaryButton type="button" @click="showAddCandidato = true"><PlusIcon class="size-4 me-2" aria-hidden="true" />Aggiungi candidato</SecondaryButton>
                    </template>
                    <form v-else @submit.prevent="addCandidato" class="flex flex-wrap gap-2 items-end">
                        <div class="min-w-[200px]">
                            <InputLabel for="new_candidato" value="Socio" />
                            <select id="new_candidato" v-model="newCandidatoId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm" required>
                                <option value="">Seleziona</option>
                                <option v-for="m in membersForCandidati" :key="m.id" :value="m.id">{{ m.cognome }} {{ m.nome }}</option>
                            </select>
                        </div>
                        <PrimaryButton type="submit"><CheckIcon class="size-4 me-2" aria-hidden="true" />Aggiungi</PrimaryButton>
                        <SecondaryButton type="button" @click="showAddCandidato = false">Annulla</SecondaryButton>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
