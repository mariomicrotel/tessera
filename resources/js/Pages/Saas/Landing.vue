<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    plans: Object,
});
</script>

<template>
    <Head title="Tessera - Gestionale Cloud per ETS e Cooperative" />

    <div class="min-h-screen bg-white">
        <!-- Navbar -->
        <nav class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <div class="flex items-center gap-3">
                        <!-- TODO: Inserire logo Infotel Sistemi qui -->
                        <div class="flex items-center gap-2">
                            <img src="/images/infotel-logo.svg" alt="Infotel Sistemi" class="h-8 w-auto" />
                            <div class="text-sm font-semibold text-gray-900">Tessera</div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <Link :href="route('saas.pricing')" class="text-gray-600 hover:text-gray-900">Prezzi</Link>
                        <Link :href="route('login')" class="text-gray-600 hover:text-gray-900">Accedi</Link>
                        <Link :href="route('saas.register')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                            Prova Gratis
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Hero -->
        <section class="py-20 bg-gradient-to-br from-blue-50 to-indigo-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <div class="inline-block mb-4 px-4 py-2 bg-blue-600/10 rounded-full border border-blue-200">
                    <span class="text-sm font-semibold text-blue-600">By Infotel Sistemi</span>
                </div>
                <h1 class="text-5xl font-extrabold text-gray-900 mb-6">
                    Tessera
                    <br />
                    <span class="text-blue-600">Il gestionale cloud per ETS e Cooperative</span>
                </h1>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto mb-10">
                    Gestione soci, contabilità digitale, organi sociali, ristorni cooperativi, protocolli e documenti.
                    Una piattaforma completa, conforme alle normative italiane, senza installazione.
                </p>
                <div class="flex justify-center space-x-4">
                    <Link :href="route('saas.register')" class="bg-blue-600 text-white px-8 py-3 rounded-lg text-lg font-semibold hover:bg-blue-700 transition shadow-lg">
                        Inizia la prova gratuita
                    </Link>
                    <Link :href="route('saas.pricing')" class="bg-white text-blue-600 border-2 border-blue-600 px-8 py-3 rounded-lg text-lg font-semibold hover:bg-blue-50 transition">
                        Vedi i piani
                    </Link>
                </div>
                <p class="mt-4 text-sm text-gray-500">14 giorni di prova gratuita • Nessuna carta richiesta</p>
            </div>
        </section>

        <!-- Features -->
        <section class="py-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold text-center text-gray-900 mb-12">Tutte le funzionalità di cui hai bisogno</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <div class="p-6 bg-white rounded-xl border border-gray-200 hover:shadow-lg transition">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Libro Soci Digitale</h3>
                        <p class="text-gray-600">Gestione soci, tessere, quote sociali, ammissioni. Conforme RUNTS per ETS e normativa cooperative.</p>
                    </div>
                    <div class="p-6 bg-white rounded-xl border border-gray-200 hover:shadow-lg transition">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Contabilità Integrata</h3>
                        <p class="text-gray-600">Rendiconto cassa, bilancio ETS, ricevute, incassi. Report automatici e moduli contabili specializzati.</p>
                    </div>
                    <div class="p-6 bg-white rounded-xl border border-gray-200 hover:shadow-lg transition">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Protocollo Comunicazioni</h3>
                        <p class="text-gray-600">Registro entrata/uscita documenti, protocolli interni, allegati e gestione archivio digitale completo.</p>
                    </div>
                    <div class="p-6 bg-white rounded-xl border border-gray-200 hover:shadow-lg transition">
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Gestione Cooperative</h3>
                        <p class="text-gray-600">Ristorni, prestito sociale, capitale sociale, bilancio cooperativo, assemblee e cariche.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- A chi è rivolto -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold text-center text-gray-900 mb-4">A chi è rivolto</h2>
                <p class="text-center text-gray-600 mb-12 max-w-2xl mx-auto">Una piattaforma pensata per le esigenze specifiche del Terzo Settore e del mondo cooperativo.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="p-8 bg-white rounded-xl border border-gray-200 hover:shadow-lg transition">
                        <div class="w-14 h-14 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Enti del Terzo Settore (ETS)</h3>
                        <p class="text-gray-600 mb-4">ODV, APS, associazioni e enti iscritti o in iscrizione al RUNTS. Conforme Riforma 2017.</p>
                        <ul class="space-y-2 text-gray-700">
                            <li class="flex items-start"><span class="text-blue-600 mr-2">✓</span> Libro soci digitale RUNTS</li>
                            <li class="flex items-start"><span class="text-blue-600 mr-2">✓</span> Rendiconto di cassa (Modello D ETS)</li>
                            <li class="flex items-start"><span class="text-blue-600 mr-2">✓</span> Ricevute fiscali e 5x1000</li>
                            <li class="flex items-start"><span class="text-blue-600 mr-2">✓</span> Protocolli comunicazioni e verbali</li>
                        </ul>
                    </div>
                    <div class="p-8 bg-white rounded-xl border border-gray-200 hover:shadow-lg transition">
                        <div class="w-14 h-14 bg-orange-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-7 h-7 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Cooperative</h3>
                        <p class="text-gray-600 mb-4">Coop di lavoro, sociali, agricole, di consumo, abitazione. Conforme al Codice Civile.</p>
                        <ul class="space-y-2 text-gray-700">
                            <li class="flex items-start"><span class="text-orange-600 mr-2">✓</span> Libro soci cooperatori con capitale sociale</li>
                            <li class="flex items-start"><span class="text-orange-600 mr-2">✓</span> Ristorni, dividendi e prestito sociale</li>
                            <li class="flex items-start"><span class="text-orange-600 mr-2">✓</span> Assemblee, elezioni e verbali</li>
                            <li class="flex items-start"><span class="text-orange-600 mr-2">✓</span> Bilancio cooperativo e contabilità</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Numeri -->
        <section class="py-16 bg-blue-600 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                    <div>
                        <div class="text-4xl font-extrabold mb-2">25+</div>
                        <div class="text-blue-100">Anni di esperienza</div>
                    </div>
                    <div>
                        <div class="text-4xl font-extrabold mb-2">100+</div>
                        <div class="text-blue-100">ETS e cooperative</div>
                    </div>
                    <div>
                        <div class="text-4xl font-extrabold mb-2">99,9%</div>
                        <div class="text-blue-100">Uptime garantito</div>
                    </div>
                    <div>
                        <div class="text-4xl font-extrabold mb-2">Italia</div>
                        <div class="text-blue-100">Server in Italia GDPR</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Perché sceglierci -->
        <section class="py-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold text-center text-gray-900 mb-4">Perché scegliere Tessera</h2>
                <p class="text-center text-gray-600 mb-12 max-w-2xl mx-auto">Sviluppato da Infotel Sistemi, con 25+ anni di esperienza nel software gestionali italiani.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">100% Cloud</h3>
                            <p class="text-gray-600">Nessuna installazione, nessun server da gestire. Accedi da qualsiasi dispositivo con una connessione internet.</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Dati al sicuro</h3>
                            <p class="text-gray-600">Server in Italia, conformi GDPR. Crittografia end-to-end e backup automatici giornalieri.</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Aggiornamenti normativi</h3>
                            <p class="text-gray-600">Riforma Terzo Settore, RUNTS, normative cooperative: aggiornamenti automatici inclusi nel canone.</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Supporto dedicato</h3>
                            <p class="text-gray-600">Team di esperti in Terzo Settore e diritto cooperativo a tua disposizione via email, telefono e chat.</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-pink-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Nessun costo nascosto</h3>
                            <p class="text-gray-600">Canone trasparente, tutte le funzionalità incluse. Nessuna sorpresa, nessun modulo extra a pagamento.</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Migrazione assistita</h3>
                            <p class="text-gray-600">Trasferiamo noi i tuoi dati dal vecchio gestionale o da fogli Excel. Operativi in pochi giorni.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Come funziona -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold text-center text-gray-900 mb-4">Come iniziare</h2>
                <p class="text-center text-gray-600 mb-12 max-w-2xl mx-auto">Operativi in tre semplici passi.</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-blue-600 text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-4">1</div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Registrati</h3>
                        <p class="text-gray-600">Crea il tuo account in meno di 2 minuti. 14 giorni gratis, nessuna carta di credito richiesta.</p>
                    </div>
                    <div class="text-center">
                        <div class="w-16 h-16 bg-blue-600 text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-4">2</div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Configura</h3>
                        <p class="text-gray-600">Importa soci e contabilità da Excel o dal precedente gestionale. Ti aiutiamo noi se preferisci.</p>
                    </div>
                    <div class="text-center">
                        <div class="w-16 h-16 bg-blue-600 text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-4">3</div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Gestisci</h3>
                        <p class="text-gray-600">Inizia subito a gestire la tua organizzazione in modo digitale, semplice e conforme.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimonianze -->
        <section class="py-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold text-center text-gray-900 mb-12">Cosa dicono di noi</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="p-6 bg-white rounded-xl border border-gray-200">
                        <div class="text-yellow-400 mb-3">★★★★★</div>
                        <p class="text-gray-700 italic mb-4">"Finalmente un gestionale che capisce le esigenze di un'APS. Il libro soci digitale ci ha fatto risparmiare ore ogni mese."</p>
                        <div class="text-sm">
                            <div class="font-semibold text-gray-900">Marco R.</div>
                            <div class="text-gray-500">Presidente APS, Milano</div>
                        </div>
                    </div>
                    <div class="p-6 bg-white rounded-xl border border-gray-200">
                        <div class="text-yellow-400 mb-3">★★★★★</div>
                        <p class="text-gray-700 italic mb-4">"La gestione dei ristorni e del prestito sociale è finalmente semplice. Assistenza italiana sempre disponibile."</p>
                        <div class="text-sm">
                            <div class="font-semibold text-gray-900">Laura M.</div>
                            <div class="text-gray-500">Cooperativa Sociale, Bologna</div>
                        </div>
                    </div>
                    <div class="p-6 bg-white rounded-xl border border-gray-200">
                        <div class="text-yellow-400 mb-3">★★★★★</div>
                        <p class="text-gray-700 italic mb-4">"Migrazione dal vecchio gestionale fatta in 3 giorni. Modello D pronto in un click, una rivoluzione."</p>
                        <div class="text-sm">
                            <div class="font-semibold text-gray-900">Giuseppe T.</div>
                            <div class="text-gray-500">Tesoriere ODV, Roma</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold text-center text-gray-900 mb-12">Domande frequenti</h2>
                <div class="space-y-4">
                    <details class="group p-6 bg-white rounded-xl border border-gray-200">
                        <summary class="flex justify-between items-center font-semibold text-gray-900 cursor-pointer">
                            Quanto costa Network GTC?
                            <span class="text-blue-600 group-open:rotate-180 transition">▼</span>
                        </summary>
                        <p class="mt-4 text-gray-600">Abbiamo piani tariffari pensati per organizzazioni di ogni dimensione, a partire da pochi euro al mese. Consulta la pagina <Link :href="route('saas.pricing')" class="text-blue-600 hover:underline">Prezzi</Link> per i dettagli.</p>
                    </details>
                    <details class="group p-6 bg-white rounded-xl border border-gray-200">
                        <summary class="flex justify-between items-center font-semibold text-gray-900 cursor-pointer">
                            È davvero conforme alla normativa RUNTS?
                            <span class="text-blue-600 group-open:rotate-180 transition">▼</span>
                        </summary>
                        <p class="mt-4 text-gray-600">Sì. Il libro soci digitale, il rendiconto di cassa (Modello D) e tutti i registri sono allineati alla riforma del Terzo Settore e vengono aggiornati ad ogni modifica normativa.</p>
                    </details>
                    <details class="group p-6 bg-white rounded-xl border border-gray-200">
                        <summary class="flex justify-between items-center font-semibold text-gray-900 cursor-pointer">
                            Posso importare i dati dal mio attuale gestionale?
                            <span class="text-blue-600 group-open:rotate-180 transition">▼</span>
                        </summary>
                        <p class="mt-4 text-gray-600">Certo. Supportiamo l'import da Excel, CSV e dai principali gestionali del settore. Il nostro team ti assiste gratuitamente durante la migrazione.</p>
                    </details>
                    <details class="group p-6 bg-white rounded-xl border border-gray-200">
                        <summary class="flex justify-between items-center font-semibold text-gray-900 cursor-pointer">
                            I miei dati sono al sicuro?
                            <span class="text-blue-600 group-open:rotate-180 transition">▼</span>
                        </summary>
                        <p class="mt-4 text-gray-600">Server in Italia, conformi GDPR. Crittografia in transito e a riposo, backup automatici giornalieri con conservazione di 30 giorni.</p>
                    </details>
                    <details class="group p-6 bg-white rounded-xl border border-gray-200">
                        <summary class="flex justify-between items-center font-semibold text-gray-900 cursor-pointer">
                            Cosa succede dopo i 14 giorni di prova?
                            <span class="text-blue-600 group-open:rotate-180 transition">▼</span>
                        </summary>
                        <p class="mt-4 text-gray-600">Nessun addebito automatico. Al termine della prova decidi se attivare un piano a pagamento. Se non scegli, l'account viene sospeso ma i dati restano disponibili.</p>
                    </details>
                    <details class="group p-6 bg-white rounded-xl border border-gray-200">
                        <summary class="flex justify-between items-center font-semibold text-gray-900 cursor-pointer">
                            Posso gestire più organizzazioni con lo stesso account?
                            <span class="text-blue-600 group-open:rotate-180 transition">▼</span>
                        </summary>
                        <p class="mt-4 text-gray-600">Sì. Network GTC è multi-tenant: con un solo accesso puoi passare da un'organizzazione all'altra, ideale per commercialisti, consulenti e centri di servizio.</p>
                    </details>
                </div>
            </div>
        </section>

        <!-- CTA finale -->
        <section class="py-20 bg-gradient-to-br from-blue-600 to-indigo-700 text-white">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h2 class="text-4xl font-extrabold mb-4">Pronto a digitalizzare la tua organizzazione?</h2>
                <p class="text-xl text-blue-100 mb-8">Unisciti alle centinaia di ETS e cooperative che hanno già scelto Tessera.</p>
                <div class="flex justify-center space-x-4">
                    <Link :href="route('saas.register')" class="bg-white text-blue-600 px-8 py-3 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg">
                        Inizia gratis
                    </Link>
                    <Link :href="route('saas.pricing')" class="bg-transparent text-white border-2 border-white px-8 py-3 rounded-lg text-lg font-semibold hover:bg-white hover:text-blue-600 transition">
                        Vedi i piani
                    </Link>
                </div>
                <p class="mt-4 text-sm text-blue-100">14 giorni gratis • Nessuna carta richiesta • Disdici quando vuoi</p>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-gray-900 text-white py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
                    <div>
                        <div class="flex items-center gap-2 mb-4">
                            <img src="/images/infotel-logo.svg" alt="Infotel Sistemi" class="h-6 w-auto" />
                            <div class="text-sm font-semibold">Tessera</div>
                        </div>
                        <p class="text-gray-400 text-sm">Il gestionale cloud per ETS e cooperative, sviluppato da Infotel Sistemi.</p>
                    </div>
                    <div>
                        <h4 class="font-semibold mb-4">Prodotto</h4>
                        <ul class="space-y-2 text-gray-400 text-sm">
                            <li><Link :href="route('saas.pricing')" class="hover:text-white">Prezzi</Link></li>
                            <li><Link :href="route('saas.register')" class="hover:text-white">Prova gratuita</Link></li>
                            <li><Link :href="route('login')" class="hover:text-white">Accedi</Link></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-semibold mb-4">Risorse</h4>
                        <ul class="space-y-2 text-gray-400 text-sm">
                            <li><a href="#" class="hover:text-white">Documentazione</a></li>
                            <li><a href="#" class="hover:text-white">Guide RUNTS</a></li>
                            <li><a href="#" class="hover:text-white">Guide Cooperative</a></li>
                            <li><a href="#" class="hover:text-white">Supporto</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-semibold mb-4">Contatti Infotel</h4>
                        <ul class="space-y-2 text-gray-400 text-sm">
                            <li><a href="mailto:info@infotelsistemi.com" class="hover:text-white">info@infotelsistemi.com</a></li>
                            <li>Lun–Ven 9:00–18:00</li>
                            <li><a href="#" class="hover:text-white">Richiedi una demo</a></li>
                        </ul>
                    </div>
                </div>
                <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center text-sm text-gray-400">
                    <p>&copy; {{ new Date().getFullYear() }} Infotel Sistemi S.p.A. Tutti i diritti riservati. Tessera è un prodotto Infotel.</p>
                    <div class="flex space-x-6 mt-4 md:mt-0">
                        <a href="#" class="hover:text-white">Privacy</a>
                        <a href="#" class="hover:text-white">Termini</a>
                        <a href="#" class="hover:text-white">Cookie</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
