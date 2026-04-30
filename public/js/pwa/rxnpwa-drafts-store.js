// RXN PWA — Drafts store (presupuestos creados offline) + attachments.
//
// Reutiliza la DB "rxnpwa" abierta por RxnPwaCatalogStore (DB version 2).
//
// Modelo:
//   presupuestos_drafts (keyPath: tmp_uuid)
//     {
//         tmp_uuid, empresa_id, created_at, updated_at,
//         cabecera: { cliente_id, cliente_data, lista_codigo, lista_data,
//                     deposito_codigo, clasificacion_codigo,
//                     comentarios, observaciones },
//         renglones: [
//             { row_uuid, articulo_id, codigo, descripcion, cantidad,
//               precio_unitario, descuento_pct, subtotal }
//         ],
//         total: number,
//         status: 'draft' | 'pending_sync' | 'syncing' | 'synced' | 'error',
//         sync_error: string|null,
//         server_id: number|null    // ID server cuando se sincroniza (Fase 3)
//     }
//
//   presupuesto_attachments (keyPath: id, autoIncrement)
//     {
//         id, tmp_uuid, name, mime, size, blob,
//         compressed: boolean,
//         created_at,
//         sync_status: 'pending' | 'uploaded' | 'failed',
//         server_attachment_id: number|null
//     }

(function (global) {
    'use strict';

    if (!global.RxnPwaCatalogStore) {
        console.error('[rxnpwa-drafts] RxnPwaCatalogStore no está cargado.');
        return;
    }

    const { openDB, DRAFTS_STORE, ATTACHMENTS_STORE } = global.RxnPwaCatalogStore;

    function generateUuid() {
        if (global.crypto && typeof global.crypto.randomUUID === 'function') {
            return 'TMP-' + global.crypto.randomUUID();
        }
        // Fallback simple: timestamp + random.
        return 'TMP-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
    }

    function nowIso() {
        return new Date().toISOString();
    }

    // ---------- Drafts ----------

    async function createDraft({ empresaId }) {
        const db = await openDB();
        const draft = {
            tmp_uuid: generateUuid(),
            empresa_id: empresaId,
            created_at: nowIso(),
            updated_at: nowIso(),
            cabecera: {
                cliente_id: null,
                cliente_data: null,
                lista_codigo: '',
                lista_data: null,
                deposito_codigo: '',
                clasificacion_codigo: '',
                comentarios: '',
                observaciones: '',
            },
            renglones: [],
            total: 0,
            status: 'draft',
            sync_error: null,
            server_id: null,
        };
        return new Promise((resolve, reject) => {
            const t = db.transaction([DRAFTS_STORE], 'readwrite');
            t.oncomplete = () => resolve(draft);
            t.onerror = () => reject(t.error);
            t.objectStore(DRAFTS_STORE).add(draft);
        });
    }

    async function getDraft(tmpUuid) {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const t = db.transaction([DRAFTS_STORE], 'readonly');
            const req = t.objectStore(DRAFTS_STORE).get(tmpUuid);
            req.onsuccess = () => resolve(req.result || null);
            req.onerror = () => reject(req.error);
        });
    }

    async function saveDraft(draft) {
        if (!draft || !draft.tmp_uuid) {
            throw new Error('Draft inválido: falta tmp_uuid.');
        }
        draft.updated_at = nowIso();
        draft.total = computeTotal(draft.renglones);
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const t = db.transaction([DRAFTS_STORE], 'readwrite');
            t.oncomplete = () => resolve(draft);
            t.onerror = () => reject(t.error);
            t.objectStore(DRAFTS_STORE).put(draft);
        });
    }

    async function listDrafts() {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const t = db.transaction([DRAFTS_STORE], 'readonly');
            const req = t.objectStore(DRAFTS_STORE).getAll();
            req.onsuccess = () => {
                const rows = req.result || [];
                rows.sort((a, b) => (b.updated_at || '').localeCompare(a.updated_at || ''));
                resolve(rows);
            };
            req.onerror = () => reject(req.error);
        });
    }

    async function deleteDraft(tmpUuid) {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const t = db.transaction([DRAFTS_STORE, ATTACHMENTS_STORE], 'readwrite');
            t.oncomplete = () => resolve();
            t.onerror = () => reject(t.error);

            t.objectStore(DRAFTS_STORE).delete(tmpUuid);

            // Borrar también los attachments asociados.
            const attStore = t.objectStore(ATTACHMENTS_STORE);
            const idx = attStore.index('by_tmp_uuid');
            const cursorReq = idx.openCursor(IDBKeyRange.only(tmpUuid));
            cursorReq.onsuccess = (e) => {
                const cursor = e.target.result;
                if (cursor) {
                    cursor.delete();
                    cursor.continue();
                }
            };
        });
    }

    function computeTotal(renglones) {
        if (!Array.isArray(renglones)) return 0;
        return renglones.reduce((acc, r) => acc + (Number(r.subtotal) || 0), 0);
    }

    // ---------- Attachments ----------

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
            const t = db.transaction([ATTACHMENTS_STORE], 'readwrite');
            const req = t.objectStore(ATTACHMENTS_STORE).add(row);
            req.onsuccess = () => {
                row.id = req.result;
                resolve(row);
            };
            req.onerror = () => reject(req.error);
        });
    }

    async function listAttachments(tmpUuid) {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const t = db.transaction([ATTACHMENTS_STORE], 'readonly');
            const idx = t.objectStore(ATTACHMENTS_STORE).index('by_tmp_uuid');
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
            const t = db.transaction([ATTACHMENTS_STORE], 'readwrite');
            t.oncomplete = () => resolve();
            t.onerror = () => reject(t.error);
            t.objectStore(ATTACHMENTS_STORE).delete(id);
        });
    }

    async function countAttachments(tmpUuid) {
        const list = await listAttachments(tmpUuid);
        return list.length;
    }

    /**
     * GC de drafts ya entregados al server (status='synced' o 'emitted') más
     * viejos que `daysOld` días. Borra el draft + sus attachments locales.
     * El presupuesto server-side queda intacto.
     *
     * @returns {Promise<{ deleted: number, retained: number }>}
     */
    async function garbageCollectSynced(daysOld = 7) {
        const cutoffMs = Date.now() - daysOld * 24 * 3600 * 1000;
        const all = await listDrafts();
        const stale = all.filter((d) => {
            if (d.status !== 'synced' && d.status !== 'emitted') return false;
            const ts = Date.parse(d.updated_at || '');
            return Number.isFinite(ts) && ts < cutoffMs;
        });
        for (const d of stale) {
            await deleteDraft(d.tmp_uuid);
        }
        return { deleted: stale.length, retained: all.length - stale.length };
    }

    /**
     * Borra TODOS los drafts ya entregados (status='synced' o 'emitted') sin
     * importar la edad. Útil para un botón "Limpiar enviados" explícito.
     */
    async function purgeAllSynced() {
        const all = await listDrafts();
        const stale = all.filter((d) => d.status === 'synced' || d.status === 'emitted');
        for (const d of stale) {
            await deleteDraft(d.tmp_uuid);
        }
        return { deleted: stale.length, retained: all.length - stale.length };
    }

    global.RxnPwaDraftsStore = {
        // Drafts
        createDraft,
        getDraft,
        saveDraft,
        listDrafts,
        deleteDraft,
        // Attachments
        addAttachment,
        listAttachments,
        removeAttachment,
        countAttachments,
        // GC
        garbageCollectSynced,
        purgeAllSynced,
        // Helpers
        generateUuid,
        computeTotal,
    };
})(window);
