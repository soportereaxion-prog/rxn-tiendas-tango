// RXN PWA — Sync queue de Horas (turnero CrmHoras).
//
// 2-step:
//   1. POST /api/rxnpwa/horas/sync             → recibe { id_server }.
//   2. POST /api/rxnpwa/horas/{id}/attachments × N (uno por archivo).
//
// Estados del draft:
//   draft         — recién creado offline. NO se sincroniza solo.
//   pending_sync  — usuario apretó Sincronizar.
//   syncing       — la cola está procesándolo.
//   synced        — header + adjuntos arriba en el server.
//   error         — agotó los reintentos.
//
// Backoff: 1s, 2s, 4s, 8s, 16s. Máximo 5 reintentos automáticos.

(function (global) {
    'use strict';

    if (!global.RxnPwaHorasDraftsStore) {
        console.error('[rxnpwa-horas-sync-queue] RxnPwaHorasDraftsStore no está cargado.');
        return;
    }

    const ENDPOINT_SYNC = '/api/rxnpwa/horas/sync';
    const ENDPOINT_ATTACHMENT = (id) => `/api/rxnpwa/horas/${id}/attachments`;

    const MAX_RETRIES = 5;
    const BACKOFF_MS = [1000, 2000, 4000, 8000, 16000];

    const listeners = new Set();
    let isProcessing = false;

    function subscribe(fn) { listeners.add(fn); return () => listeners.delete(fn); }
    function emit(eventType, payload) {
        for (const fn of listeners) {
            try { fn({ type: eventType, ...payload }); } catch (e) { /* swallow */ }
        }
    }

    async function enqueue(tmpUuid) {
        const draft = await global.RxnPwaHorasDraftsStore.getDraft(tmpUuid);
        if (!draft) throw new Error('Draft no encontrado: ' + tmpUuid);
        if (draft.status === 'syncing' || draft.status === 'synced') return draft;

        // El turno tiene que estar cerrado para sincronizar.
        if (!draft.cabecera || !draft.cabecera.fecha_inicio || !draft.cabecera.fecha_finalizado) {
            throw new Error('Cerrá el turno (poné fecha de fin) antes de sincronizar.');
        }

        draft.status = 'pending_sync';
        draft.retry_count = 0;
        draft.last_error = null;
        await global.RxnPwaHorasDraftsStore.saveDraft(draft);
        emit('enqueued', { tmpUuid });
        kick();
        return draft;
    }

    async function kick() {
        if (isProcessing) return;
        if (!navigator.onLine) { emit('skipped_offline', {}); return; }
        isProcessing = true;
        try {
            await processQueue();
        } finally {
            isProcessing = false;
        }
    }

    async function processQueue() {
        const all = await global.RxnPwaHorasDraftsStore.listDrafts();
        const pendientes = all.filter(isProcessable);
        if (pendientes.length === 0) return;
        emit('queue_start', { count: pendientes.length });
        for (const draft of pendientes) {
            if (!navigator.onLine) { emit('queue_paused_offline', {}); break; }
            await processDraft(draft);
        }
        emit('queue_end', {});
    }

    function isProcessable(draft) {
        if (draft.status === 'pending_sync') return true;
        if (draft.status === 'error' && (draft.retry_count || 0) < MAX_RETRIES) return true;
        return false;
    }

    async function processDraft(draft) {
        const tmpUuid = draft.tmp_uuid;
        emit('draft_start', { tmpUuid });

        try {
            draft.status = 'syncing';
            await global.RxnPwaHorasDraftsStore.saveDraft(draft);

            await captureGeoIfMissing(draft);

            // Step 1 — sync del header.
            let serverId = draft.server_id || null;
            if (!serverId) {
                const result = await postJson(ENDPOINT_SYNC, draftToWire(draft));
                if (!result || !result.ok || !result.id_server) {
                    throw new Error(result && result.error ? result.error : 'El server no devolvió id.');
                }
                serverId = Number(result.id_server) | 0;
                draft.server_id = serverId;
                await global.RxnPwaHorasDraftsStore.saveDraft(draft);
            }

            // Step 2 — attachments pendientes.
            const attachments = await global.RxnPwaHorasDraftsStore.listAttachments(tmpUuid);
            for (const att of attachments) {
                if (att.sync_status === 'uploaded') continue;
                if (!navigator.onLine) throw new Error('Se perdió la conexión durante el upload.');
                const uploaded = await uploadAttachment(serverId, att);
                att.sync_status = 'uploaded';
                att.server_attachment_id = uploaded.id;
                await global.RxnPwaHorasDraftsStore.putAttachment(att);
                emit('attachment_uploaded', { tmpUuid, attId: att.id });
            }

            draft.status = 'synced';
            draft.last_error = null;
            draft.retry_count = 0;
            await global.RxnPwaHorasDraftsStore.saveDraft(draft);
            emit('draft_synced', { tmpUuid, serverId });
        } catch (err) {
            console.error('[rxnpwa-horas-sync-queue] error draft ' + tmpUuid + ':', err);
            const retryCount = (draft.retry_count || 0) + 1;
            draft.retry_count = retryCount;
            draft.last_error = String(err && err.message ? err.message : err);

            if (retryCount >= MAX_RETRIES) {
                draft.status = 'error';
                emit('draft_failed_permanent', { tmpUuid, error: draft.last_error });
            } else {
                draft.status = 'pending_sync';
                const delay = BACKOFF_MS[Math.min(retryCount - 1, BACKOFF_MS.length - 1)];
                draft.next_retry_at = new Date(Date.now() + delay).toISOString();
                emit('draft_retry_scheduled', { tmpUuid, retryCount, delayMs: delay });
                setTimeout(kick, delay);
            }
            await global.RxnPwaHorasDraftsStore.saveDraft(draft);
        }
    }

    function draftToWire(draft) {
        return {
            tmp_uuid: draft.tmp_uuid,
            empresa_id: draft.empresa_id,
            created_at: draft.created_at,
            updated_at: draft.updated_at,
            cabecera: draft.cabecera || {},
            geo: draft.geo || null,
        };
    }

    async function captureGeoIfMissing(draft) {
        if (draft.geo && draft.geo.source && draft.geo.source !== 'error') return;
        if (!global.RxnPwaGeoGate) return;
        const geo = global.RxnPwaGeoGate.getCurrentGeo();
        if (geo && geo.source) {
            draft.geo = {
                lat: geo.lat, lng: geo.lng, accuracy: geo.accuracy,
                source: geo.source, captured_at: geo.captured_at,
            };
            await global.RxnPwaHorasDraftsStore.saveDraft(draft);
        }
    }

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') || '' : '';
    }

    async function postJson(url, body) {
        const resp = await fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json', 'Accept': 'application/json',
                'X-CSRF-Token': getCsrfToken(),
            },
            body: JSON.stringify(body),
        });
        if (resp.status === 401) throw new Error('Sesión expirada. Reabrí la PWA desde el browser.');
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
        if (!resp.ok && !data) throw new Error('Error HTTP ' + resp.status);
        return data;
    }

    async function uploadAttachment(serverId, att) {
        const fd = new FormData();
        fd.append('file', att.blob, att.name || 'archivo');
        const resp = await fetch(ENDPOINT_ATTACHMENT(serverId), {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-Token': getCsrfToken() }, body: fd,
        });
        if (resp.status === 401) throw new Error('Sesión expirada. Reabrí la PWA desde el browser.');
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

    function setupAutoTriggers() {
        window.addEventListener('online', () => { emit('online_detected', {}); kick(); });
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => kick());
        } else {
            kick();
        }
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (ev) => {
                if (ev.data && ev.data.type === 'rxnpwa-sync-queue-fire') kick();
            });
        }
    }

    setupAutoTriggers();

    global.RxnPwaHorasSyncQueue = {
        enqueue, kick, subscribe, MAX_RETRIES, BACKOFF_MS,
    };
})(window);
