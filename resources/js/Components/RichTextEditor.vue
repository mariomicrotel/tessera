<script setup>
import { watch, ref, onBeforeUnmount, computed, onMounted } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { TableKit } from '@tiptap/extension-table';
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyle, FontSize } from '@tiptap/extension-text-style';
import {
    BoldIcon,
    ItalicIcon,
    ListBulletIcon,
    NumberedListIcon,
    Bars3BottomLeftIcon,
    Bars3CenterLeftIcon,
    Bars3BottomRightIcon,
    Bars4Icon,
    ChevronDownIcon,
    DocumentTextIcon,
    TableCellsIcon,
} from '@heroicons/vue/24/outline';

const FONT_SIZES = [
    { label: 'Piccolo', value: '12px' },
    { label: 'Normale', value: '14px' },
    { label: 'Grande', value: '18px' },
    { label: 'Molto grande', value: '24px' },
];

const BLOCK_TYPES = [
    { label: 'Paragrafo', type: 'paragraph' },
    { label: 'Titolo 1', type: 'heading', level: 1 },
    { label: 'Titolo 2', type: 'heading', level: 2 },
    { label: 'Titolo 3', type: 'heading', level: 3 },
];

const PLACEHOLDERS = [
    { value: '{{nome-associazione}}', label: 'Nome associazione' },
    { value: '{{data}}', label: 'Data' },
    { value: '{{ora}}', label: 'Ora' },
    { value: '{{ora+30m}}', label: 'Ora +30 min' },
    { value: '{{ora+1h}}', label: 'Ora +1 h' },
    { value: '{{ora+1h20m}}', label: 'Ora +1h 20min' },
    { value: '{{n-soci}}', label: 'Numero soci attivi' },
    { value: '{{anno}}', label: 'Anno' },
    { value: '{{anno+1}}', label: 'Anno successivo' },
    { value: '{{presidente}}', label: 'Presidente' },
    { value: '{{segretario}}', label: 'Segretario' },
    { value: '{{tesoriere}}', label: 'Tesoriere' },
    { value: '{{sede}}', label: 'Sede (indirizzo)' },
    { value: '{{sede-legale}}', label: 'Sede legale' },
    { value: '{{sede-operativa}}', label: 'Sede operativa' },
    { value: '{{direttivo}}', label: 'Direttivo (nome e cariche)' },
    { value: '{{richieste-soci}}', label: 'Richieste soci (aspiranti)' },
];

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Scrivi qui...' },
    minHeight: { type: String, default: '200px' },
    /** Se valorizzato, sostituisce il menu placeholder predefinito (verbali/email). */
    placeholderItems: { type: Array, default: null },
    /** Abilita nodi tabella (HTML table/th/td); necessario per template ricevute con tabelle. */
    enableTable: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const placeholderMenu = computed(() => props.placeholderItems ?? PLACEHOLDERS);

function editorExtensions() {
    const list = [
        StarterKit,
        Placeholder.configure({ placeholder: props.placeholder }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        TextStyle,
        FontSize.configure({ types: ['textStyle'] }),
    ];
    if (props.enableTable) {
        list.push(TableKit);
    }
    return list;
}

const editor = useEditor({
    content: props.modelValue || '',
    extensions: editorExtensions(),
    editorProps: {
        attributes: {
            class: 'prose prose-sm dark:prose-invert max-w-none min-h-[120px] px-3 py-2 focus:outline-none',
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

const currentBlockLabel = computed(() => {
    if (!editor.value) return 'Paragrafo';
    const e = editor.value;
    if (e.isActive('heading', { level: 1 })) return 'Titolo 1';
    if (e.isActive('heading', { level: 2 })) return 'Titolo 2';
    if (e.isActive('heading', { level: 3 })) return 'Titolo 3';
    return 'Paragrafo';
});

const currentFontSize = ref('14px');
const blockDropdownOpen = ref(false);
const fontDropdownOpen = ref(false);
const placeholderDropdownOpen = ref(false);
const blockDropdownRef = ref(null);
const fontDropdownRef = ref(null);
const placeholderDropdownRef = ref(null);

function setBlockType(block) {
    if (!editor.value) return;
    if (block.type === 'paragraph') {
        editor.value.chain().focus().setParagraph().run();
    } else {
        editor.value.chain().focus().setHeading({ level: block.level }).run();
    }
    blockDropdownOpen.value = false;
}

function setFontSize(size) {
    if (!editor.value) return;
    if (!size || size === '14px') {
        editor.value.chain().focus().unsetFontSize().run();
        currentFontSize.value = '14px';
    } else {
        editor.value.chain().focus().setFontSize(size).run();
        currentFontSize.value = size;
    }
    fontDropdownOpen.value = false;
}

function isAlignActive(align) {
    return editor.value?.isActive({ textAlign: align });
}

function insertPlaceholder(value) {
    if (!editor.value) return;
    editor.value.chain().focus().insertContent(value).run();
    placeholderDropdownOpen.value = false;
}

function closeDropdownsIfOutside(e) {
    if (
        blockDropdownRef.value?.contains(e.target) ||
        fontDropdownRef.value?.contains(e.target) ||
        placeholderDropdownRef.value?.contains(e.target)
    ) {
        return;
    }
    blockDropdownOpen.value = false;
    fontDropdownOpen.value = false;
    placeholderDropdownOpen.value = false;
}

watch(() => props.modelValue, (val) => {
    if (!editor.value) return;
    const current = editor.value.getHTML();
    if (val !== current) {
        editor.value.commands.setContent(val || '', false);
    }
});

watch(editor, (e) => {
    if (!e) return;
    const updateFontSizeDisplay = () => {
        const attrs = e.getAttributes('textStyle');
        currentFontSize.value = attrs.fontSize || '14px';
    };
    e.on('selectionUpdate', updateFontSizeDisplay);
    e.on('transaction', updateFontSizeDisplay);
}, { immediate: true });


onMounted(() => {
    document.addEventListener('click', closeDropdownsIfOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeDropdownsIfOutside);
    editor.value?.destroy();
});
</script>

<template>
    <div class="rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
        <!-- Toolbar -->
        <div v-if="editor" class="flex flex-wrap items-center gap-0.5 border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-2 py-1">
            <!-- Tipo blocco: Paragrafo / Titolo 1,2,3 -->
            <div ref="blockDropdownRef" class="relative">
                <button
                    type="button"
                    :class="[blockDropdownOpen ? 'bg-gray-200 dark:bg-gray-600' : '']"
                    class="inline-flex items-center gap-1 px-2 py-1.5 rounded text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 text-sm min-w-[120px] justify-between"
                    title="Tipo blocco"
                    @click.stop="fontDropdownOpen = false; blockDropdownOpen = !blockDropdownOpen"
                >
                    <span>{{ currentBlockLabel }}</span>
                    <ChevronDownIcon class="size-4 shrink-0" aria-hidden="true" />
                </button>
                <div v-show="blockDropdownOpen" class="absolute left-0 top-full mt-0.5 py-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-md shadow-lg z-20 min-w-[140px]">
                    <button
                        v-for="block in BLOCK_TYPES"
                        :key="block.label"
                        type="button"
                        :class="[
                            'w-full text-left px-3 py-1.5 text-sm',
                            (block.type === 'paragraph' && editor.isActive('paragraph')) || (block.type === 'heading' && editor.isActive('heading', { level: block.level }))
                                ? 'bg-gray-200 dark:bg-gray-600 text-gray-900 dark:text-white'
                                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700',
                        ]"
                        @click.stop="setBlockType(block)"
                    >
                        {{ block.label }}
                    </button>
                </div>
            </div>

            <span class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1" aria-hidden="true" />

            <!-- Grassetto, corsivo -->
            <button
                type="button"
                :class="[editor.isActive('bold') ? 'bg-gray-300 dark:bg-gray-600 text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700']"
                class="p-2 rounded"
                title="Grassetto"
                @click="editor.chain().focus().toggleBold().run()"
            >
                <BoldIcon class="size-4" aria-hidden="true" />
            </button>
            <button
                type="button"
                :class="[editor.isActive('italic') ? 'bg-gray-300 dark:bg-gray-600 text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700']"
                class="p-2 rounded"
                title="Corsivo"
                @click="editor.chain().focus().toggleItalic().run()"
            >
                <ItalicIcon class="size-4" aria-hidden="true" />
            </button>

            <span class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1" aria-hidden="true" />

            <!-- Dimensione carattere -->
            <div ref="fontDropdownRef" class="relative">
                <button
                    type="button"
                    :class="[fontDropdownOpen ? 'bg-gray-200 dark:bg-gray-600' : '']"
                    class="inline-flex items-center gap-1 px-2 py-1.5 rounded text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 text-sm min-w-[100px] justify-between"
                    title="Dimensione carattere"
                    @click.stop="blockDropdownOpen = false; fontDropdownOpen = !fontDropdownOpen"
                >
                    <span>{{ FONT_SIZES.find((f) => f.value === currentFontSize)?.label ?? 'Normale' }}</span>
                    <ChevronDownIcon class="size-4 shrink-0" aria-hidden="true" />
                </button>
                <div v-show="fontDropdownOpen" class="absolute left-0 top-full mt-0.5 py-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-md shadow-lg z-20 min-w-[130px]">
                    <button
                        v-for="size in FONT_SIZES"
                        :key="size.value"
                        type="button"
                        :class="['w-full text-left px-3 py-1.5 text-sm', currentFontSize === size.value ? 'bg-gray-200 dark:bg-gray-600 text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700']"
                        @click.stop="setFontSize(size.value)"
                    >
                        {{ size.label }}
                    </button>
                </div>
            </div>

            <span class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1" aria-hidden="true" />

            <!-- Allineamento -->
            <button
                type="button"
                :class="[isAlignActive('left') ? 'bg-gray-300 dark:bg-gray-600 text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700']"
                class="p-2 rounded"
                title="Allinea a sinistra"
                @click="editor.chain().focus().setTextAlign('left').run()"
            >
                <Bars3BottomLeftIcon class="size-4" aria-hidden="true" />
            </button>
            <button
                type="button"
                :class="[isAlignActive('center') ? 'bg-gray-300 dark:bg-gray-600 text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700']"
                class="p-2 rounded"
                title="Centra"
                @click="editor.chain().focus().setTextAlign('center').run()"
            >
                <Bars3CenterLeftIcon class="size-4" aria-hidden="true" />
            </button>
            <button
                type="button"
                :class="[isAlignActive('right') ? 'bg-gray-300 dark:bg-gray-600 text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700']"
                class="p-2 rounded"
                title="Allinea a destra"
                @click="editor.chain().focus().setTextAlign('right').run()"
            >
                <Bars3BottomRightIcon class="size-4" aria-hidden="true" />
            </button>
            <button
                type="button"
                :class="[isAlignActive('justify') ? 'bg-gray-300 dark:bg-gray-600 text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700']"
                class="p-2 rounded"
                title="Giustificato"
                @click="editor.chain().focus().setTextAlign('justify').run()"
            >
                <Bars4Icon class="size-4" aria-hidden="true" />
            </button>

            <span class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1" aria-hidden="true" />

            <!-- Liste -->
            <button
                type="button"
                :class="[editor.isActive('bulletList') ? 'bg-gray-300 dark:bg-gray-600 text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700']"
                class="p-2 rounded"
                title="Elenco puntato"
                @click="editor.chain().focus().toggleBulletList().run()"
            >
                <ListBulletIcon class="size-4" aria-hidden="true" />
            </button>
            <button
                type="button"
                :class="[editor.isActive('orderedList') ? 'bg-gray-300 dark:bg-gray-600 text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700']"
                class="p-2 rounded"
                title="Elenco numerato"
                @click="editor.chain().focus().toggleOrderedList().run()"
            >
                <NumberedListIcon class="size-4" aria-hidden="true" />
            </button>

            <template v-if="enableTable">
                <span class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1" aria-hidden="true" />
                <button
                    type="button"
                    class="p-2 rounded text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700"
                    title="Inserisci tabella (3×3 con intestazione)"
                    @click="editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()"
                >
                    <TableCellsIcon class="size-4" aria-hidden="true" />
                </button>
            </template>

            <span class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1" aria-hidden="true" />

            <!-- Inserisci placeholder -->
            <div ref="placeholderDropdownRef" class="relative">
                <button
                    type="button"
                    :class="[placeholderDropdownOpen ? 'bg-gray-200 dark:bg-gray-600' : '']"
                    class="inline-flex items-center gap-1 px-2 py-1.5 rounded text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 text-sm"
                    title="Inserisci placeholder"
                    @click.stop="blockDropdownOpen = false; fontDropdownOpen = false; placeholderDropdownOpen = !placeholderDropdownOpen"
                >
                    <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                    <span class="hidden sm:inline">Placeholder</span>
                    <ChevronDownIcon class="size-4 shrink-0" aria-hidden="true" />
                </button>
                <div v-show="placeholderDropdownOpen" class="absolute left-0 top-full mt-0.5 py-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-md shadow-lg z-20 min-w-[220px] max-h-[280px] overflow-y-auto">
                    <button
                        v-for="ph in placeholderMenu"
                        :key="ph.value"
                        type="button"
                        class="w-full text-left px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                        :title="ph.label"
                        @click.stop="insertPlaceholder(ph.value)"
                    >
                        <span class="font-mono text-xs">{{ ph.value }}</span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400 truncate">{{ ph.label }}</span>
                    </button>
                </div>
            </div>

            <span class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1" aria-hidden="true" />
            <slot name="toolbar-actions" :editor="editor" />
        </div>
        <div class="dark:text-gray-300" :style="{ minHeight }">
            <EditorContent :editor="editor" />
        </div>
    </div>
</template>
