// RXN PWA — Wire-up de la sección "Enviar al servidor" del form mobile.
//
// Este script vive en módulo separado de rxnpwa-form.js para no inflar el
// monolito de 1800 líneas. Toda la lógica de offline form vive ahí; este sólo
// se encarga de:
//   - El botón "Sincronizar al servidor".
//   - El botón "Enviar a Tango" (gateado por estado + red).
//   - El indicador de estado del draft + el badge de red.
//   - Reactividad ante eventos de RxnPwaSyncQueue.

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', init);

    let tmpUuid = '';
    let inFlight = false;

    function init() {
        const main = document.querySelector('main.rxnpwa-shell');
        if (!main) return;

        const btnSync = document.getElementById('rxnpwa-form-sync');
        const btnTango = document.getElementById('rxnpwa-form-emit-tango');
        if (!btnSync || !btnTango) return;

        btnSync.addEventListener('click', onSyncClick);
        btnTango.addEventListener('click', onEmitTangoClick);

        // Re-render al cambiar la queue (otro tab, sync runner, etc).
        if (window.RxnPwaSyncQueue) {
            window.RxnPwaSyncQueue.subscribe((ev) => {
                if (ev && ev.tmpUuid && tmpUuid && ev.tmpUuid !== tmpUuid) return;
                refreshUI();
            });
        }
        window.addEventListener('online', refreshUI);
        window.addEventListener('offline', refreshUI);

        // Polling suave: el form puede demorar en escribir el tmp_uuid (lo escribe
        // rxnpwa-form.js al primer save). Reintentamos 2-3 veces si todavía está vacío.
        let attempts = 0;
        const tick = () => {
            tmpUuid = main.getAttribute('data-tmp-uuid') || '';
            refreshUI();
            attempts++;
            if (!tmpUuid && attempts < 20) setTimeout(tick, 500);
        };
        tick();
    }

    async function refreshUI() {
        const main = document.querySelector('main.rxnpwa-shell');
        if (!main) return;
        tmpUuid = main.getAttribute('data-tmp-uuid') || '';

        renderNetBadge();
        await renderState();
    }

    function renderNetBadge() {
        const el = document.getElementById('rxnpwa-form-net-badge');
        if (!el) return;
        if (navigator.onLine) {
            el.className = 'badge bg-success small';
            el.innerHTML = '<i class="bi bi-wifi"></i> Online';
        } else {
            el.className = 'badge bg-secondary small';
            el.innerHTML = '<i class="bi bi-wifi-off"></i> Offline';
        }
    }

    async function renderState() {
        const stateEl = document.getElementById('rxnpwa-form-sync-state');
        const btnSync = document.getElementById('rxnpwa-form-sync');
        const btnTango = document.getElementById('rxnpwa-form-emit-tango');
        if (!stateEl || !btnSync || !btnTango) return;

        // Sin draft persistido todavía (form recién abierto, sin tocar nada).
        if (!tmpUuid) {
            stateEl.innerHTML = `<i class="bi bi-info-circle"></i> Guardá el borrador primero para poder sincronizar.`;
            btnSync.disabled = true;
            btnTango.disabled = true;
            return;
        }

        const draft = await window.RxnPwaDraftsStore.getDraft(tmpUuid);
        if (!draft) {
            stateEl.innerHTML = `<i class="bi bi-info-circle"></i> Borrador no persistido todavía.`;
            btnSync.disabled = true;
            btnTango.disabled = true;
            return;
        }

        const status = draft.status || 'draft';
        const online = navigator.onLine;

        switch (status) {
            case 'draft':
                stateEl.innerHTML = `<i class="bi bi-pencil-square"></i> Borrador local — sin sincronizar.`;
                btnSync.disabled = !online || inFlight;
                btnSync.innerHTML = `<i class="bi bi-cloud-upload"></i> Sincronizar al servidor`;
                btnTango.disabled = true;
                btnTango.title = 'Sincronizá primero antes de enviar a Tango.';
                break;
            case 'pending_sync':
                stateEl.innerHTML = `<i class="bi bi-hourglass-split text-warning"></i> En cola para enviarse al servidor.`;
                btnSync.disabled = !online || inFlight;
                btnSync.innerHTML = `<i class="bi bi-arrow-clockwise"></i> Forzar sincronización`;
                btnTango.disabled = true;
                break;
            case 'syncing':
                stateEl.innerHTML = `<i class="bi bi-cloud-arrow-up text-info"></i> Sincronizando...`;
                btnSync.disabled = true;
                btnSync.innerHTML = `<i class="bi bi-arrow-repeat"></i> Sincronizando…`;
                btnTango.disabled = true;
                break;
            case 'synced':
                stateEl.innerHTML = `<i class="bi bi-check-circle text-success"></i> Sincronizado al servidor${draft.numero_server ? ` — N° interno ${draft.numero_server}` : ''}.`;
                btnSync.disabled = true;
                btnSync.innerHTML = `<i class="bi bi-check-circle"></i> Ya sincronizado`;
                btnTango.disabled = !online || inFlight;
                btnTango.title = online ? 'Listo para enviar a Tango.' : 'Necesitás conexión para enviar a Tango.';
                break;
            case 'emitted':
                stateEl.innerHTML = `<i class="bi bi-send-check text-primary"></i> Enviado a Tango.${draft.tango_message ? ' ' + escapeHtml(draft.tango_message) : ''}`;
                btnSync.disabled = true;
                btnTango.disabled = true;
                btnTango.innerHTML = `<i class="bi bi-check-circle"></i> Ya emitido`;
                break;
            case 'error':
                stateEl.innerHTML = `<i class="bi bi-exclamation-triangle text-danger"></i> Error: ${escapeHtml(draft.last_error || 'desconocido')}`;
                btnSync.disabled = !online || inFlight;
                btnSync.innerHTML = `<i class="bi bi-arrow-clockwise"></i> Reintentar`;
                btnTango.disabled = true;
                break;
            default:
                stateEl.textContent = 'Estado desconocido: ' + status;
        }
    }

    async function onSyncClick(ev) {
        ev.preventDefault();
        if (!tmpUuid) return;
        if (!navigator.onLine) {
            showMessage('Necesitás conexión para sincronizar.', 'warning');
            return;
        }
        // Antes de encolar:
        //  1) Capturar geolocalización si no está (prompt nativo del browser, una sola vez).
        //  2) Forzar autosave del draft actual.
        try {
            if (window.RxnPwaForm && typeof window.RxnPwaForm.captureGeo === 'function') {
                await window.RxnPwaForm.captureGeo();
            }
        } catch (e) { /* silent — geo es best-effort */ }
        try {
            if (window.RxnPwaForm && typeof window.RxnPwaForm.flushSave === 'function') {
                await window.RxnPwaForm.flushSave();
            }
        } catch (e) { /* silent */ }

        inFlight = true;
        await refreshUI();
        try {
            await window.RxnPwaSyncQueue.enqueue(tmpUuid);
            showMessage('Encolado. Sincronizando…', 'info');
        } catch (err) {
            showMessage('Error: ' + err.message, 'danger');
        } finally {
            inFlight = false;
            await refreshUI();
        }
    }

    async function onEmitTangoClick(ev) {
        ev.preventDefault();
        if (!tmpUuid) return;
        if (!navigator.onLine) {
            showMessage('Necesitás conexión para enviar a Tango.', 'warning');
            return;
        }
        if (!confirm('¿Enviar este presupuesto a Tango ahora?\n\nEsto crea el comprobante en el ERP. La acción no se puede deshacer desde la PWA. Si tu GPS no estaba activo, el navegador te va a pedir permiso para capturarlo.')) {
            return;
        }
        inFlight = true;
        await refreshUI();
        try {
            // El gate de GPS lo hace RxnPwaSyncQueue.emitToTango — si la geo no es
            // válida, captura, persiste, y si sigue inválida tira excepción. Acá
            // sólo mostramos el mensaje al usuario.
            const data = await window.RxnPwaSyncQueue.emitToTango(tmpUuid);
            showMessage(data && data.message ? data.message : 'Enviado a Tango.', 'success');
        } catch (err) {
            showMessage('Tango: ' + err.message, 'danger');
        } finally {
            inFlight = false;
            await refreshUI();
        }
    }

    function showMessage(msg, level) {
        const el = document.getElementById('rxnpwa-form-sync-message');
        if (!el) return;
        const cls = level === 'danger' ? 'text-danger' : level === 'warning' ? 'text-warning' : level === 'success' ? 'text-success' : 'text-info';
        el.className = 'small mt-2 ' + cls;
        el.textContent = msg;
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
