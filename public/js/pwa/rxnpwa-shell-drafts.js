// RXN PWA — Listado de drafts en el shell + sección "Cola de envío".
//
// Lee IndexedDB y renderiza:
//   - "Mis borradores": cards por draft con badge de estado + acciones contextuales
//     (Editar / Sincronizar / Reintentar / Enviar a Tango / Eliminar).
//   - "Cola de envío": resumen del estado de la cola + estado de red.
//
// Reactividad: se suscribe a RxnPwaSyncQueue y re-renderiza ante cualquier evento.

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        // GC automático: drafts synced/emitted más viejos que 7 días se limpian
        // de IndexedDB (el presupuesto server-side queda intacto). Best-effort —
        // si falla no rompe la UI.
        window.RxnPwaDraftsStore.garbageCollectSynced(7)
            .then(({ deleted }) => {
                if (deleted > 0) console.log('[rxnpwa-shell] GC: ' + deleted + ' draft(s) sincronizados eliminados.');
            })
            .catch((err) => console.warn('[rxnpwa-shell] GC falló:', err));

        renderAll();
        // Re-render al cambiar la cola.
        if (window.RxnPwaSyncQueue) {
            window.RxnPwaSyncQueue.subscribe(() => renderAll());
            // Pedir registro de Background Sync (best-effort, falla silente en iOS Safari).
            window.RxnPwaSyncQueue.registerBackgroundSync().catch(() => {});
        }
        // Estado de red.
        window.addEventListener('online', renderAll);
        window.addEventListener('offline', renderAll);
    });

    async function renderAll() {
        await Promise.all([renderDrafts(), renderQueueSummary()]);
        renderNetBadge();
    }

    /* ---------- Mis borradores ---------- */

    async function renderDrafts() {
        const container = document.getElementById('rxnpwa-drafts-list');
        if (!container) return;
        try {
            const drafts = await window.RxnPwaDraftsStore.listDrafts();
            if (!drafts || drafts.length === 0) {
                container.innerHTML = `
                    <div class="rxnpwa-placeholder small">
                        Sin borradores todavía. Tocá <strong>Nuevo</strong> para arrancar uno.
                    </div>
                `;
                return;
            }

            const items = await Promise.all(drafts.map(buildDraftCard));
            container.innerHTML = items.join('');
            attachActionHandlers(container);
        } catch (err) {
            console.error('[rxnpwa-shell] error listando drafts:', err);
            container.innerHTML = `<div class="alert alert-danger small">Error cargando borradores: ${escape(err.message)}</div>`;
        }
    }

    async function buildDraftCard(d) {
        const attCount = await window.RxnPwaDraftsStore.countAttachments(d.tmp_uuid);
        const total = window.RxnPwaDraftsStore.computeTotal(d.renglones);
        const cliente = (d.cabecera && d.cabecera.cliente_data && d.cabecera.cliente_data.razon_social) || '— sin cliente —';
        const renglones = (d.renglones || []).length;
        const updated = d.updated_at ? new Date(d.updated_at).toLocaleString('es-AR') : '—';
        const statusBadge = renderStatusBadge(d.status);
        const errorLine = (d.status === 'error' && d.last_error)
            ? `<div class="small text-danger mt-1"><i class="bi bi-exclamation-triangle"></i> ${escape(d.last_error)}</div>`
            : '';
        const numeroLine = d.numero_server
            ? `<div class="small text-success">Tango: #${d.numero_server}</div>`
            : '';
        const actions = renderDraftActions(d);

        return `
            <div class="rxnpwa-draft-card" data-tmp-uuid="${escape(d.tmp_uuid)}">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="flex-grow-1">
                        <div class="fw-bold">${escape(cliente)}</div>
                        <div class="small text-muted">
                            ${renglones} renglón${renglones === 1 ? '' : 'es'} · ${attCount} adjunto${attCount === 1 ? '' : 's'} · ${updated}
                        </div>
                        ${numeroLine}
                        ${errorLine}
                    </div>
                    <div class="text-end">
                        <div class="fw-bold">$ ${formatNum(total)}</div>
                        ${statusBadge}
                    </div>
                </div>
                <div class="rxnpwa-draft-actions mt-2 d-flex flex-wrap gap-1">
                    ${actions}
                </div>
            </div>
        `;
    }

    function renderDraftActions(d) {
        const tmp = escape(d.tmp_uuid);
        const isOnline = navigator.onLine;
        const buttons = [];

        // Editar siempre disponible (excepto si emitido — bloqueado).
        if (d.status !== 'emitted') {
            buttons.push(`<a href="/rxnpwa/presupuestos/editar/${encodeURIComponent(d.tmp_uuid)}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil"></i> Editar
            </a>`);
        } else {
            buttons.push(`<a href="/rxnpwa/presupuestos/editar/${encodeURIComponent(d.tmp_uuid)}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-eye"></i> Ver
            </a>`);
        }

        // Sincronizar / Reintentar
        if (d.status === 'draft' || d.status === 'pending_sync' || d.status === 'error' || d.status === 'syncing') {
            const label = (d.status === 'error') ? 'Reintentar' : 'Sincronizar';
            const icon = (d.status === 'error') ? 'bi-arrow-clockwise' : 'bi-cloud-upload';
            const disabled = (!isOnline || d.status === 'syncing') ? 'disabled' : '';
            buttons.push(`<button type="button" class="btn btn-sm btn-primary" data-action="sync" data-tmp="${tmp}" ${disabled}>
                <i class="bi ${icon}"></i> ${label}
            </button>`);
        }

        // Enviar a Tango — solo si synced y online.
        if (d.status === 'synced') {
            const disabled = isOnline ? '' : 'disabled';
            buttons.push(`<button type="button" class="btn btn-sm btn-success" data-action="emit-tango" data-tmp="${tmp}" ${disabled}>
                <i class="bi bi-send"></i> Enviar a Tango
            </button>`);
        }

        // Eliminar (excepto syncing en transit).
        if (d.status !== 'syncing') {
            buttons.push(`<button type="button" class="btn btn-sm btn-outline-danger" data-action="delete" data-tmp="${tmp}">
                <i class="bi bi-trash"></i>
            </button>`);
        }

        return buttons.join('');
    }

    function attachActionHandlers(container) {
        container.querySelectorAll('button[data-action]').forEach((btn) => {
            btn.addEventListener('click', async (ev) => {
                ev.preventDefault();
                ev.stopPropagation();
                const action = btn.getAttribute('data-action');
                const tmp = btn.getAttribute('data-tmp');
                btn.disabled = true;

                try {
                    if (action === 'sync') {
                        await window.RxnPwaSyncQueue.enqueue(tmp);
                    } else if (action === 'emit-tango') {
                        await window.RxnPwaSyncQueue.emitToTango(tmp);
                        toast('Presupuesto enviado a Tango.');
                    } else if (action === 'delete') {
                        if (!confirm('¿Eliminar este borrador? Esto NO afecta los presupuestos ya sincronizados al server.')) {
                            btn.disabled = false;
                            return;
                        }
                        await window.RxnPwaDraftsStore.deleteDraft(tmp);
                    }
                    await renderAll();
                } catch (err) {
                    console.error('[rxnpwa-shell] action ' + action + ':', err);
                    toast('Error: ' + err.message, true);
                    btn.disabled = false;
                }
            });
        });
    }

    /* ---------- Cola de envío ---------- */

    async function renderQueueSummary() {
        const container = document.getElementById('rxnpwa-queue-summary');
        if (!container) return;
        try {
            const drafts = await window.RxnPwaDraftsStore.listDrafts();
            const buckets = {
                pending_sync: 0,
                syncing: 0,
                synced: 0,
                error: 0,
            };
            for (const d of drafts) {
                if (buckets[d.status] !== undefined) buckets[d.status]++;
            }

            const total = buckets.pending_sync + buckets.syncing + buckets.error;
            if (total === 0 && buckets.synced === 0) {
                container.innerHTML = `<div class="rxnpwa-placeholder small">Sin elementos en cola.</div>`;
                return;
            }

            const lines = [];
            if (buckets.syncing > 0)      lines.push(`<li><span class="badge bg-info me-2">${buckets.syncing}</span>Enviando…</li>`);
            if (buckets.pending_sync > 0) lines.push(`<li><span class="badge bg-warning me-2">${buckets.pending_sync}</span>Pendientes</li>`);
            if (buckets.error > 0)        lines.push(`<li><span class="badge bg-danger me-2">${buckets.error}</span>Con error</li>`);
            if (buckets.synced > 0)       lines.push(`<li><span class="badge bg-success me-2">${buckets.synced}</span>Sincronizados</li>`);

            // Botón "Limpiar enviados" — sólo aparece si hay synced/emitted que se
            // pueden purgar. La purga es local (IndexedDB), nunca toca el server.
            const purgeable = buckets.synced;
            const purgeButton = purgeable > 0
                ? `<button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="rxnpwa-purge-synced">
                       <i class="bi bi-trash3"></i> Limpiar ${purgeable} enviado${purgeable === 1 ? '' : 's'} del celu
                   </button>`
                : '';

            container.innerHTML = `<ul class="list-unstyled mb-0">${lines.join('')}</ul>${purgeButton}`;
            const purgeBtn = container.querySelector('#rxnpwa-purge-synced');
            if (purgeBtn) {
                purgeBtn.addEventListener('click', async () => {
                    if (!confirm('¿Borrar los borradores ya enviados al server? Los presupuestos en el server NO se tocan.')) return;
                    purgeBtn.disabled = true;
                    try {
                        const result = await window.RxnPwaDraftsStore.purgeAllSynced();
                        toast(result.deleted + ' borrador(es) limpiado(s).');
                        await renderAll();
                    } catch (err) {
                        toast('Error: ' + err.message, true);
                        purgeBtn.disabled = false;
                    }
                });
            }
        } catch (err) {
            container.innerHTML = `<div class="alert alert-danger small">Error: ${escape(err.message)}</div>`;
        }
    }

    function renderNetBadge() {
        const el = document.getElementById('rxnpwa-queue-net-badge');
        if (!el) return;
        if (navigator.onLine) {
            el.className = 'badge bg-success';
            el.innerHTML = '<i class="bi bi-wifi"></i> Online';
        } else {
            el.className = 'badge bg-secondary';
            el.innerHTML = '<i class="bi bi-wifi-off"></i> Offline';
        }
    }

    /* ---------- Helpers ---------- */

    function renderStatusBadge(status) {
        const map = {
            'draft':        { cls: 'secondary', label: 'Borrador' },
            'pending_sync': { cls: 'warning',   label: 'Pendiente' },
            'syncing':      { cls: 'info',      label: 'Enviando' },
            'synced':       { cls: 'success',   label: 'Sincronizado' },
            'emitted':      { cls: 'primary',   label: 'En Tango' },
            'error':        { cls: 'danger',    label: 'Error' },
        };
        const cfg = map[status] || map.draft;
        return `<span class="badge bg-${cfg.cls} mt-1">${cfg.label}</span>`;
    }

    function escape(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatNum(n) {
        const v = Number(n) || 0;
        return v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function toast(msg, isError = false) {
        // Toast minimalista — usamos el div de status del shell para no pintar otro.
        const status = document.getElementById('rxnpwa-status');
        if (!status) {
            console[isError ? 'error' : 'log'](msg);
            return;
        }
        const cls = isError ? 'danger' : 'success';
        const orig = status.innerHTML;
        status.innerHTML = `<div class="alert alert-${cls} mb-3">${escape(msg)}</div>`;
        setTimeout(() => { status.innerHTML = orig; }, 3500);
    }
})();
