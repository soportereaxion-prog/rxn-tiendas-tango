/**
 * crm-horas-turnero.js
 *
 * Comportamiento del turnero (vista mobile-first):
 *  1. Pide geolocalización al cargar la página y la inyecta en los inputs hidden
 *     (lat / lng / geo_consent) del/los formularios visibles.
 *  2. Mantiene un contador en vivo "Hoy llevás X" sumando los segundos del
 *     turno abierto al base_seg que vino del server.
 *  3. Confirm dialog antes de cerrar turno (anti-toque accidental).
 *
 * El flujo de geo es no-bloqueante: si el usuario rechaza el permiso o el
 * navegador no soporta geolocation, los forms se envían igual con consent=0.
 */
(function () {
    'use strict';

    // ----- Geolocalización -----
    function setGeoStatus(label, cls) {
        document.querySelectorAll('.rxn-geo-label').forEach(el => { el.textContent = label; });
        document.querySelectorAll('.rxn-geo-status').forEach(el => {
            el.classList.remove('text-muted', 'text-success', 'text-warning', 'text-danger');
            if (cls) el.classList.add(cls);
        });
    }

    function applyGeo(lat, lng, consent) {
        document.querySelectorAll('.rxn-geo-lat').forEach(el => { el.value = lat ?? ''; });
        document.querySelectorAll('.rxn-geo-lng').forEach(el => { el.value = lng ?? ''; });
        document.querySelectorAll('.rxn-geo-consent').forEach(el => { el.value = consent ? '1' : '0'; });
    }

    if ('geolocation' in navigator) {
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                applyGeo(pos.coords.latitude.toFixed(7), pos.coords.longitude.toFixed(7), true);
                setGeoStatus('Ubicación capturada ✓', 'text-success');
            },
            function (err) {
                applyGeo('', '', false);
                let msg = 'Sin ubicación';
                if (err.code === err.PERMISSION_DENIED) msg = 'Permiso de ubicación denegado';
                else if (err.code === err.POSITION_UNAVAILABLE) msg = 'Ubicación no disponible';
                else if (err.code === err.TIMEOUT) msg = 'Ubicación timeout';
                setGeoStatus(msg + ' — el turno se guarda igual', 'text-warning');
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
        );
    } else {
        applyGeo('', '', false);
        setGeoStatus('Tu navegador no soporta ubicación — el turno se guarda igual', 'text-warning');
    }

    // ----- Contador en vivo -----
    function fmtHMS(totalSec) {
        const h = Math.floor(totalSec / 3600);
        const m = Math.floor((totalSec % 3600) / 60);
        const s = Math.floor(totalSec % 60);
        return [h, m, s].map(n => String(n).padStart(2, '0')).join(':');
    }

    const display = document.getElementById('totalHoyDisplay');
    if (display) {
        const baseSeg = parseInt(display.dataset.baseSeg || '0', 10);
        const abiertoIso = display.dataset.abiertoIso || '';
        let abiertoStartMs = null;
        if (abiertoIso) {
            const d = new Date(abiertoIso);
            if (!isNaN(d.getTime())) abiertoStartMs = d.getTime();
        }

        function tick() {
            let total = baseSeg;
            // Si hay turno abierto, el server ya sumó su duración hasta "now" en baseSeg,
            // pero ese valor se calculó al renderizar — vamos a recalcular el sumando
            // del abierto en vivo para que el contador siga corriendo.
            // Para no pisar la suma de los cerrados, restamos el tramo "abierto-en-server"
            // y volvemos a sumar "abierto-ahora". Al server, abierto.now ≈ render time.
            if (abiertoStartMs) {
                // Aproximación: el server contó desde started_at hasta render. Ahora sumamos
                // el delta entre render y "ahora" (contado desde el abierto sigue creciendo).
                // En la práctica baseSeg ya contiene el tramo hasta render, así que
                // sumamos solamente lo nuevo: now - first_tick_time.
                const delta = Math.floor((Date.now() - tickStart) / 1000);
                total += delta;
            }
            display.textContent = fmtHMS(total);
        }
        const tickStart = Date.now();
        tick();
        if (abiertoStartMs) {
            setInterval(tick, 1000);
        }
    }

    // ----- Confirm cerrar turno -----
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            const msg = btn.getAttribute('data-confirm');
            if (msg && !confirm(msg)) {
                e.preventDefault();
            }
        });
    });
})();
