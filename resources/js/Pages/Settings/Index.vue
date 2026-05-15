<script setup>
import { ref, computed } from 'vue';
import { CheckIcon, ArrowLeftIcon, BanknotesIcon, BuildingLibraryIcon, BuildingOfficeIcon, CurrencyEuroIcon, DocumentTextIcon, EnvelopeIcon, EyeIcon, PhotoIcon, TrashIcon, GlobeAltIcon, ShieldCheckIcon, CreditCardIcon, SignalIcon, ArrowPathIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';

const props = defineProps({
    quota_annuale:  [Number, String],
    quota_mensile:  [Number, String],
    quota_ingresso: [Number, String],
    nome_associazione: String,
    indirizzo_associazione: String,
    email_associazione: String,
    pec_associazione: String,
    codice_fiscale_associazione: String,
    partita_iva_associazione: String,
    legale_rappresentante_associazione: String,
    data_costituzione_associazione: String,
    data_iscrizione_runts: String,
    ets_è_odv: Boolean,
    luogo_emissione_ricevute: String,
    causale_default_donazione: String,
    causale_default_quota: String,
    causale_default_rimborso: String,
    logo: Object,
    mailConfig: Object,
    site_sections: Array,
    site_sections_list: Object,
    section_styles: Object,
    site_hero_title: String,
    site_hero_subtitle: String,
    site_chi_siamo_text: String,
    site_footer_text: String,
    informativa_privacy_domanda_ammissione: String,
    // Banking & Tessere
    tessera_colore: String,
    codice_sia: String,
    cab_banca: String,
    cc_banca: String,
    // Cooperative
    quota_valore_unitario_coop:       [Number, String],
    quota_minima_quote_coop:          [Number, String],
    ristorno_percentuale_max:         [Number, String],
    riserva_legale_percentuale:       [Number, String],
    riserva_indivisibile_percentuale: [Number, String],
    tasso_interesse_prestito:         [Number, String],
    is_cooperativa:                   Boolean,
    // OpenAPI Company
    openapi_company_token_set:        Boolean,
    openapi_company_daily_limit:      [Number, String],
});

const page = usePage();

const activeTab = ref('quota');
const logoFileInput = ref(null);
const logoUploading = ref(false);
const logoError = ref('');

function uploadLogo(event) {
    const file = event.target?.files?.[0];
    if (!file) return;
    logoError.value = '';
    logoUploading.value = true;
    const formData = new FormData();
    formData.append('logo', file);
    router.post(route('settings.logo.upload'), formData, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => { logoUploading.value = false; if (logoFileInput.value) logoFileInput.value.value = ''; },
        onError: (errors) => { logoError.value = errors.logo || 'Errore nel caricamento.'; },
    });
}

function removeLogo() {
    if (!confirm('Rimuovere il logo?')) return;
    router.delete(route('settings.logo.delete'), { preserveScroll: true });
}

const tabs = computed(() => [
    { id: 'quota', label: 'Quota', icon: BanknotesIcon },
    { id: 'associazione', label: 'Dati associazione', icon: BuildingOfficeIcon },
    { id: 'causali', label: 'Causali', icon: DocumentTextIcon },
    { id: 'email', label: 'Email', icon: EnvelopeIcon },
    { id: 'sito', label: 'Sito pubblico', icon: GlobeAltIcon },
    { id: 'banking', label: 'Banking & Tessere', icon: CreditCardIcon },
    { id: 'privacy', label: 'Privacy', icon: ShieldCheckIcon },
    ...(props.is_cooperativa ? [{ id: 'cooperativa', label: 'Cooperativa', icon: BuildingLibraryIcon }] : []),
    { id: 'api', label: 'API', icon: SignalIcon },
]);

const testEmailSending = ref(false);
const testEmailRecipient = ref(page.props.auth?.user?.email ?? '');
function sendTestEmail() {
    testEmailSending.value = true;
    router.post(route('settings.test-email'), { email: testEmailRecipient.value || undefined }, {
        preserveScroll: true,
        onFinish: () => { testEmailSending.value = false; },
    });
}

const sectionIds = computed(() => Object.keys(props.site_sections_list || {}));

const sectionColorFields = computed(() => {
    const out = {};
    for (const id of sectionIds.value) {
        const s = (props.section_styles || {})[id] || {};
        out['site_section_' + id + '_bg_color'] = s.bg_color ?? '';
        out['site_section_' + id + '_text_color'] = s.text_color ?? '';
    }
    return out;
});

const form = useForm({
    quota_annuale:  props.quota_annuale  != null ? String(Number(props.quota_annuale).toFixed(2))  : '0.00',
    quota_mensile:  props.quota_mensile  != null ? String(Number(props.quota_mensile).toFixed(2))  : '0.00',
    quota_ingresso: props.quota_ingresso != null ? String(Number(props.quota_ingresso).toFixed(2)) : '0.00',
    nome_associazione: props.nome_associazione ?? '',
    indirizzo_associazione: props.indirizzo_associazione ?? '',
    email_associazione: props.email_associazione ?? '',
    pec_associazione: props.pec_associazione ?? '',
    codice_fiscale_associazione: props.codice_fiscale_associazione ?? '',
    partita_iva_associazione: props.partita_iva_associazione ?? '',
    legale_rappresentante_associazione: props.legale_rappresentante_associazione ?? '',
    data_costituzione_associazione: props.data_costituzione_associazione ?? '',
    data_iscrizione_runts: props.data_iscrizione_runts ?? '',
    ets_è_odv: props.ets_è_odv ?? false,
    luogo_emissione_ricevute: props.luogo_emissione_ricevute ?? '',
    causale_default_donazione: props.causale_default_donazione ?? '',
    causale_default_quota: props.causale_default_quota ?? '',
    causale_default_rimborso: props.causale_default_rimborso ?? '',
    site_sections: props.site_sections ?? [],
    site_hero_title: props.site_hero_title ?? '',
    site_hero_subtitle: props.site_hero_subtitle ?? '',
    site_chi_siamo_text: props.site_chi_siamo_text ?? '',
    site_footer_text: props.site_footer_text ?? '',
    informativa_privacy_domanda_ammissione: props.informativa_privacy_domanda_ammissione ?? '',
    ...sectionColorFields.value,
    // Banking & Tessere
    tessera_colore: props.tessera_colore ?? '#1e40af',
    codice_sia: props.codice_sia ?? '',
    cab_banca: props.cab_banca ?? '',
    cc_banca: props.cc_banca ?? '',
    // Cooperative
    quota_valore_unitario_coop:       props.quota_valore_unitario_coop       != null ? String(Number(props.quota_valore_unitario_coop).toFixed(2))       : '50.00',
    quota_minima_quote_coop:          props.quota_minima_quote_coop          != null ? String(parseInt(props.quota_minima_quote_coop))                    : '1',
    ristorno_percentuale_max:         props.ristorno_percentuale_max         != null ? String(Number(props.ristorno_percentuale_max).toFixed(2))          : '100.00',
    riserva_legale_percentuale:       props.riserva_legale_percentuale       != null ? String(Number(props.riserva_legale_percentuale).toFixed(2))        : '30.00',
    riserva_indivisibile_percentuale: props.riserva_indivisibile_percentuale != null ? String(Number(props.riserva_indivisibile_percentuale).toFixed(2))  : '3.00',
    tasso_interesse_prestito:         props.tasso_interesse_prestito         != null ? String(Number(props.tasso_interesse_prestito).toFixed(4))          : '0.0000',
});

function toggleSiteSection(id) {
    const current = form.site_sections || [];
    if (current.includes(id)) {
        form.site_sections = current.filter((s) => s !== id);
    } else {
        form.site_sections = [...current, id];
    }
}

const sectionBgFileInput = ref(null);
const sectionBgUploading = ref(null); // sectionId when uploading
const sectionBgError = ref(null);     // { sectionId, message }
let currentBgSectionId = null;

function openSectionBgUpload(sectionId) {
    currentBgSectionId = sectionId;
    sectionBgError.value = null;
    sectionBgFileInput.value?.click();
}

function uploadSectionBg(event) {
    const file = event.target?.files?.[0];
    const sectionId = currentBgSectionId;
    if (!file || !sectionId) return;
    sectionBgUploading.value = sectionId;
    sectionBgError.value = null;
    const formData = new FormData();
    formData.append('background', file);
    router.post(route('settings.site-section-background.upload', { sectionId }), formData, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            sectionBgUploading.value = null;
            if (sectionBgFileInput.value) sectionBgFileInput.value.value = '';
        },
        onError: (errors) => {
            sectionBgError.value = { sectionId, message: errors.background || 'Errore nel caricamento.' };
        },
    });
}

function removeSectionBg(sectionId) {
    if (!confirm('Rimuovere l\'immagine di sfondo di questa sezione?')) return;
    router.delete(route('settings.site-section-background.delete', { sectionId }), { preserveScroll: true });
}

// ─── OpenAPI Company API Config ─────────────────────────────────────────────
const apiTokenField = ref(props.openapi_company_token_set ? '••••••••' : '');
const apiLimitField = ref(String(props.openapi_company_daily_limit ?? 100));
const apiSaving = ref(false);
const apiChecking = ref(false);
const apiCheckResult = ref(null);

function saveApiConfig() {
    apiSaving.value = true;
    router.put(route('settings.api-config.update'), {
        openapi_company_token: apiTokenField.value,
        openapi_company_daily_limit: parseInt(apiLimitField.value) || 100,
    }, {
        preserveScroll: true,
        onFinish: () => { apiSaving.value = false; },
    });
}

async function checkApiCredit() {
    apiChecking.value = true;
    apiCheckResult.value = null;
    try {
        const res = await fetch(route('settings.api-credit.check'), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        apiCheckResult.value = await res.json();
    } catch (e) {
        apiCheckResult.value = { status: 'error', message: 'Errore di rete.' };
    } finally {
        apiChecking.value = false;
    }
}

// Testo letterale per la help (evita che Vue interpreti le graffe nel template)
const variabiliTesto = '\u007B\u007Bnome_associazione\u007D\u007D, \u007B\u007Bindirizzo_associazione\u007D\u007D, \u007B\u007Bcodice_fiscale_associazione\u007D\u007D, \u007B\u007Banno\u007D\u007D, \u007B\u007Bdata_oggi\u007D\u007D';
const placeholderSottotitolo = 'Es: Benvenuti nel sito di \u007B\u007Bnome_associazione\u007D\u007D';
</script>

<template>
    <AppLayout title="Impostazioni">
        <Head title="Impostazioni" />
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Impostazioni</h2>
        </template>

        <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form @submit.prevent="form.put(route('settings.update'))" class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <!-- Tab list -->
                <nav class="flex border-b border-gray-200 dark:border-gray-600" aria-label="Tabs">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        :class="[
                            'flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 -mb-px transition-colors',
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

                <div class="p-6">
                    <!-- Tab: Quota -->
                    <div v-show="activeTab === 'quota'" class="space-y-6">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Configura gli importi delle quote associative. I valori a zero (0,00) indicano che quel tipo di quota non è applicato.
                            Gli importi vengono proposti automaticamente durante la registrazione degli incassi.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                            <!-- Quota annuale -->
                            <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg p-4 space-y-2">
                                <div class="flex items-center gap-2">
                                    <BanknotesIcon class="size-5 text-indigo-500 dark:text-indigo-400 shrink-0" aria-hidden="true" />
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Quota annuale</span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Versamento periodico annuale per il rinnovo dell'iscrizione.</p>
                                <div>
                                    <InputLabel for="quota_annuale" value="Importo (€)" class="text-xs" />
                                    <TextInput
                                        id="quota_annuale"
                                        v-model="form.quota_annuale"
                                        type="number" step="0.01" min="0"
                                        class="mt-1 block w-full"
                                        placeholder="0.00"
                                    />
                                    <InputError class="mt-1" :message="form.errors.quota_annuale" />
                                </div>
                            </div>

                            <!-- Quota mensile -->
                            <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg p-4 space-y-2">
                                <div class="flex items-center gap-2">
                                    <BanknotesIcon class="size-5 text-blue-500 dark:text-blue-400 shrink-0" aria-hidden="true" />
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Quota mensile</span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Versamento mensile ricorrente (es. per soci con iscrizione a cadenza mensile).</p>
                                <div>
                                    <InputLabel for="quota_mensile" value="Importo (€)" class="text-xs" />
                                    <TextInput
                                        id="quota_mensile"
                                        v-model="form.quota_mensile"
                                        type="number" step="0.01" min="0"
                                        class="mt-1 block w-full"
                                        placeholder="0.00"
                                    />
                                    <InputError class="mt-1" :message="form.errors.quota_mensile" />
                                </div>
                            </div>

                            <!-- Quota di ingresso -->
                            <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg p-4 space-y-2">
                                <div class="flex items-center gap-2">
                                    <BanknotesIcon class="size-5 text-amber-500 dark:text-amber-400 shrink-0" aria-hidden="true" />
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Quota di ingresso</span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Versamento una tantum al momento dell'ammissione come nuovo socio.</p>
                                <div>
                                    <InputLabel for="quota_ingresso" value="Importo (€)" class="text-xs" />
                                    <TextInput
                                        id="quota_ingresso"
                                        v-model="form.quota_ingresso"
                                        type="number" step="0.01" min="0"
                                        class="mt-1 block w-full"
                                        placeholder="0.00"
                                    />
                                    <InputError class="mt-1" :message="form.errors.quota_ingresso" />
                                </div>
                            </div>
                        </div>

                        <div class="rounded-md bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-3 text-xs text-blue-700 dark:text-blue-300">
                            <strong>Nota:</strong> Lo scadenzario quote utilizza la <strong>quota annuale</strong> come importo di riferimento per i solleciti.
                            Se la tua associazione utilizza solo la quota mensile, inserisci l'importo mensile nel campo "Quota annuale".
                        </div>
                    </div>

                    <!-- Tab: Dati associazione -->
                    <div v-show="activeTab === 'associazione'" class="space-y-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Usati nell'intestazione e nel footer delle ricevute PDF.</p>
                        <div>
                            <InputLabel value="Logo associazione" />
                            <div class="mt-1 flex flex-wrap items-center gap-3">
                                <input
                                    ref="logoFileInput"
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="uploadLogo"
                                />
                                <SecondaryButton type="button" :disabled="logoUploading" @click="logoFileInput?.click()">
                                    <PhotoIcon class="size-4 me-2" aria-hidden="true" />
                                    {{ logo ? 'Sostituisci logo' : 'Carica logo' }}
                                </SecondaryButton>
                                <span v-if="logoUploading" class="text-sm text-gray-500">Caricamento...</span>
                            </div>
                            <p v-if="logoError" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ logoError }}</p>
                            <div v-if="logo" class="mt-3 flex items-center gap-3">
                                <img :src="logo.url" :alt="logo.original_name" class="h-16 object-contain border border-gray-200 dark:border-gray-600 rounded" />
                                <SecondaryButton type="button" @click="removeLogo"><TrashIcon class="size-4 me-2" aria-hidden="true" />Rimuovi logo</SecondaryButton>
                            </div>
                        </div>
                        <div>
                            <InputLabel for="nome_associazione" value="Nome associazione" />
                            <TextInput id="nome_associazione" v-model="form.nome_associazione" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.nome_associazione" />
                        </div>
                        <div>
                            <InputLabel for="indirizzo_associazione" value="Indirizzo / Sede legale" />
                            <textarea id="indirizzo_associazione" v-model="form.indirizzo_associazione" rows="2" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                            <InputError class="mt-1" :message="form.errors.indirizzo_associazione" />
                        </div>
                        <div>
                            <InputLabel for="codice_fiscale_associazione" value="Codice fiscale" />
                            <TextInput id="codice_fiscale_associazione" v-model="form.codice_fiscale_associazione" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.codice_fiscale_associazione" />
                        </div>
                        <div>
                            <InputLabel for="email_associazione" value="Email associazione" />
                            <TextInput id="email_associazione" v-model="form.email_associazione" type="email" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.email_associazione" />
                        </div>
                        <div>
                            <InputLabel for="pec_associazione" value="PEC associazione" />
                            <TextInput id="pec_associazione" v-model="form.pec_associazione" type="email" class="mt-1 block w-full" placeholder="es. associazione@pec.it" />
                            <InputError class="mt-1" :message="form.errors.pec_associazione" />
                        </div>
                        <div>
                            <InputLabel for="partita_iva_associazione" value="Partita IVA" />
                            <TextInput id="partita_iva_associazione" v-model="form.partita_iva_associazione" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.partita_iva_associazione" />
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 pt-2 border-t border-gray-200 dark:border-gray-600 mt-4">Campi per la ricevuta donazione (RICEVUTA DONAZIONE con diciture ETS).</p>
                        <div>
                            <InputLabel for="legale_rappresentante_associazione" value="Legale rappresentante" />
                            <TextInput id="legale_rappresentante_associazione" v-model="form.legale_rappresentante_associazione" type="text" class="mt-1 block w-full" placeholder="Nome e cognome" />
                            <InputError class="mt-1" :message="form.errors.legale_rappresentante_associazione" />
                        </div>
                        <div>
                            <InputLabel for="data_costituzione_associazione" value="Data di costituzione dell'associazione" />
                            <input
                                id="data_costituzione_associazione"
                                v-model="form.data_costituzione_associazione"
                                type="date"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Usata come inizio mandato per gli organi in assenza di elezioni.</p>
                            <InputError class="mt-1" :message="form.errors.data_costituzione_associazione" />
                        </div>
                        <div>
                            <InputLabel for="data_iscrizione_runts" value="Data iscrizione RUNTS" />
                            <input
                                id="data_iscrizione_runts"
                                v-model="form.data_iscrizione_runts"
                                type="date"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Data di iscrizione al Registro Unico Nazionale del Terzo Settore.</p>
                            <InputError class="mt-1" :message="form.errors.data_iscrizione_runts" />
                        </div>
                        <div>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="form.ets_è_odv" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">L'ente è ODV (Organizzazione di Volontariato)</span>
                            </label>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se attivo, nella ricevuta donazione viene indicata la detrazione del 35% invece del 30%.</p>
                        </div>
                        <div>
                            <InputLabel for="luogo_emissione_ricevute" value="Luogo di emissione ricevute" />
                            <TextInput id="luogo_emissione_ricevute" v-model="form.luogo_emissione_ricevute" type="text" class="mt-1 block w-full" placeholder="Es: Roma (se vuoto si usa l'indirizzo sede)" />
                            <InputError class="mt-1" :message="form.errors.luogo_emissione_ricevute" />
                        </div>
                        <div class="pt-2">
                            <a :href="route('settings.letterhead-preview')" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <EyeIcon class="size-4" aria-hidden="true" />
                                Anteprima carta intestata
                            </a>
                        </div>
                    </div>

                    <!-- Tab: Email -->
                    <div v-show="activeTab === 'email'" class="space-y-4">
                        <div>
                            <InputLabel for="test_email_recipient" value="Destinatario email di test" />
                            <TextInput
                                id="test_email_recipient"
                                v-model="testEmailRecipient"
                                type="email"
                                class="mt-1 block w-full max-w-md"
                                placeholder="email@esempio.it"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Di default il tuo indirizzo. Puoi cambiarlo per inviare la prova a un altro indirizzo.</p>
                        </div>
                        <form @submit.prevent="sendTestEmail" class="inline">
                            <SecondaryButton type="submit" :disabled="testEmailSending">
                                <EnvelopeIcon class="size-4 me-2" aria-hidden="true" />
                                {{ testEmailSending ? 'Invio in corso...' : 'Invia email di test' }}
                            </SecondaryButton>
                        </form>

                        <div v-if="mailConfig" class="pt-4 mt-4 border-t border-gray-200 dark:border-gray-600">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Parametri SMTP (sola lettura)</h4>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Mailer attuale</dt>
                                <dd class="font-mono">{{ mailConfig.mailer ?? '—' }}</dd>
                                <dt class="text-gray-500 dark:text-gray-400">Host</dt>
                                <dd class="font-mono">{{ mailConfig.host ?? '—' }}</dd>
                                <dt class="text-gray-500 dark:text-gray-400">Porta</dt>
                                <dd class="font-mono">{{ mailConfig.port ?? '—' }}</dd>
                                <dt class="text-gray-500 dark:text-gray-400">Username</dt>
                                <dd class="font-mono">{{ mailConfig.username ?? '—' }}</dd>
                                <dt class="text-gray-500 dark:text-gray-400">Encryption</dt>
                                <dd class="font-mono">{{ mailConfig.encryption ?? '—' }}</dd>
                                <dt class="text-gray-500 dark:text-gray-400">From</dt>
                                <dd class="font-mono">{{ mailConfig.from_address ?? '—' }} {{ mailConfig.from_name ? `(${mailConfig.from_name})` : '' }}</dd>
                            </dl>
                            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Questi parametri sono letti dal file <code class="px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-700">.env</code> sul server (variabili <code class="px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-700">MAIL_*</code>). Per modificarli, modifica il file .env e riavvia l'applicazione (o il worker della coda, se usi code per le email).</p>
                        </div>
                    </div>

                    <!-- Tab: Sito pubblico (page builder: una card per sezione) -->
                    <div v-show="activeTab === 'sito'" class="space-y-4">
                        <input
                            ref="sectionBgFileInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="uploadSectionBg"
                        />
                        <p class="text-xs text-gray-500 dark:text-gray-400">Configura le sezioni della pagina pubblica. Per ogni sezione puoi attivare la visibilità, impostare testi (dove previsto), immagine di sfondo e colori.</p>
                        <div class="space-y-4">
                            <div
                                v-for="sectionId in sectionIds"
                                :key="sectionId"
                                class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden"
                            >
                                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 flex items-center justify-between gap-3">
                                    <label class="flex items-center gap-3 cursor-pointer flex-1 min-w-0">
                                        <input
                                            type="checkbox"
                                            :checked="(form.site_sections || []).includes(sectionId)"
                                            class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700"
                                            @change="toggleSiteSection(sectionId)"
                                        />
                                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ site_sections_list[sectionId] }}</span>
                                    </label>
                                </div>
                                <div class="p-4 space-y-4 bg-white dark:bg-gray-800">
                                    <!-- Testi (solo per hero, chi_siamo, footer) -->
                                    <template v-if="sectionId === 'hero'">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Variabili disponibili: <code class="px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-700">{{ variabiliTesto }}</code>.</p>
                                        <div>
                                            <InputLabel :for="'site_hero_title_' + sectionId" value="Titolo hero" />
                                            <TextInput :id="'site_hero_title_' + sectionId" v-model="form.site_hero_title" type="text" class="mt-1 block w-full" placeholder="Lascia vuoto per usare il nome associazione" />
                                            <InputError class="mt-1" :message="form.errors.site_hero_title" />
                                        </div>
                                        <div>
                                            <InputLabel :for="'site_hero_subtitle_' + sectionId" value="Sottotitolo hero" />
                                            <textarea :id="'site_hero_subtitle_' + sectionId" v-model="form.site_hero_subtitle" rows="2" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" :placeholder="placeholderSottotitolo" />
                                            <InputError class="mt-1" :message="form.errors.site_hero_subtitle" />
                                        </div>
                                    </template>
                                    <template v-else-if="sectionId === 'chi_siamo'">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Variabili: <code class="px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-700">{{ variabiliTesto }}</code>.</p>
                                        <div>
                                            <InputLabel :for="'site_chi_siamo_text_' + sectionId" value="Testo Chi siamo" />
                                            <textarea :id="'site_chi_siamo_text_' + sectionId" v-model="form.site_chi_siamo_text" rows="5" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Se vuoto vengono mostrati nome, indirizzo e codice fiscale." />
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se vuoto vengono mostrati nome, indirizzo e codice fiscale.</p>
                                            <InputError class="mt-1" :message="form.errors.site_chi_siamo_text" />
                                        </div>
                                    </template>
                                    <template v-else-if="sectionId === 'footer'">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Variabili: <code class="px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-700">{{ variabiliTesto }}</code>.</p>
                                        <div>
                                            <InputLabel :for="'site_footer_text_' + sectionId" value="Testo footer" />
                                            <textarea :id="'site_footer_text_' + sectionId" v-model="form.site_footer_text" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Se vuoto: nome associazione e indirizzo" />
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se vuoto vengono mostrati nome associazione e indirizzo.</p>
                                            <InputError class="mt-1" :message="form.errors.site_footer_text" />
                                        </div>
                                    </template>
                                    <!-- Per tutte le sezioni: sfondo e colori -->
                                    <div class="pt-2 border-t border-gray-200 dark:border-gray-600 space-y-3">
                                        <InputLabel value="Immagine di sfondo" />
                                        <div class="flex flex-wrap items-center gap-3">
                                            <SecondaryButton type="button" :disabled="sectionBgUploading === sectionId" @click="openSectionBgUpload(sectionId)">
                                                <PhotoIcon class="size-4 me-2" aria-hidden="true" />
                                                {{ (section_styles && section_styles[sectionId]?.background_image) ? 'Sostituisci' : 'Carica' }} immagine
                                            </SecondaryButton>
                                            <span v-if="sectionBgUploading === sectionId" class="text-sm text-gray-500">Caricamento...</span>
                                            <template v-if="section_styles && section_styles[sectionId]?.background_image">
                                                <img :src="section_styles[sectionId].background_image.url" :alt="section_styles[sectionId].background_image.original_name" class="h-14 object-cover border border-gray-200 dark:border-gray-600 rounded" />
                                                <SecondaryButton type="button" @click="removeSectionBg(sectionId)"><TrashIcon class="size-4 me-2" aria-hidden="true" />Rimuovi</SecondaryButton>
                                            </template>
                                        </div>
                                        <p v-if="sectionBgError && sectionBgError.sectionId === sectionId" class="text-sm text-red-600 dark:text-red-400">{{ sectionBgError.message }}</p>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <InputLabel :for="'bg_color_' + sectionId" value="Colore di sfondo" />
                                                <div class="mt-1 flex items-center gap-2">
                                                    <input :id="'bg_color_' + sectionId" v-model="form['site_section_' + sectionId + '_bg_color']" type="color" class="h-9 w-14 rounded border border-gray-300 dark:border-gray-600 cursor-pointer p-0.5 bg-white dark:bg-gray-700" />
                                                    <TextInput v-model="form['site_section_' + sectionId + '_bg_color']" type="text" class="block w-full flex-1" placeholder="#ffffff" />
                                                </div>
                                                <InputError class="mt-1" :message="form.errors['site_section_' + sectionId + '_bg_color']" />
                                            </div>
                                            <div>
                                                <InputLabel :for="'text_color_' + sectionId" value="Colore testo" />
                                                <div class="mt-1 flex items-center gap-2">
                                                    <input :id="'text_color_' + sectionId" v-model="form['site_section_' + sectionId + '_text_color']" type="color" class="h-9 w-14 rounded border border-gray-300 dark:border-gray-600 cursor-pointer p-0.5 bg-white dark:bg-gray-700" />
                                                    <TextInput v-model="form['site_section_' + sectionId + '_text_color']" type="text" class="block w-full flex-1" placeholder="#000000" />
                                                </div>
                                                <InputError class="mt-1" :message="form.errors['site_section_' + sectionId + '_text_color']" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Causali -->
                    <div v-show="activeTab === 'causali'" class="space-y-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Testo usato su ricevute quando non è indicata una causale/descrizione.</p>
                        <div>
                            <InputLabel for="causale_default_donazione" value="Causale default donazione" />
                            <TextInput id="causale_default_donazione" v-model="form.causale_default_donazione" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.causale_default_donazione" />
                        </div>
                        <div>
                            <InputLabel for="causale_default_quota" value="Causale default quota" />
                            <TextInput id="causale_default_quota" v-model="form.causale_default_quota" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.causale_default_quota" />
                        </div>
                        <div>
                            <InputLabel for="causale_default_rimborso" value="Causale default rimborso" />
                            <TextInput id="causale_default_rimborso" v-model="form.causale_default_rimborso" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.causale_default_rimborso" />
                        </div>
                    </div>

                    <!-- Tab: Cooperativa (visibile solo se is_cooperativa) -->
                    <div v-if="is_cooperativa" v-show="activeTab === 'cooperativa'" class="space-y-6">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Parametri specifici per la gestione cooperativistica: capitale sociale, ristorni e prestito sociale.
                        </p>

                        <!-- Card: Capitale Sociale -->
                        <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg p-5 space-y-4">
                            <div class="flex items-center gap-2">
                                <CurrencyEuroIcon class="size-5 text-indigo-500 dark:text-indigo-400 shrink-0" aria-hidden="true" />
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Capitale Sociale</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Definisce il valore unitario di ogni quota di capitale e il numero minimo di quote che ogni socio deve sottoscrivere all'iscrizione.
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="quota_valore_unitario_coop" value="Valore per quota (€)" class="text-xs" />
                                    <TextInput
                                        id="quota_valore_unitario_coop"
                                        v-model="form.quota_valore_unitario_coop"
                                        type="number" step="0.01" min="0.01"
                                        class="mt-1 block w-full"
                                        placeholder="50.00"
                                    />
                                    <InputError class="mt-1" :message="form.errors.quota_valore_unitario_coop" />
                                </div>
                                <div>
                                    <InputLabel for="quota_minima_quote_coop" value="Quote minime obbligatorie (n°)" class="text-xs" />
                                    <TextInput
                                        id="quota_minima_quote_coop"
                                        v-model="form.quota_minima_quote_coop"
                                        type="number" step="1" min="1"
                                        class="mt-1 block w-full"
                                        placeholder="1"
                                    />
                                    <InputError class="mt-1" :message="form.errors.quota_minima_quote_coop" />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Capitale minimo: € {{ (Number(form.quota_valore_unitario_coop || 0) * Number(form.quota_minima_quote_coop || 0)).toFixed(2) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Card: Ristorni -->
                        <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg p-5 space-y-4">
                            <div class="flex items-center gap-2">
                                <BanknotesIcon class="size-5 text-emerald-500 dark:text-emerald-400 shrink-0" aria-hidden="true" />
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Ristorni e Riserve</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                I ristorni sono soggetti a ritenuta fiscale del 30% (fisso per legge, L. 142/2001).
                                La riserva legale (30%) e indivisibile (3%) sono obbligatorie per legge (L. 59/1992 art. 11).
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <InputLabel for="ristorno_percentuale_max" value="% massima ristornabile" class="text-xs" />
                                    <TextInput
                                        id="ristorno_percentuale_max"
                                        v-model="form.ristorno_percentuale_max"
                                        type="number" step="0.01" min="0" max="100"
                                        class="mt-1 block w-full"
                                        placeholder="100.00"
                                    />
                                    <InputError class="mt-1" :message="form.errors.ristorno_percentuale_max" />
                                </div>
                                <div>
                                    <InputLabel for="riserva_legale_percentuale" value="Riserva legale (%)" class="text-xs" />
                                    <TextInput
                                        id="riserva_legale_percentuale"
                                        v-model="form.riserva_legale_percentuale"
                                        type="number" step="0.01" min="0" max="100"
                                        class="mt-1 block w-full"
                                        placeholder="30.00"
                                    />
                                    <InputError class="mt-1" :message="form.errors.riserva_legale_percentuale" />
                                    <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">Min. 30% per legge</p>
                                </div>
                                <div>
                                    <InputLabel for="riserva_indivisibile_percentuale" value="Riserva indivisibile (%)" class="text-xs" />
                                    <TextInput
                                        id="riserva_indivisibile_percentuale"
                                        v-model="form.riserva_indivisibile_percentuale"
                                        type="number" step="0.01" min="0" max="100"
                                        class="mt-1 block w-full"
                                        placeholder="3.00"
                                    />
                                    <InputError class="mt-1" :message="form.errors.riserva_indivisibile_percentuale" />
                                    <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">Min. 3% per legge</p>
                                </div>
                            </div>
                        </div>

                        <!-- Card: Prestito Sociale -->
                        <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg p-5 space-y-4">
                            <div class="flex items-center gap-2">
                                <BuildingLibraryIcon class="size-5 text-blue-500 dark:text-blue-400 shrink-0" aria-hidden="true" />
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Prestito Sociale</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Il prestito sociale consente ai soci di depositare liquidità nella cooperativa a un tasso concordato.
                                Gli interessi sono soggetti a ritenuta fiscale del 26% (art. 26 DPR 600/73).
                            </p>
                            <div class="max-w-xs">
                                <InputLabel for="tasso_interesse_prestito" value="Tasso di interesse annuo (%)" class="text-xs" />
                                <TextInput
                                    id="tasso_interesse_prestito"
                                    v-model="form.tasso_interesse_prestito"
                                    type="number" step="0.0001" min="0" max="100"
                                    class="mt-1 block w-full"
                                    placeholder="0.0000"
                                />
                                <InputError class="mt-1" :message="form.errors.tasso_interesse_prestito" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    0 = nessun prestito sociale attivo per questa cooperativa.
                                </p>
                            </div>
                        </div>

                        <div class="rounded-md bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-3 text-xs text-blue-700 dark:text-blue-300">
                            <strong>Nota:</strong> Queste impostazioni sono visibili e modificabili solo dai tenant di tipo Cooperativa.
                            I valori sono usati come predefiniti nei moduli Capitale Sociale, Ristorni e Prestito Sociale.
                        </div>
                    </div>

                    <!-- Tab: Banking & Tessere -->
                    <div v-show="activeTab === 'banking'" class="space-y-6">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Impostazioni per RI.BA/CBI, riconciliazione bancaria e tessere associative.
                        </p>

                        <!-- Card: Tessere -->
                        <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg p-5 space-y-4">
                            <div class="flex items-center gap-2">
                                <CreditCardIcon class="size-5 text-indigo-500 dark:text-indigo-400 shrink-0" aria-hidden="true" />
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Tessere Associative</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Colore della striscia intestazione nelle tessere PDF.
                            </p>
                            <div class="flex items-center gap-4">
                                <div class="flex-1">
                                    <InputLabel for="tessera_colore" value="Colore tessera (hex)" />
                                    <TextInput
                                        id="tessera_colore"
                                        v-model="form.tessera_colore"
                                        type="text"
                                        class="mt-1 block w-full"
                                        placeholder="#1e40af"
                                        maxlength="7"
                                    />
                                    <InputError class="mt-1" :message="form.errors.tessera_colore" />
                                </div>
                                <div class="mt-5">
                                    <div class="text-xs text-gray-600 dark:text-gray-400 mb-2">Anteprima:</div>
                                    <div
                                        class="w-12 h-8 rounded border border-gray-300 dark:border-gray-600"
                                        :style="{ backgroundColor: form.tessera_colore || '#1e40af' }"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        <!-- Card: RI.BA e Riconciliazione -->
                        <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg p-5 space-y-4">
                            <div class="flex items-center gap-2">
                                <BanknotesIcon class="size-5 text-emerald-500 dark:text-emerald-400 shrink-0" aria-hidden="true" />
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">RI.BA / CBI / Riconciliazione</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Dati per la generazione dei file CBI (RI.BA bancarie) e la riconciliazione estratti conto.
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="codice_sia" value="Codice SIA (5 char)" />
                                    <TextInput
                                        id="codice_sia"
                                        v-model="form.codice_sia"
                                        type="text"
                                        class="mt-1 block w-full"
                                        placeholder="00000"
                                        maxlength="5"
                                    />
                                    <InputError class="mt-1" :message="form.errors.codice_sia" />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Codice SIA del mittente per file CBI</p>
                                </div>
                                <div>
                                    <InputLabel for="cab_banca" value="CAB Banca (5 digit)" />
                                    <TextInput
                                        id="cab_banca"
                                        v-model="form.cab_banca"
                                        type="text"
                                        class="mt-1 block w-full"
                                        placeholder="00000"
                                        maxlength="5"
                                    />
                                    <InputError class="mt-1" :message="form.errors.cab_banca" />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">CAB banca presentatrice</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <InputLabel for="cc_banca" value="Numero C/C (max 12 char)" />
                                    <TextInput
                                        id="cc_banca"
                                        v-model="form.cc_banca"
                                        type="text"
                                        class="mt-1 block w-full"
                                        placeholder="000000000000"
                                        maxlength="12"
                                    />
                                    <InputError class="mt-1" :message="form.errors.cc_banca" />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Numero conto corrente presentatrice per esportazione CBI</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Privacy -->
                    <div v-show="activeTab === 'privacy'" class="space-y-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Informativa privacy mostrata nel form di richiesta ammissione soci. Se vuota, verrà mostrato un testo minimale.</p>
                        <div>
                            <InputLabel for="informativa_privacy_domanda_ammissione" value="Informativa privacy (GDPR) per domanda ammissione" />
                            <RichTextEditor
                                id="informativa_privacy_domanda_ammissione"
                                v-model="form.informativa_privacy_domanda_ammissione"
                                placeholder="Testo dell'informativa sul trattamento dei dati personali..."
                                min-height="280px"
                            />
                            <InputError class="mt-1" :message="form.errors.informativa_privacy_domanda_ammissione" />
                        </div>
                    </div>

                    <!-- Tab: API -->
                    <div v-show="activeTab === 'api'" class="space-y-6">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Configura il collegamento a OpenAPI Company per l'arricchimento automatico dell'anagrafica.
                        </p>

                        <!-- Card: Token & Limite -->
                        <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg p-5 space-y-4">
                            <div class="flex items-center gap-2">
                                <SignalIcon class="size-5 text-indigo-500 dark:text-indigo-400 shrink-0" aria-hidden="true" />
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">OpenAPI Company</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="api_token" value="Token API (Bearer)" />
                                    <TextInput
                                        id="api_token"
                                        v-model="apiTokenField"
                                        type="password"
                                        class="mt-1 block w-full font-mono"
                                        placeholder="Incolla il token qui"
                                        @focus="apiTokenField === '••••••••' && (apiTokenField = '')"
                                    />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Il token viene criptato e salvato nel database. Non viene mai esposto al frontend.</p>
                                </div>
                                <div>
                                    <InputLabel for="api_limit" value="Limite chiamate giornaliere" />
                                    <TextInput
                                        id="api_limit"
                                        v-model="apiLimitField"
                                        type="number"
                                        min="1"
                                        max="10000"
                                        class="mt-1 block w-full"
                                    />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Default: 100 al giorno (piano base OpenAPI).</p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    :disabled="apiSaving"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition"
                                    @click="saveApiConfig"
                                >
                                    <CheckIcon class="size-4" aria-hidden="true" />
                                    {{ apiSaving ? 'Salvataggio…' : 'Salva configurazione API' }}
                                </button>
                            </div>
                        </div>

                        <!-- Card: Verifica credito -->
                        <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg p-5 space-y-4">
                            <div class="flex items-center gap-2">
                                <SignalIcon class="size-5 text-emerald-500 dark:text-emerald-400 shrink-0" aria-hidden="true" />
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Verifica connessione e credito</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Controlla che il token sia valido e verifica le chiamate effettuate oggi.
                            </p>
                            <button
                                type="button"
                                :disabled="apiChecking"
                                class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 transition"
                                @click="checkApiCredit"
                            >
                                <ArrowPathIcon :class="['size-4', apiChecking && 'animate-spin']" aria-hidden="true" />
                                {{ apiChecking ? 'Verifica in corso…' : 'Verifica credito API' }}
                            </button>

                            <!-- Risultato check -->
                            <div v-if="apiCheckResult" class="rounded-lg border p-4 space-y-3" :class="{
                                'border-green-200 dark:border-green-700 bg-green-50 dark:bg-green-900/20': apiCheckResult.status === 'ok',
                                'border-amber-200 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20': apiCheckResult.status === 'warning',
                                'border-red-200 dark:border-red-700 bg-red-50 dark:bg-red-900/20': apiCheckResult.status === 'error',
                            }">
                                <div class="flex items-center gap-2 text-sm font-medium" :class="{
                                    'text-green-700 dark:text-green-300': apiCheckResult.status === 'ok',
                                    'text-amber-700 dark:text-amber-300': apiCheckResult.status === 'warning',
                                    'text-red-700 dark:text-red-300': apiCheckResult.status === 'error',
                                }">
                                    <CheckIcon v-if="apiCheckResult.status === 'ok'" class="size-5" aria-hidden="true" />
                                    <ExclamationTriangleIcon v-else class="size-5" aria-hidden="true" />
                                    {{ apiCheckResult.message }}
                                </div>
                                <div v-if="apiCheckResult.usage" class="grid grid-cols-3 gap-4 text-center">
                                    <div class="bg-white dark:bg-gray-800 rounded-md p-3">
                                        <div class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ apiCheckResult.usage.used }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Usate oggi</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-md p-3">
                                        <div class="text-2xl font-bold" :class="{
                                            'text-green-600 dark:text-green-400': apiCheckResult.usage.status === 'ok',
                                            'text-amber-600 dark:text-amber-400': apiCheckResult.usage.status === 'warning',
                                            'text-red-600 dark:text-red-400': apiCheckResult.usage.status === 'blocked',
                                        }">{{ apiCheckResult.usage.remaining }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Residue</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-md p-3">
                                        <div class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ apiCheckResult.usage.limit }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Limite giornaliero</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-md bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-3 text-xs text-blue-700 dark:text-blue-300">
                            <strong>Nota:</strong> Il contatore si azzera ogni giorno a mezzanotte (fuso Europe/Rome).
                            Il token è usato esclusivamente dal backend e non viene mai trasmesso al browser.
                            Per recuperare i dati aziendali vai nella pagina <strong>Anagrafica</strong>.
                        </div>
                    </div>

                    <div class="flex gap-2 pt-6 mt-6 border-t border-gray-200 dark:border-gray-600">
                        <PrimaryButton type="submit" :disabled="form.processing"><CheckIcon class="size-4 me-2" aria-hidden="true" />Salva</PrimaryButton>
                        <Link :href="route('dashboard')" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"><ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla</Link>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
