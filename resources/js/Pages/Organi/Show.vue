<script setup>
import { ref } from 'vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({ organo: Object, members: { type: Array, default: () => [] } });
const showAssignForCaricaId = ref(null);
const formIncarico = useForm({ member_id: '' });
const editingDurata = ref(false);
const formOrgano = useForm({
    durata_mesi: props.organo.durata_mesi ?? '',
    richiedi_elezioni_fine_mandato: !!props.organo.richiedi_elezioni_fine_mandato,
});

function avviaModificaDurata() {
    formOrgano.durata_mesi = props.organo.durata_mesi ?? '';
    formOrgano.richiedi_elezioni_fine_mandato = !!props.organo.richiedi_elezioni_fine_mandato;
    formOrgano.clearErrors();
    editingDurata.value = true;
}

function annullaModificaDurata() {
    editingDurata.value = false;
}

function salvaDurata() {
    formOrgano.put(route('organi.update', props.organo.slug), {
        preserveScroll: true,
        onSuccess: () => { editingDurata.value = false; },
    });
}

/** Soci ancora non assegnati a questa carica (per evitare doppi incarichi). */
function membersDisponibiliPerCarica(carica) {
    const assegnati = (carica.incarichi || []).map((inc) => inc.member_id);
    return props.members.filter((m) => !assegnati.includes(m.id));
}

/** True se per questa carica si può mostrare il pulsante Assegna socio / Aggiungi altro. */
function puoAggiungereAssegnatario(carica) {
    if (!membersDisponibiliPerCarica(carica).length) return false;
    if (carica.multiplo) return true;
    return (carica.incarichi || []).length === 0;
}

function avviaAssegna(carica) {
    showAssignForCaricaId.value = carica.id;
    formIncarico.reset();
    formIncarico.member_id = '';
}

function chiudiAssegna() {
    showAssignForCaricaId.value = null;
    formIncarico.reset();
}

function salvaIncarico(carica) {
    if (!formIncarico.member_id) return;
    formIncarico.transform(() => ({
        carica_sociale_id: carica.id,
        redirect_to_organo_slug: props.organo.slug,
    })).post(route('members.incarichi.store', formIncarico.member_id), {
        preserveScroll: true,
        onSuccess: () => {
            showAssignForCaricaId.value = null;
            formIncarico.reset();
        },
    });
}

function rimuoviIncarico(incarico) {
    if (!confirm('Rimuovere questo incarico?')) return;
    const url = route('incarichi.destroy', incarico.id) + '?redirect_to_organo_slug=' + encodeURIComponent(props.organo.slug);
    router.delete(url, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="organo.nome">
        <Head :title="organo.nome" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ organo.nome }}</h2>
                <Link :href="route('organi.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />Elenco organi
                </Link>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto sm:px-6 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div class="flex justify-between items-center mb-2">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Dettagli</h3>
                    <button v-if="!editingDurata" type="button" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline" @click="avviaModificaDurata">Modifica durata</button>
                </div>
                <template v-if="editingDurata">
                    <form @submit.prevent="salvaDurata" class="space-y-4">
                        <div>
                            <InputLabel for="durata_mesi" value="Durata (mesi)" />
                            <input id="durata_mesi" v-model.number="formOrgano.durata_mesi" type="number" min="1" max="120" placeholder="es. 36" class="mt-1 block w-full max-w-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <InputError :message="formOrgano.errors.durata_mesi" />
                        </div>
                        <div class="flex items-center gap-2">
                            <input id="richiedi_elezioni" v-model="formOrgano.richiedi_elezioni_fine_mandato" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                            <InputLabel for="richiedi_elezioni" value="Richiedi elezioni a fine mandato" class="mb-0" />
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50" :disabled="formOrgano.processing">Salva</button>
                            <button type="button" class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:underline" :disabled="formOrgano.processing" @click="annullaModificaDurata">Annulla</button>
                        </div>
                    </form>
                </template>
                <dl v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Durata</dt><dd>{{ organo.durata_mesi ? organo.durata_mesi + ' mesi' : '—' }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Elezione fine mandato</dt><dd>{{ organo.richiedi_elezioni_fine_mandato ? 'Sì' : 'No' }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Mandato da</dt><dd>{{ organo.mandato_da ? new Date(organo.mandato_da).toLocaleDateString('it-IT') : '—' }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Scade il</dt><dd>{{ organo.mandato_scadenza ? new Date(organo.mandato_scadenza).toLocaleDateString('it-IT') : '—' }}</dd></div>
                </dl>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <h3 class="px-4 py-3 text-lg font-medium text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700">Cariche sociali</h3>
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Nome</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Ordine</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Assegnatari</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="c in organo.cariche_sociali" :key="c.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-2 font-medium">{{ c.nome }}</td>
                            <td class="px-4 py-2">{{ c.ordine ?? 0 }}</td>
                            <td class="px-4 py-2">
                                <template v-if="showAssignForCaricaId === c.id">
                                    <div class="space-y-2">
                                        <div v-if="membersDisponibiliPerCarica(c).length">
                                            <InputLabel for="incarico_member" value="Aggiungi assegnatario" class="text-xs" />
                                            <select id="incarico_member" v-model="formIncarico.member_id" class="mt-0.5 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                                <option value="">Seleziona socio</option>
                                                <option v-for="m in membersDisponibiliPerCarica(c)" :key="m.id" :value="m.id">{{ m.cognome }} {{ m.nome }}</option>
                                            </select>
                                            <InputError :message="formIncarico.errors.member_id" />
                                        </div>
                                        <p v-else class="text-sm text-gray-500 dark:text-gray-400">Nessun altro socio da assegnare a questa carica.</p>
                                        <div class="flex flex-wrap gap-2">
                                            <button v-if="membersDisponibiliPerCarica(c).length" type="button" class="text-sm text-green-600 dark:text-green-400 hover:underline" :disabled="formIncarico.processing || !formIncarico.member_id" @click="salvaIncarico(c)">Aggiungi</button>
                                            <button type="button" class="text-sm text-gray-500 hover:underline" :disabled="formIncarico.processing" @click="chiudiAssegna">Chiudi</button>
                                        </div>
                                    </div>
                                </template>
                                <template v-else>
                                    <ul class="text-sm space-y-0.5">
                                        <li v-for="inc in (c.incarichi || [])" :key="inc.id" class="flex items-center gap-2">
                                            <Link :href="route('members.show', inc.member_id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ inc.member?.cognome }} {{ inc.member?.nome }}</Link>
                                            <button type="button" class="text-xs text-red-600 dark:text-red-400 hover:underline" @click="rimuoviIncarico(inc)">Rimuovi</button>
                                        </li>
                                    </ul>
                                    <button v-if="puoAggiungereAssegnatario(c)" type="button" class="mt-1 text-xs text-indigo-600 dark:text-indigo-400 hover:underline" @click="avviaAssegna(c)">
                                        {{ c.multiplo && (c.incarichi || []).length ? 'Aggiungi altro' : 'Assegna socio' }}
                                    </button>
                                    <span v-if="!c.incarichi?.length && showAssignForCaricaId !== c.id && !puoAggiungereAssegnatario(c)" class="text-gray-500 text-sm">—</span>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!organo.cariche_sociali?.length" class="px-4 py-8 text-center text-gray-500">Nessuna carica.</div>
            </div>
        </div>
    </AppLayout>
</template>
