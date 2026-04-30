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

    function getCurrentEmpresaId() {
        const tag = document.querySelector('meta[name="rxn-empresa-id"]');
        const id = tag ? parseInt(tag.getAttribute('content'), 10) : 0;
        return Number.isFinite(id) && id > 0 ? id : 0;
    }

    async function loadCatalog() {
        const meta = await window.RxnPwaCatalogStore.getMeta();
        const currentEmpresa = getCurrentEmpresaId();

        // Catálogo vacío → redirect al shell para resync.
        if (!meta) {
            window.location.href = '/rxnpwa/presupuestos';
            throw new Error('Sin catálogo offline. Volvé al shell para sincronizar.');
        }
        // Catálogo de otra empresa → wipe + redirect.
        if (currentEmpresa && meta.empresa_id && Number(meta.empresa_id) !== currentEmpresa) {
            console.warn('[rxnpwa-form] Empresa cambió: catálogo era de ' + meta.empresa_id + ', sesión es ' + currentEmpresa + '. Limpiando.');
            await window.RxnPwaCatalogStore.clearCatalogOnly();
            window.location.href = '/rxnpwa/presupuestos';
            throw new Error('Cambiaste de empresa — sincronizá el catálogo nuevamente.');
        }
        // Schema del catálogo desactualizado → wipe + redirect (release 1.35.0+).
        const expectedSchema = window.RxnPwaCatalogStore.CATALOG_SCHEMA_VERSION;
        if (expectedSchema && meta.schema_version !== expectedSchema) {
            console.warn('[rxnpwa-form] Schema viejo: cache=' + meta.schema_version + ', esperado=' + expectedSchema + '. Limpiando.');
            await window.RxnPwaCatalogStore.clearCatalogOnly();
            window.location.href = '/rxnpwa/presupuestos';
            throw new Error('Hay una versión nueva del catálogo — resincronizá desde el shell.');
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
        renderCondicionSelect();
        renderVendedorSelect();
        renderTransporteSelect();
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

    function renderCondicionSelect() {
        renderCatalogSelect('rxnpwa-condicion', catalog.condiciones_venta || [], draft.cabecera.condicion_codigo);
    }

    function renderVendedorSelect() {
        renderCatalogSelect('rxnpwa-vendedor', catalog.vendedores || [], draft.cabecera.vendedor_codigo);
    }

    function renderTransporteSelect() {
        renderCatalogSelect('rxnpwa-transporte', catalog.transportes || [], draft.cabecera.transporte_codigo);
    }

    /**
     * Helper común para los selects de catálogo comercial. Acepta empty (catalog
     * sin sincronizar) y muestra el código actual aunque no esté en el catálogo
     * (defensivo: el cliente puede tener un código que ya no existe en el catalog).
     */
    function renderCatalogSelect(elementId, items, selectedCodigo) {
        const sel = document.getElementById(elementId);
        if (!sel) return;
        if (!items || items.length === 0) {
            sel.innerHTML = '<option value="">— Sin opciones — corré "Sync Catálogos" —</option>';
            sel.disabled = true;
            return;
        }
        sel.disabled = false;
        sel.innerHTML = '<option value="">— Seleccionar —</option>';
        let foundSelected = false;
        items.forEach((it) => {
            const opt = document.createElement('option');
            opt.value = it.codigo;
            opt.textContent = it.codigo + ' — ' + it.descripcion;
            if (it.codigo === selectedCodigo) {
                opt.selected = true;
                foundSelected = true;
            }
            sel.appendChild(opt);
        });
        // Defensivo: si el código actual del draft no existe en el catalog (cliente
        // venía con un código viejo), igual lo incluimos para no perder la selección.
        if (selectedCodigo && !foundSelected) {
            const opt = document.createElement('option');
            opt.value = selectedCodigo;
            opt.textContent = selectedCodigo + ' (no encontrado en catálogo)';
            opt.selected = true;
            sel.appendChild(opt);
        }
    }

    function renderClasificacionSelect() {
        const sel = document.getElementById('rxnpwa-clasificacion');
        const items = catalog.clasificaciones_pds || [];
        if (items.length === 0) {
            sel.innerHTML = '<option value="">— Sin clasificaciones — corré "Sync Catálogos" en el backoffice —</option>';
            sel.disabled = true;
            return;
        }
        sel.disabled = false;
        sel.innerHTML = '<option value="">— Seleccionar —</option>';
        items.forEach((c) => {
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
                            <div class="d-flex gap-1 justify-content-end mt-1">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-rxnpwa-renglon-edit="${idx}" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-rxnpwa-renglon-del="${idx}" title="Borrar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
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

        // Fullscreen ahora lo maneja el helper global rxn-fullscreen.js — el
        // botón del header tiene data-rxn-fullscreen-toggle y la persistencia
        // queda en localStorage compartida con backoffice y shell PWA.

        // Delete
        document.getElementById('rxnpwa-form-delete').addEventListener('click', deleteDraft);

        // Cliente picker
        const clienteInput = document.getElementById('rxnpwa-cliente');
        clienteInput.addEventListener('input', debounce(() => renderClienteResults(clienteInput.value), 200));
        // Focus sin escribir → mostrar los primeros N clientes (vistazo rápido).
        clienteInput.addEventListener('focus', () => renderClienteResults(clienteInput.value));
        clienteInput.addEventListener('click', () => renderClienteResults(clienteInput.value));
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
        document.getElementById('rxnpwa-condicion').addEventListener('change', (e) => {
            draft.cabecera.condicion_codigo = e.target.value;
            scheduleAutoSave();
        });
        document.getElementById('rxnpwa-vendedor').addEventListener('change', (e) => {
            draft.cabecera.vendedor_codigo = e.target.value;
            scheduleAutoSave();
        });
        document.getElementById('rxnpwa-transporte').addEventListener('change', (e) => {
            draft.cabecera.transporte_codigo = e.target.value;
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

        // Modal renglón — picker artículo
        const articuloInput = document.getElementById('rxnpwa-renglon-articulo');
        articuloInput.addEventListener('input', debounce(() => renderArticuloResults(articuloInput.value), 200));
        // Focus / click sin escribir → mostrar los primeros N artículos.
        articuloInput.addEventListener('focus', () => renderArticuloResults(articuloInput.value));
        articuloInput.addEventListener('click', () => renderArticuloResults(articuloInput.value));
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
        const all = catalog.clientes || [];

        // Sin query: mostrar primeros 30 (vistazo cómodo al hacer focus en mobile).
        // Con query: filtrar por razon_social / documento / codigo_tango.
        const matches = q.length === 0
            ? all.slice(0, 30)
            : all.filter((c) =>
                (c.razon_social || '').toLowerCase().includes(q) ||
                (c.documento || '').toLowerCase().includes(q) ||
                (c.codigo_tango || '').toLowerCase().includes(q)
            ).slice(0, 30);

        if (matches.length === 0) {
            results.innerHTML = '<div class="small text-muted p-2">Sin resultados.</div>';
            return;
        }

        const header = q.length === 0
            ? `<div class="rxnpwa-suggest-header small text-muted px-2 py-1">Primeros ${matches.length} de ${all.length} — escribí para buscar</div>`
            : '';

        results.innerHTML = header + matches.map((c) => `
            <div class="rxnpwa-suggest-item" data-cliente-id="${c.id}">
                <strong>${escape(c.razon_social || '')}</strong>
                <div class="small text-muted">${escape(c.documento || '')} · cód ${escape(c.codigo_tango || '—')}</div>
            </div>
        `).join('');
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
        applyClienteDefaults(cliente);
        scheduleAutoSave();
    }

    /**
     * Auto-completa lista / condición / vendedor / transporte a partir de los
     * códigos comerciales configurados en el cliente. Replica `clientContext`
     * del form web. Solo pisa cuando el campo del draft está vacío — si el
     * vendedor ya eligió manualmente algo, no se sobreescribe.
     *
     * Fallback en cadena para cada campo: campo nativo CRM → campo legacy Tango
     * → vacío. Igual que en PresupuestoController::clientContext.
     */
    function applyClienteDefaults(cliente) {
        const fields = [
            { draftKey: 'lista_codigo',       selectId: 'rxnpwa-lista',       sources: ['id_gva10_lista_precios', 'id_gva10_tango'],   catalogKey: 'listas_precio' },
            { draftKey: 'condicion_codigo',   selectId: 'rxnpwa-condicion',   sources: ['id_gva01_condicion_venta', 'id_gva23_tango'], catalogKey: 'condiciones_venta' },
            { draftKey: 'vendedor_codigo',    selectId: 'rxnpwa-vendedor',    sources: ['id_gva23_vendedor', 'id_gva01_tango'],        catalogKey: 'vendedores' },
            { draftKey: 'transporte_codigo',  selectId: 'rxnpwa-transporte',  sources: ['id_gva24_transporte', 'id_gva24_tango'],      catalogKey: 'transportes' },
        ];
        const applied = [];
        const skipped = [];

        fields.forEach((f) => {
            // No pisar si el operador ya eligió algo.
            if ((draft.cabecera[f.draftKey] || '').trim() !== '') return;

            // Buscar primer código no vacío en las fuentes.
            let codigo = '';
            for (const s of f.sources) {
                const v = (cliente[s] || '').toString().trim();
                if (v !== '') { codigo = v; break; }
            }
            if (codigo === '') return;

            // Verificar que el código exista en el catálogo offline (sino el cliente
            // tiene un código viejo que no está sincronizado — lo dejamos pasar igual,
            // renderCatalogSelect lo muestra como "no encontrado en catálogo").
            const items = catalog[f.catalogKey] || [];
            const found = items.find((it) => it.codigo === codigo);

            draft.cabecera[f.draftKey] = codigo;
            if (f.draftKey === 'lista_codigo') {
                draft.cabecera.lista_data = found || { codigo, descripcion: codigo };
            }
            applied.push(f.draftKey.replace('_codigo', '') + ' = ' + codigo + (found ? '' : ' (no en catálogo)'));
        });

        // Refrescar selects y mensaje.
        renderListaSelect();
        renderCondicionSelect();
        renderVendedorSelect();
        renderTransporteSelect();

        // Recalcular precios si la lista cambió como parte del autofill.
        if (applied.some((s) => s.startsWith('lista'))) {
            recalcRenglonesPrecio();
            renderRenglones();
        }

        const msg = document.getElementById('rxnpwa-cliente-defaults-msg');
        if (msg) {
            if (applied.length > 0) {
                msg.innerHTML = '<i class="bi bi-check-circle text-success"></i> Defaults comerciales del cliente: <strong>' + applied.join(', ') + '</strong>';
            } else {
                msg.innerHTML = '<i class="bi bi-info-circle text-muted"></i> Este cliente no tiene defaults comerciales configurados — completá manualmente.';
            }
        }
    }

    // ---------- Renglón modal ----------

    let renglonModal = null;
    // -1 = modo "agregar nuevo". >=0 = modo "editar el renglón en ese índice".
    let editingRenglonIdx = -1;

    /**
     * Abre el modal de renglón. Si `idx` es un número >=0, entra en modo EDIT y
     * pre-carga los valores del renglón existente. Si es undefined/null/-1, entra
     * en modo ADD (selección de artículo desde 0).
     *
     * Gate: NO se puede agregar/editar renglones sin depósito seleccionado en
     * la cabecera. El depósito determina el stock disponible y el precio,
     * sin él no hay forma de armar un renglón consistente.
     */
    function openRenglonModal(idx) {
        // Gate depósito — bloqueante. Aplica para ambos modos.
        if (!draft.cabecera.deposito_codigo) {
            showStatus('error', 'Seleccioná un depósito en la cabecera antes de agregar artículos.');
            const dep = document.getElementById('rxnpwa-deposito');
            if (dep) {
                dep.classList.add('is-invalid');
                dep.focus();
                setTimeout(() => dep.classList.remove('is-invalid'), 2500);
                dep.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }

        const isEdit = Number.isInteger(idx) && idx >= 0;
        editingRenglonIdx = isEdit ? idx : -1;

        // Título y label del botón de confirmación.
        const title = document.getElementById('rxnpwa-renglon-modal-title');
        const confirmLabel = document.getElementById('rxnpwa-renglon-confirm-label');
        if (title) title.textContent = isEdit ? 'Editar renglón' : 'Agregar renglón';
        if (confirmLabel) confirmLabel.textContent = isEdit ? 'Guardar cambios' : 'Agregar al presupuesto';

        if (isEdit) {
            const r = draft.renglones[idx];
            // Recuperar el artículo del catálogo offline para mantener el contexto
            // (descripción rica, stock visible, etc.). Si el artículo ya no está
            // en catálogo, armamos un objeto mínimo a partir del renglón guardado.
            const fromCatalog = (catalog.articulos || []).find((a) => a.id === r.articulo_id);
            selectedRowArticulo = fromCatalog || {
                id: r.articulo_id,
                codigo_externo: r.codigo,
                nombre: r.descripcion,
                descripcion: r.descripcion,
            };
            document.getElementById('rxnpwa-renglon-articulo').value = selectedRowArticulo.nombre || selectedRowArticulo.descripcion || '';
            document.getElementById('rxnpwa-renglon-articulo-results').innerHTML = '';
            document.getElementById('rxnpwa-renglon-articulo-selected').innerHTML =
                `<i class="bi bi-check-circle text-success"></i> ${escape(selectedRowArticulo.nombre || selectedRowArticulo.descripcion || '')} · cód ${escape(selectedRowArticulo.codigo_externo || '—')}`;
            document.getElementById('rxnpwa-renglon-cantidad').value = String(r.cantidad);
            document.getElementById('rxnpwa-renglon-descuento').value = String(r.descuento_pct || 0);
            document.getElementById('rxnpwa-renglon-precio').value = String(r.precio_unitario);
            document.getElementById('rxnpwa-renglon-precio-origin').textContent = 'Editando precio existente';
            // Stock según depósito (mismo helper que el modo agregar).
            const stock = resolveStock(selectedRowArticulo, draft.cabecera.deposito_codigo);
            document.getElementById('rxnpwa-renglon-stock-info').textContent =
                stock !== null ? `Stock disponible (${draft.cabecera.deposito_codigo}): ${formatNum(stock)} u` : 'Sin info de stock para este depósito.';
            recomputeRenglonSubtotal();
        } else {
            selectedRowArticulo = null;
            document.getElementById('rxnpwa-renglon-articulo').value = '';
            // Sin pre-render: el listado aparece al hacer focus/click en el input.
            document.getElementById('rxnpwa-renglon-articulo-results').innerHTML = '';
            document.getElementById('rxnpwa-renglon-articulo-selected').innerHTML = '';
            document.getElementById('rxnpwa-renglon-cantidad').value = '1';
            document.getElementById('rxnpwa-renglon-descuento').value = '0';
            document.getElementById('rxnpwa-renglon-precio').value = '0';
            document.getElementById('rxnpwa-renglon-precio-origin').textContent = '—';
            document.getElementById('rxnpwa-renglon-stock-info').textContent = '';
            document.getElementById('rxnpwa-renglon-subtotal').textContent = '0,00';
        }

        if (!renglonModal) {
            renglonModal = new bootstrap.Modal(document.getElementById('rxnpwa-renglon-modal'));
        }
        renglonModal.show();
    }

    function renderArticuloResults(query) {
        const results = document.getElementById('rxnpwa-renglon-articulo-results');
        const q = (query || '').trim().toLowerCase();
        const all = catalog.articulos || [];
        const deposito = draft.cabecera.deposito_codigo;

        // Sin query: mostrar primeros 30 (vistazo cómodo). Con query: filtro fuzzy.
        const matches = q.length === 0
            ? all.slice(0, 30)
            : all.filter((a) =>
                (a.codigo_externo || '').toLowerCase().includes(q) ||
                (a.nombre || '').toLowerCase().includes(q) ||
                (a.descripcion || '').toLowerCase().includes(q)
            ).slice(0, 30);

        if (matches.length === 0) {
            results.innerHTML = '<div class="small text-muted p-2">Sin resultados.</div>';
            return;
        }

        const header = q.length === 0
            ? `<div class="rxnpwa-suggest-header small text-muted px-2 py-1">Primeros ${matches.length} de ${all.length} — escribí para buscar</div>`
            : '';

        results.innerHTML = header + matches.map((a) => {
            const stock = resolveStock(a, deposito);
            const stockBadge = stock !== null
                ? `<span class="badge ${stock > 0 ? 'bg-success' : 'bg-secondary'} ms-2">stock ${formatNum(stock)}</span>`
                : '<span class="badge bg-secondary ms-2">sin stock</span>';
            return `
                <div class="rxnpwa-suggest-item" data-articulo-id="${a.id}">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <strong class="text-truncate">${escape(a.nombre || a.descripcion || '')}</strong>
                        ${stockBadge}
                    </div>
                    <div class="small text-muted">cód ${escape(a.codigo_externo || '—')}</div>
                </div>
            `;
        }).join('');
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

        if (editingRenglonIdx >= 0 && editingRenglonIdx < draft.renglones.length) {
            // Editar in-place — preservamos row_uuid (idempotencia del sync).
            const existing = draft.renglones[editingRenglonIdx];
            existing.articulo_id = selectedRowArticulo.id;
            existing.codigo = selectedRowArticulo.codigo_externo;
            existing.descripcion = selectedRowArticulo.nombre || selectedRowArticulo.descripcion;
            existing.cantidad = cant;
            existing.precio_unitario = precio;
            existing.descuento_pct = desc;
            existing.subtotal = subtotal;
        } else {
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
        }
        editingRenglonIdx = -1;
        renderRenglones();
        scheduleAutoSave();
        renglonModal.hide();
    }

    function onRenglonListClick(e) {
        const editBtn = e.target.closest('[data-rxnpwa-renglon-edit]');
        if (editBtn) {
            const idx = parseInt(editBtn.dataset.rxnpwaRenglonEdit, 10);
            if (Number.isInteger(idx)) openRenglonModal(idx);
            return;
        }
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
            // Captura de geolocalización: solo al primer save manual (no silent),
            // así el prompt del browser sólo aparece una vez por draft. Auto-saves
            // silenciosos no disparan prompt para no molestar.
            if (!silent) {
                await captureGeoIfMissing();
            }
            await window.RxnPwaDraftsStore.saveDraft(draft);
            if (!silent) showStatus('success', 'Borrador guardado localmente.');
            document.getElementById('rxnpwa-form-subtitle').textContent =
                'Borrador local · guardado ' + new Date().toLocaleTimeString('es-AR');
        } catch (err) {
            console.error('[rxnpwa-form] save error:', err);
            showStatus('error', 'No se pudo guardar: ' + err.message);
        }
    }

    /**
     * Persiste en el draft la última geo capturada por el gate global.
     * No dispara permisos ni captura nueva — eso lo hace RxnPwaGeoGate al cargar
     * la PWA. Acá solo COPIAMOS la geo actual al draft para que viaje en el sync.
     *
     * Si el gate no tiene geo (overlay activo bloqueando la PWA), igual no se
     * llega acá porque el usuario no puede interactuar con el form.
     */
    async function captureGeoIfMissing() {
        const geo = window.RxnPwaGeoGate && window.RxnPwaGeoGate.getCurrentGeo();
        // 'dev_mock' sólo aparece en contexto inseguro (HTTP plano local) — el
        // gate no permite asignarlo en HTTPS, así que aceptarlo acá no afloja
        // la regla en producción.
        if (geo && (geo.source === 'gps' || geo.source === 'wifi' || geo.source === 'dev_mock')) {
            draft.geo_lat = geo.lat;
            draft.geo_lng = geo.lng;
            draft.geo_accuracy = geo.accuracy;
            draft.geo_source = geo.source;
            draft.geo_captured_at = geo.captured_at;
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

    let statusToastTimer = null;
    /**
     * Antes el aviso vivía en el div #rxnpwa-form-status (arriba del form). Si
     * el operador estaba scrolleado abajo no veía la confirmación de guardado.
     * Ahora rendereamos un TOAST CENTRADO fixed en el viewport — siempre visible
     * sin importar el scroll. Se autodescarta a los 2.5s para success/info,
     * persiste para errores hasta tap del usuario.
     */
    function showStatus(type, msg) {
        const cls = { success: 'success', info: 'info', warning: 'warning', error: 'danger' }[type] || 'info';
        const icon = {
            success: 'bi-check-circle-fill',
            info: 'bi-info-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            error: 'bi-x-octagon-fill',
        }[type] || 'bi-info-circle-fill';

        // Si ya hay un toast, reciclarlo.
        let toast = document.getElementById('rxnpwa-form-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'rxnpwa-form-toast';
            toast.className = 'rxnpwa-form-toast';
            document.body.appendChild(toast);
        }

        const isPersistent = (type === 'error' || type === 'warning');
        toast.innerHTML = `
            <div class="rxnpwa-form-toast-card alert alert-${cls} mb-0">
                <i class="bi ${icon} me-2"></i>
                <span class="rxnpwa-form-toast-msg">${escape(msg)}</span>
                ${isPersistent ? '<button type="button" class="btn-close ms-3" aria-label="Cerrar"></button>' : ''}
            </div>
        `;
        toast.classList.add('is-visible');

        if (statusToastTimer) {
            clearTimeout(statusToastTimer);
            statusToastTimer = null;
        }
        if (!isPersistent) {
            statusToastTimer = setTimeout(() => clearStatus(), 2500);
        } else {
            const closeBtn = toast.querySelector('.btn-close');
            if (closeBtn) closeBtn.addEventListener('click', clearStatus);
        }
    }
    function clearStatus() {
        const toast = document.getElementById('rxnpwa-form-toast');
        if (!toast) return;
        toast.classList.remove('is-visible');
        if (statusToastTimer) {
            clearTimeout(statusToastTimer);
            statusToastTimer = null;
        }
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

    // API pública mínima — usada por rxnpwa-form-sync.js antes de encolar.
    window.RxnPwaForm = {
        flushSave: () => saveDraft(true),
        captureGeo: () => captureGeoIfMissing(),
        getDraft: () => draft,
    };
})();
