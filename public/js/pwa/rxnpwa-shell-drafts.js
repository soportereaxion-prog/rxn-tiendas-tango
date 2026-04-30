// RXN PWA — Listado de drafts en el shell.
// Lee IndexedDB y renderiza un link por borrador con badge de estado.

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', renderDrafts);

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

            const items = await Promise.all(drafts.map(async (d) => {
                const attCount = await window.RxnPwaDraftsStore.countAttachments(d.tmp_uuid);
                const total = window.RxnPwaDraftsStore.computeTotal(d.renglones);
                const cliente = (d.cabecera && d.cabecera.cliente_data && d.cabecera.cliente_data.razon_social) || '— sin cliente —';
                const renglones = (d.renglones || []).length;
                const updated = d.updated_at ? new Date(d.updated_at).toLocaleString('es-AR') : '—';
                const statusBadge = renderStatusBadge(d.status);
                return `
                    <a href="/rxnpwa/presupuestos/editar/${encodeURIComponent(d.tmp_uuid)}" class="rxnpwa-draft-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="fw-bold">${escape(cliente)}</div>
                                <div class="small text-muted">
                                    ${renglones} renglón${renglones === 1 ? '' : 'es'} · ${attCount} adjunto${attCount === 1 ? '' : 's'} · ${updated}
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">$ ${formatNum(total)}</div>
                                ${statusBadge}
                            </div>
                        </div>
                    </a>
                `;
            }));
            container.innerHTML = items.join('');
        } catch (err) {
            console.error('[rxnpwa-shell] error listando drafts:', err);
            container.innerHTML = `<div class="alert alert-danger small">Error cargando borradores: ${escape(err.message)}</div>`;
        }
    }

    function renderStatusBadge(status) {
        const map = {
            'draft': { cls: 'secondary', label: 'Borrador' },
            'pending_sync': { cls: 'warning', label: 'Pendiente' },
            'syncing': { cls: 'info', label: 'Enviando' },
            'synced': { cls: 'success', label: 'Sincronizado' },
            'error': { cls: 'danger', label: 'Error' },
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
})();
