import { ref, computed, watch, onMounted, readonly } from 'vue';

const STORAGE_KEY = 'theme-preference';
const THEMES = {
  LIGHT: 'light',
  DARK: 'dark',
  SYSTEM: 'system'
};

// Stato globale condiviso tra tutte le istanze
const currentTheme = ref(THEMES.SYSTEM);
const systemPrefersDark = ref(false);

// Media query per rilevare la preferenza del sistema
let mediaQuery = null;

// Funzione per aggiornare la preferenza del sistema
function updateSystemPreference() {
  if (typeof window !== 'undefined') {
    systemPrefersDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
  }
}

// Funzione per applicare il tema al DOM
function applyTheme(theme, systemDark) {
  if (typeof document === 'undefined') return;
  
  const isDark = theme === THEMES.DARK || (theme === THEMES.SYSTEM && systemDark);
  
  if (isDark) {
    document.documentElement.classList.add('dark');
  } else {
    document.documentElement.classList.remove('dark');
  }
}

// Funzione per caricare il tema salvato
function loadSavedTheme() {
  if (typeof localStorage === 'undefined') return THEMES.SYSTEM;
  
  const saved = localStorage.getItem(STORAGE_KEY);
  return Object.values(THEMES).includes(saved) ? saved : THEMES.SYSTEM;
}

// Funzione per salvare il tema
function saveTheme(theme) {
  if (typeof localStorage !== 'undefined') {
    localStorage.setItem(STORAGE_KEY, theme);
  }
}

// Inizializzazione
function initializeTheme() {
  // Carica la preferenza del sistema
  updateSystemPreference();
  
  // Carica il tema salvato
  currentTheme.value = loadSavedTheme();
  
  // Applica il tema
  applyTheme(currentTheme.value, systemPrefersDark.value);
  
  // Configura il listener per i cambiamenti della preferenza del sistema
  if (typeof window !== 'undefined') {
    mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    mediaQuery.addEventListener('change', updateSystemPreference);
  }
}

export function useTheme() {
  // Computed per determinare se il tema corrente è scuro
  const isDark = computed(() => {
    return currentTheme.value === THEMES.DARK || 
           (currentTheme.value === THEMES.SYSTEM && systemPrefersDark.value);
  });
  
  // Computed per ottenere il tema effettivo (risolve 'system' in 'light' o 'dark')
  const resolvedTheme = computed(() => {
    if (currentTheme.value === THEMES.SYSTEM) {
      return systemPrefersDark.value ? THEMES.DARK : THEMES.LIGHT;
    }
    return currentTheme.value;
  });
  
  // Funzione per cambiare tema
  function setTheme(theme) {
    if (!Object.values(THEMES).includes(theme)) {
      console.warn(`Tema non valido: ${theme}. Usa uno di: ${Object.values(THEMES).join(', ')}`);
      return;
    }
    
    currentTheme.value = theme;
    saveTheme(theme);
    applyTheme(theme, systemPrefersDark.value);
  }
  
  // Funzione per ciclare tra i temi
  function cycleTheme() {
    const themes = Object.values(THEMES);
    const currentIndex = themes.indexOf(currentTheme.value);
    const nextIndex = (currentIndex + 1) % themes.length;
    setTheme(themes[nextIndex]);
  }
  
  // Funzione per ottenere l'icona appropriata per il tema corrente
  function getThemeIcon() {
    switch (currentTheme.value) {
      case THEMES.LIGHT:
        return 'sun';
      case THEMES.DARK:
        return 'moon';
      case THEMES.SYSTEM:
        return 'computer';
      default:
        return 'computer';
    }
  }
  
  // Funzione per ottenere il nome leggibile del tema
  function getThemeName() {
    switch (currentTheme.value) {
      case THEMES.LIGHT:
        return 'Chiaro';
      case THEMES.DARK:
        return 'Scuro';
      case THEMES.SYSTEM:
        return 'Sistema';
      default:
        return 'Sistema';
    }
  }
  
  // Watch per applicare i cambiamenti quando la preferenza del sistema cambia
  watch(systemPrefersDark, (newValue) => {
    if (currentTheme.value === THEMES.SYSTEM) {
      applyTheme(THEMES.SYSTEM, newValue);
    }
  });
  
  // Inizializza al mount del primo componente che usa il composable
  onMounted(() => {
    if (!mediaQuery) {
      initializeTheme();
    }
  });
  
  return {
    // Stato reattivo
    currentTheme: readonly(currentTheme),
    isDark,
    resolvedTheme,
    systemPrefersDark: readonly(systemPrefersDark),
    
    // Costanti
    THEMES,
    
    // Metodi
    setTheme,
    cycleTheme,
    getThemeIcon,
    getThemeName,
    
    // Utility per inizializzazione manuale (utile per app.js)
    initializeTheme
  };
}

// Esporta anche una funzione per l'inizializzazione precoce
export { initializeTheme };