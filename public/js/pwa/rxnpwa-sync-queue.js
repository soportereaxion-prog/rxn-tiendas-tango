// RXN PWA — Sync queue + reconciliación al server (Iteración 42 / Fase 3 — Bloque C).
//
// Modelo de estados de un draft:
//   draft         — recién creado, editado offline. NO se sincroniza solo.
//   pending_sync  — el usuario apretó "Sincronizar" y la cola lo va a tomar.
//   syncing       — la cola está actualmente procesándolo.
//   synced        — header + items + adjuntos subidos al server.
//                   `server_id` ya tiene el id real del presupuesto en crm_presupuestos.
//   emitted       — además del sync, se pegó al endpoint de Tango y volvió OK.
//   error         — agotó los reintentos. Necesita acción manual del usuario.
//
// Patrón 2-step:
//   1. POST /api/rxnpwa/presupuestos/sync   → recibe { id_server, numero }.
//      Idempotente por tmp_uuid_pwa: si reintenta el mismo draft, devuelve el id existente.
//   2. POST /api/rxnpwa/presupuestos/{id}/attachments × N → uno por archivo.
//
// Backoff: 1s, 2s, 4s, 8s, 16s. Tope 5 reintentos automáticos. Después → status='error'
// permanente hasta que el usuario manualmente reintente.

(function (global) {
    'use strict';

    if (!global.RxnPwaDraftsStore) {
        console.error('[rxnpwa-sync-queue] RxnPwaDraftsStore no está cargado.');
        return;
    }

    const ENDPOINT_SYNC = '/api/rxnpwa/presupuestos/sync';
    const ENDPOINT_ATTACHMENT = (id) => `/api/rxnpwa/presupuestos/${id}/attachments`;
    const ENDPOINT_EMIT_TANGO = (id) => `/api/rxnpwa/presupuestos/${id}/emit-tango`;

    const MAX_RETRIES = 5;
    const BACKOFF_MS = [1000, 2000, 4000, 8000, 16000];

    const listeners = new Set();
    let isProcessing = false;

    /* ---------- Eventos ---------- */

    function subscribe(fn) {
        listeners.add(fn);
        return () => listeners.delete(fn);
    }

    function emit(eventType, payload) {
        for (const fn of listeners) {
            try { fn({ type: eventType, ...payload }); } catch (e) { /* swallow */ }
        }
    }

    /* ---------- Encolar ---------- */

    async function enqueue(tmpUuid) {
        const draft = await global.RxnPwaDraftsStore.getDraft(tmpUuid);
        if (!draft) throw new Error('Draft no encontrado: ' + tmpUuid);
        if (draft.status === 'syncing' || draft.status === 'synced' || draft.status === 'emitted') {
            return draft;
        }
        draft.status = 'pending_sync';
        draft.retry_count = 0;
        draft.last_error = null;
        await global.RxnPwaDraftsStore.saveDraft(draft);
        emit('enqueued', { tmpUuid });
        // Disparo inmediato si hay red.
        kick();
        return draft;
    }

    /**
     * Ejecuta la cola si hay drafts pendientes y red. Idempotente — múltiples
     * llamadas concurrentes solo arrancan UNA pasada.
     */
    async function kick() {
        if (isProcessing) return;
        if (!navigator.onLine) {
            emit('skipped_offline', {});
            return;
        }
        isProcessing = true;
        try {
            await processQueue();
        } finally {
            isProcessing = false;
        }
    }

    async function processQueue() {
        const all = await global.RxnPwaDraftsStore.listDrafts();
        const pendientes = all.filter(d => isProcessable(d));
        if (pendientes.length === 0) return;

        emit('queue_start', { count: pendientes.length });

        for (const draft of pendientes) {
            if (!navigator.onLine) {
                emit('queue_paused_offline', {});
                break;
            }
            await processDraft(draft);
        }

        emit('queue_end', {});
    }

    function isProcessable(draft) {
        if (draft.status === 'pending_sync') return true;
        if (draft.status === 'error' && (draft.retry_count || 0) < MAX_RETRIES) return true;
        // 'syncing' lo omitimos — si quedó colgado de un reload, el usuario lo reintenta manualmente.
        return false;
    }

    async function processDraft(draft) {
        const tmpUuid = draft.tmp_uuid;
        emit('draft_start', { tmpUuid });

        try {
            // Marcar syncing
            draft.status = 'syncing';
            await global.RxnPwaDraftsStore.saveDraft(draft);

            // Step 1 — header + items.
            let serverId = draft.server_id || null;
            if (!serverId) {
                const result = await postJson(ENDPOINT_SYNC, draftToWire(draft));
                if (!result || !result.ok || !result.id_server) {
                    throw new Error(result && result.error ? result.error : 'El server no devolvió id.');
                }
                serverId = Number(result.id_server) | 0;
                draft.server_id = serverId;
                draft.numero_server = result.numero || null;
                await global.RxnPwaDraftsStore.saveDraft(draft);
            }

            // Step 2 — attachments pendientes.
            const attachments = await global.RxnPwaDraftsStore.listAttachments(tmpUuid);
            for (const att of attachments) {
                if (att.sync_status === 'uploaded') continue;
                if (!navigator.onLine) throw new Error('Se perdió la conexión durante el upload.');
                const uploaded = await uploadAttachment(serverId, att);
                att.sync_status = 'uploaded';
                att.server_attachment_id = uploaded.id;
                await replaceAttachment(att);
                emit('attachment_uploaded', { tmpUuid, attId: att.id });
            }

            // Done.
            draft.status = 'synced';
            draft.last_error = null;
            draft.retry_count = 0;
            await global.RxnPwaDraftsStore.saveDraft(draft);
            emit('draft_synced', { tmpUuid, serverId });
        } catch (err) {
            console.error('[rxnpwa-sync-queue] error draft ' + tmpUuid + ':', err);
            const retryCount = (draft.retry_count || 0) + 1;
            draft.retry_count = retryCount;
            draft.last_error = String(err && err.message ? err.message : err);

            if (retryCount >= MAX_RETRIES) {
                draft.status = 'error';
                emit('draft_failed_permanent', { tmpUuid, error: draft.last_error });
            } else {
                // Volver a pending_sync, con backoff. El "next_retry_at" es informativo;
                // la próxima pasada de la cola lo va a tomar igual.
                draft.status = 'pending_sync';
                const delay = BACKOFF_MS[Math.min(retryCount - 1, BACKOFF_MS.length - 1)];
                draft.next_retry_at = new Date(Date.now() + delay).toISOString();
                emit('draft_retry_scheduled', { tmpUuid, retryCount, delayMs: delay });

                // Auto-retry: schedule un nuevo kick después del backoff.
                setTimeout(kick, delay);
            }
            await global.RxnPwaDraftsStore.saveDraft(draft);
        }
    }

    /* ---------- Wire format ---------- */

    function draftToWire(draft) {
        return {
            tmp_uuid: draft.tmp_uuid,
            empresa_id: draft.empresa_id,
            created_at: draft.created_at,
            updated_at: draft.updated_at,
            cabecera: draft.cabecera || {},
            renglones: (draft.renglones || []).map(r => ({
                row_uuid: r.row_uuid || null,
                articulo_id: r.articulo_id || null,
                codigo: r.codigo || r.articulo_codigo || '',
                descripcion: r.descripcion || r.articulo_descripcion || '',
                cantidad: Number(r.cantidad || 0),
                precio_unitario: Number(r.precio_unitario || 0),
                descuento_pct: Number(r.descuento_pct || 0),
                subtotal: Number(r.subtotal || 0),
            })),
            // Geo capturada en el celu al guardar el draft (Iteración 43 — release 1.37.0).
            // Se persiste en RxnGeoTracking server-side al crear el presupuesto.
            geo: {
                lat: draft.geo_lat ?? null,
                lng: draft.geo_lng ?? null,
                accuracy: draft.geo_accuracy ?? null,
                source: draft.geo_source || null,
                captured_at: draft.geo_captured_at || null,
            },
        };
    }

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') || '' : '';
    }

    async function postJson(url, body) {
        const resp = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': getCsrfToken(),
            },
            body: JSON.stringify(body),
        });
        if (resp.status === 401) {
            throw new Error('Sesión expirada. Reabrí la PWA desde el browser.');
        }
        if (resp.status === 429) {
            const data = await resp.json().catch(() => null);
            const after = (data && data.retry_after) || 60;
            throw new Error('Demasiados intentos al server (esperá ' + after + 's).');
        }
        let data;
        try { data = await resp.json(); } catch (e) { throw new Error('Respuesta inválida del server (HTTP ' + resp.status + ').'); }
        if (resp.status === 403) {
            const msg = (data && data.error) ? data.error : 'Acceso denegado.';
            throw new Error(msg + ' Recargá la PWA para refrescar el token.');
        }
        if (!resp.ok && !data) {
            throw new Error('Error HTTP ' + resp.status);
        }
        return data;
    }

    async function uploadAttachment(serverPresupuestoId, att) {
        const fd = new FormData();
        const filename = att.name || 'archivo';
        fd.append('file', att.blob, filename);
        const resp = await fetch(ENDPOINT_ATTACHMENT(serverPresupuestoId), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': getCsrfToken() },
            body: fd,
        });
        if (resp.status === 401) {
            throw new Error('Sesión expirada. Reabrí la PWA desde el browser.');
        }
        if (resp.status === 429) {
            const data = await resp.json().catch(() => null);
            const after = (data && data.retry_after) || 60;
            throw new Error('Demasiados intentos al server (esperá ' + after + 's).');
        }
        const data = await resp.json().catch(() => null);
        if (resp.status === 403) {
            const msg = data && data.error ? data.error : 'Acceso denegado.';
            throw new Error(msg + ' Recargá la PWA para refrescar el token.');
        }
        if (!resp.ok || !data || !data.ok) {
            const msg = data && data.error ? data.error : 'Error subiendo adjunto (HTTP ' + resp.status + ').';
            throw new Error(msg);
        }
        return data.attachment;
    }

    /**
     * Persiste un attachment ya existente (con id) — reusa el store que solo
     * expone add/list/remove. Hacemos el put nosotros porque necesitamos updates.
     */
    async function replaceAttachment(att) {
        const dbReq = indexedDB.open('rxnpwa');
        return new Promise((resolve, reject) => {
            dbReq.onsuccess = () => {
                const db = dbReq.result;
                const t = db.transaction(['presupuesto_attachments'], 'readwrite');
                t.oncomplete = () => resolve();
                t.onerror = () => reject(t.error);
                t.objectStore('presupuesto_attachments').put(att);
            };
            dbReq.onerror = () => reject(dbReq.error);
        });
    }

    /* ---------- Emit a Tango (manual, sólo si online) ---------- */

    async function emitToTango(tmpUuid) {
        let draft = await global.RxnPwaDraftsStore.getDraft(tmpUuid);
        if (!draft) throw new Error('Draft no encontrado.');
        if (draft.status !== 'synced') {
            throw new Error('Sincronizá primero antes de enviar a Tango.');
        }
        if (!draft.server_id) {
            throw new Error('Falta el id del server. Reintentá la sincronización.');
        }
        if (!navigator.onLine) {
            throw new Error('Necesitás conexión para enviar a Tango.');
        }

        // Gate de GPS — release 1.38.0. El presupuesto debe tener geo de fuente
        // confiable (gps/wifi). Si no hay, intentamos capturar acá. Si falla, no
        // dejamos emitir hasta que el operador active el GPS y reintente.
        // Excepción dev (release 1.39.0): si el contexto es inseguro (HTTP plano,
        // pruebas locales en LAN), aceptamos `dev_mock` como source — en prod
        // (HTTPS) el gate nunca permite asignar dev_mock, así que esto NO afloja
        // la seguridad real.
        const VALID_SOURCES = (global.RxnPwaGeoGate && global.RxnPwaGeoGate.isInsecureContext && global.RxnPwaGeoGate.isInsecureContext())
            ? ['gps', 'wifi', 'dev_mock']
            : ['gps', 'wifi'];
        if (!VALID_SOURCES.includes(draft.geo_source)) {
            await captureGeoForDraft(draft);
            // Re-leer del store por las dudas que otra pestaña tocó el draft.
            draft = await global.RxnPwaDraftsStore.getDraft(tmpUuid);
            if (!VALID_SOURCES.includes(draft.geo_source)) {
                const reason = {
                    'denied': 'denegaste el permiso de ubicación',
                    'timeout': 'el GPS no respondió a tiempo',
                    'error': 'hubo un error al leer el GPS',
                    'unsupported': 'tu navegador no soporta GPS',
                }[draft.geo_source] || 'no se pudo capturar la ubicación';
                throw new Error(
                    'GPS desactivado (' + reason + '). Activá la ubicación del celular y reintentá.'
                );
            }
        }

        emit('tango_start', { tmpUuid });
        const data = await postJson(ENDPOINT_EMIT_TANGO(draft.server_id), {});
        if (!data || !data.ok) {
            throw new Error((data && data.message) ? data.message : 'Tango rechazó el envío.');
        }
        draft.status = 'emitted';
        draft.tango_message = data.message || null;
        await global.RxnPwaDraftsStore.saveDraft(draft);
        emit('tango_done', { tmpUuid, message: data.message });
        return data;
    }

    /**
     * Pide al gate global que retry y persiste lo que tenga ahora en el draft.
     * Si el gate sigue sin geo válida, el draft queda con geo_source='denied'/timeout/etc
     * y emitToTango va a tirar excepción explicando por qué no se puede emitir.
     */
    async function captureGeoForDraft(draft) {
        if (!global.RxnPwaGeoGate) {
            draft.geo_source = 'unsupported';
            await global.RxnPwaDraftsStore.saveDraft(draft);
            return;
        }
        // Forzar retry del gate — esto vuelve a pedir GPS y, si falla, muestra el
        // overlay bloqueante. Si tiene éxito, currentGeo queda actualizado.
        await global.RxnPwaGeoGate.retry();
        const geo = global.RxnPwaGeoGate.getCurrentGeo();
        if (geo && (geo.source === 'gps' || geo.source === 'wifi' || geo.source === 'dev_mock')) {
            draft.geo_lat = geo.lat;
            draft.geo_lng = geo.lng;
            draft.geo_accuracy = geo.accuracy;
            draft.geo_source = geo.source;
            draft.geo_captured_at = geo.captured_at;
        } else {
            draft.geo_source = 'denied';
            draft.geo_captured_at = new Date().toISOString();
        }
        await global.RxnPwaDraftsStore.saveDraft(draft);
    }

    /* ---------- Auto-arranque ---------- */

    function setupAutoTriggers() {
        // 1) Al volver online, intentar drenar cola.
        window.addEventListener('online', () => {
            emit('online_detected', {});
            kick();
        });
        // 2) Al cargar (tanto shell como form), drenar lo que haya.
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => kick());
        } else {
            kick();
        }
        // 3) Background Sync via SW: si el SW dispara 'sync', postea mensaje al cliente.
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (ev) => {
                if (ev.data && ev.data.type === 'rxnpwa-sync-queue-fire') {
                    kick();
                }
            });
        }
    }

    /**
     * Pide al SW que registre un Background Sync para drenar la cola al volver red.
     * Es fallback — el Background Sync API no está disponible en iOS Safari, pero
     * el listener de `online` lo cubre igual.
     */
    async function registerBackgroundSync() {
        if (!('serviceWorker' in navigator) || !('SyncManager' in window)) return false;
        try {
            const reg = await navigator.serviceWorker.ready;
            await reg.sync.register('rxnpwa-sync-queue');
            return true;
        } catch (e) {
            return false;
        }
    }

    setupAutoTriggers();

    global.RxnPwaSyncQueue = {
        enqueue,
        kick,
        emitToTango,
        subscribe,
        registerBackgroundSync,
        // expone por si UI necesita inspeccionar
        MAX_RETRIES,
        BACKOFF_MS,
    };
})(window);
