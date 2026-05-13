<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { PencilSquareIcon, TrashIcon, StarIcon } from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid';
import { ref } from 'vue';

const props = defineProps({
    entity: { type: Object, required: true },
    note:   { type: Array,  default: () => [] },
});

const form = useForm({
    testo:      '',
    visibilita: 'interna',
    fissata:    false,
});

const submitNota = () => {
    form.post(route('consultant.notes.store', props.entity.slug), {
        onSuccess: () => form.reset(),
    });
};

const eliminaNota = (id) => {
    if (!confirm('Eliminare questa nota?')) return;
    router.delete(route('consultant.notes.destroy', [props.entity.slug, id]));
};

const editingId = ref(null);
const editForm = useForm({ testo: '', visibilita: 'interna', fissata: false });

const startEdit = (nota) => {
    editingId.value = nota.id;
    editForm.testo      = nota.testo;
    editForm.visibilita = nota.visibilita;
    editForm.fissata    = nota.fissata;
};

const saveEdit = (id) => {
    editForm.put(route('consultant.notes.update', [props.entity.slug, id]), {
        onSuccess: () => { editingId.value = null; },
    });
};
</script>

<template>
    <AppLayout :title="`Note — ${entity.name}`">
        <Head :title="`Note — ${entity.name}`" />
        <template #header>
            <div>
                <div class="flex items-center gap-1 mb-1">
                    <Link :href="route('consultant.entities.show', entity.slug)"
                        class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                        ← {{ entity.name }}
                    </Link>
                </div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Note consulente
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Note interne e condivise per questo ente
                </p>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Form nuova nota -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        <PencilSquareIcon class="size-4 inline me-1.5 text-gray-500 dark:text-gray-400" />
                        Aggiungi nota
                    </h3>
                    <form @submit.prevent="submitNota" class="space-y-4">
                        <textarea v-model="form.testo" rows="3" required
                            placeholder="Scrivi una nota su questo ente..."
                            class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200 text-sm px-3 py-2" />
                        <div class="flex items-center gap-4">
                            <div>
                                <select v-model="form.visibilita"
                                    class="rounded-lg border border-gray-300 dark:border-gray-600 focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200 text-sm px-3 py-1.5">
                                    <option value="interna">Interna (solo io)</option>
                                    <option value="condivisa">Condivisa (visibile al cliente)</option>
                                </select>
                            </div>
                            <label class="flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                <input type="checkbox" v-model="form.fissata" class="rounded" />
                                Fissa in evidenza
                            </label>
                            <PrimaryButton type="submit" :disabled="form.processing" class="ms-auto">
                                {{ form.processing ? 'Salvataggio...' : 'Salva nota' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <!-- Lista note -->
                <div v-if="note.length === 0"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-5 py-10 text-center text-sm text-gray-400">
                    Nessuna nota ancora. Crea la prima nota sopra.
                </div>

                <div v-else class="space-y-3">
                    <div v-for="nota in note" :key="nota.id"
                        class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 p-4"
                        :class="nota.fissata
                            ? 'border-yellow-300 dark:border-yellow-700'
                            : 'border-gray-200'">

                        <!-- Editing -->
                        <div v-if="editingId === nota.id">
                            <textarea v-model="editForm.testo" rows="3"
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200 text-sm px-3 py-2 mb-3" />
                            <div class="flex items-center gap-3">
                                <select v-model="editForm.visibilita"
                                    class="rounded-lg border border-gray-300 dark:border-gray-600 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-200 text-sm px-3 py-1.5">
                                    <option value="interna">Interna</option>
                                    <option value="condivisa">Condivisa</option>
                                </select>
                                <label class="flex items-center gap-1 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                    <input type="checkbox" v-model="editForm.fissata" class="rounded" />
                                    Fissa
                                </label>
                                <PrimaryButton @click="saveEdit(nota.id)" :disabled="editForm.processing" class="ms-auto text-sm">
                                    Salva
                                </PrimaryButton>
                                <button type="button" @click="editingId = null"
                                    class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400">
                                    Annulla
                                </button>
                            </div>
                        </div>

                        <!-- Display -->
                        <div v-else>
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1.5">
                                        <component :is="nota.fissata ? StarSolid : StarIcon"
                                            class="size-4 flex-shrink-0"
                                            :class="nota.fissata ? 'text-yellow-400' : 'text-gray-300 dark:text-gray-600'" />
                                        <span :class="[
                                            'text-xs px-1.5 py-0.5 rounded font-medium',
                                            nota.visibilita === 'condivisa'
                                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
                                        ]">
                                            {{ nota.visibilita === 'condivisa' ? '🔗 Condivisa' : '🔒 Interna' }}
                                        </span>
                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ nota.updated_at }}</span>
                                    </div>
                                    <p class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-line">{{ nota.testo }}</p>
                                </div>
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    <button @click="startEdit(nota)" type="button"
                                        class="p-1.5 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                        <PencilSquareIcon class="size-4" />
                                    </button>
                                    <button @click="eliminaNota(nota.id)" type="button"
                                        class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                        <TrashIcon class="size-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
