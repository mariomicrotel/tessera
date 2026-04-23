<script setup>
import { ref, computed, reactive } from 'vue';
import { FolderIcon, DocumentIcon, ArrowDownTrayIcon, DocumentTextIcon, Squares2X2Icon, ListBulletIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    items: Array,
    pagination: Object,
    breadcrumb: Array,
    currentPath: String,
    filters: Object,
});

const form = reactive({
    search: props.filters?.search ?? props.filters?.q ?? '',
});

function applySearch() {
    router.get(route('file.index'), { path: props.currentPath || undefined, search: form.search || undefined }, { preserveState: true });
}

function fileIndexParams(pathSeg, page) {
    const params = {};
    if (pathSeg !== undefined && pathSeg !== null) params.path = pathSeg;
    if (form.search) params.search = form.search;
    if (page && page > 1) params.page = page;
    return Object.keys(params).length ? params : {};
}

const selected = ref(null);
const viewMode = ref('grid'); // 'grid' = icone, 'list' = elenco

const PREVIEW_IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
const PREVIEW_PDF_EXT = ['pdf'];

function ext(name) {
    const i = name.lastIndexOf('.');
    return i >= 0 ? name.slice(i + 1).toLowerCase() : '';
}

function previewType(item) {
    if (item?.type !== 'file') return null;
    const e = ext(item.name);
    if (PREVIEW_IMAGE_EXT.includes(e)) return 'image';
    if (PREVIEW_PDF_EXT.includes(e)) return 'pdf';
    return null;
}

function formatSize(bytes) {
    if (bytes == null || bytes === 0) return '—';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let n = bytes;
    while (n >= 1024 && i < units.length - 1) {
        n /= 1024;
        i++;
    }
    return n.toFixed(i > 1 ? 2 : 0) + ' ' + units[i];
}

function previewUrl(item) {
    return route('file.preview') + '?path=' + encodeURIComponent(item.path);
}

function downloadUrl(item) {
    return route('file.download') + '?path=' + encodeURIComponent(item.path);
}

const sortedItems = computed(() => {
    const list = [...(props.items || [])];
    const folders = list.filter((i) => i.type === 'folder').sort((a, b) => a.name.localeCompare(b.name));
    const files = list.filter((i) => i.type === 'file').sort((a, b) => a.name.localeCompare(b.name));
    return [...folders, ...files];
});

function selectFile(item) {
    if (item.type === 'file') selected.value = item;
}

function closePreview() {
    selected.value = null;
}
</script>

<template>
    <AppLayout title="File">
        <Head title="File" />
        <template #header>
            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">File</h2>
                        <nav class="flex flex-wrap items-center gap-1 text-sm text-gray-600 dark:text-gray-400 mt-1">
                            <template v-for="(crumb, i) in breadcrumb" :key="crumb.path">
                                <span v-if="i > 0" class="text-gray-400 dark:text-gray-500"> &gt; </span>
                                <Link
                                    v-if="i < breadcrumb.length - 1"
                                    :href="route('file.index', fileIndexParams(crumb.path))"
                                    class="text-indigo-600 dark:text-indigo-400 hover:underline"
                                >
                                    {{ crumb.label }}
                                </Link>
                                <span v-else class="text-gray-800 dark:text-gray-200 font-medium">{{ crumb.label }}</span>
                            </template>
                        </nav>
                    </div>
                    <div class="flex rounded-lg border border-gray-200 dark:border-gray-600 p-0.5 bg-gray-100 dark:bg-gray-800">
                        <button
                            type="button"
                            :class="viewMode === 'grid'
                                ? 'bg-white dark:bg-gray-700 text-indigo-600 dark:text-indigo-400 shadow-sm'
                                : 'text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'"
                            class="p-2 rounded-md transition"
                            title="Visualizzazione icone"
                            @click="viewMode = 'grid'"
                        >
                            <Squares2X2Icon class="size-5" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            :class="viewMode === 'list'
                                ? 'bg-white dark:bg-gray-700 text-indigo-600 dark:text-indigo-400 shadow-sm'
                                : 'text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'"
                            class="p-2 rounded-md transition"
                            title="Visualizzazione elenco"
                            @click="viewMode = 'list'"
                        >
                            <ListBulletIcon class="size-5" aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="flex flex-1 min-h-0 py-4 px-4 sm:px-6 lg:px-8">
            <!-- Pannello sinistro: griglia file e cartelle -->
            <div class="flex flex-col min-w-0 flex-1 lg:flex-initial lg:w-1/2 lg:max-w-[50%] lg:pr-4">
                <form @submit.prevent="applySearch" class="mb-3 flex gap-2 items-center">
                    <div class="flex-1 min-w-0">
                        <TextInput
                            v-model="form.search"
                            type="text"
                            class="block w-full"
                            placeholder="Cerca in questa cartella..."
                        />
                    </div>
                    <PrimaryButton type="submit"><MagnifyingGlassIcon class="size-4 me-2" aria-hidden="true" />Cerca</PrimaryButton>
                </form>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-auto flex-1 min-h-[280px]">
                    <!-- Modalità icone (griglia) -->
                    <div v-if="viewMode === 'grid'" class="p-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-3 gap-3">
                        <template v-for="item in sortedItems" :key="item.path">
                            <Link
                                v-if="item.type === 'folder'"
                                :href="route('file.index', fileIndexParams(item.path))"
                                class="flex flex-col items-center justify-center p-4 rounded-lg border border-transparent hover:border-gray-300 dark:hover:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                            >
                                <FolderIcon class="size-12 text-amber-500 dark:text-amber-400 mb-2" aria-hidden="true" />
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200 text-center truncate w-full">{{ item.name }}</span>
                            </Link>
                            <button
                                v-else
                                type="button"
                                class="flex flex-col items-center justify-center p-4 rounded-lg border transition text-left"
                                :class="selected?.path === item.path
                                    ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 dark:border-indigo-400'
                                    : 'border-transparent hover:border-gray-300 dark:hover:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/50'"
                                @click="selectFile(item)"
                            >
                                <DocumentIcon class="size-12 text-gray-400 dark:text-gray-500 mb-2 shrink-0" aria-hidden="true" />
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200 text-center truncate w-full">{{ item.name }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ formatSize(item.size) }}</span>
                            </button>
                        </template>
                    </div>
                    <!-- Modalità elenco -->
                    <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                        <template v-for="item in sortedItems" :key="item.path">
                            <Link
                                v-if="item.type === 'folder'"
                                :href="route('file.index', fileIndexParams(item.path))"
                                class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                            >
                                <FolderIcon class="size-8 shrink-0 text-amber-500 dark:text-amber-400" aria-hidden="true" />
                                <span class="font-medium text-gray-800 dark:text-gray-200 truncate">{{ item.name }}</span>
                            </Link>
                            <button
                                v-else
                                type="button"
                                class="w-full flex items-center gap-3 px-4 py-3 text-left transition"
                                :class="selected?.path === item.path
                                    ? 'bg-indigo-50 dark:bg-indigo-900/20'
                                    : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'"
                                @click="selectFile(item)"
                            >
                                <DocumentIcon class="size-8 shrink-0 text-gray-400 dark:text-gray-500" aria-hidden="true" />
                                <span class="flex-1 font-medium text-gray-800 dark:text-gray-200 truncate min-w-0">{{ item.name }}</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400 shrink-0">{{ formatSize(item.size) }}</span>
                                <a
                                    :href="downloadUrl(item)"
                                    class="shrink-0 p-1.5 rounded text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                                    title="Scarica"
                                    @click.stop
                                >
                                    <ArrowDownTrayIcon class="size-4" aria-hidden="true" />
                                </a>
                            </button>
                        </template>
                    </div>
                    <p v-if="!sortedItems.length" class="p-8 text-center text-gray-500 dark:text-gray-400">Questa cartella è vuota.</p>
                    <div v-if="pagination && (pagination.prev_page_url || pagination.next_page_url)" class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <Link v-if="pagination.prev_page_url" :href="pagination.prev_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">Indietro</Link>
                        <span v-else></span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Pagina {{ pagination.current_page }} di {{ pagination.last_page }}</span>
                        <Link v-if="pagination.next_page_url" :href="pagination.next_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">Avanti</Link>
                    </div>
                </div>
            </div>

            <!-- Pannello destro: anteprima (metà schermo su desktop) -->
            <div class="mt-4 lg:mt-0 lg:ml-0 lg:w-1/2 lg:max-w-[50%] flex flex-col min-h-[320px] lg:min-h-0 lg:flex-1 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div v-if="!selected" class="flex-1 flex items-center justify-center p-8 text-gray-500 dark:text-gray-400 text-center">
                    <div>
                        <DocumentTextIcon class="size-16 mx-auto mb-3 opacity-50" aria-hidden="true" />
                        <p>Seleziona un file per l'anteprima</p>
                    </div>
                </div>
                <template v-else>
                    <!-- Immagine -->
                    <div v-if="previewType(selected) === 'image'" class="flex-1 flex items-center justify-center p-4 min-h-0">
                        <img
                            :src="previewUrl(selected)"
                            :alt="selected.name"
                            class="max-w-full max-h-full object-contain"
                        />
                    </div>
                    <!-- PDF -->
                    <div v-else-if="previewType(selected) === 'pdf'" class="flex-1 min-h-0 flex flex-col">
                        <iframe
                            :src="previewUrl(selected)"
                            :title="selected.name"
                            class="w-full flex-1 min-h-[400px] rounded border-0"
                        />
                    </div>
                    <!-- Placeholder per formati non supportati -->
                    <div v-else class="flex-1 flex items-center justify-center p-8">
                        <div class="text-center">
                            <DocumentIcon class="size-16 mx-auto mb-3 text-gray-400 dark:text-gray-500" aria-hidden="true" />
                            <p class="text-gray-600 dark:text-gray-300 font-medium mb-1">Anteprima non disponibile per questo formato</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ selected.name }}</p>
                            <a
                                :href="downloadUrl(selected)"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500"
                            >
                                <ArrowDownTrayIcon class="size-4" aria-hidden="true" />
                                Scarica
                            </a>
                        </div>
                    </div>
                    <div class="shrink-0 px-4 py-2 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between bg-white dark:bg-gray-800">
                        <span class="text-sm text-gray-600 dark:text-gray-400 truncate">{{ selected.name }}</span>
                        <div class="flex gap-2 shrink-0">
                            <button
                                type="button"
                                class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                                @click="closePreview"
                            >
                                Chiudi
                            </button>
                            <a
                                :href="downloadUrl(selected)"
                                class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                            >
                                <ArrowDownTrayIcon class="size-4" aria-hidden="true" />
                                Scarica
                            </a>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
