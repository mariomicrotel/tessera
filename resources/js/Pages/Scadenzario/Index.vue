<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import {
    FunnelIcon,
    ArrowDownTrayIcon,
    EnvelopeIcon,
    ArrowLeftIcon,
    ArrowRightIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    morosi:           { type: Object, required: true },
    anno:             { type: Number, required: true },
    statoSocio:       { type: String, default: 'attivo' },
    quotaAnnuale:     { type: Number, default: 0 },
    anniDisponibili:  { type: Array,  default: () => [] },
    templatePresente: { type: Boolean, default: false },
    filters:          { type: Object, default: () => ({}) },
});

// ── Filtri ──────────────────────────────────────────────────────────────
const form = reactive({
    anno:        String(props.anno),
    stato_socio: props.statoSocio ?? 'attivo',
    search:      props.filters?.search ?? '',
});

const applyFilters = () =>
    router.get(route('scadenzario.index'), {
        anno:        form.anno,
        stato_socio: form.stato_socio,
        search:      form.search || undefined,
    }, { preserveState: false, replace: true });

// ── Selezione multipla ──────────────────────────────────────────────────
const selectedIds = ref(new Set());

const allIds = computed(() => (props.morosi.data ?? []).map(m => m.id));

const allSelected = computed({
    get: () => allIds.value.length > 0 && allIds.value.every(id => selectedIds.value.has(id)),
    set: (val) => {
        if (val) {
            allIds.value.forEach(id => selectedIds.value.add(id));
        } else {
            selectedIds.value.clear();
        }
    },
});

function toggleOne(id) {
    if (selectedIds.value.has(id)) {
        selectedIds.value.delete(id);
    } else {
        selectedIds.value.add(id);
    }
}

// ── Invio sollecito singolo ─────────────────────────────────────────────
function inviaSollecito(memberId) {
    if (!confirm('Inviare il sollecito a questo socio?')) return;
    router.post(
        route('scadenzario.sollecito', memberId),
        { anno: form.anno },
        { preserveScroll: true }
    );
}

// ── Invio sollecito massivo ─────────────────────────────────────────────
const invioMassivoForm = useForm({ member_ids: [], anno: String(props.anno) });

function inviaSollecitoMassivo() {
    const ids = [...selectedIds.value];
    if (ids.length === 0) {
        alert('Seleziona almeno un socio.');
        return;
    }
    if (!confirm(`Inviare il sollecito a ${ids.length} soci selezionati?`)) return;
    invioMassivoForm.member_ids = ids;
    invioMassivoForm.anno = form.anno;
    invioMassivoForm.post(route('scadenzario.sollecito-massivo'), {
        preserveScroll: true,
        onSuccess: () => selectedIds.value.clear(),
    });
}

// ── Export URL ──────────────────────────────────────────────────────────
const exportUrl = computed(() => {
    const p = new URLSearchParams({ anno: form.anno, stato_socio: form.stato_socio });
    return route('scadenzario.export') + '?' + p.toString();
});

// ── Formattazione ────────────────────────────────────────────────────────
function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('it-IT');
}
function fmtEur(v) {
    return '€\u00a0' + Number(v ?? 0).toFixed(2);
}
</script>

<template>
    <AppLayout title="Scadenzario quote">
        <Head title="Scadenzario quote" />
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Scadenzario quote — soci morosi
                </h2>
                <a :href="exportUrl">
                    <PrimaryButton type="button">
                        <ArrowDownTrayIcon class="size-4 me-2" aria-hidden="true" />Export CSV
                    </PrimaryButton>
                </a>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- Avviso template non configurato -->
                <div v-if="!templatePresente"
                    class="flex items-start gap-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-4 text-sm text-amber-800 dark:text-amber-300">
                    <ExclamationTriangleIcon class="size-5 shrink-0 mt-0.5" aria-hidden="true" />
                    <span>
                        Il template email <strong>Sollecito pagamento quota</strong> non è ancora configurato.
                        <Link :href="route('email-templates.edit', 'sollecito_quota')" class="underline hover:no-underline">Configuralo ora</Link>
                        prima di inviare i solleciti.
                    </span>
                </div>

                <!-- Filtri -->
                <form @submit.prevent="applyFilters"
                    class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 flex flex-wrap gap-4 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Anno</label>
                        <select v-model="form.anno" @change="applyFilters"
                            class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                            <option v-for="a in anniDisponibili" :key="a" :value="String(a)">{{ a }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Stato socio</label>
                        <select v-model="form.stato_socio" @change="applyFilters"
                            class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                            <option value="attivo">Attivo</option>
                            <option value="tutti">Tutti</option>
                            <option value="aspirante">Aspirante</option>
                            <option value="moroso">Moroso</option>
                        </select>
                    </div>
                    <div class="min-w-[200px]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Cerca</label>
                        <TextInput v-model="form.search" type="text" class="block w-full" placeholder="Nome, cognome, email…" />
                    </div>
                    <PrimaryButton type="submit">
                        <FunnelIcon class="size-4 me-2" aria-hidden="true" />Filtra
                    </PrimaryButton>
                </form>

                <!-- Riepilogo + azioni massive -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            <span class="font-semibold text-gray-900 dark:text-gray-100">
                                {{ morosi.total ?? (morosi.data ?? morosi).length }}
                            </span>
                            soci senza quota {{ anno }} —
                            quota dovuta: <span class="font-semibold">{{ fmtEur(quotaAnnuale) }}</span>
                        </p>
                        <p v-if="selectedIds.size > 0" class="mt-0.5 text-sm text-indigo-600 dark:text-indigo-400">
                            {{ selectedIds.size }} selezionati
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            :disabled="selectedIds.size === 0 || invioMassivoForm.processing || !templatePresente"
                            @click="inviaSollecitoMassivo"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium
                                   bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed
                                   dark:bg-indigo-500 dark:hover:bg-indigo-600 transition"
                        >
                            <EnvelopeIcon class="size-4" aria-hidden="true" />
                            Invia sollecito ai selezionati
                            <span v-if="invioMassivoForm.processing" class="ml-1 opacity-75">…</span>
                        </button>
                    </div>
                </div>

                <!-- Tabella -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-3 py-2 text-center w-10">
                                    <input
                                        type="checkbox"
                                        v-model="allSelected"
                                        class="rounded border-gray-300 dark:border-gray-600 text-indigo-600"
                                        :title="allSelected ? 'Deseleziona tutti' : 'Seleziona tutti'"
                                    />
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Socio</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Email</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase hidden md:table-cell">Stato</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase hidden md:table-cell">Iscritto dal</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Quota {{ anno }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="m in (morosi.data ?? morosi)"
                                :key="m.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/40"
                                :class="selectedIds.has(m.id) ? 'bg-indigo-50 dark:bg-indigo-900/10' : ''"
                            >
                                <td class="px-3 py-2 text-center">
                                    <input
                                        type="checkbox"
                                        :checked="selectedIds.has(m.id)"
                                        @change="toggleOne(m.id)"
                                        class="rounded border-gray-300 dark:border-gray-600 text-indigo-600"
                                    />
                                </td>
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">
                                    <Link :href="route('members.show', m.id)" class="hover:underline text-indigo-600 dark:text-indigo-400">
                                        {{ m.cognome }} {{ m.nome }}
                                    </Link>
                                </td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                                    <a v-if="m.email" :href="'mailto:' + m.email" class="hover:underline">{{ m.email }}</a>
                                    <span v-else class="text-gray-400 italic text-xs">nessuna email</span>
                                </td>
                                <td class="px-4 py-2 hidden md:table-cell">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                        :class="{
                                            'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': m.stato === 'attivo',
                                            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300': m.stato === 'aspirante',
                                            'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': m.stato === 'moroso',
                                            'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400': !['attivo','aspirante','moroso'].includes(m.stato),
                                        }">
                                        {{ m.stato }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400 hidden md:table-cell">{{ fmtDate(m.data_iscrizione) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums text-amber-700 dark:text-amber-400 font-medium">
                                    {{ fmtEur(quotaAnnuale) }}
                                </td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    <button
                                        type="button"
                                        :disabled="!templatePresente || !m.email"
                                        :title="!m.email ? 'Nessuna email' : (!templatePresente ? 'Template non configurato' : 'Invia sollecito')"
                                        @click="inviaSollecito(m.id)"
                                        class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline disabled:opacity-40 disabled:cursor-not-allowed"
                                    >
                                        <EnvelopeIcon class="size-4" aria-hidden="true" />Sollecita
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="!(morosi.data ?? morosi)?.length" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                        Nessun socio moroso per il {{ anno }}. 🎉
                    </p>

                    <!-- Paginazione -->
                    <div v-if="morosi.prev_page_url || morosi.next_page_url"
                        class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center text-sm">
                        <Link v-if="morosi.prev_page_url" :href="morosi.prev_page_url"
                            class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
                            <ArrowLeftIcon class="size-4" aria-hidden="true" />Precedente
                        </Link>
                        <span v-else></span>
                        <span class="text-gray-500 dark:text-gray-400">
                            Pagina {{ morosi.current_page }} di {{ morosi.last_page }}
                            ({{ morosi.total }} soci)
                        </span>
                        <Link v-if="morosi.next_page_url" :href="morosi.next_page_url"
                            class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
                            Successiva<ArrowRightIcon class="size-4" aria-hidden="true" />
                        </Link>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
