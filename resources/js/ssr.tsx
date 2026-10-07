import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { renderToString } from 'react-dom/server';

import { resolvePage } from './lib/resolvePage';

const appName = import.meta.env.VITE_APP_NAME || 'KasiHub';

/**
 * Server-side rendering entry. Enabled for public pages in production
 * (INERTIA_SSR_ENABLED=true) so job and course pages load fast and are indexed.
 */
createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => (title ? `${title} | ${appName}` : appName),
        resolve: resolvePage,
        setup: ({ App, props }) => <App {...props} />,
    }),
);
