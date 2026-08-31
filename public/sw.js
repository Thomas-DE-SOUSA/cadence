const CACHE = 'cadence-v3';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    // Purge caches from previous versions so stale assets (e.g. the old logo) go.
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

// Network-first with a cache fallback: fresh data online, shell still loads offline.
self.addEventListener('fetch', (event) => {
    const { request } = event;
    // Only cache plain GET documents/assets. Never touch Inertia XHR partials
    // (they return JSON and must never be replayed as a page).
    if (request.method !== 'GET' || request.headers.get('X-Inertia')) {
        return;
    }
    event.respondWith(
        fetch(request)
            .then((response) => {
                const copy = response.clone();
                caches.open(CACHE).then((cache) => cache.put(request, copy)).catch(() => {});
                return response;
            })
            .catch(() => caches.match(request)),
    );
});
