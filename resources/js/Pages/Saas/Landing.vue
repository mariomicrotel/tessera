<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    plans: Object,
});
</script>

<template>
    <Head title="Infotel Sistemi - Gestionale ETS" />

    <div class="min-h-screen bg-white">
        <!-- Navbar -->
        <nav class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                            <span class="text-white font-bold text-lg">I</span>
                        </div>
                        <span class="text-xl font-bold text-gray-900">Infotel Sistemi</span>
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
                <h1 class="text-5xl font-extrabold text-gray-900 mb-6">
                    Il gestionale per il tuo
                    <span class="text-blue-600">Ente del Terzo Settore</span>
                </h1>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto mb-10">
                    Gestisci soci, contabilità, organi sociali, eventi e documenti.
                    Tutto in un'unica piattaforma cloud, senza installazione.
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
                <h2 class="text-3xl font-bold text-center text-gray-900 mb-12">Tutto ciò di cui hai bisogno</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="p-6 bg-white rounded-xl border border-gray-200 hover:shadow-lg transition">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Gestione Soci</h3>
                        <p class="text-gray-600">Libro soci, ammissioni, quote, tessere e comunicazioni. Tutto digitalizzato e conforme al RUNTS.</p>
                    </div>
                    <div class="p-6 bg-white rounded-xl border border-gray-200 hover:shadow-lg transition">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Contabilità ETS</h3>
                        <p class="text-gray-600">Prima nota, rendiconto cassa (Modello D), ricevute fiscali e gestione spese con report automatici.</p>
                    </div>
                    <div class="p-6 bg-white rounded-xl border border-gray-200 hover:shadow-lg transition">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Organi e Governance</h3>
                        <p class="text-gray-600">Organi sociali, cariche, elezioni online, verbali assembleari e gestione documentale.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-gray-900 text-white py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <div class="flex items-center justify-center space-x-3 mb-4">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold">I</span>
                    </div>
                    <span class="text-lg font-bold">Infotel Sistemi</span>
                </div>
                <p class="text-gray-400">&copy; {{ new Date().getFullYear() }} Infotel Sistemi. Tutti i diritti riservati.</p>
            </div>
        </footer>
    </div>
</template>
