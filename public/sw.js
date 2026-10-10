/*
 * KasiHub service worker (docs/adr/009-pwa-caching.md).
 *
 * - Built assets (hashed file names) and fonts: cache-first, so repeat visits are instant.
 * - Pages: always from the network. Pages are NEVER cached, because they can contain
 *   personal data and hub computers are shared. With no connection, the offline page is shown.
 * - Everything else (API, uploads): network only.
 * - Exception (S14, ADR-022): courses a learner downloads for offline use live in the per-person
 *   cache `kasi-user-learn` (the offline reader page, lesson data and media). Media found there is
 *   served from it even when online (saves data). Per-person caches (`kasi-user-*`) are cleared
 *   when the person signs out and are never removed by service worker updates.
 */
const VERSION = 'v2';
const USER_LEARN_CACHE = 'kasi-user-learn';
const ASSET_CACHE = `kasi-assets-${VERSION}`;
const SHELL_CACHE = `kasi-shell-${VERSION}`;
const SHELL = ['/offline', '/icons/icon-192.png', '/icons/favicon.svg', '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(SHELL_CACHE)
            .then((cache) => cache.addAll(SHELL))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => ![ASSET_CACHE, SHELL_CACHE].includes(key) && !key.startsWith('kasi-user-')).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() =>
                url.pathname === '/learn/offline'
                    ? caches.open(USER_LEARN_CACHE).then((cache) => cache.match('/learn/offline')).then((page) => page || caches.match('/offline'))
                    : caches.match('/offline'),
            ),
        );
        return;
    }

    // Downloaded course media and lesson data: from the phone first (no data used), else the network.
    if (url.pathname.startsWith('/learn/media/') || url.pathname.startsWith('/learn/offline-data/')) {
        event.respondWith(caches.open(USER_LEARN_CACHE).then((cache) => cache.match(request)).then((cached) => cached || fetch(request)));
        return;
    }

    if (url.pathname.startsWith('/build/assets/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches.open(ASSET_CACHE).then((cache) => cache.put(request, copy));
                        }
                        return response;
                    }),
            ),
        );
    }
});
