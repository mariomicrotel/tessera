<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ChevronLeftIcon, ChevronRightIcon, CalendarDaysIcon,
    ExclamationTriangleIcon, ListBulletIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    anno:             { type: Number, required: true },
    mese:             { type: Number, required: true },
    anni_disponibili: { type: Array, default: () => [] },
    prev:             { type: Object, required: true },
    next:             { type: Object, required: true },
    days_in_month:    { type: Number, required: true },
    first_day_dow:    { type: Number, required: true },  // ISO: 1=Mon..7=Sun
    items_by_date:    { type: Object, default: () => ({}) },
    totale_items:     { type: Number, default: 0 },
    scaduti_totali:   { type: Number, default: 0 },
});

const MESI = [
    'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
    'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre',
];
const GIORNI = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];

const PRIORITA_DOT = {
    critica: 'bg-red-500',
    alta:    'bg-orange-500',
    normale: 'bg-blue-500',
    bassa:   'bg-gray-400',
};

const STATO_BG = {
    gray:  'bg-gray-50 dark:bg-gray-700/40 border-gray-200 dark:border-gray-700',
    blue:  'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800',
    cyan:  'bg-cyan-50 dark:bg-cyan-900/20 border-cyan-200 dark:border-cyan-800',
    green: 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800',
    red:   'bg-red-50 dark:bg-red-900/20 border-red-300 dark:border-red-700',
};

// Costruisce la griglia (settimane x 7 giorni) per la vista mensile
const grid = computed(() => {
    // Slot vuoti a inizio mese (ISO: lun=1, dom=7). first_day_dow=1 → 0 slot vuoti
    const offset = props.first_day_dow - 1;
    const cells = [];
    for (let i = 0; i < offset; i++) {
        cells.push({ empty: true });
    }
    for (let day = 1; day <= props.days_in_month; day++) {
        const dateStr = `${props.anno}-${String(props.mese).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        cells.push({
            empty: false,
            day,
            dateStr,
            items: props.items_by_date[dateStr] ?? [],
            isToday: isToday(props.anno, props.mese, day),
            isWeekend: ((offset + day - 1) % 7) >= 5,  // sab=5, dom=6
        });
    }
    // Riempi l'ultima settimana
    while (cells.length % 7 !== 0) {
        cells.push({ empty: true });
    }
    // Spezza in settimane
    const weeks = [];
    for (let i = 0; i < cells.length; i += 7) {
        weeks.push(cells.slice(i, i + 7));
    }
    return weeks;
});

function isToday(y, m, d) {
    const t = new Date();
    return t.getFullYear() === y && (t.getMonth() + 1) === m && t.getDate() === d;
}

function navigate(direction) {
    const target = direction === 'prev' ? props.prev : props.next;
    router.get(route('consultant.adempimenti.calendar'), target, { preserveScroll: true });
}

function jumpToMonth(anno, mese) {
    router.get(route('consultant.adempimenti.calendar'), { anno, mese }, { preserveScroll: true });
}

function changeYear(anno) {
    router.get(route('consultant.adempimenti.calendar'), { anno: Number(anno), mese: props.mese }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="`Calendario adempimenti — ${MESI[mese - 1]} ${anno}`">
        <Head :title="`Calendario — ${MESI[mese - 1]} ${anno}`" />

        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Calendario adempimenti
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Scadenze di tutti i tuoi enti — vista mensile.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('consultant.adempimenti.dashboard')"
                        class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 flex items-center gap-1">
                        <ListBulletIcon class="size-4" /> Lista
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- Banner scaduti -->
                <div v-if="scaduti_totali > 0"
                    class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-3 flex items-center gap-3">
                    <ExclamationTriangleIcon class="size-5 text-red-500 shrink-0" />
                    <p class="text-sm text-red-800 dark:text-red-200">
                        <strong>{{ scaduti_totali }}</strong> adempimenti complessivamente scaduti (su tutti gli enti).
                    </p>
                </div>

                <!-- Navigatore -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-2">
                            <button @click="navigate('prev')"
                                class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                                <ChevronLeftIcon class="size-5 text-gray-600 dark:text-gray-300" />
                            </button>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 min-w-[200px] text-center">
                                {{ MESI[mese - 1] }} {{ anno }}
                            </h3>
                            <button @click="navigate('next')"
                                class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                                <ChevronRightIcon class="size-5 text-gray-600 dark:text-gray-300" />
                            </button>
                            <button @click="jumpToMonth(new Date().getFullYear(), new Date().getMonth() + 1)"
                                class="ml-2 text-xs px-2 py-1 rounded border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                Oggi
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            <select :value="anno" @change="changeYear($event.target.value)"
                                class="text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                                <option v-for="a in anni_disponibili" :key="a" :value="a">{{ a }}</option>
                            </select>
                            <span class="text-xs text-gray-400">{{ totale_items }} scadenze nel mese</span>
                        </div>
                    </div>
                </div>

                <!-- Griglia mensile -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <!-- Header giorni settimana -->
                    <div class="grid grid-cols-7 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30">
                        <div v-for="g in GIORNI" :key="g"
                            class="px-2 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase text-center">
                            {{ g }}
                        </div>
                    </div>

                    <!-- Settimane -->
                    <div>
                        <div v-for="(week, wi) in grid" :key="wi"
                            class="grid grid-cols-7 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                            <div v-for="(cell, di) in week" :key="di"
                                :class="['min-h-[110px] p-1.5 border-r border-gray-100 dark:border-gray-700 last:border-r-0',
                                    cell.empty ? 'bg-gray-50/30 dark:bg-gray-900/10' :
                                    cell.isToday ? 'bg-blue-50/40 dark:bg-blue-900/10' :
                                    cell.isWeekend ? 'bg-gray-50/60 dark:bg-gray-900/20' : '']">

                                <template v-if="!cell.empty">
                                    <div class="flex items-center justify-between mb-1">
                                        <span :class="['text-xs font-medium',
                                            cell.isToday ? 'text-blue-700 dark:text-blue-300 font-bold' : 'text-gray-700 dark:text-gray-300']">
                                            {{ cell.day }}
                                        </span>
                                        <span v-if="cell.items.length > 3" class="text-[10px] text-gray-400">
                                            +{{ cell.items.length - 3 }}
                                        </span>
                                    </div>

                                    <!-- Item del giorno (max 3 visibili) -->
                                    <ul class="space-y-1">
                                        <li v-for="item in cell.items.slice(0, 3)" :key="item.id">
                                            <Link :href="route('consultant.adempimenti.index', item.tenant.slug)"
                                                :class="['block text-[10px] leading-tight px-1.5 py-1 rounded border truncate', STATO_BG[item.stato_badge_color]]"
                                                :title="`${item.tenant.name} · ${item.template_nome}${item.periodo ? ' (' + item.periodo + ')' : ''}`">
                                                <div class="flex items-center gap-1">
                                                    <span :class="['w-1.5 h-1.5 rounded-full shrink-0', PRIORITA_DOT[item.priorita] || PRIORITA_DOT.normale]"></span>
                                                    <span class="truncate font-medium text-gray-800 dark:text-gray-200">
                                                        {{ item.template_codice }}
                                                        <span v-if="item.periodo" class="text-gray-500">·{{ item.periodo }}</span>
                                                    </span>
                                                </div>
                                                <p class="truncate text-gray-500 dark:text-gray-400 text-[9px] mt-0.5">{{ item.tenant.name }}</p>
                                            </Link>
                                        </li>
                                    </ul>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Legenda -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 text-xs">
                    <div class="flex flex-wrap items-center gap-4 text-gray-600 dark:text-gray-400">
                        <span class="font-medium">Priorità:</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span> Critica</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-orange-500"></span> Alta</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Normale</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-gray-400"></span> Bassa</span>
                        <span class="mx-2 text-gray-300">|</span>
                        <span class="font-medium">Stato:</span>
                        <span class="px-1.5 py-0.5 rounded border" :class="STATO_BG.gray">Da fare</span>
                        <span class="px-1.5 py-0.5 rounded border" :class="STATO_BG.blue">In corso</span>
                        <span class="px-1.5 py-0.5 rounded border" :class="STATO_BG.green">Completato</span>
                        <span class="px-1.5 py-0.5 rounded border" :class="STATO_BG.red">Scaduto</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
