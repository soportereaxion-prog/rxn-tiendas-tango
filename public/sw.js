// RxnSuite — Service Worker
//
// Responsabilidades (en este orden):
//   1) Web Push: recibir push events del browser y mostrarlos como notificación nativa.
//   2) PWA offline (scope /rxnpwa/): cachear app shell + assets, runtime cache de catálogo.
//
// IMPORTANTE: el flujo de Web Push estaba operativo desde release 1.27.0. NO romper.
// La sección "PWA offline" se sumó en release 1.31.0 (Iteración 42 — PWA Fase 1).
//
// Estrategias de fetch (sólo aplican a requests dentro del scope /rxnpwa/):
//   - HTML del shell                  → network-first con fallback a cache.
//   - Assets versionados (/icons/, /manifest, /js/pwa/, /js/rxn-*.js, /css/) → stale-while-revalidate.
//   - GET /api/rxnpwa/catalog/version → siempre red (no cachear: el badge "no se sincroniza hace Xh"
//                                       depende de generated_at fresco).
//   - GET /api/rxnpwa/catalog/full    → siempre red (lo que persiste es IndexedDB, no Cache API).
//   - Todo lo demás                   → passthrough (no interceptamos nada del backoffice clásico).

const RXNPWA_VERSION = 'rxnpwa-v2-2026-04-30';
const SHELL_CACHE = `${RXNPWA_VERSION}-shell`;
const ASSETS_CACHE = `${RXNPWA_VERSION}-assets`;

const SHELL_URLS = [
    '/rxnpwa/presupuestos',
    '/rxnpwa/presupuestos/nuevo',
    '/manifest.webmanifest',
    '/icons/rxnpwa-192.png',
    '/icons/rxnpwa-512.png',
    '/css/rxnpwa.css',
];

self.addEventListener('install', (event) => {
    // Pre-cachear el shell (best-effort: si alguno falla por auth, no rompe el SW).
    event.waitUntil(
        caches.open(SHELL_CACHE).then((cache) => {
            return Promise.all(
                SHELL_URLS.map((url) =>
                    cache.add(url).catch(() => {
                        // Silenciar — el shell se rellena al primer fetch real.
                    })
                )
            );
        }).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys.map((key) => {
                    if (key.startsWith('rxnpwa-') && !key.startsWith(RXNPWA_VERSION)) {
                        return caches.delete(key);
                    }
                    return null;
                })
            )
        ).then(() => self.clients.claim())
    );
});

// ----- Web Push (preservado de release 1.27.0) -----
self.addEventListener('push', function (event) {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch (_) {
        payload = { title: 'RxnSuite', body: event.data ? event.data.text() : '' };
    }

    const title = payload.title || 'RxnSuite';
    const tag = (payload.data && payload.data.tag) || '';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/img/rxn-icon-192.png',
        badge: payload.icon || '/img/rxn-icon-192.png',
        data: { link: payload.link || '/', extra: payload.data || {} },
    };
    if (tag) {
        options.tag = tag;
        options.renotify = true;
    }

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const targetLink = (event.notification.data && event.notification.data.link) || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
            for (let i = 0; i < windowClients.length; i++) {
                const client = windowClients[i];
                if (client.url.indexOf(self.location.origin) === 0 && 'focus' in client) {
                    client.focus();
                    if ('navigate' in client) {
                        client.navigate(targetLink);
                    }
                    return;
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(targetLink);
            }
        })
    );
});

// ----- PWA fetch (scope /rxnpwa/) -----
self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Catálogo: siempre red. Quien cachea es el cliente (IndexedDB).
    if (url.pathname.startsWith('/api/rxnpwa/')) {
        event.respondWith(fetch(req));
        return;
    }

    const isShellNavigation = req.mode === 'navigate' && url.pathname.startsWith('/rxnpwa/');
    if (isShellNavigation) {
        event.respondWith(networkFirst(req, SHELL_CACHE));
        return;
    }

    const isPwaAsset =
        url.pathname.startsWith('/js/pwa/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname === '/manifest.webmanifest' ||
        url.pathname === '/css/rxnpwa.css';

    if (isPwaAsset) {
        event.respondWith(staleWhileRevalidate(req, ASSETS_CACHE));
        return;
    }

    // Todo lo demás (admin clásico, APIs no-PWA, etc): passthrough sin interceptar.
});

async function networkFirst(req, cacheName) {
    const cache = await caches.open(cacheName);
    try {
        const fresh = await fetch(req);
        if (fresh && fresh.ok) {
            cache.put(req, fresh.clone()).catch(() => {});
        }
        return fresh;
    } catch (err) {
        const cached = await cache.match(req);
        if (cached) return cached;
        // Sin cache y sin red: devolver shell mínimo para que la UI muestre el modo offline.
        const fallback = await cache.match('/rxnpwa/presupuestos');
        if (fallback) return fallback;
        throw err;
    }
}

async function staleWhileRevalidate(req, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(req);
    const networkPromise = fetch(req)
        .then((res) => {
            if (res && res.ok) {
                cache.put(req, res.clone()).catch(() => {});
            }
            return res;
        })
        .catch(() => null);
    return cached || networkPromise || fetch(req);
}
