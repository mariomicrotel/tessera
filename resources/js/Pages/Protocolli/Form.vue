<!--
  Componente form condiviso da Create.vue e Edit.vue.
  Props:
    - form: useForm object (già costruito dal parent)
    - isEdit: boolean
    - receipts: array di ricevute disponibili per collegamento
-->
<script setup>
import { computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import {
    InboxArrowDownIcon, PaperAirplaneIcon, DocumentTextIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    form:    { type: Object, required: true },
    isEdit:  { type: Boolean, default: false },
    receipts:{ type: Array,  default: () => [] },
});

const emit = defineEmits(['submit']);

const corrispondenteLabel = computed(() =>
    props.form.tipo === 'entrata' ? 'Mittente' : 'Destinatario'
);
const corrispondenteField = computed(() =>
    props.form.tipo === 'entrata' ? 'mittente' : 'destinatario'
);
</script>

<template>
    <div class="space-y-5">

        <!-- Tipo + Data in griglia -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <!-- Tipo -->
            <div>
                <InputLabel value="Tipo *" />
                <div class="mt-1.5 flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                    <label
                        :class="['flex-1 flex items-center justify-center gap-2 py-2.5 px-3 cursor-pointer text-sm font-medium transition-colors',
                            form.tipo === 'entrata'
                                ? 'bg-blue-600 text-white'
                                : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600']">
                        <input type="radio" v-model="form.tipo" value="entrata" class="sr-only" />
                        <InboxArrowDownIcon class="size-4" />
                        Entrata
                    </label>
                    <label
                        :class="['flex-1 flex items-center justify-center gap-2 py-2.5 px-3 cursor-pointer text-sm font-medium transition-colors border-l border-gray-300 dark:border-gray-600',
                            form.tipo === 'uscita'
                                ? 'bg-emerald-600 text-white'
                                : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600']">
                        <input type="radio" v-model="form.tipo" value="uscita" class="sr-only" />
                        <PaperAirplaneIcon class="size-4" />
                        Uscita
                    </label>
                </div>
                <InputError :message="form.errors.tipo" class="mt-1" />
            </div>

            <!-- Data registrazione -->
            <div>
                <InputLabel for="data_registrazione" value="Data registrazione *" />
                <TextInput
                    id="data_registrazione"
                    v-model="form.data_registrazione"
                    type="date"
                    class="mt-1 block w-full"
                    required
                />
                <InputError :message="form.errors.data_registrazione" class="mt-1" />
            </div>
        </div>

        <!-- Oggetto -->
        <div>
            <InputLabel for="oggetto" value="Oggetto *" />
            <TextInput
                id="oggetto"
                v-model="form.oggetto"
                type="text"
                class="mt-1 block w-full"
                maxlength="500"
                placeholder="Descrizione sintetica della comunicazione"
                required
            />
            <InputError :message="form.errors.oggetto" class="mt-1" />
        </div>

        <!-- Mittente / Destinatario -->
        <div>
            <InputLabel :for="corrispondenteField" :value="corrispondenteLabel" />
            <TextInput
                :id="corrispondenteField"
                v-model="form[corrispondenteField]"
                type="text"
                class="mt-1 block w-full"
                maxlength="300"
                :placeholder="form.tipo === 'entrata'
                    ? 'Nome / Ragione sociale del mittente'
                    : 'Nome, email o indirizzo del destinatario'"
            />
            <InputError :message="form.errors[corrispondenteField]" class="mt-1" />
        </div>

        <!-- Collega a ricevuta (opzionale) -->
        <div v-if="receipts.length">
            <InputLabel for="receipt_id" value="Collega a ricevuta (opzionale)" />
            <div class="mt-1 flex items-center gap-2">
                <DocumentTextIcon class="size-4 text-indigo-400 shrink-0" />
                <select
                    id="receipt_id"
                    v-model="form.receipt_id"
                    class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"
                >
                    <option value="">— Nessuna ricevuta collegata —</option>
                    <option v-for="r in receipts" :key="r.id" :value="r.id">
                        {{ r.number }}
                        <template v-if="r.issued_at">
                            — {{ new Date(r.issued_at).toLocaleDateString('it-IT') }}
                        </template>
                        <template v-if="r.member_name"> — {{ r.member_name }}</template>
                    </option>
                </select>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Se la comunicazione riguarda una ricevuta già emessa, selezionala qui.
            </p>
            <InputError :message="form.errors.receipt_id" class="mt-1" />
        </div>

        <!-- Note -->
        <div>
            <InputLabel for="note" value="Note" />
            <textarea
                id="note"
                v-model="form.note"
                rows="3"
                maxlength="5000"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                placeholder="Note aggiuntive (opzionale)"
            />
            <InputError :message="form.errors.note" class="mt-1" />
        </div>

        <!-- Submit slot -->
        <div class="flex gap-2 pt-1">
            <slot name="actions" />
        </div>
    </div>
</template>
