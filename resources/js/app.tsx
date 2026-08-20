import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';

const pages = import.meta.glob('./pages/**/*.tsx');

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
