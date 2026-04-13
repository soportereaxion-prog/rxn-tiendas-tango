(function () {
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatMoney(value) {
        var number = Number(value || 0);
        return '$' + number.toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function parseNumber(value) {
        if (value === null || value === undefined || value === '') {
            return 0;
        }

        var normalized = String(value).trim().replace(/\s+/g, '').replace(',', '.');
        var parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function setupPicker(root, onSelect) {
        var input = root.querySelector('[data-picker-input]');
        var hidden = root.querySelector('[data-picker-hidden]');
        var extraHidden = root.querySelector('[data-picker-extra-hidden]');
        var results = root.querySelector('[data-picker-results]');
        var meta = root.parentElement.querySelector('[data-picker-meta]');
        var requestToken = 0;
        var activeIndex = -1;
        var items = [];
        var debounceTimer = null;

        if (!input || !hidden || !results) {
            return;
        }

        function updateMeta(message) {
            if (meta) {
                meta.textContent = message || '';
            }
        }

        function closeResults() {
            results.innerHTML = '';
            results.classList.add('d-none');
            items = [];
            activeIndex = -1;
        }

        function setActive(index) {
            if (!items.length) {
                activeIndex = -1;
                return;
            }

            if (index < 0) {
                index = items.length - 1;
            }
            if (index >= items.length) {
                index = 0;
            }

            activeIndex = index;
            items.forEach(function (button, idx) {
                button.classList.toggle('is-active', idx === activeIndex);
                if (idx === activeIndex) {
                    button.scrollIntoView({ block: 'nearest' });
                }
            });
        }

        function applyItem(item) {
            input.value = item.value || item.label || '';
            hidden.value = item.id || '';
            if (extraHidden) {
                extraHidden.value = item.extraId != null ? item.extraId : '';
            }
            updateMeta(item.caption || 'Seleccionado');
            closeResults();

            if (typeof onSelect === 'function') {
                onSelect(item, root, input, hidden, updateMeta);
            }
        }

        function render(payload) {
            closeResults();

            if (!Array.isArray(payload) || !payload.length) {
                return;
            }

            payload.forEach(function (item, index) {
                var button = document.createElement('button');
                button.type = 'button';
                button.tabIndex = -1; // No capturar Tab: el foco salta limpio al siguiente input del form
                button.className = 'rxn-search-suggestion';
                button.innerHTML = '<strong>' + escapeHtml(item.label || item.value || 'Sin titulo') + '</strong>'
                    + '<small class="text-muted">' + escapeHtml(item.caption || 'Sin datos extra') + '</small>';
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    applyItem(item);
                });
                button.addEventListener('mouseenter', function () {
                    setActive(index);
                });
                results.appendChild(button);
                items.push(button);
            });

            results.classList.remove('d-none');
            activeIndex = -1;
            items.forEach(function (button) {
                button.classList.remove('is-active');
            });
        }

        async function load(forceEmpty = false) {
            var url = root.getAttribute('data-picker-url');
            var term = forceEmpty ? '' : input.value.trim();
            var token = ++requestToken;

            if (!url) {
                closeResults();
                return;
            }

            if (term === '' && !forceEmpty) {
                hidden.value = '';
                if (extraHidden) {
                    extraHidden.value = '';
                }
            }

            try {
                var response = await fetch(url + '?q=' + encodeURIComponent(term), {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                var payload = await response.json();

                if (token !== requestToken || !payload.success) {
                    return;
                }

                render(payload.data || []);
            } catch (error) {
                if (token === requestToken) {
                    closeResults();
                }
            }
        }

        input.addEventListener('input', function () {
            hidden.value = '';
            if (extraHidden) {
                extraHidden.value = '';
            }
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
            debounceTimer = window.setTimeout(function() { load(false); }, 120);
        });

        input.addEventListener('click', function () {
            if (results.classList.contains('d-none')) {
                input.select();
                load(true);
            }
        });

        input.addEventListener('focus', function () {
            // Si el Spotlight Modal acaba de seleccionar un item y está restaurando el foco,
            // no re-abrimos la lista inline (el flag lo limpia el spotlight con setTimeout).
            if (root.dataset.suppressNextFocus === '1') {
                return;
            }
            if (results.classList.contains('d-none')) {
                input.select();
                load(true);
            }
        });

        input.addEventListener('keydown', function (event) {
            if (!items.length) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                    closeResults();
                }
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActive(activeIndex + 1);
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActive(activeIndex - 1);
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                if (activeIndex < 0) {
                    setActive(0);
                }
                items[activeIndex].dispatchEvent(new MouseEvent('mousedown'));
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                closeResults();
            }
        });

        document.addEventListener('click', function (event) {
            if (!root.contains(event.target)) {
                closeResults();
            }
        });

        // Escuchar el evento emitido por Spotlight Modal
        input.addEventListener('picker-selected', function (event) {
            applyItem(event.detail);
        });
    }

    function setupForm() {
        var form = document.getElementById('crm-presupuesto-form');
        if (!form) {
            return;
        }

        // Blindaje contra "Guardado" accidental por Enter en cualquier input
        form.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                if (e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'BUTTON') {
                    e.preventDefault(); 
                }
            }
        });

        // Conversion magica: Click normal en Selects de catalogo asumen Spotlight en lugar de persiana aburrida nativa del browser
        var catalogSelects = form.querySelectorAll('select[data-catalog-select]');
        catalogSelects.forEach(function(sel) {
            sel.addEventListener('mousedown', function(e) {
                // Solo intervenimos si es un click real (detail > 0 = originado por mouse)
                // Si detail === 0 fue generado por teclado/focus — dejar pasar nativamente
                if (e.detail === 0) return;
                e.preventDefault(); // Anula la ventanita blanca nativa
                if (typeof openSpotlight === 'function') {
                    openSpotlight(sel, null);
                }
            });
            // Soporte teclado (barra espaciadora / Intro abren el nativo si no lo pisamos)
            sel.addEventListener('keydown', function(e) {
                if (e.key === ' ' || e.key === 'Enter') {
                    e.preventDefault();
                    if (typeof openSpotlight === 'function') {
                        openSpotlight(sel, null);
                    }
                }
            });
        });

        var clientRoot = form.querySelector('[data-client-picker]');
        var articleRoot = form.querySelector('[data-article-picker]');
        var clientDocumento = form.querySelector('[data-client-documento]');
        var clientDocumentoHidden = form.querySelector('[data-cliente-documento-hidden]');
        var clientIdPill = form.querySelector('[data-client-id-pill]');
        var listaSelect = form.querySelector('[data-lista-select]');
        var itemsBody = form.querySelector('[data-items-body]');
        var summaryTotal = form.querySelector('[data-summary-total]');
        var totalSubtotal = form.querySelector('[data-total-subtotal]');
        var totalDescuento = form.querySelector('[data-total-descuento]');
        var totalGeneral = form.querySelector('[data-total-general]');
        var itemCount = form.querySelector('[data-item-count]');

        if (!itemsBody || !listaSelect) {
            return;
        }

        function ensureOption(select, option) {
            if (!select || !option || !option.codigo) {
                return;
            }

            var existing = Array.prototype.find.call(select.options, function (candidate) {
                return candidate.value === String(option.codigo);
            });

            if (!existing) {
                var injected = document.createElement('option');
                injected.value = option.codigo;
                injected.textContent = option.descripcion || option.codigo;
                select.appendChild(injected);
            }

            select.value = String(option.codigo);
        }

        function emptyRowTemplate() {
            return '<tr data-empty-row><td colspan="7" class="crm-budget-empty-lines">Todavia no hay renglones. Busca un articulo para empezar a armar el presupuesto.</td></tr>';
        }

        function updateEmptyState() {
            var rows = itemsBody.querySelectorAll('[data-item-row]');
            var emptyRow = itemsBody.querySelector('[data-empty-row]');

            if (!rows.length && !emptyRow) {
                itemsBody.innerHTML = emptyRowTemplate();
            }

            if (rows.length && emptyRow) {
                emptyRow.remove();
            }
        }

        function createItemRow(item) {
            var row = document.createElement('tr');
            row.setAttribute('data-item-row', '1');
            row.innerHTML = [
                '<td>',
                '  <input type="hidden" value="' + escapeHtml(item.articulo_id || '') + '" data-item-field="articulo_id">',
                '  <input type="hidden" value="' + escapeHtml(item.precio_origen || 'manual') + '" data-item-field="precio_origen">',
                '  <input type="hidden" value="' + escapeHtml(item.lista_codigo_aplicada || '') + '" data-item-field="lista_codigo_aplicada">',
                '  <input type="text" class="form-control form-control-sm" value="' + escapeHtml(item.articulo_codigo || '') + '" data-item-field="articulo_codigo">',
                '</td>',
                '<td class="crm-budget-line-desc">',
                '  <textarea class="form-control form-control-sm" rows="2" data-item-field="articulo_descripcion">' + escapeHtml(item.articulo_descripcion || '') + '</textarea>',
                '  <div class="form-text mt-1">Origen: <span data-item-origin-label>' + escapeHtml(String(item.precio_origen || 'manual').toUpperCase()) + '</span></div>',
                '</td>',
                '<td><input type="number" step="0.0001" min="0" class="form-control form-control-sm" value="' + escapeHtml(item.cantidad || 1) + '" data-item-field="cantidad"></td>',
                '<td><input type="number" step="0.0001" min="0" class="form-control form-control-sm" value="' + escapeHtml(item.precio_unitario || 0) + '" data-item-field="precio_unitario"></td>',
                '<td><input type="number" step="0.0001" min="0" max="100" class="form-control form-control-sm" value="' + escapeHtml(item.bonificacion_porcentaje || 0) + '" data-item-field="bonificacion_porcentaje"></td>',
                '<td>',
                '  <input type="text" class="form-control form-control-sm crm-budget-line-amount" value="' + escapeHtml(formatMoney(item.importe_neto || 0)) + '" readonly data-item-amount>',
                '  <input type="hidden" value="' + escapeHtml(item.importe_neto || 0) + '" data-item-field="importe_neto">',
                '</td>',
                '<td class="text-end">',
                '  <button type="button" class="btn btn-outline-danger btn-sm" data-remove-item title="Quitar renglon"><i class="bi bi-x-lg"></i></button>',
                '</td>'
            ].join('');
            return row;
        }

        function reindexRows() {
            itemsBody.querySelectorAll('[data-item-row]').forEach(function (row, index) {
                row.querySelectorAll('[data-item-field]').forEach(function (field) {
                    var name = field.getAttribute('data-item-field');
                    field.name = 'items[' + index + '][' + name + ']';
                });
            });
        }

        function recalculate() {
            var subtotal = 0;
            var descuento = 0;
            var rows = itemsBody.querySelectorAll('[data-item-row]');

            rows.forEach(function (row) {
                var cantidad = parseNumber(row.querySelector('[data-item-field="cantidad"]').value);
                var precio = parseNumber(row.querySelector('[data-item-field="precio_unitario"]').value);
                var bonif = parseNumber(row.querySelector('[data-item-field="bonificacion_porcentaje"]').value);

                if (bonif < 0) {
                    bonif = 0;
                }
                if (bonif > 100) {
                    bonif = 100;
                }

                var bruto = Number((cantidad * precio).toFixed(2));
                var neto = Number((bruto - (bruto * bonif / 100)).toFixed(2));

                subtotal += bruto;
                descuento += (bruto - neto);

                row.querySelector('[data-item-field="importe_neto"]').value = neto.toFixed(2);
                row.querySelector('[data-item-amount]').value = formatMoney(neto);
                row.querySelector('[data-item-origin-label]').textContent = String(row.querySelector('[data-item-field="precio_origen"]').value || 'manual').toUpperCase();
            });

            var total = Number((subtotal - descuento).toFixed(2));
            if (totalSubtotal) {
                totalSubtotal.textContent = formatMoney(subtotal);
            }
            if (totalDescuento) {
                totalDescuento.textContent = formatMoney(descuento);
            }
            if (totalGeneral) {
                totalGeneral.textContent = formatMoney(total);
            }
            if (summaryTotal) {
                summaryTotal.textContent = formatMoney(total);
            }
            if (itemCount) {
                itemCount.textContent = String(rows.length);
            }
        }

        // --- Header lock: bloquea cabecera cuando hay artículos cargados ---
        var headerFieldSelectors = [
            '#presupuesto-fecha',
            '#presupuesto-estado',
            '#presupuesto-deposito',
            '#presupuesto-condicion',
            '[data-lista-select]',
            '#presupuesto-vendedor',
            '#clasificacion_codigo',
            '#presupuesto-transporte'
        ];

        function hasItems() {
            return itemsBody.querySelectorAll('[data-item-row]').length > 0;
        }

        function lockHeader() {
            headerFieldSelectors.forEach(function(sel) {
                var el = form.querySelector(sel);
                if (el) el.disabled = true;
            });
            // Cliente: deshabilitar picker input + hidden
            if (clientRoot) {
                var ci = clientRoot.querySelector('[data-picker-input]');
                if (ci) ci.disabled = true;
            }
        }

        function unlockHeader() {
            headerFieldSelectors.forEach(function(sel) {
                var el = form.querySelector(sel);
                if (el) el.disabled = false;
            });
            if (clientRoot) {
                var ci = clientRoot.querySelector('[data-picker-input]');
                if (ci) ci.disabled = false;
            }
        }

        // Re-habilitar antes del submit para que los valores viajen en el POST
        form.addEventListener('submit', function() {
            unlockHeader();
        });

        function appendItem(item) {
            var emptyRow = itemsBody.querySelector('[data-empty-row]');
            if (emptyRow) {
                emptyRow.remove();
            }

            itemsBody.appendChild(createItemRow(item));
            reindexRows();
            recalculate();
            if (hasItems()) lockHeader();
        }

        function applyClientContext(data, input, hidden, updateMeta) {
            var payload = data || {};
            var cliente = payload.cliente || {};

            if (hidden) {
                hidden.value = cliente.id || '';
            }
            if (input) {
                input.value = cliente.nombre || input.value;
            }
            if (clientDocumento) {
                clientDocumento.textContent = cliente.documento ? ('Doc: ' + cliente.documento) : 'Sin documento cargado';
            }
            if (clientDocumentoHidden) {
                clientDocumentoHidden.value = cliente.documento || '';
            }
            if (clientIdPill) {
                clientIdPill.textContent = cliente.id ? ('Cliente #' + cliente.id) : 'Sin cliente';
            }

            ensureOption(form.querySelector('[data-catalog-select="deposito"]'), payload.deposito || null);
            ensureOption(form.querySelector('[data-catalog-select="condicion_venta"]'), payload.condicion || null);
            ensureOption(form.querySelector('[data-catalog-select="lista_precio"]'), payload.lista || null);
            ensureOption(form.querySelector('[data-catalog-select="vendedor"]'), payload.vendedor || null);
            ensureOption(form.querySelector('[data-catalog-select="transporte"]'), payload.transporte || null);

            if (typeof updateMeta === 'function') {
                updateMeta(payload.warning || 'Cliente aplicado y cabecera comercial autocompletada.');
            }
        }

        async function fetchJson(url) {
            var response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            return response.json();
        }

        if (clientRoot) {
            setupPicker(clientRoot, async function (item, root, input, hidden, updateMeta) {
                var contextUrl = root.getAttribute('data-context-url');
                if (!contextUrl || !item.id) {
                    return;
                }

                try {
                    var payload = await fetchJson(contextUrl + '?id=' + encodeURIComponent(item.id));
                    if (!payload.success) {
                        updateMeta(payload.message || 'No se pudo cargar el contexto comercial del cliente.');
                        return;
                    }

                    applyClientContext(payload.data || {}, input, hidden, updateMeta);
                } catch (error) {
                    updateMeta('No se pudo cargar el contexto comercial del cliente.');
                }
            });

            var clientInput = clientRoot.querySelector('[data-picker-input]');
            if (clientInput) {
                clientInput.addEventListener('input', function () {
                    if (clientDocumento) {
                        clientDocumento.textContent = 'Sin documento cargado';
                    }
                    if (clientDocumentoHidden) {
                        clientDocumentoHidden.value = '';
                    }
                    if (clientIdPill) {
                        clientIdPill.textContent = 'Sin cliente';
                    }
                });
            }
        }

        var inlineQty = document.getElementById('inline-qty');
        var inlinePrice = document.getElementById('inline-price');
        var inlineBonus = document.getElementById('inline-bonus');
        var inlineTempTotal = document.getElementById('inline-temp-total');
        var inlineAddBtn = document.getElementById('inline-add-btn');
        var inlineArticuloId = document.getElementById('inline-articulo-id');
        var inlineArticuloCodigo = document.getElementById('inline-articulo-codigo');
        var inlineArticuloDescripcion = document.getElementById('inline-articulo-descripcion');
        var inlinePrecioOrigen = document.getElementById('inline-precio-origen');
        var inlinePickerInput = document.getElementById('inline-picker-input');

        function recalcInlineTotal() {
            var qty = parseNumber(inlineQty.value);
            var price = parseNumber(inlinePrice.value);
            var bonif = parseNumber(inlineBonus.value);
            
            if (bonif < 0) bonif = 0;
            if (bonif > 100) bonif = 100;
            
            var bruto = Number((qty * price).toFixed(2));
            var neto = Number((bruto - (bruto * bonif / 100)).toFixed(2));
            
            inlineTempTotal.value = formatMoney(neto);
        }

        function clearInlineForm() {
            inlineArticuloId.value = '';
            inlineArticuloCodigo.value = '';
            inlineArticuloDescripcion.value = '';
            inlinePrecioOrigen.value = 'manual';
            inlineQty.value = '1';
            inlinePrice.value = '0';
            inlineBonus.value = '0';
            inlinePickerInput.value = '';
            var pickerHidden = articleRoot ? articleRoot.querySelector('.crm-picker-hidden') : null;
            if (pickerHidden) {
                pickerHidden.value = '';
            }
            recalcInlineTotal();
            inlinePickerInput.focus();
        }

        function commitInlineItem() {
            var artId = inlineArticuloId.value;
            if (!artId) {
                return;
            }

            var newItem = {
                articulo_id: artId,
                articulo_codigo: inlineArticuloCodigo.value,
                articulo_descripcion: inlineArticuloDescripcion.value,
                precio_origen: inlinePrecioOrigen.value,
                lista_codigo_aplicada: listaSelect ? listaSelect.value : '',
                cantidad: parseNumber(inlineQty.value),
                precio_unitario: parseNumber(inlinePrice.value),
                bonificacion_porcentaje: parseNumber(inlineBonus.value),
                importe_neto: parseNumber(inlineTempTotal.value.replace(/[^0-9,-]+/g,"").replace(",", "."))
            };

            appendItem(newItem);
            clearInlineForm();
        }

        if (articleRoot) {
            setupPicker(articleRoot, async function (item, root, input, hidden, updateMeta) {
                var contextUrl = root.getAttribute('data-context-url');
                if (!contextUrl || !item.id) {
                    return;
                }

                try {
                    var listaCodigo = listaSelect ? listaSelect.value : '';
                    var query = '?id=' + encodeURIComponent(item.id) + '&lista_codigo=' + encodeURIComponent(listaCodigo);
                    var payload = await fetchJson(contextUrl + query);

                    if (!payload.success) {
                        (window.rxnAlert || alert)(payload.message || 'No se pudo resolver el articulo seleccionado.', 'warning');
                        return;
                    }

                    var artData = payload.data || {};
                    inlineArticuloId.value = artData.articulo_id || '';
                    inlineArticuloCodigo.value = artData.articulo_codigo || '';
                    inlineArticuloDescripcion.value = artData.articulo_descripcion || '';
                    inlinePrecioOrigen.value = artData.precio_origen || 'manual';
                    
                    inlineQty.value = '1';
                    inlinePrice.value = artData.precio_unitario !== null ? artData.precio_unitario : 0;
                    inlineBonus.value = '0';
                    
                    recalcInlineTotal();
                    
                    // Foco inmediato al input de cantidad para fluidez con TAB / Teclado
                    if (inlineQty) {
                        inlineQty.select();
                        inlineQty.focus();
                    }
                } catch (error) {
                    (window.rxnAlert || alert)('No se pudo cargar el contexto del articulo.', 'danger');
                }
            });
        }

        // Inicializar pickers genéricos restantes (ej: Clasificación) que no tienen callback custom
        form.querySelectorAll('[data-picker]').forEach(function(root) {
            if (root !== clientRoot && root !== articleRoot) {
                setupPicker(root);
            }
        });

        if (inlineQty && inlinePrice && inlineBonus && inlineAddBtn) {
            [inlineQty, inlinePrice, inlineBonus].forEach(function(el) {
                el.addEventListener('input', recalcInlineTotal);
                el.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        commitInlineItem();
                    }
                });
            });

            inlineAddBtn.addEventListener('click', commitInlineItem);
        }

        itemsBody.addEventListener('click', function (event) {
            var button = event.target.closest('[data-remove-item]');
            if (!button) {
                return;
            }

            var row = button.closest('[data-item-row]');
            if (row) {
                row.remove();
                reindexRows();
                updateEmptyState();
                recalculate();
                if (!hasItems()) unlockHeader();
            }
        });

        itemsBody.addEventListener('input', function (event) {
            if (event.target.matches('[data-item-field="cantidad"], [data-item-field="precio_unitario"], [data-item-field="bonificacion_porcentaje"]')) {
                recalculate();
            }
        });

        updateEmptyState();
        reindexRows();
        recalculate();

        // Lock inicial: si ya hay items cargados (modo edición), bloquear cabecera
        if (hasItems()) lockHeader();
    }

    function setupDirtyCheckAndEmailControl() {
        var mainForm = document.getElementById('crm-presupuesto-form');
        var emailForms = document.querySelectorAll('form[action$="/enviar-correo"]');
        var tangoForms = document.querySelectorAll('form[action$="/sync-tango"]');
        
        if (!mainForm) return;

        var isDirty = false;
        
        mainForm.addEventListener('input', function() {
            isDirty = true;
        });
        mainForm.addEventListener('change', function() {
            isDirty = true;
        });

        const interceptForm = function(form, message) {
            var btn = form.querySelector('button[type="submit"]') || form.querySelector('button');
            if (btn) {
                btn.addEventListener('click', function(e) {
                    if (isDirty) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        (window.rxnAlert || alert)(message, 'warning', 'Atención: Cambios sin guardar');
                    }
                }, true); // Capture phase para ganar prioridad
            }
        };

        emailForms.forEach(function(form) {
            interceptForm(form, 'Has modificado datos en este presupuesto.\n\nPor favor, hacé clic en el botón azul "Guardar" antes de enviarlo por correo para asegurarte de que el cliente reciba la información actualizada.');
        });

        tangoForms.forEach(function(form) {
            interceptForm(form, 'Has modificado datos en este presupuesto.\n\nPor favor, hacé clic en el botón azul "Guardar" antes de enviarlo a Tango.');
        });

        // Intercept Escape key globally to show confirmation modal before returning
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Ignore if any modal is already active
                if (document.querySelector('.modal.show')) return;
                // Ignore if currently inside the spotlight search
                if (document.querySelector('.rxn-spotlight-dialog.show')) return;
                
                e.preventDefault();
                e.stopImmediatePropagation();
                if (window.rxnConfirm) {
                    window.rxnConfirm({
                        title: 'Confirmar salida',
                        message: '¿Querés salir del proceso sin guardar?',
                        type: 'warning',
                        okText: 'Sí, salir',
                        cancelText: 'Cancelar',
                        onConfirm: function() {
                            // Navegar directo al href del botón "Volver al listado"
                            var backBtn = document.querySelector('.rxn-module-actions a.btn-outline-secondary') ||
                                          document.querySelector('a.btn-outline-secondary[href*="/mi-empresa"]');
                            if (backBtn && backBtn.href) {
                                window.location.href = backBtn.href;
                            } else {
                                window.history.back();
                            }
                        }
                    });
                }
            }
        }, true);
    }

    document.addEventListener('DOMContentLoaded', function() {
        setupForm();
        setupDirtyCheckAndEmailControl();
    });
})();
