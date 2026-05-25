<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    CalendarDaysIcon, CheckCircleIcon, ExclamationTriangleIcon,
    ArrowTopRightOnSquareIcon, BuildingOffice2Icon, CalendarIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    anno:                  { type: Number, required: true },
    anni_disponibili:      { type: Array, default: () => [] },
    tenants:               { type: Array, default: () => [] },
    in_scadenza_imminenti: { type: Array, default: () => [] },
});

const cambiaAnno = (a) => {
    router.get(route('consultant.adempimenti.dashboard'), { anno: a }, { preserveScroll: true });
};

const fmt = (s) => s ? new Date(s).toLocaleDateString('it-IT') : '—';

const PRIORITA_COLOR = {
    critica: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    alta:    'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
    normale: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    bassa:   'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
};
</script>

<template>
    <AppLayout title="Compliance adempimenti">
        <Head title="Compliance — Adempimenti fiscali" />

        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Compliance adempimenti
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Stato di tutti gli adempimenti fiscali per i tuoi enti — anno {{ anno }}.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('consultant.adempimenti.calendar')"
                        class="text-xs px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-1.5">
                        <CalendarIcon class="size-4" /> Calendario
                    </Link>
                    <select :value="anno" @change="cambiaAnno($event.target.value)"
                        class="text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                        <option v-for="a in anni_disponibili" :key="a" :value="a">Anno {{ a }}</option>
                    </select>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Compliance per ente -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Compliance per ente</h3>
                    </div>

                    <div v-if="tenants.length === 0" class="p-8 text-center text-sm text-gray-400">
                        Nessun ente assegnato.
                    </div>

                    <table v-else class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr class="text-left text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <th class="px-5 py-2.5">Ente</th>
                                <th class="px-5 py-2.5">Compliance</th>
                                <th class="px-5 py-2.5 text-center">Da fare</th>
                                <th class="px-5 py-2.5 text-center">In corso</th>
                                <th class="px-5 py-2.5 text-center">Scaduti</th>
                                <th class="px-5 py-2.5 text-center">Scadenza vicina</th>
                                <th class="px-5 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                            <tr v-for="t in tenants" :key="t.id" class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                                <td class="px-5 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <BuildingOffice2Icon class="size-4 text-gray-400" />
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ t.name }}</p>
                                            <p class="text-xs text-gray-400 capitalize">{{ t.organization_type }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 max-w-[120px] h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div :style="{ width: t.compliance_pct + '%' }"
                                                :class="['h-full rounded-full transition-all duration-500',
                                                    t.compliance_pct >= 80 ? 'bg-green-500' :
                                                    t.compliance_pct >= 50 ? 'bg-yellow-500' : 'bg-red-500']" />
                                        </div>
                                        <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ t.compliance_pct }}%</span>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ t.completati }}/{{ t.totali }}</p>
                                </td>
                                <td class="px-5 py-3 text-sm text-center text-gray-700 dark:text-gray-300">{{ t.da_fare }}</td>
                                <td class="px-5 py-3 text-sm text-center text-gray-700 dark:text-gray-300">{{ t.in_lavorazione }}</td>
                                <td class="px-5 py-3 text-sm text-center">
                                    <span v-if="t.scaduti > 0" class="inline-flex items-center gap-1 text-red-600 dark:text-red-400 font-medium">
                                        <ExclamationTriangleIcon class="size-3.5" />
                                        {{ t.scaduti }}
                                    </span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-5 py-3 text-sm text-center">
                                    <span v-if="t.in_scadenza > 0" class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400 font-medium">
                                        <CalendarDaysIcon class="size-3.5" />
                                        {{ t.in_scadenza }}
                                    </span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <Link :href="route('consultant.adempimenti.index', t.slug)"
                                        class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 flex items-center gap-1 justify-end">
                                        Dettaglio
                                        <ArrowTopRightOnSquareIcon class="size-3" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Scadenze imminenti cross-tenant -->
                <div v-if="in_scadenza_imminenti.length > 0"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <CalendarDaysIcon class="size-4 text-amber-500" />
                            In scadenza nei prossimi 14 giorni
                            <span class="text-xs font-normal text-gray-500">({{ in_scadenza_imminenti.length }})</span>
                        </h3>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li v-for="i in in_scadenza_imminenti" :key="i.id"
                            class="px-5 py-3 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-900/30">
                            <div class="flex items-start gap-3 flex-1 min-w-0">
                                <div class="text-center min-w-[60px]">
                                    <p class="text-xs text-gray-400">{{ fmt(i.data_scadenza) }}</p>
                                    <p :class="['text-xs font-medium mt-0.5',
                                        i.giorni_alla_scadenza <= 3 ? 'text-red-600 dark:text-red-400' :
                                        i.giorni_alla_scadenza <= 7 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-600 dark:text-gray-400']">
                                        {{ i.giorni_alla_scadenza }}g
                                    </p>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-0.5">
                                        <span :class="['text-xs px-1.5 py-0.5 rounded', PRIORITA_COLOR[i.priorita] || PRIORITA_COLOR.normale]">
                                            {{ i.priorita }}
                                        </span>
                                        <span v-if="i.periodo" class="text-xs text-gray-500">{{ i.periodo }}</span>
                                    </div>
                                    <p class="text-sm text-gray-900 dark:text-gray-100">{{ i.template_nome }}</p>
                                    <Link :href="route('consultant.entities.show', i.tenant.slug)"
                                        class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                        {{ i.tenant.name }}
                                    </Link>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
