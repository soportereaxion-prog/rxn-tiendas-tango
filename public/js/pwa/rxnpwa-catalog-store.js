// RXN PWA — Wrapper IndexedDB para el catálogo offline.
//
// Una sola DB ("rxnpwa") con stores por entidad del catálogo. Reemplaza la lógica
// de "tabla por tabla" por escrituras en bulk: cada sync overwritea la store completa.
//
// API pública:
//   RxnPwaCatalogStore.openDB()                             → Promise<IDBDatabase>
//   RxnPwaCatalogStore.saveCatalog(payload)                 → Promise<void>   (escribe TODO)
//   RxnPwaCatalogStore.loadAll()                            → Promise<{empresa_id, ...stores}>
//   RxnPwaCatalogStore.getMeta()                            → Promise<{hash, generated_at, items_count, size_bytes}|null>
//   RxnPwaCatalogStore.setMeta(meta)                        → Promise<void>
//   RxnPwaCatalogStore.clear()                              → Promise<void>
//
// La meta vive en una store aparte ("__meta") con clave "catalog".

(function (global) {
    'use strict';

    const DB_NAME = 'rxnpwa';
    // version 1: stores del catálogo + __meta (Fase 1).
    // version 2: presupuestos_drafts + presupuesto_attachments (Fase 2).
    // version 3: horas_drafts + horas_attachments (PWA Horas — release 1.43.0).
    const DB_VERSION = 3;

    // Schema version del PAYLOAD del catálogo. Independiente del DB_VERSION (que
    // refiere a la estructura de IndexedDB). Bumpear cuando RxnPwaCatalogService
    // cambia las columnas que devuelve para alguna entidad — al detectar mismatch
    // el cliente wipea SOLO el catálogo (deja drafts) y obliga a resincronizar.
    //   v1 — Fase 1 inicial.
    //   v2 — release 1.35.0: sumamos id_gvaXX_* a clientes (defaults comerciales).
    //   v3 — release 1.43.0: sumamos tratativas_activas para PWA Horas.
    const CATALOG_SCHEMA_VERSION = 'v3';

    const STORES = [
        'clientes',
        'articulos',
        'precios',
        'stocks',
        'condiciones_venta',
        'listas_precio',
        'vendedores',
        'transportes',
        'depositos',
        'clasificaciones_pds',
        'tratativas_activas',
    ];
    const META_STORE = '__meta';
    const DRAFTS_STORE = 'presupuestos_drafts';
    const ATTACHMENTS_STORE = 'presupuesto_attachments';
    const HORAS_DRAFTS_STORE = 'horas_drafts';
    const HORAS_ATTACHMENTS_STORE = 'horas_attachments';

    let dbPromise = null;

    function openDB() {
        if (dbPromise) return dbPromise;
        dbPromise = new Promise((resolve, reject) => {
            if (!('indexedDB' in global)) {
                reject(new Error('IndexedDB no soportado en este navegador.'));
                return;
            }
            const req = global.indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = () => {
                const db = req.result;
                STORES.forEach((name) => {
                    if (!db.objectStoreNames.contains(name)) {
                        // autoIncrement: la PK de la fila no la garantizamos como única acá
                        // (la query backend devuelve filas con/sin id según entidad).
                        db.createObjectStore(name, { autoIncrement: true });
                    }
                });
                if (!db.objectStoreNames.contains(META_STORE)) {
                    db.createObjectStore(META_STORE);
                }
                // v2 — drafts de presupuestos creados offline (Fase 2).
                if (!db.objectStoreNames.contains(DRAFTS_STORE)) {
                    // keyPath = tmp_uuid (lo asigna el cliente al crear el draft).
                    const draftStore = db.createObjectStore(DRAFTS_STORE, { keyPath: 'tmp_uuid' });
                    draftStore.createIndex('by_status', 'status', { unique: false });
                    draftStore.createIndex('by_updated_at', 'updated_at', { unique: false });
                }
                if (!db.objectStoreNames.contains(ATTACHMENTS_STORE)) {
                    // autoIncrement + index por tmp_uuid para listar todos los adjuntos de un draft.
                    const attStore = db.createObjectStore(ATTACHMENTS_STORE, { keyPath: 'id', autoIncrement: true });
                    attStore.createIndex('by_tmp_uuid', 'tmp_uuid', { unique: false });
                }
                // v3 — drafts de Horas (PWA turnero) + adjuntos (certificados, fotos).
                if (!db.objectStoreNames.contains(HORAS_DRAFTS_STORE)) {
                    const horasStore = db.createObjectStore(HORAS_DRAFTS_STORE, { keyPath: 'tmp_uuid' });
                    horasStore.createIndex('by_status', 'status', { unique: false });
                    horasStore.createIndex('by_updated_at', 'updated_at', { unique: false });
                }
                if (!db.objectStoreNames.contains(HORAS_ATTACHMENTS_STORE)) {
                    const attStore = db.createObjectStore(HORAS_ATTACHMENTS_STORE, { keyPath: 'id', autoIncrement: true });
                    attStore.createIndex('by_tmp_uuid', 'tmp_uuid', { unique: false });
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
        return dbPromise;
    }

    function tx(db, storeNames, mode) {
        return db.transaction(storeNames, mode);
    }

    async function saveCatalog(payload) {
        if (!payload || !payload.data) {
            throw new Error('Payload inválido — falta data.');
        }
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const t = tx(db, [...STORES, META_STORE], 'readwrite');
            t.oncomplete = () => resolve();
            t.onerror = () => reject(t.error);
            t.onabort = () => reject(t.error);

            STORES.forEach((name) => {
                const store = t.objectStore(name);
                store.clear();
                const rows = payload.data[name] || [];
                rows.forEach((row) => store.add(row));
            });

            const meta = t.objectStore(META_STORE);
            meta.put({
                hash: payload.hash,
                generated_at: payload.generated_at,
                items_count: payload.items_count,
                size_bytes: payload.size_bytes,
                empresa_id: payload.data.empresa_id || null,
                synced_at: new Date().toISOString(),
                schema_version: CATALOG_SCHEMA_VERSION,
            }, 'catalog');
        });
    }

    /**
     * Wipea SOLO el catálogo (sin tocar drafts/attachments). Para cuando detectamos
     * mismatch de empresa o de schema_version.
     */
    async function clearCatalogOnly() {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const t = tx(db, [...STORES, META_STORE], 'readwrite');
            t.oncomplete = () => resolve();
            t.onerror = () => reject(t.error);
            STORES.forEach((name) => t.objectStore(name).clear());
            t.objectStore(META_STORE).delete('catalog');
        });
    }

    async function loadAll() {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const t = tx(db, STORES, 'readonly');
            const result = {};
            t.onerror = () => reject(t.error);
            t.oncomplete = () => resolve(result);

            STORES.forEach((name) => {
                const store = t.objectStore(name);
                const req = store.getAll();
                req.onsuccess = () => {
                    result[name] = req.result || [];
                };
                req.onerror = () => reject(req.error);
            });
        });
    }

    async function getMeta() {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const t = tx(db, [META_STORE], 'readonly');
            const req = t.objectStore(META_STORE).get('catalog');
            req.onsuccess = () => resolve(req.result || null);
            req.onerror = () => reject(req.error);
        });
    }

    async function setMeta(meta) {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const t = tx(db, [META_STORE], 'readwrite');
            t.oncomplete = () => resolve();
            t.onerror = () => reject(t.error);
            t.objectStore(META_STORE).put(meta, 'catalog');
        });
    }

    async function clear() {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const t = tx(db, [...STORES, META_STORE], 'readwrite');
            t.oncomplete = () => resolve();
            t.onerror = () => reject(t.error);
            STORES.forEach((name) => t.objectStore(name).clear());
            t.objectStore(META_STORE).clear();
        });
    }

    global.RxnPwaCatalogStore = {
        openDB,
        saveCatalog,
        loadAll,
        getMeta,
        setMeta,
        clear,
        clearCatalogOnly,
        STORES,
        DRAFTS_STORE,
        ATTACHMENTS_STORE,
        HORAS_DRAFTS_STORE,
        HORAS_ATTACHMENTS_STORE,
        CATALOG_SCHEMA_VERSION,
    };
})(window);
