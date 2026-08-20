const CACHE = 'cadence-v1';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

// Network-first with a cache fallback: fresh data online, shell still loads offline.
self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') {
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
