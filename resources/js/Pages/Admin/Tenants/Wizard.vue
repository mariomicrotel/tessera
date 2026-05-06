<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    tenant:           Object,
    formeRaggruppate: Object,
    formaLabels:      Object,
    profiloCorrente:  Object,
    noteNormative:    Object,
    etsCatalog:       Object,   // { sezioni_runts, ambiti, sezione_suggerita, fasce_entrate }
});

// ─── Etichette leggibili ──────────────────────────────────────────────
const dimensioneLabels = {
    micro:           'Micro-impresa (art. 2435-ter c.c.)',
    abbreviato:      'Bilancio abbreviato (art. 2435-bis c.c.)',
    ordinario_cee:   'Bilancio ordinario CEE',
    ets_d:           'Bilancio ETS (D.M. 5/3/2020)',
    cooperativa:     'Bilancio cooperativa',
    non_applicabile: 'Non applicabile',
};
const regimeContabileLabels = {
    ordinario:       'Contabilità ordinaria',
    semplificato:    'Contabilità semplificata',
    forfettario:     'Regime forfettario',
    non_applicabile: 'Non applicabile',
};
const regimeIvaLabels = {
    ordinario:       'IVA ordinario',
    forfettario:     'Escluso IVA (forfettario)',
    agricolo:        'Regime agricolo (art. 34)',
    margine:         'Regime del margine',
    editoria:        'Editoria (L. 62/2001)',
    esente:          'Esente IVA (art. 10)',
    non_applicabile: 'Non soggetto IVA',
};
const campoLabels = {
    codice_fiscale:              'Codice Fiscale',
    partita_iva:                 'Partita IVA',
    numero_iscrizione_albo_coop: 'Numero iscrizione Albo Cooperative',
    rea_numero:                  'Numero REA',
    rea_citta:                   'Sigla provincia REA',
    attivita_ateco:              'Codice ATECO',
};

// ─── Stato corrente del wizard ────────────────────────────────────────
const initialStep = props.tenant.wizard_step_corrente || 1;
const currentStep = ref(initialStep);
const profiloLocal = ref(props.profiloCorrente);

const form = useForm({
    step: currentStep.value,
    forma_giuridica:             props.tenant.forma_giuridica || '',
    dimensione_bilancio:         props.tenant.dimensione_bilancio || '',
    regime_contabile:            props.tenant.regime_contabile || '',
    regime_iva:                  props.tenant.regime_iva || '',
    attivita_ateco:              props.tenant.attivita_ateco || '',
    codice_fiscale:              props.tenant.codice_fiscale || '',
    partita_iva:                 props.tenant.partita_iva || '',
    numero_iscrizione_albo_coop: props.tenant.numero_iscrizione_albo_coop || '',
    capitale_sottoscritto:       props.tenant.capitale_sottoscritto || '',
    capitale_versato:            props.tenant.capitale_versato || '',
    pec:                         props.tenant.pec || '',
    rea_numero:                  props.tenant.rea_numero || '',
    rea_citta:                   props.tenant.rea_citta || '',
    indirizzo:                   props.tenant.indirizzo || '',
    cap:                         props.tenant.cap || '',
    citta:                       props.tenant.citta || '',
    provincia:                   props.tenant.provincia || '',
    nazione:                     props.tenant.nazione || 'IT',
    telefono:                    props.tenant.telefono || '',
    sito_web:                    props.tenant.sito_web || '',
    // ETS compliance
    runts_numero:                       props.tenant.runts_numero || '',
    runts_sezione:                      props.tenant.runts_sezione || '',
    runts_data_iscrizione:              props.tenant.runts_data_iscrizione || '',
    personalita_giuridica:              !!props.tenant.personalita_giuridica,
    patrimonio_destinato:               props.tenant.patrimonio_destinato || '',
    ambiti_attivita:                    Array.isArray(props.tenant.ambiti_attivita) ? props.tenant.ambiti_attivita : [],
    attivita_principale:                props.tenant.attivita_principale || '',
    fascia_entrate:                     props.tenant.fascia_entrate || '',
    assicurazione_volontari_polizza:    props.tenant.assicurazione_volontari_polizza || '',
    assicurazione_volontari_compagnia:  props.tenant.assicurazione_volontari_compagnia || '',
    assicurazione_volontari_scadenza:   props.tenant.assicurazione_volontari_scadenza || '',
    bilancio_url_pubblicazione:         props.tenant.bilancio_url_pubblicazione || '',
    completa:                    false,
});

const isEtsForma = computed(() => form.forma_giuridica?.startsWith('ets_'));

const steps = computed(() => {
    const base = [
        { n: 1, title: 'Forma giuridica',     icon: '🏢' },
        { n: 2, title: 'Dimensione bilancio', icon: '📐' },
        { n: 3, title: 'Regimi',              icon: '⚖️' },
        { n: 4, title: 'Anagrafica',          icon: '📇' },
    ];
    if (isEtsForma.value) {
        base.push({ n: 5, title: 'ETS / RUNTS', icon: '🤝' });
        base.push({ n: 6, title: 'Riepilogo',  icon: '✅' });
    } else {
        base.push({ n: 5, title: 'Riepilogo',  icon: '✅' });
    }
    return base;
});

const stepFinale = computed(() => isEtsForma.value ? 6 : 5);

const wizardCompletato = computed(() => !!props.tenant.wizard_completato_at);

// ─── Profilo selezionato (per Step 2/3/4) ─────────────────────────────
const isFormaSelezionata = computed(() => !!form.forma_giuridica);
const isCoopForma = computed(() => form.forma_giuridica?.startsWith('coop_'));

const dimensioniDisponibili = computed(() => profiloLocal.value?.dimensioni_disponibili || []);
const regimiContabiliDisponibili = computed(() => profiloLocal.value?.regimi_contabili || []);
// Regime IVA: filtrato dinamicamente in base al regime contabile selezionato
const regimiIvaDisponibili = computed(() => {
    const all = profiloLocal.value?.regimi_iva || [];
    const map = profiloLocal.value?.regimi_iva_per_contabile || {};
    if (form.regime_contabile && map[form.regime_contabile]) {
        return map[form.regime_contabile];
    }
    return all;
});
const campiObbligatori = computed(() => profiloLocal.value?.campi_obbligatori || []);

// ─── Auto-select se l'opzione è unica ────────────────────────────────
watch(dimensioniDisponibili, (val) => {
    if (val.length === 1 && !form.dimensione_bilancio) {
        form.dimensione_bilancio = val[0];
    }
}, { immediate: true });
watch(regimiContabiliDisponibili, (val) => {
    if (val.length === 1 && !form.regime_contabile) {
        form.regime_contabile = val[0];
    }
}, { immediate: true });
watch(regimiIvaDisponibili, (val) => {
    if (val.length === 1 && !form.regime_iva) {
        form.regime_iva = val[0];
    }
    // Se il regime IVA correntemente selezionato non è più compatibile, resetta
    if (form.regime_iva && !val.includes(form.regime_iva)) {
        form.regime_iva = val.length === 1 ? val[0] : '';
    }
}, { immediate: true });

// ─── Selezione forma giuridica (Step 1) ──────────────────────────────
const selezionaForma = (forma) => {
    form.forma_giuridica = forma;
    // Reset dipendenti se cambia forma
    form.dimensione_bilancio = '';
    form.regime_contabile = '';
    form.regime_iva = '';
};

// ─── Navigazione ──────────────────────────────────────────────────────
const canGoNext = computed(() => {
    if (currentStep.value === 1) return !!form.forma_giuridica;
    if (currentStep.value === 2) return !!form.dimensione_bilancio;
    if (currentStep.value === 3) return !!form.regime_contabile && !!form.regime_iva;
    if (currentStep.value === 4) {
        return campiObbligatori.value.every((c) => !!form[c]);
    }
    if (currentStep.value === 5 && isEtsForma.value) {
        // Step ETS: richiede sezione RUNTS e almeno 1 ambito
        return !!form.runts_sezione && form.ambiti_attivita.length > 0;
    }
    return true;
});

const goNext = () => {
    if (!canGoNext.value) return;
    salvaStep(currentStep.value, false, () => {
        currentStep.value = Math.min(currentStep.value + 1, stepFinale.value);
        form.step = currentStep.value;
    });
};

const goPrev = () => {
    currentStep.value = Math.max(currentStep.value - 1, 1);
    form.step = currentStep.value;
};

const salvaStep = (step, completa, onSuccess = null) => {
    form.step = step;
    form.completa = completa;
    form.post(route('admin.tenants.wizard.save', props.tenant.slug), {
        preserveScroll: true,
        preserveState:  true,
        onSuccess: () => {
            // Ricarica il profilo se siamo allo Step 1 (per popolare dropdown successivi)
            if (step === 1) {
                router.reload({ only: ['profiloCorrente', 'tenant'], onSuccess: (page) => {
                    profiloLocal.value = page.props.profiloCorrente;
                    if (onSuccess) onSuccess();
                }});
            } else if (onSuccess) {
                onSuccess();
            }
        },
    });
};

const completa = () => {
    salvaStep(stepFinale.value, true);
};

// Auto-suggerisci sezione RUNTS quando si passa alla forma ETS
watch(() => form.forma_giuridica, (val) => {
    if (val?.startsWith('ets_') && !form.runts_sezione && props.etsCatalog?.sezione_suggerita) {
        form.runts_sezione = props.etsCatalog.sezione_suggerita;
    }
});

const togglAmbito = (lett) => {
    const idx = form.ambiti_attivita.indexOf(lett);
    if (idx >= 0) {
        form.ambiti_attivita.splice(idx, 1);
        if (form.attivita_principale === lett) form.attivita_principale = '';
    } else {
        form.ambiti_attivita.push(lett);
        if (!form.attivita_principale) form.attivita_principale = lett;
    }
};

// Patrimonio minimo richiesto per personalità giuridica
const patrimonioMinimo = computed(() => {
    if (form.forma_giuridica === 'ets_fondazione') return 30000;
    if (isEtsForma.value) return 15000;
    return 0;
});
</script>

<template>
    <AdminLayout :title="`Wizard — ${tenant.name}`">
        <!-- Header -->
        <div class="mb-6">
            <Link :href="route('admin.tenants')" class="text-sm text-blue-600 hover:underline">← Torna alla lista tenant</Link>
            <h1 class="text-2xl font-bold text-gray-900 mt-2">Configurazione organizzazione</h1>
            <p class="text-sm text-gray-600">{{ tenant.name }} <span class="font-mono text-gray-400">({{ tenant.slug }})</span></p>
            <div v-if="wizardCompletato" class="mt-2 inline-flex items-center gap-1 text-xs text-green-700 bg-green-50 px-2 py-1 rounded">
                ✓ Wizard già completato — puoi rivedere/aggiornare i dati
            </div>
        </div>

        <!-- Stepper -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-center justify-between">
                <template v-for="(s, idx) in steps" :key="s.n">
                    <button
                        type="button"
                        @click="currentStep = s.n; form.step = s.n"
                        :disabled="!form.forma_giuridica && s.n > 1"
                        class="flex flex-col items-center text-center flex-1 transition disabled:opacity-40 disabled:cursor-not-allowed"
                        :class="{ 'cursor-pointer': form.forma_giuridica || s.n === 1 }"
                    >
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-lg font-bold mb-1"
                            :class="currentStep === s.n
                                ? 'bg-blue-600 text-white'
                                : currentStep > s.n
                                    ? 'bg-green-500 text-white'
                                    : 'bg-gray-200 text-gray-500'">
                            <span v-if="currentStep > s.n">✓</span>
                            <span v-else>{{ s.n }}</span>
                        </div>
                        <span class="text-xs font-medium" :class="currentStep === s.n ? 'text-blue-700' : 'text-gray-600'">{{ s.title }}</span>
                    </button>
                    <div v-if="idx < steps.length - 1" class="flex-shrink-0 h-0.5 w-8 mx-1"
                        :class="currentStep > s.n ? 'bg-green-500' : 'bg-gray-200'"></div>
                </template>
            </div>
        </div>

        <!-- Errori globali -->
        <div v-if="Object.keys(form.errors).length > 0" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
            <p class="font-medium text-red-800 mb-1">Correggi gli errori prima di continuare:</p>
            <ul class="list-disc list-inside text-sm text-red-700">
                <li v-for="(err, key) in form.errors" :key="key">
                    <strong>{{ campoLabels[key] || key }}:</strong> {{ err }}
                </li>
            </ul>
        </div>

        <!-- Body Step -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">

            <!-- ── STEP 1: Forma Giuridica ─────────────────────────────────── -->
            <div v-if="currentStep === 1">
                <h2 class="text-xl font-semibold text-gray-900 mb-2">Quale forma giuridica?</h2>
                <p class="text-sm text-gray-600 mb-6">Scegli la forma giuridica dell'organizzazione: questa determina automaticamente i moduli abilitati, il piano dei conti e lo schema di bilancio applicabile.</p>

                <div v-for="(forme, gruppo) in formeRaggruppate" :key="gruppo" class="mb-6">
                    <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">{{ gruppo }}</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <button
                            v-for="f in forme"
                            :key="f.value"
                            type="button"
                            @click="selezionaForma(f.value)"
                            class="text-left p-4 border-2 rounded-lg transition cursor-pointer"
                            :class="form.forma_giuridica === f.value
                                ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-200'
                                : 'border-gray-200 hover:border-blue-300 hover:bg-gray-50'"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900">{{ f.label }}</p>
                                    <p class="text-xs text-gray-500 mt-1">{{ f.descrizione }}</p>
                                </div>
                                <span v-if="form.forma_giuridica === f.value" class="text-blue-600 text-lg">✓</span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── STEP 2: Dimensione Bilancio ─────────────────────────────── -->
            <div v-else-if="currentStep === 2">
                <h2 class="text-xl font-semibold text-gray-900 mb-2">Dimensione del bilancio</h2>
                <p class="text-sm text-gray-600 mb-6">
                    In base alla forma giuridica selezionata (<strong>{{ formaLabels[form.forma_giuridica] }}</strong>),
                    seleziona la dimensione del bilancio applicabile.
                </p>

                <div v-if="dimensioniDisponibili.length === 0" class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded p-3">
                    ⚠️ Nessuna dimensione disponibile per questa forma. Salva e passa allo step successivo.
                </div>

                <div v-else class="space-y-2">
                    <label
                        v-for="dim in dimensioniDisponibili"
                        :key="dim"
                        class="flex items-start gap-3 p-4 border rounded-lg cursor-pointer transition"
                        :class="form.dimensione_bilancio === dim ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'"
                    >
                        <input type="radio" v-model="form.dimensione_bilancio" :value="dim" class="mt-1" />
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <p class="font-medium text-gray-900">{{ noteNormative?.dimensione[dim]?.titolo || dimensioneLabels[dim] || dim }}</p>
                                <span v-if="noteNormative?.dimensione[dim]?.fonte" class="text-xs font-mono text-gray-400">{{ noteNormative.dimensione[dim].fonte }}</span>
                            </div>
                            <p v-if="noteNormative?.dimensione[dim]?.limiti" class="text-xs text-gray-700 mt-1"><strong>Limiti:</strong> {{ noteNormative.dimensione[dim].limiti }}</p>
                            <p v-if="noteNormative?.dimensione[dim]?.descr" class="text-xs text-gray-500 mt-1">{{ noteNormative.dimensione[dim].descr }}</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- ── STEP 3: Regimi ──────────────────────────────────────────── -->
            <div v-else-if="currentStep === 3">
                <h2 class="text-xl font-semibold text-gray-900 mb-2">Regimi contabile e IVA</h2>
                <p class="text-sm text-gray-600 mb-6">Determinano le regole di calcolo e gli adempimenti applicabili.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Regime contabile -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Regime contabile</label>
                        <div class="space-y-2">
                            <label
                                v-for="rc in regimiContabiliDisponibili"
                                :key="rc"
                                class="block p-3 border rounded-lg cursor-pointer transition"
                                :class="form.regime_contabile === rc ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <input type="radio" v-model="form.regime_contabile" :value="rc" />
                                        <span class="text-sm font-medium">{{ noteNormative?.regime_contabile[rc]?.titolo || regimeContabileLabels[rc] || rc }}</span>
                                    </div>
                                    <span v-if="noteNormative?.regime_contabile[rc]?.fonte" class="text-xs font-mono text-gray-400">{{ noteNormative.regime_contabile[rc].fonte }}</span>
                                </div>
                                <p v-if="noteNormative?.regime_contabile[rc]?.limiti" class="text-xs text-gray-600 mt-1 ml-6">{{ noteNormative.regime_contabile[rc].limiti }}</p>
                            </label>
                        </div>
                    </div>

                    <!-- Regime IVA -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Regime IVA
                            <span v-if="!form.regime_contabile" class="text-xs font-normal text-amber-600">(seleziona prima il regime contabile)</span>
                        </label>
                        <div class="space-y-2">
                            <label
                                v-for="riv in regimiIvaDisponibili"
                                :key="riv"
                                class="block p-3 border rounded-lg transition"
                                :class="[
                                    form.regime_iva === riv ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:bg-gray-50',
                                    !form.regime_contabile ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
                                ]"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <input type="radio" v-model="form.regime_iva" :value="riv" :disabled="!form.regime_contabile" />
                                        <span class="text-sm font-medium">{{ noteNormative?.regime_iva[riv]?.titolo || regimeIvaLabels[riv] || riv }}</span>
                                    </div>
                                    <span v-if="noteNormative?.regime_iva[riv]?.fonte" class="text-xs font-mono text-gray-400">{{ noteNormative.regime_iva[riv].fonte }}</span>
                                </div>
                                <p v-if="noteNormative?.regime_iva[riv]?.limiti" class="text-xs text-gray-600 mt-1 ml-6">{{ noteNormative.regime_iva[riv].limiti }}</p>
                            </label>
                            <p v-if="form.regime_contabile && regimiIvaDisponibili.length === 1" class="text-xs text-blue-600 mt-2">
                                ℹ️ Per il regime contabile scelto è ammesso un solo regime IVA (vincolo normativo).
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── STEP 4: Anagrafica ──────────────────────────────────────── -->
            <div v-else-if="currentStep === 4">
                <h2 class="text-xl font-semibold text-gray-900 mb-2">Dati anagrafici</h2>
                <p class="text-sm text-gray-600 mb-6">
                    Dati identificativi e di contatto.
                    <span v-if="campiObbligatori.length" class="text-red-600">
                        Campi obbligatori per questa forma: {{ campiObbligatori.map(c => campoLabels[c] || c).join(', ') }}.
                    </span>
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Codice Fiscale
                            <span v-if="campiObbligatori.includes('codice_fiscale')" class="text-red-500">*</span>
                        </label>
                        <input v-model="form.codice_fiscale" type="text" maxlength="16" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Partita IVA
                            <span v-if="campiObbligatori.includes('partita_iva')" class="text-red-500">*</span>
                        </label>
                        <input v-model="form.partita_iva" type="text" maxlength="11" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                    </div>

                    <template v-if="campiObbligatori.includes('numero_iscrizione_albo_coop') || isCoopForma">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Numero iscrizione Albo Cooperative
                                <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.numero_iscrizione_albo_coop" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Capitale sottoscritto</label>
                                <input v-model="form.capitale_sottoscritto" type="number" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Capitale versato</label>
                                <input v-model="form.capitale_versato" type="number" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                            </div>
                        </div>
                    </template>

                    <template v-if="campiObbligatori.includes('rea_numero')">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Numero REA <span class="text-red-500">*</span></label>
                            <input v-model="form.rea_numero" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Sigla provincia REA <span class="text-red-500">*</span></label>
                            <input v-model="form.rea_citta" type="text" maxlength="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                    </template>

                    <template v-if="campiObbligatori.includes('attivita_ateco')">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Codice ATECO <span class="text-red-500">*</span></label>
                            <input v-model="form.attivita_ateco" type="text" placeholder="es. 74.10.10" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                    </template>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">PEC</label>
                        <input v-model="form.pec" type="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Telefono</label>
                        <input v-model="form.telefono" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Indirizzo</label>
                        <input v-model="form.indirizzo" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">CAP</label>
                        <input v-model="form.cap" type="text" maxlength="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Città</label>
                        <input v-model="form.citta" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Provincia</label>
                        <input v-model="form.provincia" type="text" maxlength="5" placeholder="RM" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nazione</label>
                        <input v-model="form.nazione" type="text" maxlength="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Sito web</label>
                        <input v-model="form.sito_web" type="text" placeholder="https://..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                    </div>
                </div>
            </div>

            <!-- ── STEP 5 ETS: Compliance D.Lgs. 117/2017 ──────────────────── -->
            <div v-else-if="currentStep === 5 && isEtsForma">
                <h2 class="text-xl font-semibold text-gray-900 mb-2">Compliance ETS — D.Lgs. 117/2017</h2>
                <p class="text-sm text-gray-600 mb-6">
                    Iscrizione RUNTS, ambiti di attività di interesse generale, personalità giuridica e assicurazione volontari.
                </p>

                <!-- ── RUNTS ── -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                    <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">Iscrizione RUNTS</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Numero RUNTS</label>
                            <input v-model="form.runts_numero" type="text" placeholder="es. 12345" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Sezione <span class="text-red-500">*</span>
                                <span v-if="etsCatalog?.sezione_suggerita" class="text-xs font-normal text-blue-600">(suggerita: {{ etsCatalog.sezione_suggerita.toUpperCase() }})</span>
                            </label>
                            <select v-model="form.runts_sezione" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">— seleziona —</option>
                                <option v-for="(label, k) in etsCatalog?.sezioni_runts || {}" :key="k" :value="k">
                                    {{ k.toUpperCase() }} — {{ label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Data iscrizione</label>
                            <input v-model="form.runts_data_iscrizione" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">📚 Riferimento: art. 46 D.Lgs. 117/2017 — sezioni RUNTS.</p>
                </div>

                <!-- ── Personalità giuridica e patrimonio minimo ── -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                    <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">Personalità giuridica</h3>
                    <label class="flex items-start gap-2 cursor-pointer mb-3">
                        <input type="checkbox" v-model="form.personalita_giuridica" class="mt-1" />
                        <span class="text-sm text-gray-700">Ente con personalità giuridica acquisita ex art. 22 CTS (procedura semplificata RUNTS)</span>
                    </label>
                    <div v-if="form.personalita_giuridica" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Patrimonio destinato (€)
                                <span class="text-xs font-normal text-amber-700">— minimo: {{ patrimonioMinimo.toLocaleString('it-IT') }} €</span>
                            </label>
                            <input v-model="form.patrimonio_destinato" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                            <p v-if="form.patrimonio_destinato && Number(form.patrimonio_destinato) < patrimonioMinimo"
                                class="text-xs text-red-600 mt-1">
                                ⚠️ Inferiore al minimo richiesto dall'art. 22 c.4 CTS.
                            </p>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-3">
                        📚 Art. 22 D.Lgs. 117/2017: associazioni 15.000 € · fondazioni 30.000 € (patrimonio destinato all'attività).
                    </p>
                </div>

                <!-- ── Fascia entrate annue ── -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                    <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">Fascia entrate annue</h3>
                    <p class="text-xs text-gray-600 mb-3">Determina lo schema di bilancio applicabile e gli obblighi di trasparenza.</p>
                    <div class="space-y-2">
                        <label
                            v-for="(label, key) in etsCatalog?.fasce_entrate || {}"
                            :key="key"
                            class="flex items-start gap-2 p-3 border rounded cursor-pointer transition"
                            :class="form.fascia_entrate === key ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:bg-white'"
                        >
                            <input type="radio" v-model="form.fascia_entrate" :value="key" class="mt-1" />
                            <span class="text-sm">{{ label }}</span>
                        </label>
                    </div>
                </div>

                <!-- ── Ambiti art. 5 CTS ── -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                    <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-1">Ambiti di attività di interesse generale</h3>
                    <p class="text-xs text-gray-600 mb-3">
                        Seleziona uno o più ambiti tra i 26 elencati nell'art. 5 D.Lgs. 117/2017. <span class="text-red-500">*</span>
                        ({{ form.ambiti_attivita.length }} selezionati)
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-96 overflow-y-auto pr-2">
                        <button
                            v-for="(amb, lett) in etsCatalog?.ambiti || {}"
                            :key="lett"
                            type="button"
                            @click="togglAmbito(lett)"
                            class="text-left p-3 border rounded-md transition cursor-pointer"
                            :class="form.ambiti_attivita.includes(lett)
                                ? 'border-blue-500 bg-blue-50'
                                : 'border-gray-200 bg-white hover:bg-gray-50'"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">
                                        <span class="font-mono text-xs text-gray-400">{{ lett }})</span>
                                        {{ amb.titolo }}
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">{{ amb.descrizione }}</p>
                                </div>
                                <span v-if="form.ambiti_attivita.includes(lett)" class="text-blue-600 flex-shrink-0">✓</span>
                            </div>
                        </button>
                    </div>
                    <div v-if="form.ambiti_attivita.length > 1" class="mt-3">
                        <label class="block text-sm font-medium text-gray-700">Ambito principale (per dichiarazioni RUNTS)</label>
                        <select v-model="form.attivita_principale" class="mt-1 block w-full md:w-1/2 rounded-md border-gray-300 shadow-sm">
                            <option value="">— seleziona —</option>
                            <option v-for="lett in form.ambiti_attivita" :key="lett" :value="lett">
                                {{ lett.toUpperCase() }}) {{ etsCatalog?.ambiti[lett]?.titolo }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- ── Polizza volontari (OdV/APS) ── -->
                <div v-if="['ets_odv', 'ets_aps'].includes(form.forma_giuridica)"
                    class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
                    <h3 class="text-sm font-bold text-amber-800 uppercase tracking-wide mb-1">Assicurazione volontari (obbligatoria)</h3>
                    <p class="text-xs text-amber-700 mb-3">📚 Art. 18 D.Lgs. 117/2017: OdV e APS hanno l'obbligo di assicurare i volontari contro infortuni, malattie e responsabilità civile verso terzi.</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Numero polizza</label>
                            <input v-model="form.assicurazione_volontari_polizza" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Compagnia</label>
                            <input v-model="form.assicurazione_volontari_compagnia" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Scadenza</label>
                            <input v-model="form.assicurazione_volontari_scadenza" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                    </div>
                </div>

                <!-- ── Trasparenza ── -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-1">Trasparenza (L. 124/2017)</h3>
                    <p class="text-xs text-gray-600 mb-3">URL pubblico dove pubblichi il bilancio (obbligo di trasparenza per ETS).</p>
                    <input v-model="form.bilancio_url_pubblicazione" type="text" placeholder="https://..." class="block w-full rounded-md border-gray-300 shadow-sm" />
                </div>
            </div>

            <!-- ── STEP FINALE: Riepilogo ───────────────────────────────────── -->
            <div v-else-if="currentStep === stepFinale">
                <h2 class="text-xl font-semibold text-gray-900 mb-2">Riepilogo configurazione</h2>
                <p class="text-sm text-gray-600 mb-6">Controlla i dati prima di confermare. Potrai sempre modificarli successivamente.</p>

                <dl class="bg-gray-50 rounded-lg p-4 space-y-2 text-sm">
                    <div class="grid grid-cols-3 gap-2">
                        <dt class="font-semibold text-gray-700">Forma giuridica</dt>
                        <dd class="col-span-2 text-gray-900">{{ formaLabels[form.forma_giuridica] }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <dt class="font-semibold text-gray-700">Dimensione bilancio</dt>
                        <dd class="col-span-2 text-gray-900">{{ dimensioneLabels[form.dimensione_bilancio] || '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <dt class="font-semibold text-gray-700">Regime contabile</dt>
                        <dd class="col-span-2 text-gray-900">{{ regimeContabileLabels[form.regime_contabile] || '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <dt class="font-semibold text-gray-700">Regime IVA</dt>
                        <dd class="col-span-2 text-gray-900">{{ regimeIvaLabels[form.regime_iva] || '—' }}</dd>
                    </div>
                    <div v-if="profiloLocal" class="grid grid-cols-3 gap-2">
                        <dt class="font-semibold text-gray-700">Piano dei conti</dt>
                        <dd class="col-span-2 text-gray-900 font-mono text-xs">{{ profiloLocal.piano_conti_template }}</dd>
                    </div>
                    <div v-if="profiloLocal" class="grid grid-cols-3 gap-2">
                        <dt class="font-semibold text-gray-700">Schema bilancio</dt>
                        <dd class="col-span-2 text-gray-900 font-mono text-xs">{{ profiloLocal.schema_bilancio }}</dd>
                    </div>
                    <div v-if="profiloLocal" class="grid grid-cols-3 gap-2">
                        <dt class="font-semibold text-gray-700">Moduli abilitati</dt>
                        <dd class="col-span-2 flex flex-wrap gap-1">
                            <span v-for="m in profiloLocal.moduli" :key="m"
                                class="inline-flex px-2 py-0.5 text-xs bg-blue-100 text-blue-800 rounded">
                                {{ m.replace('_', ' ') }}
                            </span>
                        </dd>
                    </div>
                    <div class="border-t pt-2 mt-2 grid grid-cols-3 gap-2">
                        <dt class="font-semibold text-gray-700">Codice fiscale</dt>
                        <dd class="col-span-2 text-gray-900 font-mono">{{ form.codice_fiscale || '—' }}</dd>
                    </div>
                    <div v-if="form.partita_iva" class="grid grid-cols-3 gap-2">
                        <dt class="font-semibold text-gray-700">Partita IVA</dt>
                        <dd class="col-span-2 text-gray-900 font-mono">{{ form.partita_iva }}</dd>
                    </div>
                    <div v-if="form.pec" class="grid grid-cols-3 gap-2">
                        <dt class="font-semibold text-gray-700">PEC</dt>
                        <dd class="col-span-2 text-gray-900">{{ form.pec }}</dd>
                    </div>
                    <div v-if="form.indirizzo" class="grid grid-cols-3 gap-2">
                        <dt class="font-semibold text-gray-700">Sede legale</dt>
                        <dd class="col-span-2 text-gray-900">{{ form.indirizzo }}, {{ form.cap }} {{ form.citta }} ({{ form.provincia }})</dd>
                    </div>

                    <!-- ── Sezione ETS riepilogo ── -->
                    <template v-if="isEtsForma">
                        <div class="border-t pt-2 mt-3">
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Compliance ETS</p>
                        </div>
                        <div v-if="form.runts_sezione" class="grid grid-cols-3 gap-2">
                            <dt class="font-semibold text-gray-700">RUNTS</dt>
                            <dd class="col-span-2 text-gray-900">
                                Sezione {{ form.runts_sezione.toUpperCase() }} — {{ etsCatalog?.sezioni_runts[form.runts_sezione] }}
                                <span v-if="form.runts_numero" class="font-mono text-xs">· n. {{ form.runts_numero }}</span>
                                <span v-if="form.runts_data_iscrizione"> · iscritto il {{ form.runts_data_iscrizione }}</span>
                            </dd>
                        </div>
                        <div v-if="form.personalita_giuridica" class="grid grid-cols-3 gap-2">
                            <dt class="font-semibold text-gray-700">Personalità giuridica</dt>
                            <dd class="col-span-2 text-gray-900">
                                ✓ Sì — patrimonio destinato: {{ Number(form.patrimonio_destinato || 0).toLocaleString('it-IT') }} €
                                <span v-if="Number(form.patrimonio_destinato || 0) >= patrimonioMinimo" class="text-green-600">(≥ minimo {{ patrimonioMinimo.toLocaleString('it-IT') }} €)</span>
                                <span v-else class="text-red-600">(⚠ inferiore al minimo {{ patrimonioMinimo.toLocaleString('it-IT') }} €)</span>
                            </dd>
                        </div>
                        <div v-if="form.fascia_entrate" class="grid grid-cols-3 gap-2">
                            <dt class="font-semibold text-gray-700">Fascia entrate</dt>
                            <dd class="col-span-2 text-gray-900 text-xs">{{ etsCatalog?.fasce_entrate[form.fascia_entrate] }}</dd>
                        </div>
                        <div v-if="form.ambiti_attivita.length" class="grid grid-cols-3 gap-2">
                            <dt class="font-semibold text-gray-700">Ambiti art. 5</dt>
                            <dd class="col-span-2 flex flex-wrap gap-1">
                                <span v-for="lett in form.ambiti_attivita" :key="lett"
                                    class="inline-flex px-2 py-0.5 text-xs rounded"
                                    :class="form.attivita_principale === lett ? 'bg-blue-200 text-blue-900 font-semibold' : 'bg-blue-100 text-blue-800'">
                                    {{ lett }}) {{ etsCatalog?.ambiti[lett]?.titolo }}
                                </span>
                            </dd>
                        </div>
                        <div v-if="form.assicurazione_volontari_polizza" class="grid grid-cols-3 gap-2">
                            <dt class="font-semibold text-gray-700">Polizza volontari</dt>
                            <dd class="col-span-2 text-gray-900 text-xs">
                                n. {{ form.assicurazione_volontari_polizza }}
                                <span v-if="form.assicurazione_volontari_compagnia"> · {{ form.assicurazione_volontari_compagnia }}</span>
                                <span v-if="form.assicurazione_volontari_scadenza"> · scad. {{ form.assicurazione_volontari_scadenza }}</span>
                            </dd>
                        </div>
                    </template>
                </dl>

                <!-- Riferimenti normativi della configurazione -->
                <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm">
                    <p class="font-semibold text-blue-900 mb-2">📚 Riferimenti normativi applicati</p>
                    <ul class="text-xs text-blue-800 space-y-1 list-disc list-inside">
                        <li v-if="form.dimensione_bilancio && noteNormative?.dimensione[form.dimensione_bilancio]">
                            <strong>Bilancio:</strong> {{ noteNormative.dimensione[form.dimensione_bilancio].titolo }} ({{ noteNormative.dimensione[form.dimensione_bilancio].fonte }})
                        </li>
                        <li v-if="form.regime_contabile && noteNormative?.regime_contabile[form.regime_contabile]">
                            <strong>Contabilità:</strong> {{ noteNormative.regime_contabile[form.regime_contabile].titolo }} ({{ noteNormative.regime_contabile[form.regime_contabile].fonte }})
                        </li>
                        <li v-if="form.regime_iva && noteNormative?.regime_iva[form.regime_iva]">
                            <strong>IVA:</strong> {{ noteNormative.regime_iva[form.regime_iva].titolo }} ({{ noteNormative.regime_iva[form.regime_iva].fonte }})
                        </li>
                    </ul>
                </div>

                <div class="mt-3 bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800">
                    💡 Una volta confermato, i moduli e i template verranno applicati al tenant. La configurazione resta modificabile dalla pagina di dettaglio.
                </div>
            </div>
        </div>

        <!-- Navigation buttons -->
        <div class="flex items-center justify-between">
            <button
                type="button"
                @click="goPrev"
                :disabled="currentStep === 1 || form.processing"
                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-300 disabled:opacity-30 disabled:cursor-not-allowed"
            >
                ← Indietro
            </button>

            <span class="text-sm text-gray-500">Step {{ currentStep }} di {{ stepFinale }}</span>

            <button
                v-if="currentStep < stepFinale"
                type="button"
                @click="goNext"
                :disabled="!canGoNext || form.processing"
                class="px-6 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                Avanti →
            </button>
            <button
                v-else
                type="button"
                @click="completa"
                :disabled="form.processing"
                class="px-6 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700 disabled:opacity-50"
            >
                {{ form.processing ? 'Salvataggio...' : '✓ Conferma e completa' }}
            </button>
        </div>
    </AdminLayout>
</template>
