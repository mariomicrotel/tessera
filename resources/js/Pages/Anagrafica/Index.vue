<script setup>
import { ref, computed } from 'vue';
import {
    BuildingOffice2Icon,
    ScaleIcon,
    CalculatorIcon,
    WrenchScrewdriverIcon,
    DocumentArrowUpIcon,
    DocumentArrowDownIcon,
    TrashIcon,
    CheckIcon,
    ArrowLeftIcon,
    PaperClipIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    tenant:   Object,
    settings: Object,
    labels:   Object,
    allegati: Object,
});

const activeTab = ref('civilistico');

const tabs = [
    { id: 'civilistico',   label: 'Civilistico',    icon: ScaleIcon },
    { id: 'fiscale',       label: 'Fiscale',         icon: CalculatorIcon },
    { id: 'amministrativo',label: 'Amministrativo',  icon: WrenchScrewdriverIcon },
];

const isEts  = computed(() => props.tenant.organization_type !== 'cooperative');
const isCoop = computed(() => props.tenant.organization_type === 'cooperative');

// ─── Form unico per tutti i campi (Tenant + Settings) ───────────────────────
const form = useForm({
    // Settings
    nome_associazione:                  props.settings.nome_associazione ?? '',
    email_associazione:                 props.settings.email_associazione ?? '',
    legale_rappresentante_associazione: props.settings.legale_rappresentante_associazione ?? '',
    data_costituzione_associazione:     props.settings.data_costituzione_associazione ?? '',
    ets_è_odv:                          props.settings['ets_è_odv'] ?? false,
    luogo_emissione_ricevute:           props.settings.luogo_emissione_ricevute ?? '',
    // Tenant — Civilistico
    codice_fiscale:               props.tenant.codice_fiscale ?? '',
    partita_iva:                  props.tenant.partita_iva ?? '',
    rea_numero:                   props.tenant.rea_numero ?? '',
    rea_citta:                    props.tenant.rea_citta ?? '',
    indirizzo:                    props.tenant.indirizzo ?? '',
    cap:                          props.tenant.cap ?? '',
    citta:                        props.tenant.citta ?? '',
    provincia:                    props.tenant.provincia ?? '',
    nazione:                      props.tenant.nazione ?? 'Italia',
    personalita_giuridica:        props.tenant.personalita_giuridica ?? false,
    patrimonio_destinato:         props.tenant.patrimonio_destinato ?? '',
    numero_iscrizione_albo_coop:  props.tenant.numero_iscrizione_albo_coop ?? '',
    // Tenant — Fiscale
    attivita_ateco:               props.tenant.attivita_ateco ?? '',
    runts_numero:                 props.tenant.runts_numero ?? '',
    runts_sezione:                props.tenant.runts_sezione ?? '',
    runts_data_iscrizione:        props.tenant.runts_data_iscrizione ?? '',
    fascia_entrate:               props.tenant.fascia_entrate ?? '',
    // Tenant — Amministrativo
    pec:                                props.tenant.pec ?? '',
    telefono:                           props.tenant.telefono ?? '',
    sito_web:                           props.tenant.sito_web ?? '',
    assicurazione_volontari_polizza:    props.tenant.assicurazione_volontari_polizza ?? '',
    assicurazione_volontari_compagnia:  props.tenant.assicurazione_volontari_compagnia ?? '',
    assicurazione_volontari_scadenza:   props.tenant.assicurazione_volontari_scadenza ?? '',
    bilancio_url_pubblicazione:         props.tenant.bilancio_url_pubblicazione ?? '',
});

// ─── Allegati ────────────────────────────────────────────────────────────────
const allegatiConfig = {
    statuto:            { label: 'Statuto / Atto costitutivo', section: 'civilistico' },
    visura_camerale:    { label: 'Visura camerale',            section: 'civilistico' },
    durc:               { label: 'DURC',                       section: 'fiscale' },
    certificato_ateco:  { label: 'Certificato ATECO',          section: 'fiscale' },
    bilancio_approvato: { label: 'Bilancio approvato',         section: 'fiscale' },
    polizza_volontari:  { label: 'Polizza assicurativa volontari', section: 'amministrativo' },
};

const fileInputRefs = ref({});
const uploadingTag = ref(null);
const uploadErrors = ref({});

function allegatiFiltrati(section) {
    return Object.entries(allegatiConfig).filter(([, cfg]) => cfg.section === section);
}

function openFileInput(tag) {
    fileInputRefs.value[tag]?.click();
}

function handleFileChange(event, tag) {
    const file = event.target?.files?.[0];
    if (!file) return;
    uploadingTag.value = tag;
    uploadErrors.value[tag] = null;
    const formData = new FormData();
    formData.append('file', file);
    router.post(route('anagrafica.allegato.upload', { tipo: tag }), formData, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            uploadingTag.value = null;
            if (fileInputRefs.value[tag]) fileInputRefs.value[tag].value = '';
        },
        onError: (errors) => {
            uploadingTag.value = null;
            uploadErrors.value[tag] = errors.file || 'Errore nel caricamento.';
        },
    });
}

function deleteAllegato(tag) {
    if (!confirm('Rimuovere il documento?')) return;
    router.delete(route('anagrafica.allegato.delete', { tipo: tag }), { preserveScroll: true });
}

function fmtSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

const fasciaEntrateLevels = [
    { value: 'sotto_60k',  label: 'Fino a 60.000 €' },
    { value: 'sotto_220k', label: '60.000 € – 220.000 €' },
    { value: 'sotto_1m',   label: '220.000 € – 1.000.000 €' },
    { value: 'sopra_1m',   label: 'Oltre 1.000.000 €' },
];

const runtsSezioni = [
    { value: 'odv',          label: 'OdV – Organizzazioni di Volontariato' },
    { value: 'aps',          label: 'APS – Associazioni di Promozione Sociale' },
    { value: 'enti_filantropici', label: 'Enti Filantropici' },
    { value: 'imprese_sociali',   label: 'Imprese Sociali / Cooperative Sociali' },
    { value: 'reti_associative',  label: 'Reti Associative' },
    { value: 'enti_religiosi',    label: 'Enti Religiosi Civilmente Riconosciuti' },
    { value: 'ets_altro',         label: 'Altri ETS' },
];
</script>

<template>
    <AppLayout title="Anagrafica">
        <Head title="Anagrafica" />
        <template #header>
            <div class="flex items-center gap-3">
                <BuildingOffice2Icon class="size-6 text-gray-500 dark:text-gray-400" aria-hidden="true" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Anagrafica — {{ settings.nome_associazione || tenant.name }}
                </h2>
            </div>
        </template>

        <div class="py-6 max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form @submit.prevent="form.put(route('anagrafica.update'))" class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">

                <!-- Tab bar -->
                <nav class="flex border-b border-gray-200 dark:border-gray-600 overflow-x-auto" aria-label="Sezioni anagrafica">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        :class="[
                            'flex items-center gap-2 px-5 py-3 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition-colors',
                            activeTab === tab.id
                                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300',
                        ]"
                        @click="activeTab = tab.id"
                    >
                        <component :is="tab.icon" class="size-4" aria-hidden="true" />
                        {{ tab.label }}
                    </button>
                </nav>

                <div class="p-6 space-y-8">

                    <!-- ══════════════════════════════════════════════════════════
                         TAB 1 — CIVILISTICO
                    ══════════════════════════════════════════════════════════ -->
                    <div v-show="activeTab === 'civilistico'" class="space-y-6">

                        <!-- Denominazione -->
                        <section class="space-y-4">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Denominazione</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2">
                                    <InputLabel for="nome_associazione" value="Denominazione / Ragione sociale" />
                                    <TextInput id="nome_associazione" v-model="form.nome_associazione" type="text" class="mt-1 block w-full" />
                                    <InputError class="mt-1" :message="form.errors.nome_associazione" />
                                </div>
                                <div>
                                    <InputLabel value="Forma giuridica" />
                                    <div class="mt-1 px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded-md text-sm text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600">
                                        {{ labels.forma_giuridica || '—' }}
                                    </div>
                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Impostata nel wizard — modificabile solo dall'amministratore SaaS.</p>
                                </div>
                                <div>
                                    <InputLabel for="data_costituzione_associazione" value="Data di costituzione" />
                                    <input id="data_costituzione_associazione" v-model="form.data_costituzione_associazione" type="date" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 rounded-md shadow-sm" />
                                    <InputError class="mt-1" :message="form.errors.data_costituzione_associazione" />
                                </div>
                            </div>
                        </section>

                        <!-- Sede legale -->
                        <section class="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Sede legale</h3>
                            <div>
                                <InputLabel for="indirizzo" value="Indirizzo" />
                                <TextInput id="indirizzo" v-model="form.indirizzo" type="text" class="mt-1 block w-full" placeholder="Via / Piazza, n°" />
                                <InputError class="mt-1" :message="form.errors.indirizzo" />
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                <div>
                                    <InputLabel for="cap" value="CAP" />
                                    <TextInput id="cap" v-model="form.cap" type="text" class="mt-1 block w-full" maxlength="10" />
                                    <InputError class="mt-1" :message="form.errors.cap" />
                                </div>
                                <div class="col-span-2">
                                    <InputLabel for="citta" value="Città" />
                                    <TextInput id="citta" v-model="form.citta" type="text" class="mt-1 block w-full" />
                                    <InputError class="mt-1" :message="form.errors.citta" />
                                </div>
                                <div>
                                    <InputLabel for="provincia" value="Prov." />
                                    <TextInput id="provincia" v-model="form.provincia" type="text" class="mt-1 block w-full" maxlength="4" placeholder="RM" />
                                    <InputError class="mt-1" :message="form.errors.provincia" />
                                </div>
                            </div>
                            <div class="max-w-xs">
                                <InputLabel for="nazione" value="Nazione" />
                                <TextInput id="nazione" v-model="form.nazione" type="text" class="mt-1 block w-full" />
                                <InputError class="mt-1" :message="form.errors.nazione" />
                            </div>
                        </section>

                        <!-- REA e Personalità giuridica -->
                        <section class="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">REA e personalità giuridica</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="rea_numero" value="Numero REA" />
                                    <TextInput id="rea_numero" v-model="form.rea_numero" type="text" class="mt-1 block w-full" placeholder="es. RM-1234567" />
                                    <InputError class="mt-1" :message="form.errors.rea_numero" />
                                </div>
                                <div>
                                    <InputLabel for="rea_citta" value="CCIAA (città)" />
                                    <TextInput id="rea_citta" v-model="form.rea_citta" type="text" class="mt-1 block w-full" placeholder="es. Roma" />
                                    <InputError class="mt-1" :message="form.errors.rea_citta" />
                                </div>
                            </div>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="form.personalita_giuridica" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Personalità giuridica riconosciuta</span>
                            </label>
                            <div v-if="form.personalita_giuridica" class="max-w-xs">
                                <InputLabel for="patrimonio_destinato" value="Patrimonio minimo destinato (€)" />
                                <TextInput id="patrimonio_destinato" v-model="form.patrimonio_destinato" type="number" step="0.01" min="0" class="mt-1 block w-full" placeholder="15000.00" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Art. 22 CTS: min €15.000 (associazioni) o €30.000 (fondazioni).</p>
                                <InputError class="mt-1" :message="form.errors.patrimonio_destinato" />
                            </div>
                            <!-- Solo cooperative -->
                            <div v-if="isCoop" class="max-w-sm">
                                <InputLabel for="numero_iscrizione_albo_coop" value="Numero iscrizione Albo Cooperative" />
                                <TextInput id="numero_iscrizione_albo_coop" v-model="form.numero_iscrizione_albo_coop" type="text" class="mt-1 block w-full" />
                                <InputError class="mt-1" :message="form.errors.numero_iscrizione_albo_coop" />
                            </div>
                        </section>

                        <!-- Allegati sezione Civilistico -->
                        <AllegatiSection :tags="allegatiFiltrati('civilistico')" :allegati="allegati" :uploading-tag="uploadingTag" :upload-errors="uploadErrors" :file-input-refs="fileInputRefs" @open="openFileInput" @change="handleFileChange" @delete="deleteAllegato" :fmt-size="fmtSize" />
                    </div>

                    <!-- ══════════════════════════════════════════════════════════
                         TAB 2 — FISCALE
                    ══════════════════════════════════════════════════════════ -->
                    <div v-show="activeTab === 'fiscale'" class="space-y-6">

                        <!-- Codici fiscali -->
                        <section class="space-y-4">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Identificativi fiscali</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="codice_fiscale" value="Codice fiscale" />
                                    <TextInput id="codice_fiscale" v-model="form.codice_fiscale" type="text" class="mt-1 block w-full" maxlength="16" />
                                    <InputError class="mt-1" :message="form.errors.codice_fiscale" />
                                </div>
                                <div>
                                    <InputLabel for="partita_iva" value="Partita IVA" />
                                    <TextInput id="partita_iva" v-model="form.partita_iva" type="text" class="mt-1 block w-full" maxlength="20" />
                                    <InputError class="mt-1" :message="form.errors.partita_iva" />
                                </div>
                                <div>
                                    <InputLabel for="attivita_ateco" value="Codice ATECO" />
                                    <TextInput id="attivita_ateco" v-model="form.attivita_ateco" type="text" class="mt-1 block w-full" maxlength="10" placeholder="es. 94.99.9" />
                                    <InputError class="mt-1" :message="form.errors.attivita_ateco" />
                                </div>
                            </div>
                        </section>

                        <!-- Regimi (read-only dal wizard) -->
                        <section class="space-y-3 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Regimi contabili e IVA</h3>
                            <p class="text-xs text-gray-400 dark:text-gray-500">Impostati nel wizard — modificabili solo dall'amministratore SaaS.</p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg p-3">
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Dimensione bilancio</div>
                                    <div class="text-sm text-gray-800 dark:text-gray-200">{{ labels.dimensione_bilancio || '—' }}</div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg p-3">
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Regime contabile</div>
                                    <div class="text-sm text-gray-800 dark:text-gray-200">{{ labels.regime_contabile || '—' }}</div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg p-3">
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Regime IVA</div>
                                    <div class="text-sm text-gray-800 dark:text-gray-200">{{ labels.regime_iva || '—' }}</div>
                                </div>
                            </div>
                        </section>

                        <!-- RUNTS (solo ETS) -->
                        <section v-if="isEts" class="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">RUNTS — Registro Unico Nazionale del Terzo Settore</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <InputLabel for="runts_numero" value="Numero iscrizione RUNTS" />
                                    <TextInput id="runts_numero" v-model="form.runts_numero" type="text" class="mt-1 block w-full" />
                                    <InputError class="mt-1" :message="form.errors.runts_numero" />
                                </div>
                                <div>
                                    <InputLabel for="runts_sezione" value="Sezione RUNTS" />
                                    <select id="runts_sezione" v-model="form.runts_sezione" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                        <option value="">— Seleziona —</option>
                                        <option v-for="s in runtsSezioni" :key="s.value" :value="s.value">{{ s.label }}</option>
                                    </select>
                                    <InputError class="mt-1" :message="form.errors.runts_sezione" />
                                </div>
                                <div>
                                    <InputLabel for="runts_data_iscrizione" value="Data iscrizione RUNTS" />
                                    <input id="runts_data_iscrizione" v-model="form.runts_data_iscrizione" type="date" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                    <InputError class="mt-1" :message="form.errors.runts_data_iscrizione" />
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="fascia_entrate" value="Fascia entrate (art. 13 CTS)" />
                                    <select id="fascia_entrate" v-model="form.fascia_entrate" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                        <option value="">— Seleziona —</option>
                                        <option v-for="f in fasciaEntrateLevels" :key="f.value" :value="f.value">{{ f.label }}</option>
                                    </select>
                                    <InputError class="mt-1" :message="form.errors.fascia_entrate" />
                                </div>
                                <div class="flex items-end pb-1">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input v-model="form['ets_è_odv']" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700" />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">L'ente è ODV (Organizzazione di Volontariato)</span>
                                    </label>
                                </div>
                            </div>
                        </section>

                        <!-- Allegati sezione Fiscale -->
                        <AllegatiSection :tags="allegatiFiltrati('fiscale')" :allegati="allegati" :uploading-tag="uploadingTag" :upload-errors="uploadErrors" :file-input-refs="fileInputRefs" @open="openFileInput" @change="handleFileChange" @delete="deleteAllegato" :fmt-size="fmtSize" />
                    </div>

                    <!-- ══════════════════════════════════════════════════════════
                         TAB 3 — AMMINISTRATIVO
                    ══════════════════════════════════════════════════════════ -->
                    <div v-show="activeTab === 'amministrativo'" class="space-y-6">

                        <!-- Contatti -->
                        <section class="space-y-4">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Contatti</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="email_associazione" value="Email istituzionale" />
                                    <TextInput id="email_associazione" v-model="form.email_associazione" type="email" class="mt-1 block w-full" />
                                    <InputError class="mt-1" :message="form.errors.email_associazione" />
                                </div>
                                <div>
                                    <InputLabel for="pec" value="PEC" />
                                    <TextInput id="pec" v-model="form.pec" type="email" class="mt-1 block w-full" placeholder="es. nome@pec.it" />
                                    <InputError class="mt-1" :message="form.errors.pec" />
                                </div>
                                <div>
                                    <InputLabel for="telefono" value="Telefono" />
                                    <TextInput id="telefono" v-model="form.telefono" type="text" class="mt-1 block w-full" />
                                    <InputError class="mt-1" :message="form.errors.telefono" />
                                </div>
                                <div>
                                    <InputLabel for="sito_web" value="Sito web" />
                                    <TextInput id="sito_web" v-model="form.sito_web" type="url" class="mt-1 block w-full" placeholder="https://www.esempio.it" />
                                    <InputError class="mt-1" :message="form.errors.sito_web" />
                                </div>
                            </div>
                        </section>

                        <!-- Rappresentanza e ricevute -->
                        <section class="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Rappresentanza legale e ricevute</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="legale_rappresentante_associazione" value="Legale rappresentante" />
                                    <TextInput id="legale_rappresentante_associazione" v-model="form.legale_rappresentante_associazione" type="text" class="mt-1 block w-full" placeholder="Nome e cognome" />
                                    <InputError class="mt-1" :message="form.errors.legale_rappresentante_associazione" />
                                </div>
                                <div>
                                    <InputLabel for="luogo_emissione_ricevute" value="Luogo di emissione ricevute" />
                                    <TextInput id="luogo_emissione_ricevute" v-model="form.luogo_emissione_ricevute" type="text" class="mt-1 block w-full" placeholder="Città (vuoto = usa sede)" />
                                    <InputError class="mt-1" :message="form.errors.luogo_emissione_ricevute" />
                                </div>
                            </div>
                        </section>

                        <!-- Assicurazione volontari (ETS) -->
                        <section v-if="isEts" class="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Assicurazione volontari (art. 18 CTS)</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <InputLabel for="assicurazione_volontari_polizza" value="Numero polizza" />
                                    <TextInput id="assicurazione_volontari_polizza" v-model="form.assicurazione_volontari_polizza" type="text" class="mt-1 block w-full" />
                                    <InputError class="mt-1" :message="form.errors.assicurazione_volontari_polizza" />
                                </div>
                                <div>
                                    <InputLabel for="assicurazione_volontari_compagnia" value="Compagnia assicurativa" />
                                    <TextInput id="assicurazione_volontari_compagnia" v-model="form.assicurazione_volontari_compagnia" type="text" class="mt-1 block w-full" />
                                    <InputError class="mt-1" :message="form.errors.assicurazione_volontari_compagnia" />
                                </div>
                                <div>
                                    <InputLabel for="assicurazione_volontari_scadenza" value="Scadenza polizza" />
                                    <input id="assicurazione_volontari_scadenza" v-model="form.assicurazione_volontari_scadenza" type="date" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                    <InputError class="mt-1" :message="form.errors.assicurazione_volontari_scadenza" />
                                </div>
                            </div>
                            <div v-if="form.assicurazione_volontari_scadenza && new Date(form.assicurazione_volontari_scadenza) < new Date()" class="flex items-center gap-2 rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 p-3 text-xs text-amber-700 dark:text-amber-300">
                                <ExclamationTriangleIcon class="size-4 shrink-0" aria-hidden="true" />
                                La polizza assicurativa risulta scaduta. Rinnovare tempestivamente per garantire la copertura dei volontari.
                            </div>
                            <div>
                                <InputLabel for="bilancio_url_pubblicazione" value="URL pubblicazione bilancio (art. 14 CTS)" />
                                <TextInput id="bilancio_url_pubblicazione" v-model="form.bilancio_url_pubblicazione" type="url" class="mt-1 block w-full" placeholder="https://..." />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Obbligatorio per ETS con entrate &gt; 1.000.000 €.</p>
                                <InputError class="mt-1" :message="form.errors.bilancio_url_pubblicazione" />
                            </div>
                        </section>

                        <!-- Allegati sezione Amministrativo -->
                        <AllegatiSection :tags="allegatiFiltrati('amministrativo')" :allegati="allegati" :uploading-tag="uploadingTag" :upload-errors="uploadErrors" :file-input-refs="fileInputRefs" @open="openFileInput" @change="handleFileChange" @delete="deleteAllegato" :fmt-size="fmtSize" />
                    </div>

                    <!-- Azioni -->
                    <div class="flex gap-2 pt-6 mt-6 border-t border-gray-200 dark:border-gray-600">
                        <PrimaryButton type="submit" :disabled="form.processing">
                            <CheckIcon class="size-4 me-2" aria-hidden="true" />
                            Salva
                        </PrimaryButton>
                        <Link :href="route('dashboard')" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                            <ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />
                            Annulla
                        </Link>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<!-- ═══════════════════════════════════════════════════════════════════════════
     Componente locale: griglia allegati per sezione
═══════════════════════════════════════════════════════════════════════════ -->
<script>
import { defineComponent, h, ref } from 'vue';
import { DocumentArrowUpIcon, DocumentArrowDownIcon, TrashIcon, PaperClipIcon } from '@heroicons/vue/24/outline';

export default defineComponent({
    name: 'AllegatiSection',
    props: {
        tags:           Array,
        allegati:       Object,
        uploadingTag:   String,
        uploadErrors:   Object,
        fileInputRefs:  Object,
        fmtSize:        Function,
    },
    emits: ['open', 'change', 'delete'],
    setup(props, { emit }) {
        return () => {
            if (!props.tags || props.tags.length === 0) return null;
            return h('section', { class: 'space-y-3 pt-4 border-t border-gray-200 dark:border-gray-600' }, [
                h('h3', { class: 'text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide flex items-center gap-2' }, [
                    h(PaperClipIcon, { class: 'size-4', 'aria-hidden': 'true' }),
                    'Allegati',
                ]),
                h('div', { class: 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3' },
                    props.tags.map(([tag, cfg]) => {
                        const att = props.allegati?.[tag];
                        const isUploading = props.uploadingTag === tag;
                        const err = props.uploadErrors?.[tag];
                        return h('div', {
                            key: tag,
                            class: 'border border-gray-200 dark:border-gray-600 rounded-lg p-4 flex flex-col gap-3 bg-white dark:bg-gray-800',
                        }, [
                            // Hidden file input
                            h('input', {
                                type: 'file',
                                accept: '.pdf,.jpg,.jpeg,.png,.doc,.docx',
                                class: 'hidden',
                                ref: (el) => { if (el) props.fileInputRefs[tag] = el; },
                                onChange: (e) => emit('change', e, tag),
                            }),
                            // Header
                            h('div', { class: 'flex items-start justify-between gap-2' }, [
                                h('span', { class: 'text-sm font-medium text-gray-800 dark:text-gray-200 leading-snug' }, cfg.label),
                            ]),
                            // Stato allegato
                            att
                                ? h('div', { class: 'flex flex-col gap-1' }, [
                                    h('div', { class: 'flex items-center gap-2 min-w-0' }, [
                                        h(DocumentArrowDownIcon, { class: 'size-4 shrink-0 text-green-600 dark:text-green-400', 'aria-hidden': 'true' }),
                                        h('span', { class: 'text-xs text-gray-600 dark:text-gray-400 truncate', title: att.original_name }, att.original_name),
                                    ]),
                                    h('div', { class: 'flex items-center gap-2 mt-1' }, [
                                        h('a', {
                                            href: att.url,
                                            target: '_blank',
                                            rel: 'noopener',
                                            class: 'text-xs text-indigo-600 dark:text-indigo-400 hover:underline',
                                        }, 'Scarica'),
                                        h('span', { class: 'text-xs text-gray-400' }, props.fmtSize(att.size)),
                                        h('button', {
                                            type: 'button',
                                            class: 'ml-auto text-red-500 dark:text-red-400 hover:text-red-700',
                                            onClick: () => emit('delete', tag),
                                            title: 'Rimuovi',
                                        }, h(TrashIcon, { class: 'size-4', 'aria-hidden': 'true' })),
                                    ]),
                                ])
                                : h('div', { class: 'text-xs text-gray-400 dark:text-gray-500 italic' }, 'Nessun documento caricato'),
                            // Upload button
                            h('button', {
                                type: 'button',
                                disabled: isUploading,
                                class: 'inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 transition',
                                onClick: () => emit('open', tag),
                            }, [
                                h(DocumentArrowUpIcon, { class: 'size-4', 'aria-hidden': 'true' }),
                                isUploading ? 'Caricamento…' : (att ? 'Sostituisci' : 'Carica documento'),
                            ]),
                            err ? h('p', { class: 'text-xs text-red-600 dark:text-red-400' }, err) : null,
                        ]);
                    })
                ),
            ]);
        };
    },
});
</script>
