import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot, hydrateRoot } from 'react-dom/client';

import { resolvePage } from './lib/resolvePage';

const appName = import.meta.env.VITE_APP_NAME || 'KasiHub';

void createInertiaApp({
    title: (title) => (title ? `${title} | ${appName}` : appName),
    resolve: resolvePage,
    setup({ el, App, props }) {
        if (el.hasChildNodes()) {
            hydrateRoot(el, <App {...props} />);
            return;
        }
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#F5B700' },
});

// Installable app + offline page (production builds only; see public/sw.js).
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => undefined);
    });
}
