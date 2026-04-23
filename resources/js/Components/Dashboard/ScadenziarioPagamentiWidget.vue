<script setup>
import { ExclamationTriangleIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    fatture: {
        type: Array,
        default: () => [],
        // Ogni fattura: { id, fornitore_nome, numero_fattura, totale, data_scadenza, giorni_alla_scadenza }
    },
});

// Ordina per data_scadenza crescente
const fattureOrdinate = computed(() => {
    return [...props.fatture].sort((a, b) => {
        const dateA = new Date(a.data_scadenza);
        const dateB = new Date(b.data_scadenza);
        return dateA - dateB;
    });
});

// Totale da pagare
const totaleDaPagare = computed(() => {
    return props.fatture.reduce((sum, f) => sum + Number(f.totale || 0), 0);
});

// Badge colore basato su giorni_alla_scadenza
const getBadgeClass = (giorni) => {
    const days = Number(giorni ?? 999);
    if (days < 0) {
        // Scaduta
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
    } else if (days <= 7) {
        // Entro 7 giorni
        return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
    } else if (days <= 30) {
        // Entro 30 giorni
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
    } else {
        // Oltre 30 giorni
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
    }
};

// Etichetta badge
const getBadgeLabel = (giorni) => {
    const days = Number(giorni ?? 999);
    if (days < 0) {
        return `Scaduta ${Math.abs(days)}g fa`;
    } else if (days === 0) {
        return 'Scade oggi';
    } else if (days <= 7) {
        return `Scade in ${days}g`;
    } else if (days <= 30) {
        return `Scade in ${days}g`;
    } else {
        return `Scade in ${days}g`;
    }
};

const fmt = (n) => Number(n ?? 0).toFixed(2);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-4 border-b dark:border-gray-700 flex items-center gap-3">
            <ExclamationTriangleIcon class="size-5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Pagamenti in scadenza</h3>
        </div>

        <!-- Content -->
        <div class="divide-y dark:divide-gray-700">
            <!-- Lista fatture -->
            <template v-if="fatture.length > 0">
                <div v-for="f in fattureOrdinate" :key="f.id"
                     class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                    <div class="flex items-start justify-between gap-4 mb-2">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 dark:text-gray-100 truncate">
                                {{ f.fornitore_nome || 'Fornitore sconosciuto' }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 font-mono">
                                {{ f.numero_fattura }}
                            </p>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium whitespace-nowrap"
                              :class="getBadgeClass(f.giorni_alla_scadenza)">
                            {{ getBadgeLabel(f.giorni_alla_scadenza) }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">
                            {{ fmtDate(f.data_scadenza) }}
                        </span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100 font-mono">
                            € {{ fmt(f.totale) }}
                        </span>
                    </div>
                </div>
            </template>

            <!-- Messaggio vuoto -->
            <div v-else class="px-6 py-8 text-center">
                <p class="text-gray-500 dark:text-gray-400 text-sm">
                    Nessun pagamento in scadenza
                </p>
            </div>

            <!-- Footer: totale + link -->
            <template v-if="fatture.length > 0">
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Totale da pagare</p>
                        <p class="text-xl font-semibold text-gray-900 dark:text-gray-100 font-mono">
                            € {{ fmt(totaleDaPagare) }}
                        </p>
                    </div>
                    <Link :href="route('iva.fatture-passive.index')"
                          class="inline-flex items-center gap-1 px-3 py-2 rounded-md text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors">
                        Vai allo scadenziario
                        <ArrowRightIcon class="size-4" aria-hidden="true" />
                    </Link>
                </div>
            </template>
        </div>
    </div>
</template>
