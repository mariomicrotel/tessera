<script setup>
import { PencilSquareIcon, ArrowLeftIcon, TrashIcon, PlusIcon, BanknotesIcon, EyeIcon } from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({ member: Object, caricheSociali: { type: Array, default: () => [] }, roles: { type: Array, default: () => [] }, accessError: String, accessSuccess: String });
const page = usePage();
const canManage = computed(() => page.props?.userRoles?.includes('admin') || page.props?.userRoles?.includes('segreteria'));
const canAssignRoles = computed(() => page.props?.userRoles?.includes('admin'));
const canRegisterQuota = computed(() => ['admin', 'segreteria', 'contabile'].some(r => page.props?.userRoles?.includes(r)));
const authMember = computed(() => page.props?.authMember ?? null);
const isOwnProfile = computed(() => authMember.value && Number(authMember.value.id) === Number(props.member.id));
const canEdit = computed(() => canManage.value || isOwnProfile.value);
const hasPersoQualita = computed(() => ['decesso', 'dimesso', 'escluso', 'moroso'].includes(props.member.stato));
const quoteVersate = computed(() => (props.member.incassi || []).filter(i => i.type === 'quota'));

const enableAccess = () => {
    router.post(route('members.enable-access', props.member.id), {}, { preserveScroll: true });
};
const revokeAccess = () => {
    if (!confirm('Revocare l\'accesso area soci? Il socio non potrà più accedere.')) return;
    router.post(route('members.revoke-access', props.member.id), {}, { preserveScroll: true });
};

const showRolesForm = ref(false);
const roleIds = ref([]);
const openRolesForm = () => {
    roleIds.value = (props.member.user?.roles ?? []).map(r => r.id);
    showRolesForm.value = true;
};
const submitUserRoles = () => {
    router.put(route('members.user-roles.update', props.member.id), { role_ids: roleIds.value }, { preserveScroll: true, onSuccess: () => { showRolesForm.value = false; } });
};

const showRejectForm = ref(false);
const rejectMotivo = ref('');
const acceptInviaEmail = ref(true);
const submitAccept = () => {
    router.post(route('members.accept-admission', props.member.id), { invia_email: acceptInviaEmail.value }, { preserveScroll: true });
};
const showAssemblyForm = ref(false);
const assemblyEsito = ref('accolto');
const showDeathForm = ref(false);
const deathDate = ref(new Date().toISOString().slice(0, 10));
const showDimissioniForm = ref(false);
const dimissioniDate = ref(new Date().toISOString().slice(0, 10));
const showEsclusioneForm = ref(false);
const esclusioneMotivo = ref('');
const esclusioneData = ref(new Date().toISOString().slice(0, 10));
const showIncaricoForm = ref(false);
const incaricoForm = ref({ carica_sociale_id: '' });

const submitIncarico = () => {
    if (!incaricoForm.value.carica_sociale_id) return;
    router.post(route('members.incarichi.store', props.member.id), { carica_sociale_id: incaricoForm.value.carica_sociale_id }, { preserveScroll: true });
    showIncaricoForm.value = false;
    incaricoForm.value = { carica_sociale_id: '' };
};
const deleteIncarico = (inc) => {
    if (!confirm('Rimuovere questo incarico?')) return;
    router.delete(route('incarichi.destroy', inc.id), { preserveScroll: true });
};

const deleteMember = () => {
    if (confirm('Eliminare questo socio? Operazione irreversibile.')) {
        router.delete(route('members.destroy', props.member.id));
    }
};

const submitReject = () => {
    if (!rejectMotivo.value.trim()) return;
    router.post(route('members.reject-admission', props.member.id), { rigetto_motivo: rejectMotivo.value }, { preserveScroll: true });
    showRejectForm.value = false;
    rejectMotivo.value = '';
};
const submitAssembly = () => {
    router.post(route('members.assembly-outcome', props.member.id), { esito: assemblyEsito.value }, { preserveScroll: true });
    showAssemblyForm.value = false;
};
const submitDeath = () => {
    router.post(route('members.register-death', props.member.id), { deceduto_at: deathDate.value }, { preserveScroll: true });
    showDeathForm.value = false;
};
const submitDimissioni = () => {
    router.post(route('members.register-dimissioni', props.member.id), { dimissioni_presentate_at: dimissioniDate.value }, { preserveScroll: true });
    showDimissioniForm.value = false;
};
const submitEsclusione = () => {
    if (!esclusioneMotivo.value.trim()) return;
    router.post(route('members.register-esclusione', props.member.id), { motivo_esclusione: esclusioneMotivo.value, data_cessazione: esclusioneData.value }, { preserveScroll: true });
    showEsclusioneForm.value = false;
};
</script>

<template>
    <AppLayout :title="member.cognome + ' ' + member.nome">
        <Head :title="member.cognome + ' ' + member.nome" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ member.cognome }} {{ member.nome }}</h2>
                <div class="flex gap-2">
                    <Link v-if="$page.props.auth.user && canEdit" :href="route('members.edit', member.id)">
                        <PrimaryButton><PencilSquareIcon class="size-4 me-2" aria-hidden="true" />Modifica</PrimaryButton>
                    </Link>
                    <Link v-if="isOwnProfile" :href="route('dashboard')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Torna alla Dashboard</Link>
                    <Link v-else :href="route('members.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Elenco soci</Link>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-5xl mx-auto sm:px-6 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Anagrafica</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Numero tessera</dt><dd>{{ member.numero_tessera ?? '—' }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Tipologia</dt><dd>{{ member.member_type?.display_name || member.member_type?.name }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Email (anagrafica)</dt><dd>{{ member.email || '—' }}</dd></div>
                    <div v-if="member.user"><dt class="text-sm text-gray-500 dark:text-gray-400">Email account (accesso)</dt><dd>{{ member.user.email || '—' }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Codice fiscale</dt><dd>{{ member.codice_fiscale || '—' }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Data di nascita</dt><dd>{{ member.data_nascita ? new Date(member.data_nascita).toLocaleDateString('it-IT') : '—' }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Data iscrizione</dt><dd>{{ member.data_iscrizione ? new Date(member.data_iscrizione).toLocaleDateString('it-IT') : '—' }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Stato</dt><dd><span class="px-2 py-0.5 rounded text-xs" :class="{ 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': member.stato === 'attivo', 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400': member.stato === 'aspirante', 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400': member.stato === 'in_ricorso', 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': ['rigettato', 'escluso', 'decesso'].includes(member.stato), 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300': !['attivo', 'aspirante', 'in_ricorso', 'rigettato', 'escluso', 'decesso'].includes(member.stato) }">{{ member.stato }}</span></dd></div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Quota sociale</dt>
                        <dd class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span v-if="member.in_regola_con_quota" class="inline-flex items-center px-2 py-1 rounded text-sm bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">In regola con la quota sociale</span>
                            <span v-else class="inline-flex items-center px-2 py-1 rounded text-sm bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">Non in regola con la quota sociale</span>
                            <Link v-if="canRegisterQuota && !member.in_regola_con_quota" :href="route('incassi.create', { member_id: member.id, type: 'quota' })" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                                <BanknotesIcon class="size-4" aria-hidden="true" />Registra pagamento quota
                            </Link>
                        </dd>
                    </div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Telefono</dt><dd>{{ member.telefono || '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-sm text-gray-500 dark:text-gray-400">Indirizzo</dt><dd>{{ member.indirizzo || '—' }}</dd></div>
                    <div v-if="member.note" class="sm:col-span-2"><dt class="text-sm text-gray-500 dark:text-gray-400">Note</dt><dd class="whitespace-pre-wrap">{{ member.note }}</dd></div>
                </dl>
            </div>

            <!-- Quote sociali versate -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Quote sociali versate</h3>
                <template v-if="quoteVersate.length">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Importo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Conto</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="inc in quoteVersate" :key="inc.id">
                                <td class="px-4 py-2">{{ inc.paid_at ? new Date(inc.paid_at).toLocaleDateString('it-IT') : '—' }}</td>
                                <td class="px-4 py-2 text-right">€ {{ Number(inc.amount).toFixed(2) }}</td>
                                <td class="px-4 py-2">{{ inc.conto?.name ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <Link :href="route('incassi.show', inc.id)" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                                        <EyeIcon class="size-4" aria-hidden="true" />Dettaglio
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
                <p v-else class="text-gray-500 dark:text-gray-400 text-sm py-2">Nessuna quota registrata.</p>
            </div>

            <!-- Accesso area soci (solo staff, solo socio attivo) -->
            <div v-if="canManage && member.stato === 'attivo'" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Accesso area soci</h3>
                <p v-if="member.user_id" class="text-sm text-gray-600 dark:text-gray-400 mb-2">Il socio può accedere all’area riservata con il proprio account.</p>
                <p v-else class="text-sm text-gray-600 dark:text-gray-400 mb-2">Il socio non ha ancora accesso all’area riservata.</p>
                <div v-if="accessError" class="mb-3 rounded-md bg-red-50 dark:bg-red-900/20 px-3 py-2 text-sm text-red-800 dark:text-red-300">
                    {{ accessError }}
                </div>
                <div v-if="accessSuccess" class="mb-3 rounded-md bg-green-50 dark:bg-green-900/20 px-3 py-2 text-sm text-green-800 dark:text-green-300">
                    {{ accessSuccess }}
                </div>
                <div class="flex flex-wrap gap-2 items-center">
                    <form v-if="!member.user_id" method="post" :action="route('members.enable-access', member.id)" @submit.prevent="enableAccess" class="inline">
                        <PrimaryButton type="submit">Attiva accesso area soci</PrimaryButton>
                    </form>
                    <template v-else>
                        <form v-if="!isOwnProfile" method="post" :action="route('members.revoke-access', member.id)" @submit.prevent="revokeAccess" class="inline">
                            <SecondaryButton type="submit">Revoca accesso</SecondaryButton>
                        </form>
                        <p v-if="canAssignRoles || (member.user?.roles?.length)" class="text-sm text-gray-600 dark:text-gray-400">
                            Ruoli utente: {{ (member.user?.roles ?? []).map(r => r.display_name).join(', ') || '—' }}
                        </p>
                        <PrimaryButton v-if="canAssignRoles && !showRolesForm" type="button" @click="openRolesForm">Assegna ruoli</PrimaryButton>
                    </template>
                </div>
                <form v-if="showRolesForm" @submit.prevent="submitUserRoles" class="mt-4 p-4 rounded-lg border border-gray-200 dark:border-gray-600 space-y-3">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Seleziona i ruoli da assegnare</p>
                    <div class="flex flex-wrap gap-4">
                        <label v-for="r in roles" :key="r.id" class="inline-flex items-center gap-2 cursor-pointer">
                            <input v-model="roleIds" type="checkbox" :value="r.id" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ r.display_name }}</span>
                        </label>
                    </div>
                    <div class="flex gap-2">
                        <PrimaryButton type="submit">Salva ruoli</PrimaryButton>
                        <SecondaryButton type="button" @click="showRolesForm = false">Annulla</SecondaryButton>
                    </div>
                </form>
            </div>


            <!-- Cariche sociali (solo staff, solo socio attivo) -->
            <div v-if="canManage && member.stato === 'attivo'" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Cariche sociali</h3>
                    <PrimaryButton v-if="!showIncaricoForm" type="button" @click="showIncaricoForm = true"><PlusIcon class="size-4 me-2" aria-hidden="true" />Assegna carica</PrimaryButton>
                </div>
                <form v-if="showIncaricoForm" @submit.prevent="submitIncarico" class="mb-4 p-4 rounded-lg border border-gray-200 dark:border-gray-600 space-y-3">
                    <div class="flex flex-wrap gap-3 items-end">
                        <div class="min-w-[200px]">
                            <InputLabel for="carica_sociale_id" value="Carica *" />
                            <select id="carica_sociale_id" v-model="incaricoForm.carica_sociale_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm" required>
                                <option value="">Seleziona carica</option>
                                <option v-for="c in caricheSociali" :key="c.id" :value="c.id">{{ c.nome }} — {{ c.organo?.nome }}</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <PrimaryButton type="submit">Assegna</PrimaryButton>
                            <SecondaryButton type="button" @click="showIncaricoForm = false">Annulla</SecondaryButton>
                        </div>
                    </div>
                </form>
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Carica</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Organo</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="inc in member.incarichi" :key="inc.id">
                            <td class="px-4 py-2 font-medium">{{ inc.carica_sociale?.nome }}</td>
                            <td class="px-4 py-2">{{ inc.carica_sociale?.organo?.nome ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">
                                <button type="button" class="text-sm text-red-600 dark:text-red-400 hover:underline" @click="deleteIncarico(inc)">Rimuovi</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!member.incarichi?.length && !showIncaricoForm" class="text-gray-500 text-sm py-2">Nessuna carica assegnata.</p>
            </div>

            <!-- Ammissione (Parte B) -->
            <div v-if="member.domanda_presentata_at || member.ammissione_decisa_at || member.stato === 'aspirante' || member.stato === 'rigettato' || member.stato === 'in_ricorso'" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Ammissione</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm mb-4">
                    <div v-if="member.domanda_presentata_at"><dt class="text-gray-500 dark:text-gray-400">Domanda presentata</dt><dd>{{ new Date(member.domanda_presentata_at).toLocaleDateString('it-IT') }}</dd></div>
                    <div v-if="member.ammissione_decisa_at"><dt class="text-gray-500 dark:text-gray-400">Decisione CD</dt><dd>{{ new Date(member.ammissione_decisa_at).toLocaleDateString('it-IT') }} — {{ member.ammissione_esito }}</dd></div>
                    <div v-if="member.rigetto_motivo"><dt class="text-gray-500 dark:text-gray-400">Motivo rigetto</dt><dd class="whitespace-pre-wrap">{{ member.rigetto_motivo }}</dd></div>
                    <div v-if="member.rigetto_comunicato_at"><dt class="text-gray-500 dark:text-gray-400">Rigetto comunicato il</dt><dd>{{ new Date(member.rigetto_comunicato_at).toLocaleDateString('it-IT') }}</dd></div>
                    <div v-if="member.ricorso_presentato_at"><dt class="text-gray-500 dark:text-gray-400">Ricorso presentato il</dt><dd>{{ new Date(member.ricorso_presentato_at).toLocaleDateString('it-IT') }}</dd></div>
                    <div v-if="member.assemblea_esame_data"><dt class="text-gray-500 dark:text-gray-400">Esame assemblea</dt><dd>{{ new Date(member.assemblea_esame_data).toLocaleDateString('it-IT') }}</dd></div>
                </dl>
                <div v-if="canManage && member.stato === 'aspirante'" class="flex flex-wrap items-center gap-2">
                    <form @submit.prevent="submitAccept" class="inline-flex flex-wrap items-center gap-2">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" v-model="acceptInviaEmail" class="rounded border-gray-300 dark:border-gray-600" />
                            Invia email di notifica al socio
                        </label>
                        <PrimaryButton type="submit">Accogli domanda</PrimaryButton>
                    </form>
                    <template v-if="!showRejectForm">
                        <SecondaryButton type="button" @click="showRejectForm = true">Rigetta domanda</SecondaryButton>
                    </template>
                    <form v-else @submit.prevent="submitReject" class="inline-flex flex-col gap-2 max-w-md">
                        <InputLabel for="rigetto_motivo" value="Motivo rigetto (obbligatorio)" />
                        <textarea id="rigetto_motivo" v-model="rejectMotivo" rows="3" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm" required></textarea>
                        <div class="flex gap-2">
                            <PrimaryButton type="submit">Conferma rigetto</PrimaryButton>
                            <SecondaryButton type="button" @click="showRejectForm = false">Annulla</SecondaryButton>
                        </div>
                    </form>
                </div>
                <div v-if="canManage && member.stato === 'rigettato' && member.ammissione_decisa_at && !member.rigetto_comunicato_at" class="mt-2">
                    <form @submit.prevent="router.post(route('members.communicate-rejection', member.id))" class="inline">
                        <SecondaryButton type="submit">Comunica rigetto (per iscritto)</SecondaryButton>
                    </form>
                </div>
                <div v-if="canManage && member.stato === 'rigettato' && member.rigetto_comunicato_at" class="mt-2">
                    <form @submit.prevent="router.post(route('members.register-appeal', member.id))" class="inline">
                        <SecondaryButton type="submit">Registra ricorso</SecondaryButton>
                    </form>
                </div>
                <div v-if="canManage && member.stato === 'in_ricorso'" class="mt-2">
                    <template v-if="!showAssemblyForm">
                        <SecondaryButton type="button" @click="showAssemblyForm = true">Esito assemblea</SecondaryButton>
                    </template>
                    <form v-else @submit.prevent="submitAssembly" class="inline-flex flex-col gap-2 max-w-xs mt-2">
                        <InputLabel for="esito" value="Esito" />
                        <select id="esito" v-model="assemblyEsito" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                            <option value="accolto">Accolto</option>
                            <option value="rigettato">Rigettato</option>
                        </select>
                        <div class="flex gap-2">
                            <PrimaryButton type="submit">Conferma</PrimaryButton>
                            <SecondaryButton type="button" @click="showAssemblyForm = false">Annulla</SecondaryButton>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Perdita qualità di socio-->
            <div v-if="canManage && !hasPersoQualita && (member.stato === 'attivo' || member.stato === 'sospeso')" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Perdita qualità di socio</h3>
                <div class="flex flex-wrap gap-2">
                    <template v-if="!showDeathForm">
                        <SecondaryButton type="button" @click="showDeathForm = true">Registra decesso</SecondaryButton>
                    </template>
                    <form v-else @submit.prevent="submitDeath" class="inline-flex flex-col gap-2">
                        <InputLabel for="deceduto_at" value="Data decesso" />
                        <TextInput id="deceduto_at" v-model="deathDate" type="date" class="block w-full" />
                        <div class="flex gap-2">
                            <PrimaryButton type="submit">Conferma</PrimaryButton>
                            <SecondaryButton type="button" @click="showDeathForm = false">Annulla</SecondaryButton>
                        </div>
                    </form>
                    <form @submit.prevent="router.post(route('members.register-morosita', member.id))" class="inline">
                        <SecondaryButton type="submit">Registra perdita per morosità</SecondaryButton>
                    </form>
                    <template v-if="!showDimissioniForm">
                        <SecondaryButton type="button" @click="showDimissioniForm = true">Registra dimissioni</SecondaryButton>
                    </template>
                    <form v-else @submit.prevent="submitDimissioni" class="inline-flex flex-col gap-2">
                        <InputLabel for="dimissioni_presentate_at" value="Data presentazione dimissioni" />
                        <TextInput id="dimissioni_presentate_at" v-model="dimissioniDate" type="date" class="block w-full" />
                        <div class="flex gap-2">
                            <PrimaryButton type="submit">Conferma</PrimaryButton>
                            <SecondaryButton type="button" @click="showDimissioniForm = false">Annulla</SecondaryButton>
                        </div>
                    </form>
                    <template v-if="!showEsclusioneForm">
                        <SecondaryButton type="button" @click="showEsclusioneForm = true">Registra esclusione</SecondaryButton>
                    </template>
                    <form v-else @submit.prevent="submitEsclusione" class="inline-flex flex-col gap-2 max-w-md">
                        <InputLabel for="motivo_esclusione" value="Motivo esclusione" />
                        <textarea id="motivo_esclusione" v-model="esclusioneMotivo" rows="2" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm" required></textarea>
                        <InputLabel for="data_cessazione" value="Data cessazione" />
                        <TextInput id="data_cessazione" v-model="esclusioneData" type="date" class="block w-full" />
                        <div class="flex gap-2">
                            <PrimaryButton type="submit">Conferma</PrimaryButton>
                            <SecondaryButton type="button" @click="showEsclusioneForm = false">Annulla</SecondaryButton>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="canManage" class="flex justify-end">
                <DangerButton @click="deleteMember"><TrashIcon class="size-4 me-2" aria-hidden="true" />Elimina socio</DangerButton>
            </div>
        </div>
    </AppLayout>
</template>
