<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    plans: Object,
});
</script>

<template>
    <Head title="Prezzi - Infotel Sistemi" />

    <div class="min-h-screen bg-white">
        <!-- Navbar -->
        <nav class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <Link href="/" class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                            <span class="text-white font-bold text-lg">I</span>
                        </div>
                        <span class="text-xl font-bold text-gray-900">Infotel Sistemi</span>
                    </Link>
                    <div class="flex items-center space-x-4">
                        <Link :href="route('login')" class="text-gray-600 hover:text-gray-900">Accedi</Link>
                        <Link :href="route('saas.register')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                            Prova Gratis
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <section class="py-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h1 class="text-4xl font-bold text-center text-gray-900 mb-4">Piani e Prezzi</h1>
                <p class="text-center text-gray-600 mb-12 max-w-2xl mx-auto">
                    Scegli il piano più adatto alla tua organizzazione. Tutti i piani includono 14 giorni di prova gratuita.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div v-for="(plan, key) in plans" :key="key"
                        :class="[
                            'rounded-xl border p-6 flex flex-col',
                            key === 'pro' ? 'border-blue-500 ring-2 ring-blue-500 shadow-lg' : 'border-gray-200'
                        ]">
                        <div v-if="key === 'pro'" class="text-xs font-semibold text-blue-600 uppercase tracking-wide mb-2">Più popolare</div>
                        <h3 class="text-xl font-bold text-gray-900">{{ plan.name }}</h3>
                        <div class="mt-4 mb-6">
                            <span v-if="plan.price_monthly !== null" class="text-4xl font-extrabold text-gray-900">&euro;{{ plan.price_monthly }}</span>
                            <span v-else class="text-2xl font-bold text-gray-900">Su misura</span>
                            <span v-if="plan.price_monthly !== null" class="text-gray-500">/mese</span>
                        </div>
                        <ul class="space-y-3 text-sm text-gray-600 flex-grow">
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                {{ plan.members_limit || 'Illimitati' }} soci
                            </li>
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                {{ plan.staff_limit || 'Illimitati' }} utenti staff
                            </li>
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                {{ plan.storage_mb >= 1024 ? (plan.storage_mb / 1024) + ' GB' : plan.storage_mb + ' MB' }} storage
                            </li>
                            <li class="flex items-center">
                                <svg :class="[plan.public_site ? 'text-green-500' : 'text-gray-300', 'w-4 h-4 mr-2 flex-shrink-0']" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                Sito pubblico
                            </li>
                        </ul>
                        <Link :href="route('saas.register')"
                            :class="[
                                'mt-6 block text-center py-2 px-4 rounded-lg font-semibold transition',
                                key === 'pro'
                                    ? 'bg-blue-600 text-white hover:bg-blue-700'
                                    : 'bg-gray-100 text-gray-900 hover:bg-gray-200'
                            ]">
                            {{ plan.price_monthly === 0 ? 'Inizia gratis' : (plan.price_monthly ? 'Prova gratis' : 'Contattaci') }}
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
