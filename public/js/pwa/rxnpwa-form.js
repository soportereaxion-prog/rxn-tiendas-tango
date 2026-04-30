// RXN PWA — Lógica del form mobile de Presupuestos (Fase 2 — 100% offline-first).
//
// Carga: catalog (clientes, articulos, listas, depositos, clasificaciones, precios, stocks)
//        desde IndexedDB. Si la DB está vacía, redirect al shell para sincronizar.
//
// Estado: 1 draft en IndexedDB store presupuestos_drafts (keyPath tmp_uuid).
// Si la URL no trae tmp_uuid, crea uno nuevo al primer save.
// Si trae tmp_uuid en data-tmp-uuid del <main>, lo carga.
//
// Save: onclick botón Guardar → saveDraft con todos los campos serializados + total recalculado.
// Auto-save: cada cambio de input/select/textarea → debounce 1.5s → saveDraft silencioso.

(function () {
    'use strict';

    let catalog = null; // { clientes, articulos, listas_precio, depositos, clasificaciones_pds, precios, stocks }
    let draft = null;
    let selectedRowArticulo = null; // estado del modal de renglón
    let selectedCliente = null;

    const MAX_ATTACHMENTS = 10;
    const ATT_WARNING_AT = 5;

    document.addEventListener('DOMContentLoaded', init);

    async function init() {
        try {
            await loadCatalog();
            await loadOrCreateDraft();
            renderAllSections();
            wireEvents();
        } catch (err) {
            console.error('[rxnpwa-form] init error:', err);
            showStatus('error', 'No se pudo cargar el form: ' + err.message);
        }
    }

    // ---------- Carga catálogo ----------

    async function loadCatalog() {
        const meta = await window.RxnPwaCatalogStore.getMeta();
        if (!meta) {
            window.location.href = '/rxnpwa/presupuestos';
            throw new Error('Sin catálogo offline. Volvé al shell para sincronizar.');
        }
        catalog = await window.RxnPwaCatalogStore.loadAll();
    }

    // ---------- Carga/crea draft ----------

    async function loadOrCreateDraft() {
        const main = document.querySelector('main[data-tmp-uuid]');
        const empresaId = parseInt(main.dataset.empresaId, 10) || 0;
        const tmpUuid = (main.dataset.tmpUuid || '').trim();

        if (tmpUuid) {
            const existing = await window.RxnPwaDraftsStore.getDraft(tmpUuid);
            if (existing) {
                draft = existing;
                document.getElementById('rxnpwa-form-title').textContent = 'Editar presupuesto';
                document.getElementById('rxnpwa-form-subtitle').textContent =
                    'Borrador local · ' + new Date(existing.updated_at).toLocaleString('es-AR');
                return;
            }
        }
        draft = await window.RxnPwaDraftsStore.createDraft({ empresaId });
        // Refrescar la URL al UUID nuevo así el reload edita el mismo draft.
        history.replaceState({}, '', '/rxnpwa/presupuestos/editar/' + encodeURIComponent(draft.tmp_uuid));
        main.dataset.tmpUuid = draft.tmp_uuid;
    }

    // ---------- Render ----------

    function renderAllSections() {
        renderClienteSelected();
        renderListaSelect();
        renderDepositoSelect();
        renderClasificacionSelect();
        document.getElementById('rxnpwa-comentarios').value = draft.cabecera.comentarios || '';
        document.getElementById('rxnpwa-observaciones').value = draft.cabecera.observaciones || '';
        renderRenglones();
        renderAttachments();
        updateObsCounter();
    }

    function renderClienteSelected() {
        const el = document.getElementById('rxnpwa-cliente-selected');
        if (draft.cabecera.cliente_data) {
            const c = draft.cabecera.cliente_data;
            el.innerHTML = `<i class="bi bi-check-circle text-success"></i> <strong>${escape(c.razon_social || '')}</strong> · ${escape(c.documento || '')} · cód ${escape(c.codigo_tango || '—')}`;
        } else {
            el.innerHTML = '';
        }
    }

    function renderListaSelect() {
        const sel = document.getElementById('rxnpwa-lista');
        sel.innerHTML = '<option value="">— Seleccionar —</option>';
        (catalog.listas_precio || []).forEach((l) => {
            const opt = document.createElement('option');
            opt.value = l.codigo;
            opt.textContent = l.codigo + ' — ' + l.descripcion;
            if (l.codigo === draft.cabecera.lista_codigo) opt.selected = true;
            sel.appendChild(opt);
        });
    }

    function renderDepositoSelect() {
        const sel = document.getElementById('rxnpwa-deposito');
        sel.innerHTML = '<option value="">— Seleccionar —</option>';
        (catalog.depositos || []).forEach((d) => {
            const opt = document.createElement('option');
            opt.value = d.codigo;
            opt.textContent = d.codigo + ' — ' + d.descripcion;
            if (d.codigo === draft.cabecera.deposito_codigo) opt.selected = true;
            sel.appendChild(opt);
        });
    }

    function renderClasificacionSelect() {
        const sel = document.getElementById('rxnpwa-clasificacion');
        sel.innerHTML = '<option value="">— Seleccionar —</option>';
        (catalog.clasificaciones_pds || []).forEach((c) => {
            const opt = document.createElement('option');
            opt.value = c.codigo;
            opt.textContent = c.codigo + ' — ' + c.descripcion;
            if (c.codigo === draft.cabecera.clasificacion_codigo) opt.selected = true;
            sel.appendChild(opt);
        });
    }

    function renderRenglones() {
        const list = document.getElementById('rxnpwa-renglones-list');
        if (!draft.renglones || draft.renglones.length === 0) {
            list.innerHTML = '<div class="rxnpwa-placeholder small">Sin renglones todavía. Tocá <strong>Agregar</strong>.</div>';
        } else {
            list.innerHTML = '';
            draft.renglones.forEach((r, idx) => {
                const card = document.createElement('div');
                card.className = 'rxnpwa-renglon';
                card.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="fw-bold">${escape(r.descripcion || r.codigo)}</div>
                            <div class="small text-muted">cód ${escape(r.codigo || '—')} · ${formatNum(r.cantidad)} u × $ ${formatNum(r.precio_unitario)}${r.descuento_pct > 0 ? ' · -' + formatNum(r.descuento_pct) + '%' : ''}</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">$ ${formatNum(r.subtotal)}</div>
                            <button type="button" class="btn btn-sm btn-outline-danger mt-1" data-rxnpwa-renglon-del="${idx}" title="Borrar">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
                list.appendChild(card);
            });
        }
        document.getElementById('rxnpwa-total').textContent = formatNum(window.RxnPwaDraftsStore.computeTotal(draft.renglones));
    }

    async function renderAttachments() {
        const list = document.getElementById('rxnpwa-attachments-list');
        const atts = await window.RxnPwaDraftsStore.listAttachments(draft.tmp_uuid);

        list.innerHTML = '';
        let totalSize = 0;

        atts.forEach((a) => {
            totalSize += a.size;
            const isImg = (a.mime || '').startsWith('image/');
            const item = document.createElement('div');
            item.className = 'rxnpwa-att-item d-flex align-items-center gap-2';
            const thumb = document.createElement(isImg ? 'img' : 'div');
            if (isImg) {
                thumb.className = 'rxnpwa-att-thumb';
                thumb.src = URL.createObjectURL(a.blob);
                thumb.onload = () => URL.revokeObjectURL(thumb.src);
            } else {
                thumb.className = 'rxnpwa-att-thumb d-flex align-items-center justify-content-center';
                thumb.innerHTML = '<i class="bi bi-file-earmark fs-3"></i>';
            }
            item.appendChild(thumb);

            const meta = document.createElement('div');
            meta.className = 'flex-grow-1 small';
            meta.innerHTML = `<div class="text-truncate" style="max-width: 60vw;"><strong>${escape(a.name)}</strong></div>
                <div class="text-muted">${formatBytes(a.size)}${a.compressed ? ' · comprimida' : ''}</div>`;
            item.appendChild(meta);

            const del = document.createElement('button');
            del.type = 'button';
            del.className = 'btn btn-sm btn-outline-danger';
            del.innerHTML = '<i class="bi bi-x-lg"></i>';
            del.dataset.rxnpwaAttDel = String(a.id);
            item.appendChild(del);

            list.appendChild(item);
        });

        document.getElementById('rxnpwa-att-count').textContent = String(atts.length);
        document.getElementById('rxnpwa-att-total-size').textContent = formatBytes(totalSize);
        document.getElementById('rxnpwa-attachments-warning').classList.toggle('d-none', atts.length < ATT_WARNING_AT);
    }

    // ---------- Eventos ----------

    function wireEvents() {
        // Save
        document.getElementById('rxnpwa-form-save').addEventListener('click', () => saveDraft(false));
        document.getElementById('rxnpwa-form-save-bottom').addEventListener('click', () => saveDraft(false));

        // Delete
        document.getElementById('rxnpwa-form-delete').addEventListener('click', deleteDraft);

        // Cliente picker
        const clienteInput = document.getElementById('rxnpwa-cliente');
        clienteInput.addEventListener('input', debounce(() => renderClienteResults(clienteInput.value), 200));
        document.getElementById('rxnpwa-cliente-results').addEventListener('click', onClienteResultClick);
        document.getElementById('rxnpwa-cliente-clear').addEventListener('click', () => {
            draft.cabecera.cliente_id = null;
            draft.cabecera.cliente_data = null;
            clienteInput.value = '';
            renderClienteSelected();
            document.getElementById('rxnpwa-cliente-results').innerHTML = '';
            scheduleAutoSave();
        });

        // Cabecera selects + textareas
        document.getElementById('rxnpwa-lista').addEventListener('change', (e) => {
            draft.cabecera.lista_codigo = e.target.value;
            const lista = (catalog.listas_precio || []).find((l) => l.codigo === e.target.value);
            draft.cabecera.lista_data = lista || null;
            // Recalcular precios de los renglones existentes con la nueva lista
            recalcRenglonesPrecio();
            renderRenglones();
            scheduleAutoSave();
        });
        document.getElementById('rxnpwa-deposito').addEventListener('change', (e) => {
            draft.cabecera.deposito_codigo = e.target.value;
            scheduleAutoSave();
        });
        document.getElementById('rxnpwa-clasificacion').addEventListener('change', (e) => {
            draft.cabecera.clasificacion_codigo = e.target.value;
            scheduleAutoSave();
        });
        document.getElementById('rxnpwa-comentarios').addEventListener('input', (e) => {
            draft.cabecera.comentarios = e.target.value;
            updateObsCounter();
            scheduleAutoSave();
        });
        document.getElementById('rxnpwa-observaciones').addEventListener('input', (e) => {
            draft.cabecera.observaciones = e.target.value;
            updateObsCounter();
            scheduleAutoSave();
        });

        // Renglones
        document.getElementById('rxnpwa-renglon-add').addEventListener('click', openRenglonModal);
        document.getElementById('rxnpwa-renglones-list').addEventListener('click', onRenglonListClick);

        // Modal renglón
        const articuloInput = document.getElementById('rxnpwa-renglon-articulo');
        articuloInput.addEventListener('input', debounce(() => renderArticuloResults(articuloInput.value), 200));
        document.getElementById('rxnpwa-renglon-articulo-results').addEventListener('click', onArticuloResultClick);
        ['rxnpwa-renglon-cantidad', 'rxnpwa-renglon-descuento', 'rxnpwa-renglon-precio'].forEach((id) => {
            document.getElementById(id).addEventListener('input', recomputeRenglonSubtotal);
        });
        document.getElementById('rxnpwa-renglon-confirm').addEventListener('click', confirmRenglon);

        // Attachments
        document.getElementById('rxnpwa-att-photo').addEventListener('click', () => {
            document.getElementById('rxnpwa-att-photo-input').click();
        });
        document.getElementById('rxnpwa-att-file').addEventListener('click', () => {
            document.getElementById('rxnpwa-att-file-input').click();
        });
        document.getElementById('rxnpwa-att-photo-input').addEventListener('change', onAttachmentInput);
        document.getElementById('rxnpwa-att-file-input').addEventListener('change', onAttachmentInput);
        document.getElementById('rxnpwa-attachments-list').addEventListener('click', onAttachmentDelete);
    }

    // ---------- Cliente picker ----------

    function renderClienteResults(query) {
        const results = document.getElementById('rxnpwa-cliente-results');
        const q = (query || '').trim().toLowerCase();
        if (q.length < 2) {
            results.innerHTML = '';
            return;
        }
        const matches = (catalog.clientes || [])
            .filter((c) =>
                (c.razon_social || '').toLowerCase().includes(q) ||
                (c.documento || '').toLowerCase().includes(q) ||
                (c.codigo_tango || '').toLowerCase().includes(q)
            )
            .slice(0, 30);
        results.innerHTML = matches.map((c) => `
            <div class="rxnpwa-suggest-item" data-cliente-id="${c.id}">
                <strong>${escape(c.razon_social || '')}</strong>
                <div class="small text-muted">${escape(c.documento || '')} · cód ${escape(c.codigo_tango || '—')}</div>
            </div>
        `).join('') || '<div class="small text-muted p-2">Sin resultados.</div>';
    }

    function onClienteResultClick(e) {
        const item = e.target.closest('[data-cliente-id]');
        if (!item) return;
        const id = parseInt(item.dataset.clienteId, 10);
        const cliente = (catalog.clientes || []).find((c) => c.id === id);
        if (!cliente) return;
        draft.cabecera.cliente_id = cliente.id;
        draft.cabecera.cliente_data = cliente;
        document.getElementById('rxnpwa-cliente').value = cliente.razon_social || '';
        document.getElementById('rxnpwa-cliente-results').innerHTML = '';
        renderClienteSelected();
        scheduleAutoSave();
    }

    // ---------- Renglón modal ----------

    let renglonModal = null;

    function openRenglonModal() {
        selectedRowArticulo = null;
        document.getElementById('rxnpwa-renglon-articulo').value = '';
        document.getElementById('rxnpwa-renglon-articulo-results').innerHTML = '';
        document.getElementById('rxnpwa-renglon-articulo-selected').innerHTML = '';
        document.getElementById('rxnpwa-renglon-cantidad').value = '1';
        document.getElementById('rxnpwa-renglon-descuento').value = '0';
        document.getElementById('rxnpwa-renglon-precio').value = '0';
        document.getElementById('rxnpwa-renglon-precio-origin').textContent = '—';
        document.getElementById('rxnpwa-renglon-stock-info').textContent = '';
        document.getElementById('rxnpwa-renglon-subtotal').textContent = '0,00';

        if (!renglonModal) {
            renglonModal = new bootstrap.Modal(document.getElementById('rxnpwa-renglon-modal'));
        }
        renglonModal.show();
    }

    function renderArticuloResults(query) {
        const results = document.getElementById('rxnpwa-renglon-articulo-results');
        const q = (query || '').trim().toLowerCase();
        if (q.length < 2) {
            results.innerHTML = '';
            return;
        }
        const matches = (catalog.articulos || [])
            .filter((a) =>
                (a.codigo_externo || '').toLowerCase().includes(q) ||
                (a.nombre || '').toLowerCase().includes(q) ||
                (a.descripcion || '').toLowerCase().includes(q)
            )
            .slice(0, 30);
        results.innerHTML = matches.map((a) => `
            <div class="rxnpwa-suggest-item" data-articulo-id="${a.id}">
                <strong>${escape(a.nombre || a.descripcion || '')}</strong>
                <div class="small text-muted">cód ${escape(a.codigo_externo || '—')}</div>
            </div>
        `).join('') || '<div class="small text-muted p-2">Sin resultados.</div>';
    }

    function onArticuloResultClick(e) {
        const item = e.target.closest('[data-articulo-id]');
        if (!item) return;
        const id = parseInt(item.dataset.articuloId, 10);
        const art = (catalog.articulos || []).find((a) => a.id === id);
        if (!art) return;
        selectedRowArticulo = art;

        document.getElementById('rxnpwa-renglon-articulo').value = art.nombre || art.descripcion || '';
        document.getElementById('rxnpwa-renglon-articulo-results').innerHTML = '';
        document.getElementById('rxnpwa-renglon-articulo-selected').innerHTML =
            `<i class="bi bi-check-circle text-success"></i> ${escape(art.nombre || art.descripcion || '')} · cód ${escape(art.codigo_externo || '—')}`;

        // Auto-precio según lista
        const { price, origin } = resolvePrice(art, draft.cabecera.lista_codigo);
        document.getElementById('rxnpwa-renglon-precio').value = String(price);
        document.getElementById('rxnpwa-renglon-precio-origin').textContent = origin;

        // Stock según depósito
        const stock = resolveStock(art, draft.cabecera.deposito_codigo);
        document.getElementById('rxnpwa-renglon-stock-info').textContent =
            stock !== null ? `Stock disponible (${draft.cabecera.deposito_codigo || 'sin depósito'}): ${formatNum(stock)} u` : 'Sin info de stock para este depósito.';

        recomputeRenglonSubtotal();
    }

    function resolvePrice(articulo, listaCodigo) {
        if (listaCodigo) {
            const match = (catalog.precios || []).find((p) => p.articulo_id === articulo.id && p.lista_codigo === listaCodigo);
            if (match && match.precio !== null && match.precio !== undefined) {
                return { price: parseFloat(match.precio), origin: 'Lista ' + listaCodigo };
            }
        }
        // Fallback: precio_lista_1, después precio
        if (articulo.precio_lista_1) return { price: parseFloat(articulo.precio_lista_1), origin: 'Fallback lista 1' };
        if (articulo.precio) return { price: parseFloat(articulo.precio), origin: 'Fallback precio base' };
        return { price: 0, origin: 'Sin precio · cargar manual' };
    }

    function resolveStock(articulo, depositoCodigo) {
        if (!depositoCodigo) return null;
        const match = (catalog.stocks || []).find((s) => s.articulo_id === articulo.id && s.deposito_codigo === depositoCodigo);
        return match ? parseFloat(match.stock_actual) : null;
    }

    function recomputeRenglonSubtotal() {
        const cant = parseFloat(document.getElementById('rxnpwa-renglon-cantidad').value) || 0;
        const desc = parseFloat(document.getElementById('rxnpwa-renglon-descuento').value) || 0;
        const precio = parseFloat(document.getElementById('rxnpwa-renglon-precio').value) || 0;
        const sub = cant * precio * (1 - desc / 100);
        document.getElementById('rxnpwa-renglon-subtotal').textContent = formatNum(sub);
    }

    function confirmRenglon() {
        if (!selectedRowArticulo) {
            alert('Tenés que seleccionar un artículo.');
            return;
        }
        const cant = parseFloat(document.getElementById('rxnpwa-renglon-cantidad').value) || 0;
        const desc = parseFloat(document.getElementById('rxnpwa-renglon-descuento').value) || 0;
        const precio = parseFloat(document.getElementById('rxnpwa-renglon-precio').value) || 0;
        if (cant <= 0) {
            alert('Cantidad tiene que ser mayor a 0.');
            return;
        }
        const subtotal = cant * precio * (1 - desc / 100);
        draft.renglones.push({
            row_uuid: window.RxnPwaDraftsStore.generateUuid(),
            articulo_id: selectedRowArticulo.id,
            codigo: selectedRowArticulo.codigo_externo,
            descripcion: selectedRowArticulo.nombre || selectedRowArticulo.descripcion,
            cantidad: cant,
            precio_unitario: precio,
            descuento_pct: desc,
            subtotal,
        });
        renderRenglones();
        scheduleAutoSave();
        renglonModal.hide();
    }

    function onRenglonListClick(e) {
        const del = e.target.closest('[data-rxnpwa-renglon-del]');
        if (!del) return;
        const idx = parseInt(del.dataset.rxnpwaRenglonDel, 10);
        if (Number.isInteger(idx)) {
            draft.renglones.splice(idx, 1);
            renderRenglones();
            scheduleAutoSave();
        }
    }

    function recalcRenglonesPrecio() {
        // Cuando el operador cambia la lista, recalculamos los precios sugeridos
        // sin pisar los manuales (si la origin del precio era manual no lo pisamos).
        // En v1 simple: pisamos todos. Iteramos cuando se vea uso.
        (draft.renglones || []).forEach((r) => {
            const art = (catalog.articulos || []).find((a) => a.id === r.articulo_id);
            if (!art) return;
            const { price } = resolvePrice(art, draft.cabecera.lista_codigo);
            r.precio_unitario = price;
            r.subtotal = r.cantidad * price * (1 - (r.descuento_pct || 0) / 100);
        });
    }

    // ---------- Attachments ----------

    async function onAttachmentInput(e) {
        const files = Array.from(e.target.files || []);
        e.target.value = ''; // reset así el mismo file puede reagregarse

        const current = await window.RxnPwaDraftsStore.countAttachments(draft.tmp_uuid);
        if (current + files.length > MAX_ATTACHMENTS) {
            alert(`Máximo ${MAX_ATTACHMENTS} adjuntos por presupuesto. Tenés ${current}, intentás sumar ${files.length}.`);
            return;
        }

        showStatus('info', 'Procesando ' + files.length + ' adjunto(s)...');
        for (const file of files) {
            try {
                const result = await window.RxnPwaImageCompressor.compress(file);
                await window.RxnPwaDraftsStore.addAttachment({
                    tmpUuid: draft.tmp_uuid,
                    name: result.name,
                    mime: result.mime,
                    blob: result.blob,
                    compressed: result.compressed,
                });
            } catch (err) {
                console.error('[rxnpwa-form] error procesando attachment:', err);
                showStatus('error', 'Error procesando archivo: ' + err.message);
            }
        }
        await renderAttachments();
        clearStatus();
    }

    async function onAttachmentDelete(e) {
        const btn = e.target.closest('[data-rxnpwa-att-del]');
        if (!btn) return;
        const id = parseInt(btn.dataset.rxnpwaAttDel, 10);
        if (!confirm('¿Borrar este adjunto?')) return;
        await window.RxnPwaDraftsStore.removeAttachment(id);
        await renderAttachments();
    }

    // ---------- Save / Delete ----------

    let saveTimer = null;
    function scheduleAutoSave() {
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(() => saveDraft(true), 1500);
    }

    async function saveDraft(silent) {
        try {
            await window.RxnPwaDraftsStore.saveDraft(draft);
            if (!silent) showStatus('success', 'Borrador guardado localmente.');
            document.getElementById('rxnpwa-form-subtitle').textContent =
                'Borrador local · guardado ' + new Date().toLocaleTimeString('es-AR');
        } catch (err) {
            console.error('[rxnpwa-form] save error:', err);
            showStatus('error', 'No se pudo guardar: ' + err.message);
        }
    }

    async function deleteDraft() {
        if (!confirm('¿Descartar este borrador y todos sus adjuntos? Esta acción no se puede deshacer.')) return;
        try {
            await window.RxnPwaDraftsStore.deleteDraft(draft.tmp_uuid);
            window.location.href = '/rxnpwa/presupuestos';
        } catch (err) {
            showStatus('error', 'No se pudo borrar: ' + err.message);
        }
    }

    // ---------- UI helpers ----------

    function updateObsCounter() {
        const c = (draft.cabecera.comentarios || '').trim();
        const o = (draft.cabecera.observaciones || '').trim();
        const total = (c.length > 0 && o.length > 0) ? c.length + o.length + 3 /* ' | ' */ : c.length + o.length;
        const el = document.getElementById('rxnpwa-obs-counter');
        el.textContent = total + ' / 950 chars a Tango';
        el.className = total > 950 ? 'text-warning fw-bold' : 'text-muted';
    }

    function showStatus(type, msg) {
        const el = document.getElementById('rxnpwa-form-status');
        const cls = { success: 'success', info: 'info', warning: 'warning', error: 'danger' }[type] || 'info';
        el.innerHTML = `<div class="alert alert-${cls} mb-3" role="status">${escape(msg)}</div>`;
        if (type === 'success' || type === 'info') {
            setTimeout(() => clearStatus(), 2500);
        }
    }
    function clearStatus() {
        document.getElementById('rxnpwa-form-status').innerHTML = '';
    }

    function debounce(fn, wait) {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(null, args), wait);
        };
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

    function formatBytes(bytes) {
        bytes = Number(bytes) || 0;
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }
})();
