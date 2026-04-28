import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initializeTheme } from './Composables/useTheme.js';

const defaultAppName = import.meta.env.VITE_APP_NAME || 'Laravel';
let appDisplayName = defaultAppName;

/**
 * Sincronizza window.Ziggy con i props inviati dal middleware HandleInertiaRequests.
 * Necessario perché il middleware include 'defaults.tenant' (impostato da ResolveTenant)
 * mentre la direttiva @routes Blade emette window.Ziggy senza defaults.
 */
function syncZiggyFromInertiaProps(inertiaProps) {
    if (!inertiaProps || typeof window === 'undefined') return;

    const ziggyProp = inertiaProps.ziggy;
    if (ziggyProp && typeof ziggyProp === 'object') {
        // Sostituisce completamente window.Ziggy con la versione del server (include defaults.tenant)
        window.Ziggy = { ...ziggyProp };
        if (typeof globalThis !== 'undefined') globalThis.Ziggy = window.Ziggy;
    } else if (inertiaProps.currentTenant?.slug && window.Ziggy) {
        // Fallback: se ziggy prop non c'è ma currentTenant sì, imposta solo i defaults
        window.Ziggy.defaults = { ...(window.Ziggy.defaults ?? {}), tenant: inertiaProps.currentTenant.slug };
    }

    if (inertiaProps.nome_associazione) {
        appDisplayName = inertiaProps.nome_associazione;
    }
}

// Sincronizza Ziggy dal tag data-page PRIMA di creare l'app Vue (primo render)
try {
    const el = document.getElementById('app');
    const pageJson = el?.getAttribute?.('data-page');
    if (pageJson) {
        const data = JSON.parse(pageJson);
        syncZiggyFromInertiaProps(data?.props);
    }
} catch (_) {}

// Inizializza il tema prima di creare l'app Vue
initializeTheme();

// Aggiorna Ziggy ad ogni risposta Inertia (capture phase) per i navigation successivi
document.addEventListener('inertia:beforeUpdate', (event) => {
    syncZiggyFromInertiaProps(event.detail?.page?.props);
}, true);

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
