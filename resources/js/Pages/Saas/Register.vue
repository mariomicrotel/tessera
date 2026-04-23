<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    plans: Object,
    trialDays: Number,
});

const form = useForm({
    organization_name: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    plan: 'free',
    organization_type: 'ets',
    cooperative_type: '',
});

const cooperativeTypes = [
    { value: 'lavoro',      label: 'Cooperativa di Lavoro' },
    { value: 'sociale_a',   label: 'Cooperativa Sociale (Tipo A)' },
    { value: 'sociale_b',   label: 'Cooperativa Sociale (Tipo B)' },
    { value: 'agricola',    label: 'Cooperativa Agricola' },
    { value: 'comunita',    label: 'Cooperativa di Comunità' },
    { value: 'consumo',     label: 'Cooperativa di Consumo' },
    { value: 'abitazione',  label: 'Cooperativa di Abitazione' },
    { value: 'consortile',  label: 'Cooperativa Consortile' },
];

const submit = () => {
    form.post(route('saas.register.store'));
};
</script>

<template>
    <Head title="Registra la tua organizzazione" />

    <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="flex justify-center">
                <Link href="/" class="flex items-center space-x-2">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-lg">I</span>
                    </div>
                    <span class="text-xl font-bold text-gray-900">Infotel Sistemi</span>
                </Link>
            </div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Crea il tuo account
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                {{ trialDays }} giorni di prova gratuita, nessuna carta richiesta
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow-lg sm:rounded-lg sm:px-10">
                <form @submit.prevent="submit" class="space-y-6">

                    <!-- Tipo organizzazione -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Tipo di organizzazione *
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label
                                :class="[
                                    'relative flex cursor-pointer rounded-lg border p-4 focus:outline-none transition-colors',
                                    form.organization_type === 'ets'
                                        ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-600'
                                        : 'border-gray-300 bg-white hover:bg-gray-50',
                                ]"
                            >
                                <input
                                    type="radio"
                                    v-model="form.organization_type"
                                    value="ets"
                                    class="sr-only"
                                />
                                <div class="flex flex-col items-center gap-1 w-full text-center">
                                    <span class="text-2xl">🏛️</span>
                                    <span :class="['text-sm font-semibold', form.organization_type === 'ets' ? 'text-blue-700' : 'text-gray-800']">
                                        ETS / ODV / APS
                                    </span>
                                    <span class="text-xs text-gray-500">Ente del Terzo Settore</span>
                                </div>
                            </label>

                            <label
                                :class="[
                                    'relative flex cursor-pointer rounded-lg border p-4 focus:outline-none transition-colors',
                                    form.organization_type === 'cooperative'
                                        ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-600'
                                        : 'border-gray-300 bg-white hover:bg-gray-50',
                                ]"
                            >
                                <input
                                    type="radio"
                                    v-model="form.organization_type"
                                    value="cooperative"
                                    class="sr-only"
                                />
                                <div class="flex flex-col items-center gap-1 w-full text-center">
                                    <span class="text-2xl">🤝</span>
                                    <span :class="['text-sm font-semibold', form.organization_type === 'cooperative' ? 'text-blue-700' : 'text-gray-800']">
                                        Cooperativa
                                    </span>
                                    <span class="text-xs text-gray-500">Società mutualistica</span>
                                </div>
                            </label>
                        </div>
                        <p v-if="form.errors.organization_type" class="mt-1 text-sm text-red-600">
                            {{ form.errors.organization_type }}
                        </p>
                    </div>

                    <!-- Tipo cooperativa (visibile solo se cooperative) -->
                    <div v-show="form.organization_type === 'cooperative'">
                        <label for="cooperative_type" class="block text-sm font-medium text-gray-700">
                            Tipo di cooperativa *
                        </label>
                        <select
                            id="cooperative_type"
                            v-model="form.cooperative_type"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="" disabled>— Seleziona il tipo —</option>
                            <option
                                v-for="ct in cooperativeTypes"
                                :key="ct.value"
                                :value="ct.value"
                            >{{ ct.label }}</option>
                        </select>
                        <p v-if="form.errors.cooperative_type" class="mt-1 text-sm text-red-600">
                            {{ form.errors.cooperative_type }}
                        </p>
                    </div>

                    <!-- Nome organizzazione -->
                    <div>
                        <label for="organization_name" class="block text-sm font-medium text-gray-700">
                            {{ form.organization_type === 'cooperative' ? 'Ragione sociale' : 'Nome organizzazione' }} *
                        </label>
                        <input
                            id="organization_name"
                            v-model="form.organization_name"
                            type="text"
                            required
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            :placeholder="form.organization_type === 'cooperative' ? 'Es. Cooperativa Sociale Aurora srl' : 'Es. Associazione Volontari Roma'"
                        />
                        <p v-if="form.errors.organization_name" class="mt-1 text-sm text-red-600">
                            {{ form.errors.organization_name }}
                        </p>
                    </div>

                    <!-- Nome utente -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Il tuo nome *</label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email *</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password *</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                        />
                        <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                    </div>

                    <!-- Conferma password -->
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                            Conferma password *
                        </label>
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>

                    <!-- Submit -->
                    <div>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Creazione in corso...' : 'Crea il tuo gestionale' }}
                        </button>
                    </div>
                </form>

                <p class="mt-6 text-center text-sm text-gray-600">
                    Hai già un account?
                    <Link :href="route('login')" class="font-medium text-blue-600 hover:text-blue-500">Accedi</Link>
                </p>
            </div>
        </div>
    </div>
</template>
