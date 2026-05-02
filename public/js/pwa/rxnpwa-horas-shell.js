// RXN PWA — Shell del turnero CrmHoras (mobile, espejo del desktop /mi-empresa/crm/horas).
//
// Funcionalidad principal:
//  - Total trabajado hoy en vivo (suma turnos cerrados del día + cronómetro abierto).
//  - Botón único contextual: "Iniciar turno" o "Cerrar turno" según draft abierto.
//  - Concepto + descuento + tratativa al iniciar (textarea/details).
//  - Listado de turnos del día con badge de estado.
//  - Cola de envío con purga manual de sincronizados.

(function () {
    'use strict';

    if (!window.RxnPwaCatalogStore || !window.RxnPwaHorasDraftsStore) {
        console.error('[rxnpwa-horas-shell] Dependencias no cargadas.');
        return;
    }

    let liveTickerId = null;
    let catalogTratativas = [];

    document.addEventListener('DOMContentLoaded', boot);

    async function boot() {
        // GC drafts viejos sincronizados.
        window.RxnPwaHorasDraftsStore.garbageCollectSynced(7).catch(() => {});

        // Cargar tratativas activas del catálogo offline.
        try {
            const all = await window.RxnPwaCatalogStore.loadAll();
            catalogTratativas = Array.isArray(all.tratativas_activas) ? all.tratativas_activas : [];
        } catch (e) {
            catalogTratativas = [];
        }

        await renderAll();

        if (window.RxnPwaHorasSyncQueue) {
            window.RxnPwaHorasSyncQueue.subscribe(() => renderAll());
        }
        window.addEventListener('online', renderAll);
        window.addEventListener('offline', renderAll);

        // Sync manual desde el botón del header.
        document.querySelectorAll('[data-rxnpwa-sync]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (window.RxnPwaHorasSyncQueue) window.RxnPwaHorasSyncQueue.kick();
            });
        });

        startLiveTicker();
    }

    async function renderAll() {
        const open = await window.RxnPwaHorasDraftsStore.findOpenDraft();
        const all = await window.RxnPwaHorasDraftsStore.listDrafts();
        const todayDrafts = filterToday(all);

        renderTotalHoy(todayDrafts, open);
        renderCronCard(open);
        renderDraftsList(todayDrafts);
        renderQueueSummary(all);
        renderNetBadge();
    }

    function startLiveTicker() {
        stopLiveTicker();
        liveTickerId = setInterval(async () => {
            const open = await window.RxnPwaHorasDraftsStore.findOpenDraft();
            const all = await window.RxnPwaHorasDraftsStore.listDrafts();
            renderTotalHoy(filterToday(all), open);
        }, 1000);
    }
    function stopLiveTicker() {
        if (liveTickerId) { clearInterval(liveTickerId); liveTickerId = null; }
    }

    function filterToday(drafts) {
        const today = new Date();
        const y = today.getFullYear(), m = today.getMonth(), d = today.getDate();
        return drafts.filter(dr => {
            const ini = parseLocalIso(dr.cabecera && dr.cabecera.fecha_inicio);
            return ini && ini.getFullYear() === y && ini.getMonth() === m && ini.getDate() === d;
        });
    }

    /* ---------- Total hoy ---------- */

    function renderTotalHoy(todayDrafts, open) {
        const el = document.getElementById('rxnpwa-horas-total');
        if (!el) return;
        let totalSecs = 0;
        const now = new Date();
        for (const dr of todayDrafts) {
            const ini = parseLocalIso(dr.cabecera && dr.cabecera.fecha_inicio);
            if (!ini) continue;
            const fin = dr.cabecera.fecha_finalizado ? parseLocalIso(dr.cabecera.fecha_finalizado) : (open && open.tmp_uuid === dr.tmp_uuid ? now : null);
            if (!fin || fin < ini) continue;
            const bruto = Math.floor((fin - ini) / 1000);
            const desc = Number(dr.cabecera.descuento_segundos || 0);
            totalSecs += Math.max(0, bruto - desc);
        }
        el.textContent = formatDuration(totalSecs);
    }

    /* ---------- Card del cronómetro ---------- */

    function renderCronCard(open) {
        const card = document.getElementById('rxnpwa-horas-cron-card');
        if (!card) return;

        if (open) {
            const ini = parseLocalIso(open.cabecera.fecha_inicio);
            const horaIni = ini ? ini.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' }) : '—';
            const concepto = open.cabecera.concepto ? `<div class="text-secondary small mt-1">${escape(open.cabecera.concepto)}</div>` : '';
            card.innerHTML = `
                <div class="text-center mb-3">
                    <div class="text-muted small">Turno abierto desde</div>
                    <div class="fw-bold fs-5">${horaIni}</div>
                    ${concepto}
                </div>
                <button type="button" class="btn btn-danger btn-lg fw-bold w-100 py-3" id="rxnpwa-horas-btn-cerrar">
                    <i class="bi bi-stop-circle"></i> Cerrar turno
                </button>
            `;
            card.querySelector('#rxnpwa-horas-btn-cerrar').addEventListener('click', () => cerrarTurno(open));
        } else {
            const optTrat = catalogTratativas.map(t => {
                const lbl = `#${Number(t.numero || 0)} — ${t.titulo || ''}`;
                return `<option value="${escape(t.id)}">${escape(lbl)}</option>`;
            }).join('');
            card.innerHTML = `
                <div class="mb-3">
                    <label class="form-label small" for="rxnpwa-horas-concepto-iniciar">Concepto (opcional)</label>
                    <textarea class="form-control" id="rxnpwa-horas-concepto-iniciar" rows="2" maxlength="2000" placeholder="Ej: Visita técnica - Cliente X. Detalles..."></textarea>
                </div>
                ${optTrat ? `
                <div class="mb-3">
                    <label class="form-label small" for="rxnpwa-horas-tratativa-iniciar">Vincular a tratativa (opcional)</label>
                    <select class="form-select" id="rxnpwa-horas-tratativa-iniciar">
                        <option value="">— ninguna —</option>
                        ${optTrat}
                    </select>
                </div>` : ''}
                <details class="mb-3">
                    <summary class="small text-muted">Aplicar descuento al tiempo (opcional)</summary>
                    <div class="row g-2 mt-2">
                        <div class="col-12">
                            <label class="form-label small">Descuento (HH:MM:SS)</label>
                            <input type="text" class="form-control" id="rxnpwa-horas-descuento-iniciar" placeholder="00:00:00">
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Motivo</label>
                            <textarea class="form-control" id="rxnpwa-horas-motivo-iniciar" rows="2" placeholder="Ej: pausa larga, traslado..."></textarea>
                        </div>
                    </div>
                </details>
                <button type="button" class="btn btn-success btn-lg fw-bold w-100 py-3" id="rxnpwa-horas-btn-iniciar">
                    <i class="bi bi-play-circle"></i> Iniciar turno
                </button>
            `;
            card.querySelector('#rxnpwa-horas-btn-iniciar').addEventListener('click', iniciarTurno);
        }
    }

    async function iniciarTurno() {
        try {
            const empresaId = parseInt(document.querySelector('meta[name="rxn-empresa-id"]')?.getAttribute('content') || '0', 10);
            const concepto = (document.getElementById('rxnpwa-horas-concepto-iniciar')?.value || '').trim();
            const tratativaId = parseInt(document.getElementById('rxnpwa-horas-tratativa-iniciar')?.value || '0', 10) || null;
            const descuento = (document.getElementById('rxnpwa-horas-descuento-iniciar')?.value || '').trim();
            const motivo = (document.getElementById('rxnpwa-horas-motivo-iniciar')?.value || '').trim();
            const descSecs = parseDuration(descuento) || 0;
            if (descSecs > 0 && motivo === '') {
                toast('Si cargás descuento, el motivo es obligatorio.', true);
                return;
            }

            // Capturar tratativa_data desde el catálogo offline.
            let tratativa_data = null;
            if (tratativaId) {
                const t = catalogTratativas.find(x => Number(x.id) === tratativaId);
                if (t) tratativa_data = { id: t.id, numero: t.numero, titulo: t.titulo };
            }

            const draft = await window.RxnPwaHorasDraftsStore.createDraft({
                empresaId,
                cabeceraOverrides: {
                    fecha_inicio: nowLocalIso(),
                    fecha_finalizado: '',
                    concepto,
                    tratativa_id: tratativaId,
                    tratativa_data,
                    descuento_segundos: descSecs,
                    motivo_descuento: motivo,
                },
            });

            captureGeoIfAvailable(draft, 'inicio');
            await window.RxnPwaHorasDraftsStore.saveDraft(draft);
            toast('Turno iniciado.');
            await renderAll();
        } catch (err) {
            toast('Error al iniciar: ' + err.message, true);
        }
    }

    async function cerrarTurno(open) {
        if (!confirm('¿Cerrar el turno actual?')) return;
        try {
            open.cabecera.fecha_finalizado = nowLocalIso();
            captureGeoIfAvailable(open, 'fin');
            await window.RxnPwaHorasDraftsStore.saveDraft(open);
            toast('Turno cerrado. Tocá Sincronizar para subirlo.');
            await renderAll();
        } catch (err) {
            toast('Error al cerrar: ' + err.message, true);
        }
    }

    function captureGeoIfAvailable(draft, _moment) {
        if (!window.RxnPwaGeoGate) return;
        const geo = window.RxnPwaGeoGate.getCurrentGeo();
        if (geo && geo.source) {
            draft.geo = {
                lat: geo.lat, lng: geo.lng, accuracy: geo.accuracy,
                source: geo.source, captured_at: geo.captured_at,
            };
        }
    }

    /* ---------- Lista del día ---------- */

    function renderDraftsList(todayDrafts) {
        const container = document.getElementById('rxnpwa-horas-drafts-list');
        if (!container) return;

        if (todayDrafts.length === 0) {
            container.innerHTML = '<div class="rxnpwa-placeholder small text-center py-3">Todavía no registraste turnos hoy.</div>';
            return;
        }

        const items = todayDrafts.map(d => {
            const ini = parseLocalIso(d.cabecera.fecha_inicio);
            const fin = parseLocalIso(d.cabecera.fecha_finalizado);
            const horaIni = ini ? ini.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' }) : '—';
            const horaFin = fin ? fin.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' }) : '—';
            let durLabel = '';
            if (ini && fin && fin >= ini) {
                const segs = Math.floor((fin - ini) / 1000) - Number(d.cabecera.descuento_segundos || 0);
                durLabel = `<span class="badge bg-secondary-subtle text-secondary ms-1">${formatDurationShort(Math.max(0, segs))}</span>`;
            }
            const isOpen = ini && !fin;
            const statusBadge = renderStatusBadge(d.status, isOpen);
            const concepto = d.cabecera.concepto ? `<div class="small text-muted">${escape(d.cabecera.concepto)}</div>` : '';
            const errorLine = (d.status === 'error' && d.last_error)
                ? `<div class="small text-danger"><i class="bi bi-exclamation-triangle"></i> ${escape(d.last_error)}</div>` : '';
            const numLine = d.server_id ? `<div class="small text-success">Server: #${d.server_id}</div>` : '';
            const actions = renderDraftActions(d, isOpen);

            return `
                <div class="rxnpwa-draft-card mb-2" data-tmp-uuid="${escape(d.tmp_uuid)}">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="flex-grow-1">
                            <div class="fw-semibold">${horaIni} <span class="text-muted">→</span> ${horaFin}${durLabel}</div>
                            ${concepto}
                            ${numLine}
                            ${errorLine}
                        </div>
                        <div class="text-end">${statusBadge}</div>
                    </div>
                    ${actions ? `<div class="rxnpwa-draft-actions mt-2 d-flex flex-wrap gap-1">${actions}</div>` : ''}
                </div>
            `;
        });
        container.innerHTML = items.join('');
        container.querySelectorAll('button[data-action]').forEach(btn => {
            btn.addEventListener('click', async (ev) => {
                ev.preventDefault();
                const action = btn.getAttribute('data-action');
                const tmp = btn.getAttribute('data-tmp');
                btn.disabled = true;
                try {
                    if (action === 'sync') {
                        await window.RxnPwaHorasSyncQueue.enqueue(tmp);
                    } else if (action === 'edit') {
                        window.location.href = '/rxnpwa/horas/editar/' + encodeURIComponent(tmp);
                        return;
                    } else if (action === 'delete') {
                        if (!confirm('¿Eliminar este borrador? Esta acción no se puede deshacer.')) {
                            btn.disabled = false; return;
                        }
                        await window.RxnPwaHorasDraftsStore.deleteDraft(tmp);
                    }
                    await renderAll();
                } catch (err) {
                    toast('Error: ' + err.message, true);
                    btn.disabled = false;
                }
            });
        });
    }

    function renderDraftActions(d, isOpen) {
        const tmp = escape(d.tmp_uuid);
        const isOnline = navigator.onLine;
        const buttons = [];
        if (!isOpen && d.status !== 'syncing') {
            buttons.push(`<button type="button" class="btn btn-sm btn-outline-primary" data-action="edit" data-tmp="${tmp}">
                <i class="bi bi-pencil"></i> Editar
            </button>`);
        }
        if (!isOpen && (d.status === 'draft' || d.status === 'pending_sync' || d.status === 'error')) {
            const label = (d.status === 'error') ? 'Reintentar' : 'Sincronizar';
            const icon = (d.status === 'error') ? 'bi-arrow-clockwise' : 'bi-cloud-upload';
            const disabled = !isOnline ? 'disabled' : '';
            buttons.push(`<button type="button" class="btn btn-sm btn-primary" data-action="sync" data-tmp="${tmp}" ${disabled}>
                <i class="bi ${icon}"></i> ${label}
            </button>`);
        }
        if (d.status !== 'syncing' && d.status !== 'synced') {
            buttons.push(`<button type="button" class="btn btn-sm btn-outline-danger" data-action="delete" data-tmp="${tmp}">
                <i class="bi bi-trash"></i>
            </button>`);
        }
        return buttons.join('');
    }

    /* ---------- Cola ---------- */

    async function renderQueueSummary(all) {
        const container = document.getElementById('rxnpwa-horas-queue-summary');
        if (!container) return;
        const buckets = { pending_sync: 0, syncing: 0, synced: 0, error: 0 };
        for (const d of all) if (buckets[d.status] !== undefined) buckets[d.status]++;
        const total = buckets.pending_sync + buckets.syncing + buckets.error;
        if (total === 0 && buckets.synced === 0) {
            container.innerHTML = '<div class="rxnpwa-placeholder small">Sin elementos en cola.</div>';
            return;
        }
        const lines = [];
        if (buckets.syncing > 0)      lines.push(`<li><span class="badge bg-info me-2">${buckets.syncing}</span>Enviando…</li>`);
        if (buckets.pending_sync > 0) lines.push(`<li><span class="badge bg-warning me-2">${buckets.pending_sync}</span>Pendientes</li>`);
        if (buckets.error > 0)        lines.push(`<li><span class="badge bg-danger me-2">${buckets.error}</span>Con error</li>`);
        if (buckets.synced > 0)       lines.push(`<li><span class="badge bg-success me-2">${buckets.synced}</span>Sincronizados</li>`);
        const purgeBtn = buckets.synced > 0
            ? `<button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="rxnpwa-horas-purge-synced">
                   <i class="bi bi-trash3"></i> Limpiar ${buckets.synced} sincronizado${buckets.synced === 1 ? '' : 's'} del celu
               </button>`
            : '';
        container.innerHTML = `<ul class="list-unstyled mb-0">${lines.join('')}</ul>${purgeBtn}`;
        const btn = container.querySelector('#rxnpwa-horas-purge-synced');
        if (btn) {
            btn.addEventListener('click', async () => {
                if (!confirm('¿Borrar los borradores ya enviados al server? Los turnos en el server NO se tocan.')) return;
                btn.disabled = true;
                try {
                    const res = await window.RxnPwaHorasDraftsStore.purgeAllSynced();
                    toast(res.deleted + ' borrador(es) limpiado(s).');
                    await renderAll();
                } catch (err) {
                    toast('Error: ' + err.message, true);
                    btn.disabled = false;
                }
            });
        }
    }

    function renderNetBadge() {
        const el = document.getElementById('rxnpwa-horas-net-badge');
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

    function renderStatusBadge(status, isOpen) {
        if (isOpen) return '<span class="badge bg-success">EN CURSO</span>';
        const map = {
            'draft':        { cls: 'secondary', label: 'Borrador' },
            'pending_sync': { cls: 'warning',   label: 'Pendiente' },
            'syncing':      { cls: 'info',      label: 'Enviando' },
            'synced':       { cls: 'success',   label: 'Sincronizado' },
            'error':        { cls: 'danger',    label: 'Error' },
        };
        const cfg = map[status] || map.draft;
        return `<span class="badge bg-${cfg.cls}">${cfg.label}</span>`;
    }

    function nowLocalIso() {
        const d = new Date();
        const pad = n => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
             + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }

    function parseLocalIso(s) {
        if (!s) return null;
        const norm = String(s).replace(' ', 'T');
        const d = new Date(norm);
        return Number.isNaN(d.getTime()) ? null : d;
    }

    function parseDuration(value) {
        if (!value) return 0;
        const m = /^(\d{1,3}):([0-5]?\d):([0-5]?\d)$/.exec(value);
        if (!m) return null;
        return Number(m[1]) * 3600 + Number(m[2]) * 60 + Number(m[3]);
    }

    function formatDuration(totalSeconds) {
        const t = Math.max(0, Math.floor(totalSeconds || 0));
        const h = Math.floor(t / 3600), m = Math.floor((t % 3600) / 60), s = t % 60;
        return [h, m, s].map(n => String(n).padStart(2, '0')).join(':');
    }
    function formatDurationShort(totalSeconds) {
        const t = Math.max(0, Math.floor(totalSeconds || 0));
        const h = Math.floor(t / 3600), m = Math.floor((t % 3600) / 60);
        return `${h}h ${String(m).padStart(2, '0')}m`;
    }

    function escape(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function toast(msg, isError = false) {
        const status = document.getElementById('rxnpwa-status');
        if (!status) { console[isError ? 'error' : 'log'](msg); return; }
        const cls = isError ? 'danger' : 'success';
        const orig = status.innerHTML;
        status.innerHTML = `<div class="alert alert-${cls} mb-0">${escape(msg)}</div>`;
        setTimeout(() => { status.innerHTML = orig; }, 3500);
    }
})();
