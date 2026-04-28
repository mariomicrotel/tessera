import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initializeTheme } from './Composables/useTheme.js';

const defaultAppName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Nome da mostrare (titolo browser): da props Inertia nome_associazione se disponibile
let appDisplayName = defaultAppName;
try {
    const el = document.getElementById('app');
    const pageJson = el?.getAttribute?.('data-page');
    if (pageJson) {
        const data = JSON.parse(pageJson);
        if (data?.props?.nome_associazione) {
            appDisplayName = data.props.nome_associazione;
        }
    }
} catch (_) {}

// Inizializza il tema prima di creare l'app Vue
initializeTheme();

// Dopo il login (navigazione Inertia) window.Ziggy ha ancora solo le route guest; aggiorniamo
// Ziggy da ogni risposta Inertia PRIMA del render (capture phase) così route('dashboard') funziona.
function updateZiggy(page) {
    const ziggy = page?.props?.ziggy;
    if (ziggy && typeof window !== 'undefined') {
        window.Ziggy = { ...ziggy };
        if (typeof globalThis !== 'undefined') globalThis.Ziggy = window.Ziggy;
    }
    if (page?.props?.nome_associazione) {
        appDisplayName = page.props.nome_associazione;
    }
}

document.addEventListener('inertia:beforeUpdate', (event) => {
    updateZiggy(event.detail?.page);
}, true);

// Aggiorna Ziggy anche durante il primo caricamento/navigazione via Inertia::location
document.addEventListener('inertia:navigate', () => {
    if (typeof window !== 'undefined' && window.Ziggy?.location) {
        // Usa gli URL defaults che il server ha impostato
        const path = window.location.pathname;
        if (path && typeof window.Ziggy.defaults === 'object') {
            // URL defaults sono già nel Ziggy object dal server
        }
    }
});

createInertiaApp({
    title: (title) => `${title} - ${appDisplayName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
