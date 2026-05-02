// RXN PWA — Drafts store de Horas (turnero CrmHoras) + attachments.
//
// Reusa la DB "rxnpwa" (v3+) abierta por RxnPwaCatalogStore.
//
// Modelo:
//   horas_drafts (keyPath: tmp_uuid)
//     {
//       tmp_uuid, empresa_id, created_at, updated_at,
//       cabecera: {
//         fecha_inicio, fecha_finalizado, concepto,
//         tratativa_id, tratativa_data,
//         descuento_segundos, motivo_descuento
//       },
//       status: 'draft' | 'pending_sync' | 'syncing' | 'synced' | 'error',
//       sync_error: string|null,
//       server_id: number|null,
//       retry_count, last_error, next_retry_at,
//       geo: {lat, lng, accuracy, source, captured_at} | null
//     }
//
//   horas_attachments (keyPath: id, autoIncrement, index by_tmp_uuid)
//     { id, tmp_uuid, name, mime, size, blob, compressed, created_at,
//       sync_status: 'pending' | 'uploaded' | 'failed', server_attachment_id }

(function (global) {
    'use strict';

    if (!global.RxnPwaCatalogStore) {
        console.error('[rxnpwa-horas-drafts] RxnPwaCatalogStore no está cargado.');
        return;
    }

    const { openDB, HORAS_DRAFTS_STORE, HORAS_ATTACHMENTS_STORE } = global.RxnPwaCatalogStore;

    function generateUuid() {
        if (global.crypto && typeof global.crypto.randomUUID === 'function') {
            return 'TMP-' + global.crypto.randomUUID();
        }
        return 'TMP-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
    }

    function nowIso() { return new Date().toISOString(); }

    /* ---------- Drafts ---------- */

    async function createDraft({ empresaId, cabeceraOverrides = {} }) {
        const db = await openDB();
        const draft = {
            tmp_uuid: generateUuid(),
            empresa_id: empresaId,
            created_at: nowIso(),
            updated_at: nowIso(),
            cabecera: Object.assign({
                fecha_inicio: '',
                fecha_finalizado: '',
                concepto: '',
                tratativa_id: null,
                tratativa_data: null,
                descuento_segundos: 0,
                motivo_descuento: '',
            }, cabeceraOverrides || {}),
            status: 'draft',
            sync_error: null,
            server_id: null,
            retry_count: 0,
            last_error: null,
            next_retry_at: null,
            geo: null,
        };
        return new Promise((resolve, reject) => {
            const t = db.transaction([HORAS_DRAFTS_STORE], 'readwrite');
            t.oncomplete = () => resolve(draft);
            t.onerror = () => reject(t.error);
            t.objectStore(HORAS_DRAFTS_STORE).add(draft);
        });
    }

    async function getDraft(tmpUuid) {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const t = db.transaction([HORAS_DRAFTS_STORE], 'readonly');
            const req = t.objectStore(HORAS_DRAFTS_STORE).get(tmpUuid);
            req.onsuccess = () => resolve(req.result || null);
            req.onerror = () => reject(req.error);
        });
    }

    async function saveDraft(draft) {
        if (!draft || !draft.tmp_uuid) throw new Error('Draft inválido: falta tmp_uuid.');
        draft.updated_at = nowIso();
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const t = db.transaction([HORAS_DRAFTS_STORE], 'readwrite');
            t.oncomplete = () => resolve(draft);
            t.onerror = () => reject(t.error);
            t.objectStore(HORAS_DRAFTS_STORE).put(draft);
        });
    }

    async function listDrafts() {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const t = db.transaction([HORAS_DRAFTS_STORE], 'readonly');
            const req = t.objectStore(HORAS_DRAFTS_STORE).getAll();
            req.onsuccess = () => {
                const rows = req.result || [];
                rows.sort((a, b) => (b.updated_at || '').localeCompare(a.updated_at || ''));
                resolve(rows);
            };
            req.onerror = () => reject(req.error);
        });
    }

    /**
     * Devuelve el draft "abierto" (cronómetro corriendo): fecha_inicio !== '' y fecha_finalizado === ''.
     */
    async function findOpenDraft() {
        const all = await listDrafts();
        return all.find(d => d.cabecera && d.cabecera.fecha_inicio && !d.cabecera.fecha_finalizado) || null;
    }

    async function deleteDraft(tmpUuid) {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const t = db.transaction([HORAS_DRAFTS_STORE, HORAS_ATTACHMENTS_STORE], 'readwrite');
            t.oncomplete = () => resolve();
            t.onerror = () => reject(t.error);

            t.objectStore(HORAS_DRAFTS_STORE).delete(tmpUuid);

            const attStore = t.objectStore(HORAS_ATTACHMENTS_STORE);
            const idx = attStore.index('by_tmp_uuid');
            const cursorReq = idx.openCursor(IDBKeyRange.only(tmpUuid));
            cursorReq.onsuccess = (e) => {
                const cursor = e.target.result;
                if (cursor) { cursor.delete(); cursor.continue(); }
            };
        });
    }

    /* ---------- Attachments ---------- */

    async function addAttachment({ tmpUuid, name, mime, blob, compressed }) {
        const db = await openDB();
        const row = {
            tmp_uuid: tmpUuid,
            name: String(name || 'archivo'),
            mime: String(mime || 'application/octet-stream'),
            size: blob.size,
            blob,
            compressed: !!compressed,
            created_at: nowIso(),
            sync_status: 'pending',
            server_attachment_id: null,
        };
        return new Promise((resolve, reject) => {
            const t = db.transaction([HORAS_ATTACHMENTS_STORE], 'readwrite');
            const req = t.objectStore(HORAS_ATTACHMENTS_STORE).add(row);
            req.onsuccess = () => { row.id = req.result; resolve(row); };
            req.onerror = () => reject(req.error);
        });
    }

    async function listAttachments(tmpUuid) {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const t = db.transaction([HORAS_ATTACHMENTS_STORE], 'readonly');
            const idx = t.objectStore(HORAS_ATTACHMENTS_STORE).index('by_tmp_uuid');
            const req = idx.getAll(IDBKeyRange.only(tmpUuid));
            req.onsuccess = () => {
                const rows = req.result || [];
                rows.sort((a, b) => (a.created_at || '').localeCompare(b.created_at || ''));
                resolve(rows);
            };
            req.onerror = () => reject(req.error);
        });
    }

    async function removeAttachment(id) {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const t = db.transaction([HORAS_ATTACHMENTS_STORE], 'readwrite');
            t.oncomplete = () => resolve();
            t.onerror = () => reject(t.error);
            t.objectStore(HORAS_ATTACHMENTS_STORE).delete(id);
        });
    }

    async function countAttachments(tmpUuid) {
        const list = await listAttachments(tmpUuid);
        return list.length;
    }

    async function putAttachment(att) {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const t = db.transaction([HORAS_ATTACHMENTS_STORE], 'readwrite');
            t.oncomplete = () => resolve();
            t.onerror = () => reject(t.error);
            t.objectStore(HORAS_ATTACHMENTS_STORE).put(att);
        });
    }

    /* ---------- GC ---------- */

    async function garbageCollectSynced(daysOld = 7) {
        const cutoffMs = Date.now() - daysOld * 24 * 3600 * 1000;
        const all = await listDrafts();
        const stale = all.filter((d) => {
            if (d.status !== 'synced') return false;
            const ts = Date.parse(d.updated_at || '');
            return Number.isFinite(ts) && ts < cutoffMs;
        });
        for (const d of stale) await deleteDraft(d.tmp_uuid);
        return { deleted: stale.length, retained: all.length - stale.length };
    }

    async function purgeAllSynced() {
        const all = await listDrafts();
        const stale = all.filter((d) => d.status === 'synced');
        for (const d of stale) await deleteDraft(d.tmp_uuid);
        return { deleted: stale.length, retained: all.length - stale.length };
    }

    global.RxnPwaHorasDraftsStore = {
        createDraft, getDraft, saveDraft, listDrafts, findOpenDraft, deleteDraft,
        addAttachment, listAttachments, removeAttachment, countAttachments, putAttachment,
        garbageCollectSynced, purgeAllSynced,
        generateUuid,
    };
})(window);
