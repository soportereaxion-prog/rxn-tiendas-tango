// RXN PWA — Captura de errores client-side y reporte al server.
//
// Objetivo: poder debuggear bugs del cliente PWA en mobile sin pedirle al
// operador que abra DevTools. Los errores se acumulan en el server y el admin
// descarga el txt desde /admin/rxnpwa-logs/download.
//
// Hooks:
//  - window.onerror — errores síncronos no capturados.
//  - window.onunhandledrejection — promesas rechazadas sin .catch.
//  - console.error / console.warn — wrap del console nativo (sin romperlo).
//
// Throttling: máximo 30 reports por minuto por cliente (anti-flood).

(function () {
    'use strict';

    const ENDPOINT = '/api/rxnpwa/log';
    const MAX_PER_MINUTE = 30;
    const recentSends = []; // timestamps de envíos en la última ventana

    function canSend() {
        const now = Date.now();
        // Mantener solo timestamps de los últimos 60s.
        while (recentSends.length && (now - recentSends[0]) > 60000) {
            recentSends.shift();
        }
        if (recentSends.length >= MAX_PER_MINUTE) return false;
        recentSends.push(now);
        return true;
    }

    function send(level, message, stack) {
        if (!canSend()) return;
        try {
            const payload = JSON.stringify({
                level: String(level).slice(0, 16),
                message: String(message).slice(0, 1500),
                stack: String(stack || '').slice(0, 1500),
                url: location.href.slice(0, 300),
            });
            // Preferir sendBeacon (no bloquea unload). Fallback fetch.
            if (navigator.sendBeacon) {
                const blob = new Blob([payload], { type: 'application/json' });
                navigator.sendBeacon(ENDPOINT, blob);
            } else {
                fetch(ENDPOINT, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: payload,
                    keepalive: true,
                }).catch(() => {});
            }
        } catch (e) {
            // No queremos que el collector mismo rompa la página.
        }
    }

    // Hook 1 — errores JS no capturados.
    window.addEventListener('error', (event) => {
        const msg = event.message || (event.error && event.error.message) || 'unknown error';
        const stack = (event.error && event.error.stack) || `${event.filename}:${event.lineno}:${event.colno}`;
        send('error', msg, stack);
    });

    // Hook 2 — promesas rechazadas.
    window.addEventListener('unhandledrejection', (event) => {
        const reason = event.reason;
        const msg = (reason && reason.message) ? reason.message : String(reason);
        const stack = (reason && reason.stack) ? reason.stack : '';
        send('unhandledrejection', msg, stack);
    });

    // Hook 3 — console.error / console.warn (wrap, sin romper el original).
    const origError = console.error.bind(console);
    const origWarn  = console.warn.bind(console);
    console.error = function (...args) {
        try { send('console.error', args.map(stringify).join(' '), ''); } catch (e) {}
        origError(...args);
    };
    console.warn = function (...args) {
        try { send('console.warn', args.map(stringify).join(' '), ''); } catch (e) {}
        origWarn(...args);
    };

    function stringify(arg) {
        if (typeof arg === 'string') return arg;
        if (arg instanceof Error) return arg.message;
        try { return JSON.stringify(arg); } catch (e) { return String(arg); }
    }

    window.RxnPwaErrorCollector = { send };
})();
