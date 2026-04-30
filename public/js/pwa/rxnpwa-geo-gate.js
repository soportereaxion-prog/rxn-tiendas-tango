// RXN PWA — Gate global de GPS (Iteración 43, release 1.38.0+).
//
// Pedido del rey: la PWA es para tracking de presupuestos en campo, sin GPS no se
// puede usar. Este módulo:
//   - Al cargar (shell o form), pide acceso a la ubicación inmediatamente.
//   - Si falla (denied/timeout/error/unsupported), muestra un overlay bloqueante
//     que cubre toda la pantalla con botón "Reintentar".
//   - Refresca la posición cada 5 minutos en background para que las operaciones
//     (guardar, sync, emit-tango) tengan siempre lat/lng frescas.
//   - Expone `window.RxnPwaGeoGate.getCurrentGeo()` para los otros módulos.
//
// Importante: este script DEBE cargarse antes que rxnpwa-form.js, rxnpwa-sync-queue.js
// y rxnpwa-shell-drafts.js, que dependen del gate para resolver geo.
//
// CONTEXTO INSEGURO (release 1.39.0):
//   En HTTP plano (ej: http://192.168.10.10:9021 para pruebas en LAN) los browsers
//   bloquean Geolocation API por seguridad — devuelven "denied" sin siquiera mostrar
//   el prompt al usuario. Solo HTTPS y localhost califican como "secure context".
//   Para que las pruebas locales no se queden trancadas, cuando detectamos
//   `!isSecureContext`, ofrecemos un BYPASS DEV: el operador acepta usar una
//   coordenada mock (Buenos Aires centro), y el draft viaja con `source='dev_mock'`.
//   En PROD (HTTPS) el bypass NUNCA se ofrece.

(function (global) {
    'use strict';

    const REFRESH_INTERVAL_MS = 5 * 60 * 1000; // 5 minutos
    const TIMEOUT_MS = 10000;
    const DEV_MOCK_COORDS = { lat: -34.6037, lng: -58.3816, accuracy: 50 }; // Obelisco BA — sólo para pruebas locales

    let currentGeo = null;     // { lat, lng, accuracy, source, captured_at }
    let overlay = null;
    let refreshTimer = null;
    let devMockBanner = null;

    function isInsecureContext() {
        // `isSecureContext` es false en HTTP plano (excepto localhost/127.0.0.1).
        // En esos casos navigator.geolocation NO funciona — los browsers lo bloquean
        // por seguridad. Sólo permitimos bypass dev si NO estamos en secure context.
        return typeof window.isSecureContext !== 'undefined' && window.isSecureContext === false;
    }

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        captureAndGate();
        // Reintento periódico — si entró con GPS y se desactivó después, lo detecta.
        // En modo dev_mock no tiene sentido refrescar — la coord es estática.
        refreshTimer = setInterval(() => {
            if (currentGeo && currentGeo.source === 'dev_mock') return;
            captureAndGate();
        }, REFRESH_INTERVAL_MS);
    }

    async function captureAndGate() {
        // Si estamos en contexto inseguro, NO llamamos a navigator.geolocation —
        // sabemos que va a devolver "denied". Mostramos overlay específico de dev.
        if (isInsecureContext()) {
            // Si ya aceptamos el mock antes en esta sesión, mantenerlo y no molestar.
            if (currentGeo && currentGeo.source === 'dev_mock') {
                hideLock();
                renderDevMockBanner();
                return currentGeo;
            }
            showLock('insecure_context');
            return null;
        }

        const geo = await captureGeo();
        if (geo.source === 'gps' || geo.source === 'wifi') {
            currentGeo = geo;
            hideLock();
            removeDevMockBanner();
            return geo;
        }
        // Conservar la última geo válida si la teníamos. La operación que pida geo
        // va a re-validar y, si la actual sigue inválida, va a forzar retry.
        showLock(geo.source);
        return null;
    }

    function captureGeo() {
        return new Promise((resolve) => {
            if (!('geolocation' in navigator)) {
                resolve({ source: 'unsupported', captured_at: new Date().toISOString() });
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    const acc = pos.coords.accuracy;
                    resolve({
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                        accuracy: acc ? Math.round(acc) : null,
                        source: acc && acc < 100 ? 'gps' : 'wifi',
                        captured_at: new Date().toISOString(),
                    });
                },
                (err) => {
                    const source = err.code === 1 ? 'denied'
                        : err.code === 3 ? 'timeout'
                        : 'error';
                    resolve({ source, captured_at: new Date().toISOString() });
                },
                { enableHighAccuracy: true, timeout: TIMEOUT_MS, maximumAge: 60000 }
            );
        });
    }

    function showLock(source) {
        if (overlay) {
            updateLockReason(source);
            return;
        }
        overlay = document.createElement('div');
        overlay.id = 'rxnpwa-geo-gate-overlay';
        overlay.className = 'rxnpwa-geo-gate';
        overlay.innerHTML = renderLockBody(source);
        document.body.appendChild(overlay);

        const retryBtn = document.getElementById('rxnpwa-geo-gate-retry');
        if (retryBtn) {
            retryBtn.addEventListener('click', async (e) => {
                const btn = e.currentTarget;
                const orig = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Pidiendo permiso…';
                await captureAndGate();
                btn.disabled = false;
                btn.innerHTML = orig;
            });
        }

        // Bypass dev — sólo aparece en contexto inseguro.
        const devBtn = document.getElementById('rxnpwa-geo-gate-dev-mock');
        if (devBtn) {
            devBtn.addEventListener('click', () => {
                currentGeo = {
                    lat: DEV_MOCK_COORDS.lat,
                    lng: DEV_MOCK_COORDS.lng,
                    accuracy: DEV_MOCK_COORDS.accuracy,
                    source: 'dev_mock',
                    captured_at: new Date().toISOString(),
                };
                hideLock();
                renderDevMockBanner();
            });
        }
    }

    function renderLockBody(source) {
        if (source === 'insecure_context') {
            return `
                <div class="rxnpwa-geo-gate-content">
                    <div class="text-center">
                        <i class="bi bi-shield-exclamation display-1 text-warning"></i>
                        <h2 class="h4 mt-3 mb-2">Servidor sin HTTPS</h2>
                        <p class="text-muted small">
                            Estás en un servidor de prueba sin certificado SSL. Los navegadores
                            modernos no permiten leer el GPS en HTTP plano (excepto localhost).
                            Para pruebas locales podés activar un GPS simulado.
                        </p>
                        <div class="alert alert-warning small text-start my-3">
                            <strong>Importante:</strong> el GPS simulado registra una coordenada fija
                            (Buenos Aires centro). Sirve para validar el flujo, no para tracking real.
                            En producción (HTTPS) este aviso no aparece — la PWA pide GPS de verdad.
                        </div>
                        <button type="button" class="btn btn-warning btn-lg w-100 mb-2" id="rxnpwa-geo-gate-dev-mock">
                            <i class="bi bi-geo-alt"></i> Activar GPS simulado (sólo dev)
                        </button>
                        <a href="/mi-empresa/crm/dashboard" class="btn btn-outline-light btn-sm w-100">
                            <i class="bi bi-box-arrow-left"></i> Volver al backoffice
                        </a>
                    </div>
                </div>
            `;
        }

        const reasonText = {
            'denied':      'Denegaste el permiso de ubicación.',
            'timeout':     'El GPS no respondió a tiempo. Verificá que esté activado.',
            'error':       'Hubo un error al leer el GPS.',
            'unsupported': 'Tu navegador no soporta acceso a la ubicación.',
        }[source] || 'No se pudo capturar la ubicación.';

        return `
            <div class="rxnpwa-geo-gate-content">
                <div class="text-center">
                    <i class="bi bi-geo-alt-fill display-1 text-warning"></i>
                    <h2 class="h4 mt-3 mb-2">GPS desactivado</h2>
                    <p class="text-muted">
                        La PWA de Presupuestos rastrea desde dónde se emite cada presupuesto. Sin GPS no se puede operar.
                    </p>
                    <div class="alert alert-warning small text-start my-3" id="rxnpwa-geo-gate-reason">${reasonText}</div>
                    <button type="button" class="btn btn-primary btn-lg w-100 mb-2" id="rxnpwa-geo-gate-retry">
                        <i class="bi bi-arrow-clockwise"></i> Reintentar GPS
                    </button>
                    <a href="/mi-empresa/crm/dashboard" class="btn btn-outline-light btn-sm w-100">
                        <i class="bi bi-box-arrow-left"></i> Volver al backoffice
                    </a>
                    <div class="small text-muted mt-3 text-start">
                        <strong>¿Cómo activarlo?</strong>
                        <ol class="mb-0 ps-3 mt-1">
                            <li>Tocá el ícono de candado / "i" al lado de la URL del navegador.</li>
                            <li>En "Permisos", habilitá "Ubicación".</li>
                            <li>Verificá que el GPS del celular esté encendido en los ajustes del sistema.</li>
                            <li>Tocá "Reintentar GPS".</li>
                        </ol>
                    </div>
                </div>
            </div>
        `;
    }

    function updateLockReason(source) {
        // En modo insecure_context el overlay tiene otro shape — re-render entero.
        if (source === 'insecure_context' && overlay) {
            overlay.innerHTML = renderLockBody(source);
            return;
        }
        const reasonEl = document.getElementById('rxnpwa-geo-gate-reason');
        if (!reasonEl) return;
        const reasonText = {
            'denied':      'Denegaste el permiso de ubicación.',
            'timeout':     'El GPS no respondió a tiempo. Verificá que esté activado.',
            'error':       'Hubo un error al leer el GPS.',
            'unsupported': 'Tu navegador no soporta acceso a la ubicación.',
        }[source] || 'No se pudo capturar la ubicación.';
        reasonEl.textContent = reasonText;
    }

    function hideLock() {
        if (!overlay) return;
        overlay.remove();
        overlay = null;
    }

    /**
     * Banner amarillo arriba de la PWA mientras estamos usando el mock dev.
     * Recordatorio constante de que la geo es ficticia.
     */
    function renderDevMockBanner() {
        if (devMockBanner) return;
        devMockBanner = document.createElement('div');
        devMockBanner.className = 'rxnpwa-dev-mock-banner';
        devMockBanner.innerHTML = `
            <i class="bi bi-shield-exclamation"></i>
            <span class="fw-bold">Modo dev — GPS simulado</span>
            <span class="small">(${DEV_MOCK_COORDS.lat.toFixed(4)}, ${DEV_MOCK_COORDS.lng.toFixed(4)})</span>
        `;
        document.body.insertBefore(devMockBanner, document.body.firstChild);
    }

    function removeDevMockBanner() {
        if (!devMockBanner) return;
        devMockBanner.remove();
        devMockBanner = null;
    }

    global.RxnPwaGeoGate = {
        getCurrentGeo: () => currentGeo,
        retry: captureAndGate,
        isInsecureContext,
    };
})(window);
