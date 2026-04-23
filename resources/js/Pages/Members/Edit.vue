<script setup>
import { computed } from 'vue';
import { CheckIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({ member: Object, memberTypes: Array });
const page = usePage();

const canManage    = computed(() => page.props?.userRoles?.includes('admin') || page.props?.userRoles?.includes('segreteria'));
const authMember   = computed(() => page.props?.authMember ?? null);
const isSocioEdit  = computed(() => authMember.value && Number(authMember.value.id) === Number(props.member.id) && !canManage.value);

const isCooperativa = computed(() => page.props.is_cooperativa === true);
const isCoopLavoro  = computed(() => isCooperativa.value && page.props.cooperative_type === 'lavoro');

const formFull = useForm({
    member_type_id:      props.member.member_type_id,
    numero_tessera:      props.member.numero_tessera ?? '',
    tipo_persona:        props.member.tipo_persona ?? 'fisica',
    nome:                props.member.nome ?? '',
    cognome:             props.member.cognome ?? '',
    ragione_sociale:     props.member.ragione_sociale ?? '',
    partita_iva:         props.member.partita_iva ?? '',
    referente_nome:      props.member.referente_nome ?? '',
    referente_cognome:   props.member.referente_cognome ?? '',
    email:               props.member.email ?? '',
    codice_fiscale:      props.member.codice_fiscale ?? '',
    data_nascita:        props.member.data_nascita ? props.member.data_nascita.slice(0, 10) : '',
    data_iscrizione:     props.member.data_iscrizione ? props.member.data_iscrizione.slice(0, 10) : '',
    stato:               props.member.stato ?? 'attivo',
    indirizzo:           props.member.indirizzo ?? '',
    telefono:            props.member.telefono ?? '',
    note:                props.member.note ?? '',
    socio_lavoratore:    props.member.socio_lavoratore ?? false,
    socio_sovventore:    props.member.socio_sovventore ?? false,
    socio_onorario:      props.member.socio_onorario ?? false,
    data_ammissione_cda: props.member.data_ammissione_cda ? props.member.data_ammissione_cda.slice(0, 10) : '',
});

const formSocio = useForm({
    tipo_persona:      props.member.tipo_persona ?? 'fisica',
    nome:              props.member.nome ?? '',
    cognome:           props.member.cognome ?? '',
    referente_nome:    props.member.referente_nome ?? '',
    referente_cognome: props.member.referente_cognome ?? '',
    email:             props.member.email ?? '',
    data_nascita:      props.member.data_nascita ? props.member.data_nascita.slice(0, 10) : '',
    indirizzo:         props.member.indirizzo ?? '',
    telefono:          props.member.telefono ?? '',
});

const isGiuridicaFull  = computed(() => isCooperativa.value && formFull.tipo_persona === 'giuridica');
const isGiuridicaSocio = computed(() => isCooperativa.value && formSocio.tipo_persona === 'giuridica');
</script>

<template>
    <AppLayout title="Modifica socio">
        <Head :title="'Modifica ' + (member.ragione_sociale || member.cognome)" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Modifica socio</h2>
                <Link :href="route('members.show', member.id)" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Annulla</Link>
            </div>
        </template>

        <div class="py-6 max-w-3xl mx-auto sm:px-6">

            <!-- Form ridotta per socio (solo anagrafica personale) -->
            <form v-if="isSocioEdit" @submit.prevent="formSocio.put(route('members.update', member.id))" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">

                <!-- Tipo persona (solo coop) -->
                <div v-show="isCooperativa">
                    <InputLabel value="Tipo socio" />
                    <div class="mt-1 flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="formSocio.tipo_persona" value="fisica" class="text-blue-600 border-gray-300 focus:ring-blue-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">👤 Persona fisica</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="formSocio.tipo_persona" value="giuridica" class="text-blue-600 border-gray-300 focus:ring-blue-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">🏢 Persona giuridica</span>
                        </label>
                    </div>
                </div>

                <!-- Referente (giuridica) -->
                <div v-show="isGiuridicaSocio" class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="s_referente_nome" value="Nome referente" />
                        <TextInput id="s_referente_nome" v-model="formSocio.referente_nome" class="mt-1 block w-full" maxlength="100" />
                        <InputError class="mt-1" :message="formSocio.errors.referente_nome" />
                    </div>
                    <div>
                        <InputLabel for="s_referente_cognome" value="Cognome referente" />
                        <TextInput id="s_referente_cognome" v-model="formSocio.referente_cognome" class="mt-1 block w-full" maxlength="100" />
                        <InputError class="mt-1" :message="formSocio.errors.referente_cognome" />
                    </div>
                </div>

                <div v-show="!isGiuridicaSocio" class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="socio_cognome" value="Cognome *" />
                        <TextInput id="socio_cognome" v-model="formSocio.cognome" class="mt-1 block w-full" :required="!isGiuridicaSocio" />
                        <InputError class="mt-1" :message="formSocio.errors.cognome" />
                    </div>
                    <div>
                        <InputLabel for="socio_nome" value="Nome *" />
                        <TextInput id="socio_nome" v-model="formSocio.nome" class="mt-1 block w-full" :required="!isGiuridicaSocio" />
                        <InputError class="mt-1" :message="formSocio.errors.nome" />
                    </div>
                </div>

                <div>
                    <InputLabel for="socio_email" value="Email (anagrafica socio)" />
                    <TextInput id="socio_email" v-model="formSocio.email" type="email" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="formSocio.errors.email" />
                </div>
                <div v-show="!isGiuridicaSocio">
                    <InputLabel for="socio_data_nascita" value="Data di nascita" />
                    <TextInput id="socio_data_nascita" v-model="formSocio.data_nascita" type="date" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="formSocio.errors.data_nascita" />
                </div>
                <div>
                    <InputLabel for="socio_indirizzo" value="Indirizzo" />
                    <TextInput id="socio_indirizzo" v-model="formSocio.indirizzo" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="formSocio.errors.indirizzo" />
                </div>
                <div>
                    <InputLabel for="socio_telefono" value="Telefono" />
                    <TextInput id="socio_telefono" v-model="formSocio.telefono" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="formSocio.errors.telefono" />
                </div>
                <div class="flex gap-2">
                    <PrimaryButton type="submit" :disabled="formSocio.processing"><CheckIcon class="size-4 me-2" aria-hidden="true" />Salva</PrimaryButton>
                    <Link :href="route('members.show', member.id)" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"><ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla</Link>
                </div>
            </form>

            <!-- Form completa per staff -->
            <form v-else @submit.prevent="formFull.put(route('members.update', member.id))" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">

                <div>
                    <InputLabel for="member_type_id" value="Tipologia *" />
                    <select id="member_type_id" v-model="formFull.member_type_id" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                        <option v-for="t in memberTypes" :key="t.id" :value="t.id">{{ t.display_name || t.name }}</option>
                    </select>
                    <InputError class="mt-1" :message="formFull.errors.member_type_id" />
                </div>

                <div>
                    <InputLabel for="numero_tessera" value="Numero tessera" />
                    <TextInput id="numero_tessera" v-model="formFull.numero_tessera" type="number" min="1" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="formFull.errors.numero_tessera" />
                </div>

                <!-- Tipo persona (solo coop) -->
                <div v-show="isCooperativa">
                    <InputLabel value="Tipo socio *" />
                    <div class="mt-1 flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="formFull.tipo_persona" value="fisica" class="text-blue-600 border-gray-300 focus:ring-blue-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">👤 Persona fisica</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="formFull.tipo_persona" value="giuridica" class="text-blue-600 border-gray-300 focus:ring-blue-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">🏢 Persona giuridica</span>
                        </label>
                    </div>
                    <InputError class="mt-1" :message="formFull.errors.tipo_persona" />
                </div>

                <!-- Campi persona giuridica (solo coop) -->
                <div v-show="isGiuridicaFull" class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-700 space-y-4">
                    <p class="text-xs font-semibold text-purple-700 dark:text-purple-300 uppercase tracking-wide">Dati persona giuridica</p>
                    <div>
                        <InputLabel for="ragione_sociale" value="Ragione sociale *" />
                        <TextInput id="ragione_sociale" v-model="formFull.ragione_sociale" class="mt-1 block w-full" maxlength="200" />
                        <InputError class="mt-1" :message="formFull.errors.ragione_sociale" />
                    </div>
                    <div>
                        <InputLabel for="partita_iva" value="Partita IVA" />
                        <TextInput id="partita_iva" v-model="formFull.partita_iva" class="mt-1 block w-full" maxlength="11" placeholder="11 cifre" />
                        <InputError class="mt-1" :message="formFull.errors.partita_iva" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="referente_nome" value="Nome referente" />
                            <TextInput id="referente_nome" v-model="formFull.referente_nome" class="mt-1 block w-full" maxlength="100" />
                            <InputError class="mt-1" :message="formFull.errors.referente_nome" />
                        </div>
                        <div>
                            <InputLabel for="referente_cognome" value="Cognome referente" />
                            <TextInput id="referente_cognome" v-model="formFull.referente_cognome" class="mt-1 block w-full" maxlength="100" />
                            <InputError class="mt-1" :message="formFull.errors.referente_cognome" />
                        </div>
                    </div>
                </div>

                <!-- Nome / cognome (persona fisica) -->
                <div v-show="!isGiuridicaFull" class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="cognome" value="Cognome *" />
                        <TextInput id="cognome" v-model="formFull.cognome" class="mt-1 block w-full" :required="!isGiuridicaFull" />
                        <InputError class="mt-1" :message="formFull.errors.cognome" />
                    </div>
                    <div>
                        <InputLabel for="nome" value="Nome *" />
                        <TextInput id="nome" v-model="formFull.nome" class="mt-1 block w-full" :required="!isGiuridicaFull" />
                        <InputError class="mt-1" :message="formFull.errors.nome" />
                    </div>
                </div>

                <div>
                    <InputLabel for="email" value="Email (anagrafica socio)" />
                    <TextInput id="email" v-model="formFull.email" type="email" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="formFull.errors.email" />
                </div>

                <div v-show="!isGiuridicaFull">
                    <InputLabel for="codice_fiscale" value="Codice fiscale" />
                    <TextInput id="codice_fiscale" v-model="formFull.codice_fiscale" class="mt-1 block w-full" maxlength="64" />
                    <InputError class="mt-1" :message="formFull.errors.codice_fiscale" />
                </div>

                <div v-show="!isGiuridicaFull">
                    <InputLabel for="data_nascita" value="Data di nascita" />
                    <TextInput id="data_nascita" v-model="formFull.data_nascita" type="date" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="formFull.errors.data_nascita" />
                </div>

                <!-- Qualifica cooperativa -->
                <div v-show="isCooperativa" class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700 space-y-3">
                    <p class="text-xs font-semibold text-blue-700 dark:text-blue-300 uppercase tracking-wide">Qualifica cooperativa</p>
                    <label v-show="isCoopLavoro" class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" v-model="formFull.socio_lavoratore" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Socio lavoratore</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" v-model="formFull.socio_sovventore" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Socio sovventore <span class="text-xs text-gray-400">(solo investimento)</span></span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" v-model="formFull.socio_onorario" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Socio onorario</span>
                    </label>
                    <div>
                        <InputLabel for="data_ammissione_cda" value="Data approvazione CDA" />
                        <TextInput id="data_ammissione_cda" v-model="formFull.data_ammissione_cda" type="date" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="formFull.errors.data_ammissione_cda" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="data_iscrizione" value="Data iscrizione" />
                        <TextInput id="data_iscrizione" v-model="formFull.data_iscrizione" type="date" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="formFull.errors.data_iscrizione" />
                    </div>
                    <div>
                        <InputLabel for="stato" value="Stato" />
                        <select id="stato" v-model="formFull.stato" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                            <option value="aspirante">Aspirante</option>
                            <option value="attivo">Attivo</option>
                            <option value="sospeso">Sospeso</option>
                            <option value="cessato">Cessato</option>
                            <option value="rigettato">Rigettato</option>
                            <option value="in_ricorso">In ricorso</option>
                            <option value="decesso">Decesso</option>
                            <option value="dimesso">Dimesso</option>
                            <option value="escluso">Escluso</option>
                            <option value="moroso">Moroso</option>
                        </select>
                    </div>
                </div>

                <div>
                    <InputLabel for="indirizzo" value="Indirizzo" />
                    <TextInput id="indirizzo" v-model="formFull.indirizzo" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel for="telefono" value="Telefono" />
                    <TextInput id="telefono" v-model="formFull.telefono" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel for="note" value="Note" />
                    <textarea id="note" v-model="formFull.note" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm"></textarea>
                </div>

                <div class="flex gap-2">
                    <PrimaryButton type="submit" :disabled="formFull.processing"><CheckIcon class="size-4 me-2" aria-hidden="true" />Salva</PrimaryButton>
                    <Link :href="route('members.show', member.id)" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"><ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
