// RXN PWA — registro del Service Worker + sincronización del catálogo offline.
//
// Lo que hace al cargarse en el shell:
//   1) Registra /sw.js (con scope = root para que también tome el flujo de Web Push).
//   2) Pide /api/rxnpwa/catalog/version y compara hash con la meta guardada en IndexedDB.
//   3) Renderiza el badge de estado:
//        🟢 Catálogo al día — generated_at reciente y hash igual.
//        🟡 Catálogo desactualizado — hash distinto o más viejo de lo deseable (umbral configurable).
//        🔴 Sin catálogo offline — IndexedDB vacía. Botón "Preparar offline" descarga full.
//        ⚠️ Sin red — no podemos chequear; mostramos lo que tenemos cacheado.
//   4) Botón "Preparar offline" / "Re-sincronizar" → pega a /api/rxnpwa/catalog/full y guarda en IndexedDB.
//
// La fase 2 (form_mobile) y la fase 3 (sync queue) extienden este script. Por ahora
// el shell solo persiste el catálogo y muestra el estado.

(function () {
    'use strict';

    const STATUS_EL_ID = 'rxnpwa-status';
    const ACTIONS_EL_ID = 'rxnpwa-actions';
    const STALE_HOURS_THRESHOLD = 6; // umbral inicial — afinar viendo el comportamiento real.

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        registerServiceWorker();
        wireSyncButton();
        refreshStatus();
        // Si volvemos online, re-chequear estado.
        window.addEventListener('online', refreshStatus);
        window.addEventListener('offline', renderOfflineNotice);
    }

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            console.warn('[rxnpwa] Service Worker no soportado.');
            return;
        }
        navigator.serviceWorker
            .register('/sw.js', { scope: '/' })
            .then((reg) => {
                console.log('[rxnpwa] SW registrado, scope:', reg.scope);
            })
            .catch((err) => {
                console.error('[rxnpwa] Error registrando SW:', err);
            });
    }

    function wireSyncButton() {
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-rxnpwa-sync]');
            if (!btn) return;
            e.preventDefault();
            await syncCatalog(btn);
        });
    }

    async function syncCatalog(btn) {
        const status = document.getElementById(STATUS_EL_ID);
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳ Descargando catálogo...';
        if (status) status.innerHTML = renderBadge('downloading', { msg: 'Descargando catálogo…' });

        try {
            const res = await fetch('/api/rxnpwa/catalog/full', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }
            const payload = await res.json();
            if (!payload.ok) {
                throw new Error('Respuesta sin ok=true');
            }
            await window.RxnPwaCatalogStore.saveCatalog(payload);
            await refreshStatus();
        } catch (err) {
            console.error('[rxnpwa] Falló sync de catálogo:', err);
            if (status) {
                status.innerHTML = renderBadge('error', { msg: 'No se pudo descargar el catálogo: ' + err.message });
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    }

    function getCurrentEmpresaId() {
        const tag = document.querySelector('meta[name="rxn-empresa-id"]');
        const id = tag ? parseInt(tag.getAttribute('content'), 10) : 0;
        return Number.isFinite(id) && id > 0 ? id : 0;
    }

    /**
     * Si el catálogo local pertenece a otra empresa (Charly cambió de empresa
     * en el backoffice), wipear todo el catálogo offline para forzar resync.
     * Los DRAFTS no se tocan — son por-empresa y se filtran en runtime.
     *
     * @returns {string|null} 'empresa' / 'schema' si hubo wipe, null si está OK.
     */
    async function ensureCatalogConsistency() {
        const current = getCurrentEmpresaId();
        const meta = await window.RxnPwaCatalogStore.getMeta().catch(() => null);
        if (!meta) return null;

        // (1) Cambio de empresa.
        if (current && meta.empresa_id && Number(meta.empresa_id) !== current) {
            console.warn('[rxnpwa] Empresa cambió: catálogo era de ' + meta.empresa_id + ', sesión es ' + current + '. Wipeando catálogo.');
            await window.RxnPwaCatalogStore.clearCatalogOnly();
            return 'empresa';
        }

        // (2) Schema del payload desactualizado (ej: server agregó columnas al cliente).
        const expected = window.RxnPwaCatalogStore.CATALOG_SCHEMA_VERSION;
        if (expected && meta.schema_version !== expected) {
            console.warn('[rxnpwa] Schema del catálogo desactualizado: cache=' + meta.schema_version + ', esperado=' + expected + '. Wipeando catálogo.');
            await window.RxnPwaCatalogStore.clearCatalogOnly();
            return 'schema';
        }
        return null;
    }

    async function refreshStatus() {
        const status = document.getElementById(STATUS_EL_ID);
        if (!status) return;

        const wiped = await ensureCatalogConsistency();
        const meta = wiped ? null : await window.RxnPwaCatalogStore.getMeta().catch(() => null);

        if (!navigator.onLine) {
            renderOfflineNotice(meta);
            return;
        }

        if (wiped === 'empresa') {
            status.innerHTML = renderBadge('empty', {
                msg: 'Cambiaste de empresa — el catálogo offline anterior se borró. Descargá el nuevo antes de salir a campo.',
            });
            return;
        }
        if (wiped === 'schema') {
            status.innerHTML = renderBadge('empty', {
                msg: 'Hay una versión nueva del catálogo (con más datos del cliente). Resincronizá para tener los defaults comerciales actualizados.',
            });
            return;
        }

        // Pegar a /version. Si falla la red, mostrar lo que tenemos cacheado.
        let serverVersion = null;
        try {
            const res = await fetch('/api/rxnpwa/catalog/version', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            if (res.ok) {
                serverVersion = await res.json();
            }
        } catch (_) {
            // sin red, fallback al cache local
        }

        if (!meta) {
            status.innerHTML = renderBadge('empty', {
                msg: 'Sin catálogo offline. Descargalo antes de salir a campo.',
                serverVersion,
            });
            return;
        }

        if (serverVersion && serverVersion.hash && meta.hash !== serverVersion.hash) {
            status.innerHTML = renderBadge('stale-hash', {
                meta,
                serverVersion,
                msg: 'Hay una versión más nueva del catálogo en el servidor.',
            });
            return;
        }

        const ageHours = computeAgeHours(meta.synced_at);
        if (ageHours !== null && ageHours >= STALE_HOURS_THRESHOLD) {
            status.innerHTML = renderBadge('stale-time', {
                meta,
                serverVersion,
                msg: `Tu app no se sincroniza hace ${formatAge(ageHours)}.`,
            });
            return;
        }

        status.innerHTML = renderBadge('fresh', { meta, serverVersion });
    }

    function renderOfflineNotice(meta) {
        const status = document.getElementById(STATUS_EL_ID);
        if (!status) return;
        if (!meta) {
            status.innerHTML = renderBadge('offline-empty', {
                msg: 'Sin red y sin catálogo offline. Conectate a internet para preparar la app.',
            });
            return;
        }
        const ageHours = computeAgeHours(meta.synced_at);
        status.innerHTML = renderBadge('offline-cached', {
            meta,
            msg: ageHours !== null
                ? `Modo offline. Catálogo descargado hace ${formatAge(ageHours)}.`
                : 'Modo offline. Catálogo offline disponible.',
        });
    }

    function renderBadge(state, ctx = {}) {
        const meta = ctx.meta;
        const itemsCount = meta ? (meta.items_count || 0).toLocaleString('es-AR') : null;
        const sizeText = meta ? formatBytes(meta.size_bytes || 0) : null;
        const syncedDate = meta && meta.synced_at ? new Date(meta.synced_at).toLocaleString('es-AR') : null;

        // Paleta. El "color" se aplica al texto del título — el contenedor (card)
        // viene del shell, no usamos alert acá para que entre compacto en la grid.
        const palette = {
            fresh: { color: 'text-success', icon: '🟢', title: 'Catálogo al día' },
            'stale-time': { color: 'text-warning', icon: '🟡', title: 'Desactualizado' },
            'stale-hash': { color: 'text-warning', icon: '🟡', title: 'Versión nueva' },
            empty: { color: 'text-danger', icon: '🔴', title: 'Sin catálogo' },
            'offline-empty': { color: 'text-danger', icon: '⚠️', title: 'Sin red ni catálogo' },
            'offline-cached': { color: 'text-info', icon: '📡', title: 'Offline' },
            error: { color: 'text-danger', icon: '⚠️', title: 'Error de sync' },
            downloading: { color: 'text-info', icon: '⏳', title: 'Sincronizando…' },
        };
        const cfg = palette[state] || palette.empty;
        const msg = ctx.msg || '';

        // Línea de meta: items + tamaño + fecha. Cada uno en su línea para que
        // se lea bien en la card chica del header (col-7).
        const metaLines = [];
        if (itemsCount !== null) metaLines.push(`${itemsCount} ítems · ${sizeText}`);
        if (syncedDate) metaLines.push(syncedDate);

        return `
            <div class="d-flex flex-column">
                <div class="fw-bold ${cfg.color} small">${cfg.icon} ${cfg.title}</div>
                ${metaLines.map((l) => `<div class="rxnpwa-badge-meta small">${escapeHtml(l)}</div>`).join('')}
                ${msg ? `<div class="small text-muted mt-1">${escapeHtml(msg)}</div>` : ''}
            </div>
        `;
    }

    function computeAgeHours(syncedAtIso) {
        if (!syncedAtIso) return null;
        const t = Date.parse(syncedAtIso);
        if (Number.isNaN(t)) return null;
        return (Date.now() - t) / 3600000;
    }

    function formatAge(hours) {
        if (hours < 1) return Math.round(hours * 60) + ' min';
        if (hours < 24) return Math.round(hours * 10) / 10 + ' hs';
        const d = Math.floor(hours / 24);
        return d + ' día' + (d === 1 ? '' : 's');
    }

    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
