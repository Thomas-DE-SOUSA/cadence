// Self-destroying service worker.
//
// The previous network-first SW could leave clients on a stale bundle after
// rapid redeploys. This version unregisters itself, purges every cache and
// reloads controlled clients so everyone lands on fresh assets served straight
// from the network (Vite's hashed filenames already make browser caching safe).
// Offline support can return later via a properly versioned tool (Workbox).
self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const keys = await caches.keys();
            await Promise.all(keys.map((k) => caches.delete(k)));
            await self.clients.claim();
            await self.registration.unregister();
            const clients = await self.clients.matchAll({ type: 'window' });
            clients.forEach((client) => client.navigate(client.url));
        })(),
    );
});
