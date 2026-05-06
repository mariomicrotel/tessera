<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    CheckCircleIcon,
    ExclamationTriangleIcon,
    XCircleIcon,
    MinusCircleIcon,
    ArrowLeftIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    check: Object,
    risultatiPerCategoria: Object,
});

const categorieLabel = {
    statuto:      'Statuto',
    atto:         'Atto Costitutivo',
    runts:        'RUNTS',
    governance:   'Governance',
    contabilita:  'Contabilità',
    volontari:    'Volontari',
    trasparenza:  'Trasparenza',
    altro:        'Altro',
};

const esitoIcon = (esito) => ({
    ok:              CheckCircleIcon,
    avviso:          ExclamationTriangleIcon,
    errore:          XCircleIcon,
    non_applicabile: MinusCircleIcon,
}[esito] ?? MinusCircleIcon);

const esitoClass = (esito) => ({
    ok:              'text-green-500',
    avviso:          'text-yellow-500',
    errore:          'text-red-500',
    non_applicabile: 'text-gray-400',
}[esito] ?? 'text-gray-400');

const rowBg = (esito) => ({
    errore: 'bg-red-50 dark:bg-red-900/10',
    avviso: 'bg-yellow-50 dark:bg-yellow-900/10',
}[esito] ?? '');

const formatDate = (d) => d ? new Date(d).toLocaleString('it-IT') : '—';

const categorie = computed(() => Object.keys(props.risultatiPerCategoria));
</script>

<template>
    <AppLayout title="Dettaglio Controllo">
        <Head title="Dettaglio Controllo Compliance" />
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('ets.compliance.dashboard')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <ArrowLeftIcon class="size-5" />
                </Link>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Controllo Compliance</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ formatDate(check.run_at) }}</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Riepilogo -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg px-6 py-4">
                    <div class="grid grid-cols-5 gap-4 text-center">
                        <div>
                            <div class="text-3xl font-bold" :class="check.punteggio_percentuale >= 80 ? 'text-green-600' : check.punteggio_percentuale >= 50 ? 'text-yellow-600' : 'text-red-600'">
                                {{ check.punteggio_percentuale }}%
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Punteggio</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-green-600">{{ check.n_ok }}</div>
                            <div class="text-xs text-gray-500 mt-1">Conformi</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-yellow-600">{{ check.n_avviso }}</div>
                            <div class="text-xs text-gray-500 mt-1">Avvisi</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-red-600">{{ check.n_errore }}</div>
                            <div class="text-xs text-gray-500 mt-1">Non conformi</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-400">{{ check.n_na }}</div>
                            <div class="text-xs text-gray-500 mt-1">N/A</div>
                        </div>
                    </div>
                </div>

                <!-- Risultati per categoria -->
                <div v-for="cat in categorie" :key="cat" class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                        <h3 class="font-medium text-gray-700 dark:text-gray-300 uppercase text-xs tracking-wide">
                            {{ categorieLabel[cat] ?? cat }}
                        </h3>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        <div
                            v-for="r in risultatiPerCategoria[cat]"
                            :key="r.id"
                            :class="rowBg(r.esito)"
                            class="px-6 py-4 flex items-start gap-4"
                        >
                            <component
                                :is="esitoIcon(r.esito)"
                                :class="esitoClass(r.esito)"
                                class="size-5 mt-0.5 shrink-0"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-sm text-gray-900 dark:text-gray-100">
                                    {{ r.rule?.titolo }}
                                    <span v-if="r.valore_rilevato" class="ml-2 text-xs text-gray-500 font-normal">
                                        ({{ r.valore_rilevato }})
                                    </span>
                                </div>
                                <div v-if="r.messaggio" class="text-sm mt-0.5" :class="esitoClass(r.esito)">
                                    {{ r.messaggio }}
                                </div>
                                <div v-if="r.suggerimento && r.esito !== 'ok' && r.esito !== 'non_applicabile'" class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic">
                                    {{ r.suggerimento }}
                                </div>
                            </div>
                            <span
                                class="text-xs font-medium px-2 py-1 rounded-full shrink-0"
                                :class="{
                                    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': r.esito === 'ok',
                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': r.esito === 'avviso',
                                    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': r.esito === 'errore',
                                    'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400': r.esito === 'non_applicabile',
                                }"
                            >{{ r.esito_label }}</span>
                        </div>
                    </div>
                </div>

                <p class="text-xs text-gray-400 dark:text-gray-500 italic text-center">
                    ⚠ I controlli automatici sono indicativi e non sostituiscono il parere di un notaio, commercialista o consulente legale specializzato in diritto del Terzo Settore.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
