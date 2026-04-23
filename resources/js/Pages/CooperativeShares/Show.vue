<script setup>
import { computed, ref } from 'vue';
import {
    ArrowLeftIcon, CheckCircleIcon, BanknotesIcon,
    ExclamationTriangleIcon, InformationCircleIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    share: Object,
});

const today = new Date().toISOString().slice(0, 10);

// Form versamento
const formVersa = useForm({
    importo_versato: '',
    data:            today,
});

// Form riscatto
const formRiscatta = useForm({
    motivo_riscatto: '',
    data:            today,
});

const showRiscattaConfirm = ref(false);

// ── Helpers ───────────────────────────────────────────────────────────────────

const fmt = (v) => new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(Number(v) || 0);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const memberName = computed(() => {
    const m = props.share?.member;
    if (!m) return '—';
    if (m.tipo_persona === 'giuridica') return m.ragione_sociale ?? '—';
    return `${m.cognome ?? ''} ${m.nome ?? ''}`.trim() || '—';
});

const ancoraDaVersare = () => Math.max(0, Number(props.share.totale_sottoscritto) - Number(props.share.totale_versato));
const percentualeVersamento = () => {
    const tot = Number(props.share.totale_sottoscritto);
    if (tot === 0) return 0;
    return Math.min(100, Math.round((Number(props.share.totale_versato) / tot) * 100));
};

const statusConfig = {
    sottoscritta:         { label: 'Sottoscritta',  cls: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' },
    parzialmente_versata: { label: 'Parz. versata', cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' },
    versata:              { label: 'Versata',        cls: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' },
    riscattata:           { label: 'Riscattata',     cls: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' },
    annullata:            { label: 'Annullata',      cls: 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400' },
};
const badge = (s) => statusConfig[s] ?? { label: s, cls: 'bg-gray-100 text-gray-600' };

const canVersa    = () => !['versata', 'riscattata', 'annullata'].includes(props.share.status);
const canRiscatta = () => !['riscattata', 'annullata'].includes(props.share.status);
</script>

<template>
    <AppLayout :title="`Quote – ${memberName}`">
        <Head :title="`Quote – ${memberName}`" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Quote di Capitale
                </h2>
                <Link
                    :href="route('capitale-sociale.index')"
                    class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300 text-sm"
                >
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />Torna all'elenco
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Card informativa -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ memberName }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ share.member?.tipo_persona === 'giuridica' ? '🏢 Persona giuridica' : '👤 Persona fisica' }}
                                <span v-if="share.member?.numero_tessera" class="ml-2">· Tessera n° {{ share.member.numero_tessera }}</span>
                            </p>
                        </div>
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold shrink-0" :class="badge(share.status).cls">
                            {{ badge(share.status).label }}
                        </span>
                    </div>

                    <!-- Dati quote -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">N° Quote</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">{{ share.numero_quote }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Valore unitario</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">{{ fmt(share.valore_unitario) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Sottoscritto</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">{{ fmt(share.totale_sottoscritto) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Versato</p>
                            <p class="text-xl font-bold text-green-600 dark:text-green-400 mt-0.5">{{ fmt(share.totale_versato) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Da versare</p>
                            <p
                                class="text-xl font-bold mt-0.5"
                                :class="ancoraDaVersare() > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400'"
                            >{{ fmt(ancoraDaVersare()) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Data sottoscrizione</p>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mt-0.5">{{ fmtDate(share.data_sottoscrizione) }}</p>
                        </div>
                    </div>

                    <!-- Progress bar versamento -->
                    <div class="pt-1">
                        <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                            <span>Versamento</span>
                            <span>{{ percentualeVersamento() }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div
                                class="h-2 rounded-full transition-all"
                                :class="percentualeVersamento() >= 100 ? 'bg-green-500' : 'bg-amber-400'"
                                :style="{ width: percentualeVersamento() + '%' }"
                            ></div>
                        </div>
                    </div>

                    <!-- Info riscatto (se riscattata) -->
                    <div v-if="share.status === 'riscattata'" class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 px-4 py-3">
                        <p class="text-sm font-medium text-red-700 dark:text-red-400">Quote riscattate il {{ fmtDate(share.data_riscatto) }}</p>
                        <p v-if="share.motivo_riscatto" class="text-sm text-red-600 dark:text-red-300 mt-1">Motivo: {{ share.motivo_riscatto }}</p>
                    </div>

                    <!-- Note -->
                    <div v-if="share.note" class="rounded-lg bg-gray-50 dark:bg-gray-700/50 px-4 py-3">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Note</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ share.note }}</p>
                    </div>
                </div>

                <!-- Sezione Versa quota -->
                <div v-if="canVersa()" id="versa" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <BanknotesIcon class="size-5 text-green-600 dark:text-green-400" />
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Registra Versamento</h3>
                    </div>

                    <div class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Ancora da versare: <strong class="text-amber-600 dark:text-amber-400">{{ fmt(ancoraDaVersare()) }}</strong>
                    </div>

                    <form
                        @submit.prevent="formVersa.post(route('capitale-sociale.versa', share.id))"
                        class="space-y-4"
                    >
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="importo_versato" value="Importo versato (€) *" />
                                <TextInput
                                    id="importo_versato"
                                    v-model="formVersa.importo_versato"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    :max="ancoraDaVersare()"
                                    class="mt-1 block w-full"
                                    :placeholder="`Max ${fmt(ancoraDaVersare())}`"
                                    required
                                />
                                <InputError class="mt-1" :message="formVersa.errors.importo_versato" />
                            </div>
                            <div>
                                <InputLabel for="data_versa" value="Data versamento *" />
                                <TextInput
                                    id="data_versa"
                                    v-model="formVersa.data"
                                    type="date"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError class="mt-1" :message="formVersa.errors.data" />
                            </div>
                        </div>
                        <PrimaryButton type="submit" :disabled="formVersa.processing" class="!bg-green-600 hover:!bg-green-700">
                            <CheckCircleIcon class="size-4 me-2" aria-hidden="true" />
                            Registra Versamento
                        </PrimaryButton>
                    </form>
                </div>

                <!-- Versata: conferma -->
                <div v-else-if="share.status === 'versata'" class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 px-4 py-4 flex items-center gap-3">
                    <CheckCircleIcon class="size-6 text-green-600 dark:text-green-400 shrink-0" />
                    <div>
                        <p class="text-sm font-medium text-green-700 dark:text-green-400">Capitale completamente versato</p>
                        <p v-if="share.data_versamento" class="text-xs text-green-600 dark:text-green-300 mt-0.5">
                            Versamento completato il {{ fmtDate(share.data_versamento) }}
                        </p>
                    </div>
                </div>

                <!-- Sezione Riscatta quota -->
                <div v-if="canRiscatta()" id="riscatta" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-red-200 dark:border-red-800">
                    <div class="flex items-center gap-2 mb-4">
                        <ExclamationTriangleIcon class="size-5 text-red-500 dark:text-red-400" />
                        <h3 class="text-base font-semibold text-red-700 dark:text-red-400">Riscatto Quote</h3>
                    </div>

                    <div v-if="!showRiscattaConfirm">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                            Il riscatto registra l'uscita del socio e il rimborso del capitale versato
                            (<strong>{{ fmt(share.totale_versato) }}</strong>).
                            Questa operazione non è reversibile.
                        </p>
                        <button
                            type="button"
                            @click="showRiscattaConfirm = true"
                            class="px-4 py-2 border border-red-300 dark:border-red-600 text-red-700 dark:text-red-400 rounded-md text-sm font-medium hover:bg-red-50 dark:hover:bg-red-900/20"
                        >
                            Procedi con il riscatto…
                        </button>
                    </div>

                    <form
                        v-else
                        @submit.prevent="formRiscatta.post(route('capitale-sociale.riscatta', share.id))"
                        class="space-y-4"
                    >
                        <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 px-3 py-2 flex items-start gap-2">
                            <InformationCircleIcon class="size-4 text-amber-600 shrink-0 mt-0.5" />
                            <p class="text-xs text-amber-700 dark:text-amber-300">
                                Verrà creata una spesa di rimborso per {{ fmt(share.totale_versato) }}.
                            </p>
                        </div>
                        <div>
                            <InputLabel for="motivo_riscatto" value="Motivo del riscatto *" />
                            <textarea
                                id="motivo_riscatto"
                                v-model="formRiscatta.motivo_riscatto"
                                rows="2"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-red-500 focus:border-red-500 text-sm"
                                placeholder="Dimissioni volontarie, esclusione, decesso…"
                            ></textarea>
                            <InputError class="mt-1" :message="formRiscatta.errors.motivo_riscatto" />
                        </div>
                        <div>
                            <InputLabel for="data_riscatto" value="Data riscatto *" />
                            <TextInput
                                id="data_riscatto"
                                v-model="formRiscatta.data"
                                type="date"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-1" :message="formRiscatta.errors.data" />
                        </div>
                        <div class="flex gap-3">
                            <button
                                type="submit"
                                :disabled="formRiscatta.processing"
                                class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded-md text-sm font-medium"
                            >
                                Conferma Riscatto
                            </button>
                            <button
                                type="button"
                                @click="showRiscattaConfirm = false"
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-md text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                Annulla
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
