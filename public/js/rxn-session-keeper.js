/**
 * rxn-session-keeper — aviso preventivo de sesión por vencer.
 *
 * Pollea /api/internal/session/heartbeat cada 60s. Cuando faltan ≤ WARN_SECONDS
 * para el idle timeout, muestra un toast persistente con botón "Extender ahora"
 * que dispara otro hit al heartbeat (lo cual renueva la sesión naturalmente
 * porque App.php actualiza last_activity en cada request autenticado).
 *
 * Si el heartbeat devuelve 401 → la sesión ya murió. Mostramos overlay duro
 * con CTA al login y query string ?next=<url-actual>, así no se pierde el lugar.
 *
 * No tocar formularios en curso. No hacer auto-extend silencioso (el usuario
 * tiene que confirmar que sigue ahí, sino para qué expirar).
 */
(function () {
    'use strict';

    const HEARTBEAT_URL = '/api/internal/session/heartbeat';
    const POLL_INTERVAL_MS = 60 * 1000; // 1 min
    const WARN_SECONDS = 15 * 60;       // avisar cuando faltan 15 min idle
    const CRITICAL_SECONDS = 2 * 60;    // pintar rojo cuando faltan 2 min

    let warned = false;
    let banner = null;
    let countdownTimer = null;
    let lastRemainingIdle = null;
    let lastChecked = 0;

    function ensureBanner() {
        if (banner) return banner;
        banner = document.createElement('div');
        banner.id = 'rxn-session-keeper-banner';
        banner.setAttribute('role', 'alertdialog');
        banner.style.cssText = [
            'position:fixed', 'bottom:20px', 'right:20px', 'z-index:11000',
            'min-width:280px', 'max-width:380px',
            'background:#fff8e1', 'border:1px solid #f0c43a',
            'color:#5b4a00', 'border-radius:10px',
            'box-shadow:0 8px 24px rgba(0,0,0,.18)',
            'padding:14px 16px', 'font-size:.92rem', 'line-height:1.35'
        ].join(';');
        banner.innerHTML = `
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                <i class="bi bi-clock-history" style="font-size:1.25rem"></i>
                <strong>Tu sesión está por vencer</strong>
            </div>
            <div data-rxn-session-message style="margin-bottom:10px"></div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-rxn-session-dismiss>Después</button>
                <button type="button" class="btn btn-sm btn-warning" data-rxn-session-extend>Extender ahora</button>
            </div>
        `;
        banner.querySelector('[data-rxn-session-dismiss]').addEventListener('click', () => {
            removeBanner();
        });
        banner.querySelector('[data-rxn-session-extend]').addEventListener('click', () => {
            // Cualquier hit autenticado al server renueva last_activity en App.php.
            checkHeartbeat({ reason: 'extend' }).then(() => {
                removeBanner();
            });
        });
        document.body.appendChild(banner);
        return banner;
    }

    function removeBanner() {
        if (banner && banner.parentNode) banner.parentNode.removeChild(banner);
        banner = null;
        warned = false;
        if (countdownTimer) {
            clearInterval(countdownTimer);
            countdownTimer = null;
        }
    }

    function formatRemaining(seconds) {
        const s = Math.max(0, Math.floor(seconds));
        const m = Math.floor(s / 60);
        const r = s % 60;
        if (m === 0) return `${r}s`;
        return `${m} min ${r}s`;
    }

    function paintBanner(remainingIdle) {
        ensureBanner();
        const msg = banner.querySelector('[data-rxn-session-message]');
        msg.textContent = `Vence en ${formatRemaining(remainingIdle)}. Tocá "Extender ahora" para mantenerla viva.`;
        if (remainingIdle <= CRITICAL_SECONDS) {
            banner.style.background = '#ffe1e1';
            banner.style.borderColor = '#e07a7a';
            banner.style.color = '#5b0000';
        }
    }

    function startCountdown() {
        if (countdownTimer) return;
        countdownTimer = setInterval(() => {
            if (lastRemainingIdle === null) return;
            const elapsed = Math.floor((Date.now() - lastChecked) / 1000);
            const current = lastRemainingIdle - elapsed;
            if (current <= 0) {
                redirectToLogin();
                return;
            }
            if (banner) paintBanner(current);
        }, 1000);
    }

    function buildLoginUrl() {
        const next = window.location.pathname + window.location.search;
        return '/login?next=' + encodeURIComponent(next);
    }

    function redirectToLogin() {
        // Evitar bucle si ya estamos en /login.
        if (window.location.pathname === '/login') return;
        window.location.href = buildLoginUrl();
    }

    function checkHeartbeat(opts) {
        opts = opts || {};
        return fetch(HEARTBEAT_URL, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
        }).then((res) => {
            if (res.status === 401) {
                redirectToLogin();
                return null;
            }
            if (!res.ok) return null;
            return res.json();
        }).then((data) => {
            if (!data || !data.ok) return null;
            lastRemainingIdle = data.remaining_idle;
            lastChecked = Date.now();

            if (lastRemainingIdle <= WARN_SECONDS) {
                if (!warned) {
                    warned = true;
                    paintBanner(lastRemainingIdle);
                    startCountdown();
                } else if (banner) {
                    paintBanner(lastRemainingIdle);
                }
            } else if (warned && opts.reason === 'extend') {
                // Extender exitoso — sesión renovada, sacamos el banner.
                removeBanner();
            }
            return data;
        }).catch(() => null);
    }

    // Solo activar si hay sesión backoffice (presencia de meta csrf-token).
    function start() {
        if (!document.querySelector('meta[name="csrf-token"]')) return;
        // Primer check al toque.
        checkHeartbeat();
        // Polling.
        setInterval(checkHeartbeat, POLL_INTERVAL_MS);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
