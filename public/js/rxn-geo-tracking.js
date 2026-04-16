/**
 * rxn-geo-tracking.js
 *
 * Helper cliente del módulo RxnGeoTracking. Expone window.RxnGeoTracking.report(eventoId)
 * que los módulos consumidores (Auth, CrmPresupuestos, CrmTratativas, CrmPedidosServicio)
 * invocan después de una acción exitosa cuando el server les devolvió un `evento_id`.
 *
 * Funcionamiento:
 *   1. Si el browser no tiene Geolocation API → POST con source='error'.
 *   2. Si el user ya denegó permiso anteriormente → POST con source='denied'.
 *   3. Si el user acepta → POST con { lat, lng, accuracy, source: 'gps'|'wifi' }.
 *
 * TIMEOUT de 5 segundos para getCurrentPosition() — si no responde, reporta 'error'.
 *
 * El evento ya está creado server-side con resolución IP. Este helper solo COMPLETA
 * la precisión si el user consintió. Si nunca se invoca este helper (ej. porque el
 * module consumer decidió no pedir ubicación), el evento queda tal cual con accuracy_source='ip'.
 *
 * Conveniencia: si el elemento <meta name="rxn-pending-geo-event" content="ID"> existe
 * en el head (inyectado por el backend en redirects post-creación), reportamos automáticamente.
 */
(function () {
    'use strict';

    const REPORT_ENDPOINT = '/geo-tracking/report';
    const GEOLOCATION_TIMEOUT_MS = 5000;

    function reportToServer(eventoId, lat, lng, accuracy, source) {
        if (!eventoId || eventoId <= 0) return Promise.resolve(false);

        return fetch(REPORT_ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                evento_id: eventoId,
                lat: lat,
                lng: lng,
                accuracy: accuracy,
                source: source,
            }),
        })
            .then(function (res) { return res.json().catch(function () { return { success: false }; }); })
            .then(function (data) { return !!(data && data.success); })
            .catch(function () { return false; });
    }

    function requestBrowserPosition() {
        return new Promise(function (resolve) {
            if (!('geolocation' in navigator)) {
                resolve({ source: 'error', lat: null, lng: null, accuracy: null });
                return;
            }

            let resolved = false;
            const finish = function (result) {
                if (resolved) return;
                resolved = true;
                resolve(result);
            };

            // Timeout manual además del de la API.
            const timer = setTimeout(function () {
                finish({ source: 'error', lat: null, lng: null, accuracy: null });
            }, GEOLOCATION_TIMEOUT_MS + 500);

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    clearTimeout(timer);
                    // Heurística: accuracy menor a 100m la consideramos GPS; más grande, WiFi triangulation.
                    const acc = position.coords.accuracy;
                    const source = acc !== undefined && acc !== null && acc < 100 ? 'gps' : 'wifi';
                    finish({
                        source: source,
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        accuracy: acc !== undefined ? Math.round(acc) : null,
                    });
                },
                function (err) {
                    clearTimeout(timer);
                    // err.code === 1 (PERMISSION_DENIED) → el user rechazó.
                    // err.code === 2 (POSITION_UNAVAILABLE) o 3 (TIMEOUT) → error.
                    const source = (err && err.code === 1) ? 'denied' : 'error';
                    finish({ source: source, lat: null, lng: null, accuracy: null });
                },
                {
                    enableHighAccuracy: true,
                    timeout: GEOLOCATION_TIMEOUT_MS,
                    maximumAge: 60000, // 1 minuto — evita re-preguntar al GPS en navegaciones rápidas.
                }
            );
        });
    }

    /**
     * API pública: reporta la posición del evento cuyo ID es `eventoId`.
     * Fire-and-forget desde la perspectiva del caller: la promise nunca rejecta.
     */
    function report(eventoId) {
        const id = parseInt(eventoId, 10);
        if (!id || id <= 0) return Promise.resolve(false);

        return requestBrowserPosition().then(function (pos) {
            return reportToServer(id, pos.lat, pos.lng, pos.accuracy, pos.source);
        });
    }

    // Expone la API para que cualquier módulo pueda invocar.
    window.RxnGeoTracking = {
        report: report,
    };

    // Auto-report si hay un evento pendiente anotado en <meta> o sessionStorage.
    // Esto habilita el flujo "el backend crea el evento en el login, redirige, el
    // frontend lee el meta y reporta posición" sin acoplar lógica a cada módulo.
    document.addEventListener('DOMContentLoaded', function () {
        // Vía 1: meta tag (inyectado en el head por el backend).
        const meta = document.querySelector('meta[name="rxn-pending-geo-event"]');
        if (meta) {
            const id = parseInt(meta.getAttribute('content'), 10);
            if (id > 0) {
                report(id);
                meta.remove(); // Evitar re-report al navegar con back/forward cache.
            }
            return;
        }

        // Vía 2: sessionStorage (útil cuando el flujo es SPA o el backend no pudo inyectar meta).
        try {
            const pending = sessionStorage.getItem('rxn_pending_geo_event');
            if (pending) {
                const id = parseInt(pending, 10);
                sessionStorage.removeItem('rxn_pending_geo_event');
                if (id > 0) {
                    report(id);
                }
            }
        } catch (e) {
            // sessionStorage deshabilitado — skip.
        }
    });
})();
