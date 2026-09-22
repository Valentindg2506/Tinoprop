/**
 * CAS Service Worker - DESTRUCTOR DE CACHE
 * Este SW reemplaza al viejo y elimina toda la caché para forzar la actualización de la página.
 */
self.addEventListener('install', event => {
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => Promise.all(keys.map(k => caches.delete(k))))
        .then(() => self.registration.unregister())
        .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    // Si intenta pedir código, ir obligatoriamente con anti-cache
    if (event.request.url.includes('.js') || event.request.url.includes('.html') || event.request.url.includes('.css')) {
        let req = new Request(event.request.url + (event.request.url.includes('?') ? '&' : '?') + 'nocache=' + Date.now(), {
            cache: 'no-store'
        });
        event.respondWith(fetch(req));
    } else {
        event.respondWith(fetch(event.request));
    }
});
