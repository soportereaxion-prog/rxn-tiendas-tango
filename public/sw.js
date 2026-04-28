// RxnSuite — Service Worker para Web Push
// Recibe push events del browser y los muestra como notificación nativa.
// Click en la notif abre el link correspondiente (si la app ya está abierta,
// foca esa pestaña en lugar de abrir una nueva).

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', function (event) {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch (_) {
        payload = { title: 'RxnSuite', body: event.data ? event.data.text() : '' };
    }

    const title = payload.title || 'RxnSuite';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/img/rxn-icon-192.png',
        badge: payload.icon || '/img/rxn-icon-192.png',
        data: { link: payload.link || '/', extra: payload.data || {} },
        tag: (payload.data && payload.data.tag) || undefined,
        renotify: true,
    };

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
