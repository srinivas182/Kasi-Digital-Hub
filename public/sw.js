/*
 * KasiHub service worker (docs/adr/009-pwa-caching.md).
 *
 * - Built assets (hashed file names) and fonts: cache-first, so repeat visits are instant.
 * - Pages: always from the network. Pages are NEVER cached, because they can contain
 *   personal data and hub computers are shared. With no connection, the offline page is shown.
 * - Everything else (API, uploads): network only.
 */
const VERSION = 'v1';
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
            .then((keys) => Promise.all(keys.filter((key) => ![ASSET_CACHE, SHELL_CACHE].includes(key)).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline')));
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
