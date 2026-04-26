<script setup>
import {
    DocumentTextIcon,
    ChevronDownIcon,
    InformationCircleIcon,
    CheckIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, usePage, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    relazione:        Object,
    variabili:        Object,
    statiLabel:       Object,
    placeholder_list: Array,
});

const page   = usePage();
const tenant = page.props.tenant;

const form = useForm({
    stato:               props.relazione.stato,
    organo_approvante:   props.relazione.organo_approvante ?? '',
    data_approvazione:   props.relazione.data_approvazione
                           ? props.relazione.data_approvazione.substring(0, 10)
                           : '',
    luogo_approvazione:  props.relazione.luogo_approvazione ?? '',
    note_interne:        props.relazione.note_interne ?? '',
    sezioni:             (props.relazione.sezioni ?? []).map(s => ({
        id:     s.id,
        titolo: s.titolo,
        testo:  s.testo ?? '',
    })),
});

const submit = () => {
    form.put(route('relazione-missione.update', [tenant, props.relazione.id]));
};

// Placeholder insertion
const showPlaceholders   = ref(false);
const activeTextareaIdx  = ref(null);
const activeTextareaRef  = ref(null);

const setActiveArea = (idx, el) => {
    activeTextareaIdx.value = idx;
    activeTextareaRef.value = el;
};

const insertPlaceholder = (key) => {
    const idx = activeTextareaIdx.value;
    if (idx === null) return;
    const el = activeTextareaRef.value;
    const tag = `{{${key}}}`;
    if (el) {
        const start  = el.selectionStart ?? form.sezioni[idx].testo.length;
        const end    = el.selectionEnd   ?? start;
        const before = form.sezioni[idx].testo.substring(0, start);
        const after  = form.sezioni[idx].testo.substring(end);
        form.sezioni[idx].testo = before + tag + after;
        // Restore cursor after inserted tag
        setTimeout(() => {
            el.selectionStart = el.selectionEnd = start + tag.length;
            el.focus();
        }, 0);
    } else {
        form.sezioni[idx].testo += tag;
    }
    showPlaceholders.value = false;
};

const compiledCount = computed(() =>
    form.sezioni.filter(s => s.testo.trim()).length
);
</script>

<template>
    <AppLayout :title="`Modifica Relazione ${relazione.anno}`">
        <Head :title="`Modifica Relazione ${relazione.anno}`" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-2">
                    <DocumentTextIcon class="w-6 h-6 text-blue-600" />
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">
                        Relazione di Missione {{ relazione.anno }}
                    </h2>
                    <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded-full font-semibold">bozza</span>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <CheckIcon class="w-4 h-4 text-green-600" />
                    {{ compiledCount }} / {{ form.sezioni.length }} sezioni compilate
                </div>
            </div>
        </template>

        <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Placeholder toolbar -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-1">
                        <InformationCircleIcon class="w-4 h-4" />
                        Segnaposto automatici
                    </span>
                    <div class="relative">
                        <button @click="showPlaceholders = !showPlaceholders"
                                class="flex items-center gap-1 text-sm bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg px-3 py-1.5">
                            Inserisci segnaposto
                            <ChevronDownIcon class="w-4 h-4" />
                        </button>
                        <div v-if="showPlaceholders"
                             class="absolute z-10 mt-1 w-72 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 py-1">
                            <button v-for="p in placeholder_list" :key="p.key"
                                    @click="insertPlaceholder(p.key)"
                                    class="w-full text-left px-4 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 text-sm">
                                <span class="font-mono text-indigo-700 dark:text-indigo-400">{{ '{{' + p.key + '}}' }}</span>
                                <span class="text-gray-500 text-xs ml-2">{{ p.label }}</span>
                            </button>
                        </div>
                    </div>
                    <span v-if="activeTextareaIdx !== null" class="text-xs text-gray-400">
                        Sezione attiva: {{ form.sezioni[activeTextareaIdx]?.titolo }}
                    </span>
                    <span v-else class="text-xs text-gray-400 italic">
                        Clicca su una textarea per selezionare dove inserire
                    </span>
                </div>
            </div>

            <!-- Sezioni -->
            <div v-for="(sez, idx) in form.sezioni" :key="sez.id"
                 class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                <div class="bg-blue-900 text-white px-5 py-3 flex justify-between items-center">
                    <span class="font-semibold text-sm">{{ sez.titolo }}</span>
                    <span v-if="sez.testo.trim()"
                          class="text-xs bg-green-500/30 text-green-200 px-2 py-0.5 rounded-full">
                        ✓ Compilata
                    </span>
                </div>
                <div class="p-4">
                    <textarea
                        v-model="sez.testo"
                        rows="6"
                        @focus="(e) => setActiveArea(idx, e.target)"
                        class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm font-mono focus:border-indigo-400 focus:ring-indigo-400"
                        :placeholder="`Testo della sezione: ${sez.titolo}`"
                    ></textarea>
                    <p v-if="form.errors[`sezioni.${idx}.testo`]" class="text-red-600 text-xs mt-1">
                        {{ form.errors[`sezioni.${idx}.testo`] }}
                    </p>
                </div>
            </div>

            <!-- Metadati -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="font-semibold text-gray-700 dark:text-gray-200 mb-4">Metadati</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stato</label>
                        <select v-model="form.stato"
                                class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm">
                            <option v-for="(lbl, key) in statiLabel" :key="key" :value="key">{{ lbl }}</option>
                        </select>
                        <p v-if="form.errors.stato" class="text-red-600 text-xs mt-1">{{ form.errors.stato }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Organo approvante</label>
                        <input v-model="form.organo_approvante" type="text"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data approvazione</label>
                        <input v-model="form.data_approvazione" type="date"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Luogo approvazione</label>
                        <input v-model="form.luogo_approvazione" type="text"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Note interne</label>
                        <textarea v-model="form.note_interne" rows="2"
                                  class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm"></textarea>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-between items-center">
                <Link :href="route('relazione-missione.show', [tenant, relazione.id])"
                      class="text-sm text-gray-500 hover:text-gray-700">
                    ← Annulla
                </Link>
                <PrimaryButton @click="submit" :disabled="form.processing">
                    {{ form.processing ? 'Salvataggio…' : 'Salva relazione' }}
                </PrimaryButton>
            </div>

        </div>
    </AppLayout>
</template>
