/* Service Worker PWA Keuangan — cache shell + offline fallback.
   Catatan: integrasi penuh (Vite/Workbox) menyusul di Sprint 9 (shell Vue). */
const CACHE = 'keuangan-pwa-v1';
const SHELL = ['/pwa-keuangan/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    // GET → cache-first untuk shell; POST (belanja/pemasukan) ditangani offline-sync.js (IndexedDB).
    if (request.method !== 'GET') return;
    event.respondWith(
        caches.match(request).then((cached) => cached || fetch(request).catch(() => caches.match('/pwa-keuangan/manifest.webmanifest')))
    );
});
