// RXN — helper global de pantalla completa con persistencia.
//
// Pedido del rey (release 1.42.0): un mismo toggle de fullscreen tiene que
// recordarse al ir navegando entre módulos del backoffice y entre pantallas
// de la PWA. Si lo activé en el dashboard CRM, al entrar a Presupuestos ya
// tiene que estar activo. Idem en la PWA: si lo activé en el shell, al
// abrir un form del presupuesto sigue activo.
//
// Estrategia:
//   - Estado persistido en localStorage bajo la clave RXN_FS_PREF_KEY.
//     Valores: 'on' / 'off' (default 'off').
//   - Al cargar la página, si la pref es 'on', aplicamos el modo automático.
//   - "Modo automático" prioriza la Fullscreen API real cuando es invocable
//     desde gesto del usuario; cuando viene del bootstrap silencioso (sin
//     gesto), cae al modo "faux" que oculta el header sticky con CSS. Esto
//     se debe a que los browsers exigen un user gesture para entrar en FS.
//   - Cualquier botón con `data-rxn-fullscreen-toggle` actúa como switch:
//     el click toggle la pref + intenta aplicar la API real. Eventos
//     fullscreenchange refrescan los íconos de TODOS los botones bound.

(function (global) {
    'use strict';

    const STORAGE_KEY = 'rxn_fullscreen_pref';
    const BODY_FAUX_CLASS = 'rxn-faux-fullscreen';

    function getPref() {
        try {
            return localStorage.getItem(STORAGE_KEY) === 'on' ? 'on' : 'off';
        } catch (_) {
            return 'off';
        }
    }

    function setPref(value) {
        try { localStorage.setItem(STORAGE_KEY, value === 'on' ? 'on' : 'off'); }
        catch (_) { /* silent */ }
    }

    function isApiFullscreen() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement);
    }

    function supportsApi() {
        const el = document.documentElement;
        return !!(el.requestFullscreen || el.webkitRequestFullscreen);
    }

    function isActive() {
        return isApiFullscreen() || document.body.classList.contains(BODY_FAUX_CLASS);
    }

    function enableFaux() {
        document.body.classList.add(BODY_FAUX_CLASS);
        refreshButtons();
    }

    function disableFaux() {
        document.body.classList.remove(BODY_FAUX_CLASS);
        refreshButtons();
    }

    function tryEnterApi() {
        if (!supportsApi()) return Promise.reject(new Error('Fullscreen API no soportada.'));
        const el = document.documentElement;
        const req = el.requestFullscreen || el.webkitRequestFullscreen;
        try {
            return req.call(el);
        } catch (e) {
            return Promise.reject(e);
        }
    }

    function exitApi() {
        if (!isApiFullscreen()) return Promise.resolve();
        const exit = document.exitFullscreen || document.webkitExitFullscreen;
        try { return exit.call(document); }
        catch (_) { return Promise.resolve(); }
    }

    /**
     * Toggle invocado por el usuario via click. Es el único path donde podemos
     * llamar la API real (necesita user gesture). Si la API falla (browser
     * niega, iOS Safari), caemos a faux-fullscreen.
     *
     * Tres caminos según el estado actual:
     *   (a) API real activa  → SALIR (apaga pref, exit API).
     *   (b) faux activo, API NO → UPGRADEAR a API real (este es el caso típico
     *       cuando navegamos entre páginas con pref='on': el helper aplicó faux
     *       silencioso al cargar y ahora el click puede entrar a la API real
     *       que sí esconde la URL bar). Mantenemos pref='on' porque sigue
     *       activo, solo cambia el "modo".
     *   (c) ninguno activo  → ENTRAR a la API real con fallback a faux.
     */
    async function toggle() {
        // (a) API real activa → salida total.
        if (isApiFullscreen()) {
            setPref('off');
            disableFaux();
            try { await exitApi(); } catch (_) { /* silent */ }
            refreshButtons();
            return false;
        }

        // (b) faux activo y la API SÍ está soportada → upgrade a API real.
        if (document.body.classList.contains(BODY_FAUX_CLASS) && supportsApi()) {
            try {
                await tryEnterApi();
                // Mantener faux como respaldo si el browser bloqueó la API real
                // por silente: si no estamos en API real, dejamos faux para no
                // perder el estado visual.
                if (isApiFullscreen()) {
                    disableFaux();
                }
                refreshButtons();
                return true;
            } catch (_) {
                // Si falla el upgrade, salimos del faux como segundo click.
                setPref('off');
                disableFaux();
                refreshButtons();
                return false;
            }
        }

        // (c) ninguno activo → entrar.
        setPref('on');
        if (supportsApi()) {
            try {
                await tryEnterApi();
                refreshButtons();
                return true;
            } catch (_) {
                // Fallback faux si la API falla (ej iOS).
                enableFaux();
                return true;
            }
        }
        enableFaux();
        return true;
    }

    /**
     * Aplicar la pref guardada al cargar una nueva página. Sin user gesture la
     * Fullscreen API NO se puede invocar — usamos siempre el modo faux para
     * persistir el estado visual entre páginas.
     */
    function applyPersistedPref() {
        if (getPref() === 'on') {
            enableFaux();
        }
    }

    function refreshButtons() {
        const active = isActive();
        document.querySelectorAll('[data-rxn-fullscreen-toggle]').forEach((btn) => {
            const enterIcon = btn.getAttribute('data-rxn-fs-enter-icon') || 'bi-fullscreen';
            const exitIcon = btn.getAttribute('data-rxn-fs-exit-icon') || 'bi-fullscreen-exit';
            const enterTitle = btn.getAttribute('data-rxn-fs-enter-title') || 'Pantalla completa';
            const exitTitle = btn.getAttribute('data-rxn-fs-exit-title') || 'Salir de pantalla completa';
            // Buscar el <i> interno; si no hay, reemplazar el contenido entero.
            const icon = btn.querySelector('i.bi');
            if (icon) {
                icon.classList.remove('bi-fullscreen', 'bi-fullscreen-exit');
                icon.classList.add(active ? exitIcon : enterIcon);
            } else {
                btn.innerHTML = `<i class="bi ${active ? exitIcon : enterIcon}"></i>`;
            }
            btn.title = active ? exitTitle : enterTitle;
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function bindClickHandlers() {
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-rxn-fullscreen-toggle]');
            if (!btn) return;
            e.preventDefault();
            toggle();
        });
        document.addEventListener('fullscreenchange', refreshButtons);
        document.addEventListener('webkitfullscreenchange', refreshButtons);
    }

    /**
     * Inyecta un FAB (floating action button) fixed que SIEMPRE está disponible
     * mientras el faux está activo. Razón: cuando el faux oculta la topbar del
     * backoffice (o el header de la PWA), el botón fullscreen embebido en esa
     * topbar también desaparece — el operador queda sin forma de salir o
     * upgradear a API real. El FAB resuelve eso: vive en <body>, fuera de los
     * headers ocultables, y siempre se ve.
     */
    function ensureFab() {
        if (document.getElementById('rxn-fullscreen-fab')) return;
        const fab = document.createElement('button');
        fab.type = 'button';
        fab.id = 'rxn-fullscreen-fab';
        fab.className = 'rxn-fullscreen-fab';
        fab.setAttribute('data-rxn-fullscreen-toggle', '');
        fab.setAttribute('aria-label', 'Pantalla completa');
        fab.innerHTML = '<i class="bi bi-fullscreen-exit"></i>';
        document.body.appendChild(fab);
    }

    function init() {
        applyPersistedPref();
        ensureFab();
        bindClickHandlers();
        refreshButtons();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    global.RxnFullscreen = {
        toggle,
        isActive,
        getPref,
        setPref,
    };
})(window);
