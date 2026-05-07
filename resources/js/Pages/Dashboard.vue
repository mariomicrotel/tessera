<script setup>
import {
    UserGroupIcon, BanknotesIcon, ExclamationTriangleIcon, UserCircleIcon,
    CalendarDaysIcon, ClipboardDocumentListIcon, BuildingLibraryIcon,
    BuildingOffice2Icon, ScaleIcon, ArrowTrendingUpIcon, WrenchScrewdriverIcon,
    ExclamationCircleIcon,
} from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ScadenziarioPagamentiWidget from '@/Components/Dashboard/ScadenziarioPagamentiWidget.vue';
import AnalyticsCharts from '@/Components/Dashboard/AnalyticsCharts.vue';
import { computed } from 'vue';

const props = defineProps({
    forSocio: Boolean,
    noRole: { type: Boolean, default: false },
    memberStatusBlocked: { type: Boolean, default: false },
    member: Object,
    votazioniAperte: { type: Array, default: () => [] },
    members_count: Number,
    payments_this_month: [Number, String],
    members_not_in_regola: { type: Number, default: 0 },
    upcoming_events: { type: Array, default: () => [] },
    recent_incassi: { type: Array, default: () => [] },
    organiInScadenza: { type: Array, default: () => [] },
    refunds_pending_approval: { type: Array, default: () => [] },
    saldi_conti: { type: Array, default: () => [] },
    liquidita_totale: { type: Number, default: 0 },
    // KPI cooperativa (presenti solo se is_cooperativa)
    capitale_versato_totale: { type: Number, default: null },
    quote_da_versare: { type: Number, default: null },
    totale_prestito_sociale: { type: Number, default: null },
    ristorno_ultimo_anno: { type: Number, default: null },
    soci_lavoratori_attivi: { type: Number, default: null },
    cooperative_type: { type: String, default: null },
    // Scadenziario pagamenti (presenti solo se is_cooperativa con modulo IVA)
    fatture_in_scadenza: { type: Array, default: () => [] },
    // Analytics grafici
    grafico_labels:     { type: Array,  default: () => [] },
    grafico_incassi:    { type: Array,  default: () => [] },
    grafico_uscite:     { type: Array,  default: () => [] },
    grafico_soci_stato: { type: Object, default: () => ({}) },
    grafico_iscrizioni: { type: Array,  default: () => [] },
    grafico_cessazioni: { type: Array,  default: () => [] },
    grafico_donazioni:  { type: Object, default: null },
});

const page = usePage();
const is_cooperativa = computed(() => page.props.is_cooperativa === true);

const fmtEur = (v) => '€\u00a0' + Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <AppLayout title="Dashboard">
        <Head title="Dashboard" />
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Dashboard</h2>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Utente senza ruoli: nessun dato -->
                <div v-if="noRole" class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Account in attesa di attivazione</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Il tuo account non ha ancora un ruolo assegnato. Non puoi accedere ai contenuti dell’area riservata. Contatta un amministratore per ottenere l’attivazione.</p>
                    </div>
                </div>

                <!-- Socio con stato diverso da Attivo: accesso area soci non disponibile -->
                <div v-else-if="memberStatusBlocked" class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Accesso all'area soci non disponibile</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">L'accesso all'area riservata è consentito solo ai soci con stato <strong>Attivo</strong>. Per il tuo stato attuale non puoi accedere ai contenuti riservati. Contatta la segreteria per informazioni.</p>
                    </div>
                </div>

                <!-- Dashboard socio: solo area personale -->
                <div v-else-if="forSocio && member" class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Benvenuto, {{ member.full_name }}</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Qui puoi vedere il riepilogo del tuo profilo socio e lo stato della quota.</p>
                        <div class="mb-4">
                            <span v-if="member.in_regola_con_quota" class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">In regola con la quota sociale</span>
                            <span v-else class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">Non in regola con la quota sociale</span>
                        </div>
                        <Link :href="route('members.show', member.id)">
                            <PrimaryButton><UserCircleIcon class="size-4 me-2" aria-hidden="true" />Vai al mio profilo</PrimaryButton>
                        </Link>
                    </div>
                    <div v-if="votazioniAperte?.length" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Votazioni in corso</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Puoi esprimere il tuo voto (anonimo) per le seguenti votazioni.</p>
                        <ul class="space-y-2">
                            <li v-for="v in votazioniAperte" :key="v.id">
                                <Link :href="route('elezioni.vota', v.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">{{ v.titolo }}</Link>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Dashboard staff: riepilogo compatto (solo se ha almeno un ruolo) -->
                <div v-else class="space-y-4">
                    <!-- Card Riepilogo: 3 KPI in un unico box -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="flex items-center gap-3">
                                <UserGroupIcon class="size-6 text-gray-400 dark:text-gray-500 shrink-0" aria-hidden="true" />
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Soci attivi</div>
                                    <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ members_count ?? 0 }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <BanknotesIcon class="size-6 text-gray-400 dark:text-gray-500 shrink-0" aria-hidden="true" />
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Incassi questo mese</div>
                                    <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">€ {{ Number(payments_this_month ?? 0).toFixed(2) }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <ExclamationTriangleIcon class="size-6 text-gray-400 dark:text-gray-500 shrink-0" aria-hidden="true" />
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Soci non in regola</div>
                                    <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ members_not_in_regola ?? 0 }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- KPI Cooperativa (visibili solo per organizzazioni cooperative) -->
                    <div v-if="is_cooperativa" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <h3 class="text-sm font-semibold text-purple-700 dark:text-purple-400 uppercase tracking-wide mb-3 flex items-center gap-2">
                            <BuildingOffice2Icon class="size-4 shrink-0" aria-hidden="true" />
                            Cooperativa — riepilogo
                        </h3>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <!-- Capitale Versato -->
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 flex-shrink-0 w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                    <BanknotesIcon class="size-4 text-green-600 dark:text-green-400" aria-hidden="true" />
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide leading-tight">Capitale versato</div>
                                    <div class="text-lg font-semibold text-green-700 dark:text-green-400 tabular-nums">{{ fmtEur(capitale_versato_totale) }}</div>
                                </div>
                            </div>
                            <!-- Prestito Sociale -->
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                    <ScaleIcon class="size-4 text-blue-600 dark:text-blue-400" aria-hidden="true" />
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide leading-tight">Prestito sociale</div>
                                    <div class="text-lg font-semibold text-blue-700 dark:text-blue-400 tabular-nums">{{ fmtEur(totale_prestito_sociale) }}</div>
                                </div>
                            </div>
                            <!-- Ristorni anno precedente -->
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 flex-shrink-0 w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                                    <ArrowTrendingUpIcon class="size-4 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide leading-tight">Ristorni {{ new Date().getFullYear() - 1 }}</div>
                                    <div class="text-lg font-semibold text-indigo-700 dark:text-indigo-400 tabular-nums">{{ fmtEur(ristorno_ultimo_anno) }}</div>
                                </div>
                            </div>
                            <!-- Soci lavoratori (solo coop di lavoro/sociale) -->
                            <div v-if="cooperative_type && ['lavoro','sociale_a','sociale_b'].includes(cooperative_type)" class="flex items-start gap-3">
                                <div class="mt-0.5 flex-shrink-0 w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                                    <WrenchScrewdriverIcon class="size-4 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide leading-tight">Soci lavoratori</div>
                                    <div class="text-lg font-semibold text-emerald-700 dark:text-emerald-400">{{ soci_lavoratori_attivi ?? 0 }}</div>
                                </div>
                            </div>
                            <!-- Slot vuoto se soci lavoratori non applicabile, per mantenere il grid -->
                            <div v-else />
                        </div>
                        <!-- Quote da versare: alert arancio se > 0 -->
                        <div
                            v-if="(quote_da_versare ?? 0) > 0"
                            class="mt-4 flex items-center gap-3 px-3 py-2.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700"
                        >
                            <ExclamationCircleIcon class="size-5 text-amber-500 dark:text-amber-400 shrink-0" aria-hidden="true" />
                            <div class="flex-1 min-w-0">
                                <span class="text-sm font-medium text-amber-800 dark:text-amber-300">Quote da versare: </span>
                                <span class="text-sm font-semibold text-amber-900 dark:text-amber-200 tabular-nums">{{ fmtEur(quote_da_versare) }}</span>
                                <span class="text-xs text-amber-700 dark:text-amber-400 ms-1">(quota sottoscritta non ancora completamente versata)</span>
                            </div>
                            <Link :href="route('capitale-sociale.index')" class="text-xs text-amber-700 dark:text-amber-400 hover:underline font-medium shrink-0">Gestisci →</Link>
                        </div>
                        <!-- Tutti i link di navigazione rapida -->
                        <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 flex flex-wrap gap-3 text-xs">
                            <Link :href="route('capitale-sociale.index')" class="text-purple-600 dark:text-purple-400 hover:underline">Capitale sociale</Link>
                            <Link :href="route('prestito-sociale.index')" class="text-purple-600 dark:text-purple-400 hover:underline">Prestito sociale</Link>
                            <Link :href="route('ristorni.index')" class="text-purple-600 dark:text-purple-400 hover:underline">Ristorni</Link>
                            <Link :href="route('reports.situazione-capitale')" class="text-purple-600 dark:text-purple-400 hover:underline">Situazione capitale</Link>
                        </div>
                    </div>

                    <!-- Widget Scadenziario Pagamenti (solo per cooperative) -->
                    <ScadenziarioPagamentiWidget
                        v-if="is_cooperativa && fatture_in_scadenza?.length"
                        :fatture="fatture_in_scadenza"
                    />

                    <!-- Card Liquidità conti -->
                    <div v-if="saldi_conti?.length" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-base font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                <BuildingLibraryIcon class="size-5 text-gray-400 dark:text-gray-500" aria-hidden="true" />
                                Liquidità conti
                            </h3>
                            <Link :href="route('conti.index')" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Gestisci</Link>
                        </div>
                        <!-- Totale liquidità -->
                        <div class="mb-3 pb-3 border-b border-gray-200 dark:border-gray-700 flex items-baseline justify-between">
                            <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Totale cassa + banca</span>
                            <span
                                class="text-2xl font-bold"
                                :class="(liquidita_totale ?? 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                            >
                                €&nbsp;{{ Number(liquidita_totale ?? 0).toFixed(2) }}
                            </span>
                        </div>
                        <!-- Lista conti -->
                        <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                            <li v-for="c in saldi_conti" :key="c.id" class="flex items-center justify-between py-1.5 first:pt-0 last:pb-0">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium"
                                        :class="{
                                            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300': c.type === 'cassa',
                                            'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300': c.type === 'banca',
                                            'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300': c.type === 'altro',
                                        }"
                                    >{{ c.type === 'cassa' ? 'Cassa' : c.type === 'banca' ? 'Banca' : 'Altro' }}</span>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ c.name }}</span>
                                </div>
                                <span
                                    class="text-sm font-semibold tabular-nums"
                                    :class="(c.saldo ?? 0) > 0 ? 'text-green-600 dark:text-green-400' : (c.saldo ?? 0) < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400'"
                                >
                                    €&nbsp;{{ Number(c.saldo ?? 0).toFixed(2) }}
                                </span>
                            </li>
                        </ul>
                    </div>

                    <!-- Card Da fare: rimborsi da approvare (solo se ce ne sono) -->
                    <div v-if="refunds_pending_approval?.length" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <h3 class="text-base font-medium text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                            <ClipboardDocumentListIcon class="size-5 text-amber-500 dark:text-amber-400" aria-hidden="true" />
                            Rimborsi da approvare
                        </h3>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700 space-y-0">
                            <li v-for="r in refunds_pending_approval" :key="r.id" class="py-2 first:pt-0">
                                <Link :href="route('expense-refunds.show', r.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                                    Rimborso #{{ r.id }}
                                </Link>
                                <span class="text-gray-600 dark:text-gray-300"> – {{ r.member ? r.member.cognome + ' ' + r.member.nome : '—' }} – € {{ Number(r.total ?? 0).toFixed(2) }} – {{ r.refund_date ? new Date(r.refund_date).toLocaleDateString('it-IT') : '—' }}</span>
                            </li>
                        </ul>
                        <Link :href="route('expense-refunds.index')" class="mt-2 inline-block text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Vedi tutti</Link>
                    </div>

                    <!-- Card Attività: eventi + incassi in due colonne -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div>
                                <h3 class="text-base font-medium text-gray-900 dark:text-gray-100 mb-2">Prossimi eventi</h3>
                                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <li v-for="e in upcoming_events" :key="e.id" class="py-1.5 first:pt-0">
                                        <Link :href="route('events.show', e.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline text-sm">{{ e.title }}</Link>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ e.start_at ? new Date(e.start_at).toLocaleString('it-IT') : '' }}</span>
                                    </li>
                                </ul>
                                <p v-if="!upcoming_events?.length" class="py-1.5 text-sm text-gray-500 dark:text-gray-400">Nessun evento in programma.</p>
                                <Link :href="route('events.index')" class="mt-2 inline-block text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Vedi tutti</Link>
                            </div>
                            <div>
                                <h3 class="text-base font-medium text-gray-900 dark:text-gray-100 mb-2">Ultimi incassi</h3>
                                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <li v-for="i in recent_incassi" :key="i.id" class="py-1.5 first:pt-0 text-sm">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">€ {{ Number(i.amount ?? 0).toFixed(2) }}</span>
                                        <span class="text-gray-500 dark:text-gray-400 ms-2">{{ i.paid_at ? new Date(i.paid_at).toLocaleDateString('it-IT') : '' }}</span>
                                        <span class="text-gray-500 dark:text-gray-400 ms-2">{{ i.type === 'quota' ? 'Quota' : 'Donazione' }}</span>
                                        <span v-if="i.member" class="block text-xs text-gray-600 dark:text-gray-300">{{ i.member.cognome }} {{ i.member.nome }}</span>
                                    </li>
                                </ul>
                                <p v-if="!recent_incassi?.length" class="py-1.5 text-sm text-gray-500 dark:text-gray-400">Nessun incasso recente.</p>
                                <Link :href="route('quote-sociali.index')" class="mt-2 inline-block text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Vedi tutti</Link>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics Grafici -->
                    <AnalyticsCharts
                        :labels="grafico_labels"
                        :incassi="grafico_incassi"
                        :uscite="grafico_uscite"
                        :soci-stato="grafico_soci_stato"
                        :iscrizioni="grafico_iscrizioni"
                        :cessazioni="grafico_cessazioni"
                        :donazioni="grafico_donazioni"
                        :is-cooperativa="is_cooperativa"
                    />

                    <!-- Card Mandati in scadenza -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <h3 class="text-base font-medium text-gray-900 dark:text-gray-100 mb-2 flex items-center gap-2">
                            <CalendarDaysIcon class="size-5 text-gray-400 dark:text-gray-500" aria-hidden="true" />
                            Mandati in scadenza
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Organi il cui mandato scade nei prossimi 90 giorni.</p>
                        <div v-if="organiInScadenza?.length" class="space-y-3">
                            <div v-for="o in organiInScadenza" :key="o.id" class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-1">
                                    <Link :href="route('organi.show', o.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold text-sm">
                                        {{ o.nome }}
                                    </Link>
                                    <span class="text-xs text-red-600 dark:text-red-400 font-medium">
                                        Scade il {{ o.mandato_scadenza ? new Date(o.mandato_scadenza).toLocaleDateString('it-IT') : '—' }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                    Mandato dal {{ o.mandato_da ? new Date(o.mandato_da).toLocaleDateString('it-IT') : '—' }}, durata {{ o.durata_mesi }} {{ o.durata_mesi === 1 ? 'mese' : 'mesi' }}
                                </p>
                                <ul v-if="o.cariche_sociali?.length" class="space-y-0.5 text-xs">
                                    <li v-for="c in o.cariche_sociali" :key="c.id" class="flex flex-wrap items-center gap-x-2">
                                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ c.nome }}:</span>
                                        <template v-if="c.incarichi?.length">
                                            <span v-for="(inc, idx) in c.incarichi" :key="inc.id">
                                                <Link :href="route('members.show', inc.member_id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ inc.member?.cognome }} {{ inc.member?.nome }}</Link><span v-if="idx < c.incarichi.length - 1" class="text-gray-400">, </span>
                                            </span>
                                        </template>
                                        <span v-else class="text-gray-400 italic">non assegnata</span>
                                    </li>
                                </ul>
                                <p v-else class="text-xs text-gray-400 italic">Nessuna carica definita</p>
                            </div>
                        </div>
                        <p v-else class="py-2 text-sm text-gray-500 dark:text-gray-400">Nessun mandato in scadenza nei prossimi 90 giorni.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
