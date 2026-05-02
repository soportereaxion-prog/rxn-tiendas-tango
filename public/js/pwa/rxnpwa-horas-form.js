// RXN PWA — Form mobile de Horas (turno diferido o edición de borrador).
//
// Inputs:
//  - Fechas inicio + fin (datetime-local).
//  - Concepto (textarea).
//  - Tratativa (select cargado del catálogo offline).
//  - Descuento HH:MM:SS + motivo (textarea).
//  - Adjuntos: cámara (capture=environment) + file picker. Compresión cliente.
//
// Auto-save debounced. Sincronización delegada a RxnPwaHorasSyncQueue.

(function () {
    'use strict';

    if (!window.RxnPwaCatalogStore || !window.RxnPwaHorasDraftsStore) {
        console.error('[rxnpwa-horas-form] Dependencias no cargadas.');
        return;
    }

    const SAVE_DEBOUNCE_MS = 1500;

    let currentDraft = null;
    let catalog = null;
    let saveTimer = null;

    document.addEventListener('DOMContentLoaded', boot);

    async function boot() {
        const main = document.querySelector('main.rxnpwa-shell');
        if (!main) return;

        const tmpUuidInput = String(main.getAttribute('data-tmp-uuid') || '').trim();
        const empresaId = parseInt(main.getAttribute('data-empresa-id') || '0', 10);

        showStatus('Cargando catálogo...');
        try {
            catalog = await window.RxnPwaCatalogStore.loadAll();
        } catch (err) {
            showStatus('Error cargando catálogo: ' + err.message, 'danger');
            return;
        }

        const meta = await window.RxnPwaCatalogStore.getMeta();
        if (meta && meta.empresa_id && Number(meta.empresa_id) !== empresaId) {
            window.location.href = '/rxnpwa/horas';
            return;
        }

        if (tmpUuidInput) {
            currentDraft = await window.RxnPwaHorasDraftsStore.getDraft(tmpUuidInput);
            if (!currentDraft) {
                showStatus('Borrador no encontrado. Volvé al listado.', 'warning');
                return;
            }
            updateTitleSubtitle('Editar turno', formatStatus(currentDraft.status));
        } else {
            currentDraft = await window.RxnPwaHorasDraftsStore.createDraft({ empresaId });
            history.replaceState(null, '', '/rxnpwa/horas/editar/' + encodeURIComponent(currentDraft.tmp_uuid));
            updateTitleSubtitle('Cargar turno', 'Borrador local');
        }

        populateTratativas();
        wireForm();
        renderFromDraft();
        await renderAttachments();
        wireAttachments();
        wireSync();
        clearStatus();
    }

    function populateTratativas() {
        const sel = document.getElementById('rxnpwa-horas-tratativa');
        if (!sel) return;
        const list = (catalog.tratativas_activas || []).map(t => ({
            id: Number(t.id) || 0,
            numero: Number(t.numero) || 0,
            titulo: String(t.titulo || ''),
        })).filter(t => t.id > 0);
        sel.innerHTML = '<option value="">— ninguna —</option>'
            + list.map(t => `<option value="${t.id}" data-titulo="${escape(t.titulo)}" data-numero="${t.numero}">
                #${t.numero} — ${escape(t.titulo)}
            </option>`).join('');
    }

    /* ---------- Wire form ---------- */

    function wireForm() {
        const ini = document.getElementById('rxnpwa-horas-inicio');
        const fin = document.getElementById('rxnpwa-horas-fin');
        ini.addEventListener('change', () => {
            currentDraft.cabecera.fecha_inicio = ini.value || '';
            updateDurationLabels();
            scheduleSave();
        });
        fin.addEventListener('change', () => {
            currentDraft.cabecera.fecha_finalizado = fin.value || '';
            updateDurationLabels();
            scheduleSave();
        });

        document.getElementById('rxnpwa-horas-concepto').addEventListener('input', (e) => {
            currentDraft.cabecera.concepto = e.target.value;
            scheduleSave();
        });

        document.getElementById('rxnpwa-horas-tratativa').addEventListener('change', (e) => {
            const id = parseInt(e.target.value || '0', 10) || null;
            const opt = e.target.options[e.target.selectedIndex];
            currentDraft.cabecera.tratativa_id = id;
            currentDraft.cabecera.tratativa_data = id && opt
                ? { id, numero: parseInt(opt.getAttribute('data-numero') || '0', 10), titulo: opt.getAttribute('data-titulo') || '' }
                : null;
            scheduleSave();
        });

        document.getElementById('rxnpwa-horas-descuento').addEventListener('input', (e) => {
            const val = e.target.value;
            const segs = parseDuration(val);
            // Solo guardamos si parsea bien (HH:MM:SS); inválido → 0.
            currentDraft.cabecera.descuento_segundos = segs === null ? 0 : segs;
            updateDurationLabels();
            scheduleSave();
        });

        document.getElementById('rxnpwa-horas-motivo').addEventListener('input', (e) => {
            currentDraft.cabecera.motivo_descuento = e.target.value;
            scheduleSave();
        });

        document.getElementById('rxnpwa-horas-save').addEventListener('click', saveNow);
        document.getElementById('rxnpwa-horas-save-bottom').addEventListener('click', saveNow);

        document.getElementById('rxnpwa-horas-delete').addEventListener('click', async () => {
            if (!confirm('¿Descartar este borrador? Esta acción no se puede deshacer.')) return;
            try {
                await window.RxnPwaHorasDraftsStore.deleteDraft(currentDraft.tmp_uuid);
                window.location.href = '/rxnpwa/horas';
            } catch (err) {
                showStatus('Error al borrar: ' + err.message, 'danger');
            }
        });
    }

    function renderFromDraft() {
        const cab = currentDraft.cabecera;
        document.getElementById('rxnpwa-horas-inicio').value = cab.fecha_inicio || '';
        document.getElementById('rxnpwa-horas-fin').value = cab.fecha_finalizado || '';
        document.getElementById('rxnpwa-horas-concepto').value = cab.concepto || '';
        const sel = document.getElementById('rxnpwa-horas-tratativa');
        if (sel) sel.value = cab.tratativa_id ? String(cab.tratativa_id) : '';
        document.getElementById('rxnpwa-horas-descuento').value = formatDurationFromSecs(Number(cab.descuento_segundos || 0));
        document.getElementById('rxnpwa-horas-motivo').value = cab.motivo_descuento || '';
        updateDurationLabels();
    }

    function updateDurationLabels() {
        const cab = currentDraft.cabecera;
        const dur = document.getElementById('rxnpwa-horas-duracion');
        const net = document.getElementById('rxnpwa-horas-net-time');
        if (!dur || !net) return;
        if (!cab.fecha_inicio || !cab.fecha_finalizado) {
            dur.textContent = 'Duración: —';
            net.textContent = 'Tiempo neto: — (necesitás inicio y fin)';
            return;
        }
        const ini = parseLocalIso(cab.fecha_inicio);
        const fin = parseLocalIso(cab.fecha_finalizado);
        if (!ini || !fin || fin < ini) {
            dur.textContent = 'Duración: — (fin debe ser posterior al inicio)';
            net.textContent = 'Tiempo neto: —';
            return;
        }
        const bruto = Math.floor((fin - ini) / 1000);
        const desc = Number(cab.descuento_segundos || 0);
        const neto = Math.max(0, bruto - desc);
        dur.textContent = `Duración: ${formatDuration(bruto)}`;
        net.textContent = `Bruto: ${formatDuration(bruto)} · Descuento: ${formatDuration(desc)} · Neto: ${formatDuration(neto)} (${(neto / 3600).toFixed(2)} h)`;
    }

    /* ---------- Adjuntos ---------- */

    function wireAttachments() {
        document.getElementById('rxnpwa-horas-att-photo').addEventListener('click', () => {
            document.getElementById('rxnpwa-horas-att-photo-input').click();
        });
        document.getElementById('rxnpwa-horas-att-file').addEventListener('click', () => {
            document.getElementById('rxnpwa-horas-att-file-input').click();
        });
        document.getElementById('rxnpwa-horas-att-photo-input').addEventListener('change', (e) => {
            handleFiles(e.target.files);
            e.target.value = '';
        });
        document.getElementById('rxnpwa-horas-att-file-input').addEventListener('change', (e) => {
            handleFiles(e.target.files);
            e.target.value = '';
        });
    }

    async function handleFiles(fileList) {
        const files = Array.from(fileList || []);
        for (const f of files) {
            try {
                let blob = f;
                let compressed = false;
                if (f.type && f.type.indexOf('image/') === 0 && window.RxnPwaImageCompressor && typeof window.RxnPwaImageCompressor.compress === 'function') {
                    try {
                        const result = await window.RxnPwaImageCompressor.compress(f);
                        if (result && result.blob) { blob = result.blob; compressed = !!result.compressed; }
                    } catch (e) { /* fallback a archivo original */ }
                }
                await window.RxnPwaHorasDraftsStore.addAttachment({
                    tmpUuid: currentDraft.tmp_uuid,
                    name: f.name || 'archivo',
                    mime: f.type || 'application/octet-stream',
                    blob,
                    compressed,
                });
            } catch (err) {
                showStatus('Error agregando adjunto: ' + err.message, 'danger');
            }
        }
        await renderAttachments();
    }

    async function renderAttachments() {
        const container = document.getElementById('rxnpwa-horas-att-list');
        const counter = document.getElementById('rxnpwa-horas-att-count');
        if (!container) return;
        const list = await window.RxnPwaHorasDraftsStore.listAttachments(currentDraft.tmp_uuid);
        if (counter) counter.textContent = String(list.length);
        if (list.length === 0) {
            container.innerHTML = '<div class="text-muted small">Sin adjuntos cargados.</div>';
            return;
        }
        container.innerHTML = list.map(a => {
            const sizeKb = Math.round((a.size || 0) / 1024);
            const isImg = a.mime && a.mime.indexOf('image/') === 0;
            const preview = isImg ? `<img class="rxnpwa-att-thumb" alt="" data-att-id="${a.id}">` : `<i class="bi bi-file-earmark fs-3"></i>`;
            const statusBadge = a.sync_status === 'uploaded'
                ? '<span class="badge bg-success">Subido</span>'
                : '<span class="badge bg-warning">Pendiente</span>';
            return `
                <div class="d-flex align-items-center gap-2 border rounded p-2">
                    <div style="width:48px;height:48px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:rgba(255,255,255,0.05);border-radius:6px;">
                        ${preview}
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-truncate fw-semibold small">${escape(a.name || 'archivo')}</div>
                        <div class="small text-muted">${sizeKb} KB · ${statusBadge}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-att-remove="${a.id}" ${a.sync_status === 'uploaded' ? 'disabled title="Ya está en el server, borralo desde el web"' : ''}>
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `;
        }).join('');

        // Renderizar thumbnails
        for (const a of list) {
            if (!a.mime || a.mime.indexOf('image/') !== 0) continue;
            const img = container.querySelector(`img.rxnpwa-att-thumb[data-att-id="${a.id}"]`);
            if (img) {
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'cover';
                img.src = URL.createObjectURL(a.blob);
            }
        }

        container.querySelectorAll('button[data-att-remove]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = parseInt(btn.getAttribute('data-att-remove') || '0', 10);
                if (!id || !confirm('¿Quitar este adjunto del borrador?')) return;
                try {
                    await window.RxnPwaHorasDraftsStore.removeAttachment(id);
                    await renderAttachments();
                } catch (err) {
                    showStatus('Error: ' + err.message, 'danger');
                }
            });
        });
    }

    /* ---------- Auto-save ---------- */

    function scheduleSave() {
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(() => doSave(false), SAVE_DEBOUNCE_MS);
        updateTitleSubtitle(null, 'Guardando…');
    }

    async function saveNow() {
        if (saveTimer) { clearTimeout(saveTimer); saveTimer = null; }
        await doSave(true);
    }

    async function doSave(manual) {
        try {
            await window.RxnPwaHorasDraftsStore.saveDraft(currentDraft);
            updateTitleSubtitle(null, manual ? 'Guardado ✓' : 'Borrador guardado');
        } catch (err) {
            showStatus('Error al guardar: ' + err.message, 'danger');
        }
    }

    /* ---------- Sync ---------- */

    function wireSync() {
        const btn = document.getElementById('rxnpwa-horas-sync');
        if (btn) {
            btn.addEventListener('click', async () => {
                btn.disabled = true;
                try {
                    const errs = validateBeforeSync();
                    if (errs.length) {
                        showStatus('Faltan datos: ' + errs.join(' · '), 'warning');
                        btn.disabled = false;
                        return;
                    }
                    await saveNow();
                    captureGeoIfMissing();
                    await window.RxnPwaHorasSyncQueue.enqueue(currentDraft.tmp_uuid);
                    refreshSyncState();
                } catch (err) {
                    showStatus('Error al sincronizar: ' + err.message, 'danger');
                } finally {
                    btn.disabled = false;
                }
            });
        }

        if (window.RxnPwaHorasSyncQueue) {
            window.RxnPwaHorasSyncQueue.subscribe(async () => {
                const fresh = await window.RxnPwaHorasDraftsStore.getDraft(currentDraft.tmp_uuid);
                if (fresh) currentDraft = fresh;
                refreshSyncState();
                await renderAttachments();
            });
        }

        window.addEventListener('online', refreshSyncState);
        window.addEventListener('offline', refreshSyncState);
        refreshSyncState();
    }

    function validateBeforeSync() {
        const errs = [];
        const cab = currentDraft.cabecera;
        if (!cab.fecha_inicio) errs.push('Inicio');
        if (!cab.fecha_finalizado) errs.push('Fin (cerrá el cronómetro)');
        if (cab.fecha_inicio && cab.fecha_finalizado) {
            const ini = parseLocalIso(cab.fecha_inicio);
            const fin = parseLocalIso(cab.fecha_finalizado);
            if (ini && fin && fin <= ini) errs.push('Fin debe ser posterior al inicio');
        }
        if (Number(cab.descuento_segundos || 0) > 0 && !String(cab.motivo_descuento || '').trim()) {
            errs.push('Motivo del descuento');
        }
        return errs;
    }

    function refreshSyncState() {
        const stateEl = document.getElementById('rxnpwa-horas-form-sync-state');
        const msgEl = document.getElementById('rxnpwa-horas-sync-message');
        const btn = document.getElementById('rxnpwa-horas-sync');
        const badge = document.getElementById('rxnpwa-horas-form-net-badge');

        if (badge) {
            if (navigator.onLine) {
                badge.className = 'badge bg-success small';
                badge.innerHTML = '<i class="bi bi-wifi"></i> Online';
            } else {
                badge.className = 'badge bg-secondary small';
                badge.innerHTML = '<i class="bi bi-wifi-off"></i> Offline';
            }
        }

        const status = currentDraft.status || 'draft';
        const labels = {
            'draft': 'Borrador local — sin sincronizar.',
            'pending_sync': 'En cola para sincronizar…',
            'syncing': 'Enviando al servidor…',
            'synced': '✓ Sincronizado al servidor (turno #' + (currentDraft.server_id || '?') + ').',
            'error': 'Error al sincronizar — reintentá.',
        };
        if (stateEl) stateEl.textContent = labels[status] || labels.draft;

        if (msgEl) {
            if (status === 'error' && currentDraft.last_error) {
                msgEl.innerHTML = '<i class="bi bi-exclamation-triangle text-danger"></i> ' + escape(currentDraft.last_error);
            } else {
                msgEl.textContent = '';
            }
        }

        if (btn) {
            const allow = status !== 'syncing' && status !== 'synced' && navigator.onLine;
            btn.disabled = !allow;
            btn.innerHTML = (status === 'error')
                ? '<i class="bi bi-arrow-clockwise"></i> Reintentar'
                : '<i class="bi bi-cloud-upload"></i> Sincronizar al servidor';
        }
    }

    function captureGeoIfMissing() {
        if (!window.RxnPwaGeoGate) return;
        const geo = window.RxnPwaGeoGate.getCurrentGeo();
        if (geo && geo.source) {
            currentDraft.geo = {
                lat: geo.lat, lng: geo.lng, accuracy: geo.accuracy,
                source: geo.source, captured_at: geo.captured_at,
            };
        }
    }

    /* ---------- Utils ---------- */

    function parseLocalIso(s) {
        if (!s) return null;
        const norm = String(s).replace(' ', 'T');
        const d = new Date(norm);
        return Number.isNaN(d.getTime()) ? null : d;
    }

    function parseDuration(value) {
        if (value === '' || value == null) return 0;
        const m = /^(\d{1,3}):([0-5]?\d):([0-5]?\d)$/.exec(value);
        if (!m) return null;
        return Number(m[1]) * 3600 + Number(m[2]) * 60 + Number(m[3]);
    }

    function formatDuration(totalSeconds) {
        const t = Math.max(0, Math.floor(totalSeconds || 0));
        const h = Math.floor(t / 3600), m = Math.floor((t % 3600) / 60), s = t % 60;
        return [h, m, s].map(n => String(n).padStart(2, '0')).join(':');
    }
    function formatDurationFromSecs(secs) { return formatDuration(secs); }

    function showStatus(msg, kind = 'info') {
        const el = document.getElementById('rxnpwa-horas-form-status');
        if (!el) { console[kind === 'danger' ? 'error' : 'log'](msg); return; }
        const cls = { info: 'info', danger: 'danger', warning: 'warning', success: 'success' }[kind] || 'info';
        el.innerHTML = `<div class="alert alert-${cls} mb-3">${escape(msg)}</div>`;
        if (kind !== 'danger') setTimeout(() => { el.innerHTML = ''; }, 4000);
    }
    function clearStatus() {
        const el = document.getElementById('rxnpwa-horas-form-status');
        if (el) el.innerHTML = '';
    }

    function updateTitleSubtitle(title, subtitle) {
        if (title) {
            const t = document.getElementById('rxnpwa-horas-form-title');
            if (t) t.textContent = title;
        }
        if (subtitle) {
            const s = document.getElementById('rxnpwa-horas-form-subtitle');
            if (s) s.textContent = subtitle;
        }
    }

    function formatStatus(status) {
        return ({
            'draft': 'Borrador local',
            'pending_sync': 'Pendiente de envío',
            'syncing': 'Enviando…',
            'synced': 'Sincronizado',
            'error': 'Error de envío',
        }[status]) || 'Borrador local';
    }

    function escape(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
})();
