<script setup>
import { ArrowLeftIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AttachmentsPanel from '@/Components/AttachmentsPanel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    spesa: Object,
    uploadMaxFileSizeHuman: { type: String, default: '10 MB' },
});

const page = usePage();

const primaNotaEntry = Array.isArray(props.spesa.prima_nota_entries) && props.spesa.prima_nota_entries.length
    ? props.spesa.prima_nota_entries[0]
    : null;

function destroy() {
    if (!confirm('Eliminare questa spesa? Verrà eliminato anche l\'eventuale movimento in prima nota collegato.')) return;
    router.delete(route('spese.destroy', props.spesa.id));
}

function removeAttachment(attachment) {
    if (!confirm('Rimuovere questo allegato?')) return;
    router.delete(route('spese.attachments.destroy', [props.spesa.id, attachment.id]));
}

const attachmentError = computed(() => {
    const err = page.props.errors?.file;
    if (!err) return null;
    return Array.isArray(err) ? err[0] : err;
});
</script>

<template>
    <AppLayout title="Dettaglio spesa">
        <Head title="Dettaglio spesa" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-2">
                <div class="flex items-center gap-2">
                    <Link :href="route('spese.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Spese</Link>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Spesa #{{ spesa.id }}</h2>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="route('spese.edit', spesa.id)"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        <PencilIcon class="size-4" aria-hidden="true" />Modifica
                    </Link>
                    <SecondaryButton type="button" class="!text-red-600 dark:!text-red-400" @click="destroy">
                        <TrashIcon class="size-4 me-2" aria-hidden="true" />Elimina
                    </SecondaryButton>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto sm:px-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Data</dt>
                        <dd>{{ spesa.date ? new Date(spesa.date).toLocaleDateString('it-IT') : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Importo</dt>
                        <dd class="font-medium">€ {{ Number(spesa.amount).toFixed(2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Conto</dt>
                        <dd>{{ spesa.conto?.name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Registrata in prima nota</dt>
                        <dd>
                            <template v-if="spesa.genera_prima_nota && spesa.rendiconto_label">
                                Sì – {{ spesa.rendiconto_label }}
                            </template>
                            <span v-else>No</span>
                        </dd>
                    </div>
                    <div v-if="primaNotaEntry" class="sm:col-span-2">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Movimento in prima nota</dt>
                        <dd>
                            <Link :href="route('prima-nota.edit', primaNotaEntry.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                Modifica movimento ({{ primaNotaEntry.date ? new Date(primaNotaEntry.date).toLocaleDateString('it-IT') : '' }} – € {{ Number(primaNotaEntry.amount).toFixed(2) }})
                            </Link>
                        </dd>
                    </div>
                    <div v-if="spesa.description" class="sm:col-span-2">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Descrizione</dt>
                        <dd>{{ spesa.description }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Allegati -->
            <AttachmentsPanel
                :attachments="spesa.attachments ?? []"
                :can-edit="true"
                :store-action="route('spese.attachments.store', spesa.id)"
                :upload-max-file-size-human="uploadMaxFileSizeHuman"
                :error="attachmentError"
                @remove="removeAttachment"
            />
        </div>
    </AppLayout>
</template>
