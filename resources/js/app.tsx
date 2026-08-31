import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { toast } from 'sonner';
import '../css/app.css';

const pages = import.meta.glob('./pages/**/*.tsx');

// Never let a request fail silently: a non-Inertia response (419 session
// expired, 500…) or a network drop now surfaces a toast instead of "nothing
// happens". Validation errors stay per-form (they populate `errors`).
router.on('invalid', (event) => {
    event.preventDefault();
    toast.error('La requête a échoué (session expirée ou erreur serveur). Recharge la page et réessaie.');
});
router.on('exception', () => {
    toast.error('Erreur réseau — vérifie ta connexion et réessaie.');
});

createInertiaApp({
    title: (title) => (title ? `${title} · Cadence` : 'Cadence'),
    resolve: (name) => {
        const page = pages[`./pages/${name}.tsx`];
        if (!page) {
            throw new Error(`Unknown Inertia page: ${name}`);
        }
        return page();
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#D7FF3E' },
});

// Register the service worker for offline shell + installability (Android/Chrome).
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            /* offline support is best-effort */
        });
    });
}
