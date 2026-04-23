<script setup>
import { computed } from 'vue';
import { 
  SunIcon, 
  MoonIcon, 
  ComputerDesktopIcon 
} from '@heroicons/vue/24/outline';
import { useTheme } from '@/Composables/useTheme.js';

const { currentTheme, THEMES, cycleTheme, getThemeName } = useTheme();

// Mappa delle icone per ogni tema
const themeIcons = {
  [THEMES.LIGHT]: SunIcon,
  [THEMES.DARK]: MoonIcon,
  [THEMES.SYSTEM]: ComputerDesktopIcon
};

// Ottieni l'icona corrente
const currentIcon = computed(() => themeIcons[currentTheme.value] || ComputerDesktopIcon);

function handleClick() {
  cycleTheme();
}
</script>

<template>
  <button
    type="button"
    @click="handleClick"
    class="flex items-center justify-center w-8 h-8 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition duration-150 ease-in-out"
    :title="`Tema: ${getThemeName()}. Clicca per cambiare.`"
  >
    <component 
      :is="currentIcon" 
      class="size-5 transition-colors duration-200"
      aria-hidden="true" 
    />
  </button>
</template>