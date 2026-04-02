(function () {
    function clone(value) {
        return JSON.parse(JSON.stringify(value));
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function clamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }

    function resolvePath(path, context) {
        if (!path) {
            return '';
        }

        return String(path)
            .split('.')
            .reduce(function (carry, key) {
                if (carry && Object.prototype.hasOwnProperty.call(carry, key)) {
                    return carry[key];
                }
                return null;
            }, context) ?? '';
    }

    function setupEditor() {
        var config = window.printFormsEditorConfig || {};
        var form = document.getElementById('print-form-editor-form');
        if (!form) {
            return;
        }

        var sheet = form.querySelector('[data-print-sheet]');
        var stage = form.querySelector('[data-print-sheet-stage]');
        var objectList = form.querySelector('[data-object-list]');
        var deleteButton = form.querySelector('[data-delete-object]');
        var emptyState = form.querySelector('[data-object-empty-state]');
        var propertiesPanel = form.querySelector('[data-object-properties]');
        var selectedType = form.querySelector('[data-selected-type]');
        var pageInput = document.getElementById('print-page-config-json');
        var objectsInput = document.getElementById('print-objects-json');
        var fontsInput = document.getElementById('print-fonts-json');
        var backgroundInput = form.querySelector('[data-background-input]');
        var backgroundPreview = form.querySelector('[data-background-preview]');
        var backgroundPreviewWrap = form.querySelector('[data-background-preview-wrap]');
        var clearBackground = document.getElementById('print-clear-background');
        var orientationSelect = form.querySelector('[data-page-prop="orientation"]');
        var gridEnabledInput = form.querySelector('[data-page-prop="grid_enabled"]');

        var propertyFields = {
            content: document.querySelector('[data-object-prop="content"]'),
            source: document.querySelector('[data-object-prop="source"]'),
            x_mm: document.querySelector('[data-object-prop="x_mm"]'),
            y_mm: document.querySelector('[data-object-prop="y_mm"]'),
            w_mm: document.querySelector('[data-object-prop="w_mm"]'),
            h_mm: document.querySelector('[data-object-prop="h_mm"]'),
            font_family: document.querySelector('[data-style-prop="font_family"]'),
            font_size_pt: document.querySelector('[data-style-prop="font_size_pt"]'),
            font_weight: document.querySelector('[data-style-prop="font_weight"]'),
            color: document.querySelector('[data-style-prop="color"]'),
            align: document.querySelector('[data-style-prop="align"]'),
            stroke: document.querySelector('[data-style-prop="stroke"]'),
            fill: document.querySelector('[data-style-prop="fill"]'),
            stroke_width_mm: document.querySelector('[data-style-prop="stroke_width_mm"]'),
            show_header: document.querySelector('[data-object-prop="show_header"]'),
            row_height_mm: document.querySelector('[data-object-prop="row_height_mm"]'),
            columns: document.querySelector('[data-object-prop="columns"]'),
            header_background: document.querySelector('[data-style-prop="header_background"]'),
            header_color: document.querySelector('[data-style-prop="header_color"]'),
            border_color: document.querySelector('[data-style-prop="border_color"]')
        };

        var rowsForField = {};
        Object.keys(propertyFields).forEach(function (key) {
            var field = propertyFields[key];
            if (field) {
                var group = field.closest('[data-object-prop-group]');
                rowsForField[key] = group || field.closest('.mb-3, .row');
            }
        });

        var state = {
            pageConfig: clone(config.pageConfig || {}),
            objects: clone(config.objects || []),
            fonts: clone(config.fonts || { used: [] }),
            variables: clone(config.variables || []),
            sampleContext: clone(config.sampleContext || {}),
            availableFonts: clone(config.availableFonts || []),
            selectedId: null,
            drag: null,
            backgroundUrl: config.backgroundUrl || '',
            originalBackgroundUrl: config.backgroundUrl || '',
            uploadedBackgroundUrl: ''
        };

        var zoomState = {
            level: 100
        };

        function applyZoom() {
            var label = document.getElementById('print-zoom-label');
            if (label) {
                label.textContent = zoomState.level + '%';
            }
            if (sheet && sheet.parentElement) {
                var scale = zoomState.level / 100;
                
                // Chrome/Edge/Safari support `zoom`, which perfectly adjusts layout and scrollbars
                // Firefox >= 126 supports `zoom` as well.
                sheet.style.zoom = scale;
                
                // Fallback for old Firefox: transform
                if (typeof sheet.style.zoom === 'undefined' || navigator.userAgent.toLowerCase().indexOf('firefox') > -1) {
                    sheet.style.transform = 'scale(' + scale + ')';
                    sheet.style.transformOrigin = 'top center';
                    // We need to adjust parent height to avoid massive whitespace
                    var baseHeight = 820 * 1.414285;
                    var orientation = (state.pageConfig.page && state.pageConfig.page.orientation) || 'portrait';
                    if (orientation === 'landscape') {
                        baseHeight = 820 * 0.70707;
                    }
                    sheet.parentElement.style.height = ((baseHeight * scale) + 60) + 'px';
                } else {
                    sheet.parentElement.style.height = 'auto'; 
                    sheet.style.transform = '';
                }
            }
        }

        var zoomOutBtn = document.querySelector('[data-zoom="out"]');
        var zoomInBtn = document.querySelector('[data-zoom="in"]');
        var zoomFitBtn = document.querySelector('[data-zoom="fit"]');

        if (zoomOutBtn) {
            zoomOutBtn.addEventListener('click', function() {
                zoomState.level = Math.max(25, zoomState.level - 10);
                applyZoom();
            });
        }
        if (zoomInBtn) {
            zoomInBtn.addEventListener('click', function() {
                zoomState.level = Math.min(200, zoomState.level + 10);
                applyZoom();
            });
        }
        if (zoomFitBtn) {
            zoomFitBtn.addEventListener('click', function() {
                if (sheet && sheet.parentElement) {
                    var availableWidth = sheet.parentElement.clientWidth;
                    var padding = 60; // 30px each side
                    var targetWidth = availableWidth - padding;
                    if (targetWidth < 820) {
                        zoomState.level = Math.floor((targetWidth / 820) * 100);
                    } else {
                        zoomState.level = 100;
                    }
                    applyZoom();
                }
            });
        }

        // Auto-fit on initial load if screen is too small
        setTimeout(function() {
            if (zoomFitBtn) zoomFitBtn.click();
            else applyZoom();
        }, 50);

        function pageBox() {
            var page = state.pageConfig.page || {};
            var orientation = page.orientation || 'portrait';
            var baseWidth = Number(page.width_mm || 210);
            var baseHeight = Number(page.height_mm || 297);

            return orientation === 'landscape'
                ? { widthMm: baseHeight, heightMm: baseWidth }
                : { widthMm: baseWidth, heightMm: baseHeight };
        }

        function mmToStagePixels(mm, axis) {
            var box = pageBox();
            if (axis === 'x' || axis === 'w') {
                return (Number(mm || 0) / box.widthMm) * stage.clientWidth;
            }

            return (Number(mm || 0) / box.heightMm) * stage.clientHeight;
        }

        function syncHiddenInputs() {
            var pageConfig = clone(state.pageConfig);
            if (!pageConfig.background || typeof pageConfig.background !== 'object') {
                pageConfig.background = {};
            }

            if (!pageConfig.grid || typeof pageConfig.grid !== 'object') {
                pageConfig.grid = { enabled: true, step_mm: 2, snap: true };
            }

            pageInput.value = JSON.stringify(pageConfig);
            objectsInput.value = JSON.stringify(state.objects);

            var usedFonts = [];
            state.objects.forEach(function (object) {
                var fontFamily = object && object.style ? object.style.font_family : null;
                if (fontFamily && usedFonts.indexOf(fontFamily) === -1) {
                    usedFonts.push(fontFamily);
                }
            });
            fontsInput.value = JSON.stringify({ used: usedFonts });
        }

        function refreshSheetBackground() {
            var url = state.backgroundUrl;
            stage.style.setProperty('--print-sheet-background-image', url ? ('url("' + url + '")') : 'none');

            var gridEnabled = !!(state.pageConfig.grid && state.pageConfig.grid.enabled);
            stage.classList.toggle('has-grid', gridEnabled);

            var step = Number((state.pageConfig.grid && state.pageConfig.grid.step_mm) || 2);
            stage.style.setProperty('--print-grid-size', mmToStagePixels(step, 'x') + 'px');

            var orientation = (state.pageConfig.page && state.pageConfig.page.orientation) || 'portrait';
            sheet.classList.toggle('is-landscape', orientation === 'landscape');

            if (backgroundPreview) {
                if (url) {
                    backgroundPreview.src = url;
                    if (backgroundPreviewWrap) {
                        backgroundPreviewWrap.classList.remove('d-none');
                    }
                } else if (backgroundPreviewWrap) {
                    backgroundPreviewWrap.classList.add('d-none');
                }
            }
        }

        function renderObject(object) {
            var node = document.createElement('div');
            node.className = 'print-object';
            if (state.selectedId === object.id) {
                node.classList.add('is-selected');
            }
            if (object.type === 'line') {
                node.classList.add('is-line');
            }

            node.dataset.objectId = object.id;
            node.style.left = (Number(object.x_mm || 0) / pageBox().widthMm * 100) + '%';
            node.style.top = (Number(object.y_mm || 0) / pageBox().heightMm * 100) + '%';
            node.style.width = (Number(object.w_mm || 0) / pageBox().widthMm * 100) + '%';
            node.style.height = (Number(object.h_mm || 0) / pageBox().heightMm * 100) + '%';
            node.style.zIndex = String(object.z_index || 1);

            var inner = document.createElement('div');
            inner.className = 'print-object__inner';

            var style = object.style || {};
            if (object.type === 'text' || object.type === 'variable') {
                inner.style.display = 'flex';
                inner.style.alignItems = 'center';
                inner.style.justifyContent = style.align === 'right'
                    ? 'flex-end'
                    : (style.align === 'center' ? 'center' : 'flex-start');
                inner.style.fontFamily = style.font_family || ((state.pageConfig.defaults || {}).font_family || 'Arial, Helvetica, sans-serif');
                inner.style.fontSize = String(style.font_size_pt || ((state.pageConfig.defaults || {}).font_size_pt || 10)) + 'pt';
                inner.style.fontWeight = String(style.font_weight || 400);
                inner.style.color = style.color || ((state.pageConfig.defaults || {}).color || '#111111');
                inner.style.padding = '0 0.08rem';
                inner.style.whiteSpace = 'pre-wrap';
                inner.style.textAlign = style.align || 'left';

                var content = object.type === 'variable'
                    ? resolvePath(object.source, state.sampleContext)
                    : object.content;
                if (!content) {
                    content = object.type === 'variable' && object.source ? '{{' + object.source + '}}' : 'Texto';
                }
                inner.textContent = content;
            } else if (object.type === 'line') {
                var stroke = style.stroke || '#1f2937';
                var strokeWidth = Math.max(1, mmToStagePixels(style.stroke_width_mm || 0.3, 'x'));
                if (Number(object.h_mm || 0) > Number(object.w_mm || 0)) {
                    inner.style.borderLeft = strokeWidth + 'px solid ' + stroke;
                    inner.style.height = '100%';
                } else {
                    inner.style.borderTop = strokeWidth + 'px solid ' + stroke;
                    inner.style.marginTop = '1px';
                    inner.style.width = '100%';
                }
            } else if (object.type === 'rect') {
                inner.style.border = Math.max(1, mmToStagePixels(style.stroke_width_mm || 0.3, 'x')) + 'px solid ' + (style.stroke || '#94a3b8');
                inner.style.background = style.fill && style.fill !== 'transparent' ? style.fill : 'transparent';
                inner.style.width = '100%';
                inner.style.height = '100%';
            } else if (object.type === 'image') {
                var img = document.createElement('img');
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = style.object_fit || 'contain';
                
                var src = resolvePath(object.source, state.sampleContext);
                if (!src) {
                    src = object.content;
                }
                if (!src) {
                    // Placeholder for images with no defined source/content
                    src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect width="100" height="100" fill="%23e2e8f0"/><text x="50" y="50" font-family="sans-serif" font-size="14" fill="%2364748b" text-anchor="middle" dominant-baseline="middle">IMAGEN</text></svg>';
                }
                
                img.src = src;
                inner.appendChild(img);
            } else if (object.type === 'table_repeater') {
                var tableStr = '<table style="width:100%; height:100%; border-collapse:collapse; table-layout:fixed; '
                    + 'font-family:' + (style.font_family || ((state.pageConfig.defaults || {}).font_family || 'Arial, Helvetica, sans-serif')) + '; '
                    + 'font-size:' + (style.font_size_pt || 9) + 'pt; color:' + (style.color || '#111111') + ';">';

                var columns = Array.isArray(object.columns) ? object.columns : [];
                var rowHeight = Math.max(1, mmToStagePixels(object.row_height_mm || 8, 'y'));

                if (object.show_header !== false) {
                    tableStr += '<thead><tr style="height:' + rowHeight + 'px;">';
                    columns.forEach(function (col) {
                        tableStr += '<th style="border:1px solid ' + (style.border_color || '#94a3b8') + '; padding:0.2rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; '
                            + 'background:' + (style.header_background || '#e5e7eb') + '; color:' + (style.header_color || '#111111') + '; '
                            + 'text-align:' + (col.align || 'left') + ';">' + escapeHtml(col.label || col.key || '') + '</th>';
                    });
                    tableStr += '</tr></thead>';
                }

                tableStr += '<tbody>';
                var availableHeight = Math.max(0, mmToStagePixels(object.h_mm || 0, 'y') - (object.show_header !== false ? rowHeight : 0));
                var mockRows = Math.max(1, Math.floor(availableHeight / rowHeight));

                for (var r = 0; r < mockRows; r++) {
                    tableStr += '<tr style="height:' + rowHeight + 'px;">';
                    columns.forEach(function (col) {
                        tableStr += '<td style="border:1px solid ' + (style.border_color || '#94a3b8') + '; padding:0.2rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; '
                            + 'text-align:' + (col.align || 'left') + ';">' + escapeHtml('{' + (col.key || '') + '}') + '</td>';
                    });
                    tableStr += '</tr>';
                }

                tableStr += '</tbody></table>';
                inner.innerHTML = tableStr;
            }

            node.appendChild(inner);

            if (state.selectedId === object.id) {
                var resizer = document.createElement('div');
                resizer.dataset.resizer = 'true';
                resizer.style.position = 'absolute';
                resizer.style.right = '-6px';
                resizer.style.bottom = '-6px';
                resizer.style.width = '12px';
                resizer.style.height = '12px';
                resizer.style.backgroundColor = '#0d6efd';
                resizer.style.borderRadius = '50%';
                resizer.style.cursor = 'se-resize';
                resizer.style.zIndex = '20';
                resizer.style.border = '2px solid #fff';
                resizer.style.boxShadow = '0 2px 4px rgba(0,0,0,0.2)';
                node.appendChild(resizer);
            }

            return node;
        }

        function renderObjectList() {
            if (!objectList) {
                return;
            }

            objectList.innerHTML = '';
            state.objects.forEach(function (object, index) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-outline-secondary btn-sm';
                if (state.selectedId === object.id) {
                    button.classList.remove('btn-outline-secondary');
                    button.classList.add('btn-primary');
                }
                button.dataset.objectId = object.id;
                button.innerHTML = '<strong>' + escapeHtml('#' + (index + 1) + ' - ' + String(object.type || 'objeto').toUpperCase()) + '</strong><br><small>'
                    + escapeHtml(object.type === 'variable' ? (object.source || 'Variable') : (object.content || 'Sin contenido')) + '</small>';
                button.addEventListener('click', function () {
                    selectObject(object.id);
                });
                objectList.appendChild(button);
            });
        }

        function renderCanvas() {
            if (!stage) {
                return;
            }

            refreshSheetBackground();
            stage.innerHTML = '';
            state.objects.forEach(function (object) {
                stage.appendChild(renderObject(object));
            });
            renderObjectList();
            syncHiddenInputs();
        }

        function selectedObject() {
            return state.objects.find(function (object) {
                return object.id === state.selectedId;
            }) || null;
        }

        function togglePropertyRows(object) {
            var isTextLike = object && (object.type === 'text' || object.type === 'variable');
            var isVariable = object && object.type === 'variable';
            var isImage = object && object.type === 'image';
            var isShape = object && (object.type === 'line' || object.type === 'rect');
            var isTable = object && object.type === 'table_repeater';

            if (rowsForField.content) {
                // If it is an image, we can use content as static URL, or use source for variable. But text uses content. Let's show content for images too, as a fallback URL ? Or just source? Let's show both, but "source" for variable. We can reuse 'source' for image variable and 'content' for direct url
                rowsForField.content.style.display = object && (object.type === 'text' || object.type === 'image') ? '' : 'none';
            }
            if (rowsForField.source) {
                rowsForField.source.style.display = isVariable || isTable || isImage ? '' : 'none';
            }
            if (rowsForField.font_family) {
                rowsForField.font_family.style.display = isTextLike || isTable ? '' : 'none';
            }
            if (rowsForField.font_size_pt) {
                rowsForField.font_size_pt.style.display = isTextLike || isTable ? '' : 'none';
            }
            if (rowsForField.color) {
                rowsForField.color.style.display = isTextLike || isTable ? '' : 'none';
            }
            if (rowsForField.stroke) {
                rowsForField.stroke.style.display = isShape ? '' : 'none';
            }
            if (rowsForField.fill) {
                rowsForField.fill.style.display = isShape ? '' : 'none';
            }
            if (rowsForField.show_header) {
                rowsForField.show_header.style.display = isTable ? '' : 'none';
            }
            if (rowsForField.header_background) {
                rowsForField.header_background.style.display = isTable ? '' : 'none';
            }
        }

        function updatePropertiesPanel() {
            var object = selectedObject();

            if (!object) {
                if (emptyState) {
                    emptyState.classList.remove('d-none');
                }
                if (propertiesPanel) {
                    propertiesPanel.classList.add('d-none');
                }
                if (deleteButton) {
                    deleteButton.disabled = true;
                }
                return;
            }

            if (emptyState) {
                emptyState.classList.add('d-none');
            }
            if (propertiesPanel) {
                propertiesPanel.classList.remove('d-none');
            }
            if (deleteButton) {
                deleteButton.disabled = false;
            }
            if (selectedType) {
                selectedType.textContent = String(object.type || '--').toUpperCase();
            }

            if (propertyFields.content) {
                propertyFields.content.value = object.content || '';
            }
            if (propertyFields.source) {
                propertyFields.source.value = object.source || '';
            }
            if (propertyFields.x_mm) {
                propertyFields.x_mm.value = Number(object.x_mm || 0).toFixed(1);
            }
            if (propertyFields.y_mm) {
                propertyFields.y_mm.value = Number(object.y_mm || 0).toFixed(1);
            }
            if (propertyFields.w_mm) {
                propertyFields.w_mm.value = Number(object.w_mm || 0).toFixed(1);
            }
            if (propertyFields.h_mm) {
                propertyFields.h_mm.value = Number(object.h_mm || 0).toFixed(1);
            }

            var style = object.style || {};
            if (propertyFields.font_family) {
                propertyFields.font_family.value = style.font_family || ((state.pageConfig.defaults || {}).font_family || 'Arial, Helvetica, sans-serif');
            }
            if (propertyFields.font_size_pt) {
                propertyFields.font_size_pt.value = style.font_size_pt || ((state.pageConfig.defaults || {}).font_size_pt || 10);
            }
            if (propertyFields.font_weight) {
                propertyFields.font_weight.value = String(style.font_weight || 400);
            }
            if (propertyFields.color) {
                propertyFields.color.value = style.color || '#111111';
            }
            if (propertyFields.align) {
                propertyFields.align.value = style.align || 'left';
            }
            if (propertyFields.stroke) {
                propertyFields.stroke.value = style.stroke || '#1f2937';
            }
            if (propertyFields.fill) {
                propertyFields.fill.value = style.fill && style.fill !== 'transparent' ? style.fill : '#ffffff';
            }
            if (propertyFields.stroke_width_mm) {
                propertyFields.stroke_width_mm.value = style.stroke_width_mm || 0.3;
            }
            if (propertyFields.show_header) {
                propertyFields.show_header.checked = object.show_header !== false;
            }
            if (propertyFields.row_height_mm) {
                propertyFields.row_height_mm.value = Number(object.row_height_mm || 8).toFixed(1);
            }
            if (propertyFields.columns) {
                propertyFields.columns.value = JSON.stringify(object.columns || [], null, 2);
            }
            if (propertyFields.header_background) {
                propertyFields.header_background.value = style.header_background || '#e5e7eb';
            }
            if (propertyFields.header_color) {
                propertyFields.header_color.value = style.header_color || '#111111';
            }
            if (propertyFields.border_color) {
                propertyFields.border_color.value = style.border_color || '#94a3b8';
            }

            togglePropertyRows(object);
        }

        function selectObject(objectId) {
            state.selectedId = objectId;
            renderCanvas();
            updatePropertiesPanel();
        }

        function nextObjectId(type) {
            return 'obj_' + type + '_' + Date.now() + '_' + Math.floor(Math.random() * 10000);
        }

        function defaultObject(type, source) {
            var defaults = state.pageConfig.defaults || {};

            if (type === 'line') {
                return {
                    id: nextObjectId(type),
                    type: 'line',
                    x_mm: 15,
                    y_mm: 50,
                    w_mm: 80,
                    h_mm: 0,
                    z_index: 5,
                    style: {
                        stroke: '#1f2937',
                        stroke_width_mm: 0.3
                    }
                };
            }

            if (type === 'rect') {
                return {
                    id: nextObjectId(type),
                    type: 'rect',
                    x_mm: 15,
                    y_mm: 60,
                    w_mm: 80,
                    h_mm: 25,
                    z_index: 4,
                    style: {
                        stroke: '#94a3b8',
                        stroke_width_mm: 0.3,
                        fill: 'transparent'
                    }
                };
            }

            if (type === 'variable') {
                return {
                    id: nextObjectId(type),
                    type: 'variable',
                    x_mm: 15,
                    y_mm: 90,
                    w_mm: 70,
                    h_mm: 8,
                    z_index: 10,
                    source: source || 'cliente.nombre',
                    style: {
                        font_family: defaults.font_family || 'Arial, Helvetica, sans-serif',
                        font_size_pt: defaults.font_size_pt || 10,
                        font_weight: 400,
                        color: defaults.color || '#111111',
                        align: 'left'
                    }
                };
            }

            if (type === 'table_repeater') {
                return {
                    id: nextObjectId(type),
                    type: 'table_repeater',
                    x_mm: 10,
                    y_mm: 50,
                    w_mm: 190,
                    h_mm: 150,
                    z_index: 6,
                    source: 'items[]',
                    show_header: true,
                    row_height_mm: 8,
                    columns: [
                        { key: 'codigo', label: 'Codigo', width_mm: 30, align: 'left' },
                        { key: 'descripcion', label: 'Descripcion', width_mm: 80, align: 'left' },
                        { key: 'cantidad', label: 'Cant.', width_mm: 20, align: 'right' },
                        { key: 'precio_unitario', label: 'Precio', width_mm: 30, align: 'right' },
                        { key: 'importe', label: 'Importe', width_mm: 30, align: 'right' }
                    ],
                    style: {
                        font_family: defaults.font_family || 'Arial, Helvetica, sans-serif',
                        font_size_pt: 9,
                        color: defaults.color || '#111111',
                        header_background: '#e5e7eb',
                        header_color: '#111111',
                        border_color: '#94a3b8'
                    }
                };
            }

            if (type === 'image') {
                return {
                    id: nextObjectId(type),
                    type: 'image',
                    x_mm: 15,
                    y_mm: 50,
                    w_mm: 40,
                    h_mm: 40,
                    z_index: 2,
                    source: '', // Variable
                    content: '', // URL estática
                    style: {
                        object_fit: 'contain'
                    }
                };
            }

            return {
                id: nextObjectId(type),
                type: 'text',
                x_mm: 15,
                y_mm: 100,
                w_mm: 70,
                h_mm: 8,
                z_index: 10,
                content: 'Nuevo texto',
                style: {
                    font_family: defaults.font_family || 'Arial, Helvetica, sans-serif',
                    font_size_pt: defaults.font_size_pt || 10,
                    font_weight: 400,
                    color: defaults.color || '#111111',
                    align: 'left'
                }
            };
        }

        function addObject(type, source) {
            var object = defaultObject(type, source);
            state.objects.push(object);
            selectObject(object.id);
        }

        function updateSelected(callback) {
            var object = selectedObject();
            if (!object) {
                return;
            }

            callback(object);
            renderCanvas();
            updatePropertiesPanel();
        }

        if (orientationSelect) {
            orientationSelect.value = ((state.pageConfig.page || {}).orientation || 'portrait');
            orientationSelect.addEventListener('change', function () {
                state.pageConfig.page.orientation = orientationSelect.value;
                renderCanvas();
            });
        }

        if (gridEnabledInput) {
            gridEnabledInput.checked = !!(state.pageConfig.grid && state.pageConfig.grid.enabled);
            gridEnabledInput.addEventListener('change', function () {
                state.pageConfig.grid.enabled = !!gridEnabledInput.checked;
                renderCanvas();
            });
        }

        Object.keys(propertyFields).forEach(function (key) {
            var field = propertyFields[key];
            if (!field) {
                return;
            }

            field.addEventListener('input', function () {
                updateSelected(function (object) {
                    if (key === 'content' || key === 'source') {
                        object[key] = field.value;
                        return;
                    }

                    if (key === 'x_mm' || key === 'y_mm' || key === 'w_mm' || key === 'h_mm' || key === 'row_height_mm') {
                        var value = parseFloat(field.value || '0');
                        object[key] = Number.isFinite(value) ? value : 0;
                        return;
                    }

                    if (key === 'show_header') {
                        object[key] = field.checked;
                        return;
                    }

                    if (key === 'columns') {
                        try {
                            object[key] = JSON.parse(field.value || '[]');
                        } catch (e) {
                            // Ignorar parse error en vivo
                        }
                        return;
                    }

                    object.style = object.style || {};
                    if (key === 'font_size_pt' || key === 'stroke_width_mm') {
                        var numericValue = parseFloat(field.value || '0');
                        object.style[key] = Number.isFinite(numericValue) ? numericValue : 0;
                    } else if (key === 'font_weight') {
                        object.style[key] = parseInt(field.value || '400', 10);
                    } else if (key === 'fill' && object.type === 'rect') {
                        object.style[key] = field.value || '#ffffff';
                    } else {
                        object.style[key] = field.value;
                    }
                });
            });
        });

        form.querySelectorAll('[data-add-object]').forEach(function (button) {
            button.addEventListener('click', function () {
                addObject(button.getAttribute('data-add-object') || 'text');
            });
        });

        form.querySelectorAll('[data-add-variable]').forEach(function (button) {
            button.addEventListener('click', function () {
                var v = button.getAttribute('data-add-variable') || '';
                if (v === 'empresa.header_url' || v === 'empresa.footer_url') {
                    addObject('image', v);
                } else {
                    addObject('variable', v);
                }
            });
        });

        var variableSearchInput = document.getElementById('print-variable-search');
        if (variableSearchInput) {
            variableSearchInput.addEventListener('input', function (e) {
                var term = e.target.value.toLowerCase().trim();
                var groups = form.querySelectorAll('.print-variable-group');
                groups.forEach(function (group) {
                    var hasVisibleItem = false;
                    var items = group.querySelectorAll('.print-variable-chip');
                    items.forEach(function (item) {
                        var text = (item.textContent || '').toLowerCase();
                        var source = (item.getAttribute('data-add-variable') || '').toLowerCase();
                        if (term === '' || text.indexOf(term) > -1 || source.indexOf(term) > -1) {
                            item.style.display = '';
                            hasVisibleItem = true;
                        } else {
                            item.style.display = 'none';
                        }
                    });
                    group.style.display = hasVisibleItem ? '' : 'none';
                });
            });
        }

        if (deleteButton) {
            deleteButton.addEventListener('click', function () {
                if (!state.selectedId) {
                    return;
                }

                state.objects = state.objects.filter(function (object) {
                    return object.id !== state.selectedId;
                });
                state.selectedId = null;
                renderCanvas();
                updatePropertiesPanel();
            });
        }

        if (backgroundInput) {
            backgroundInput.addEventListener('change', function () {
                var file = backgroundInput.files && backgroundInput.files[0];
                if (!file) {
                    return;
                }

                clearBackground.checked = false;
                var reader = new FileReader();
                reader.onload = function (event) {
                    state.uploadedBackgroundUrl = String((event.target && event.target.result) || '');
                    state.backgroundUrl = state.uploadedBackgroundUrl;
                    refreshSheetBackground();
                };
                reader.readAsDataURL(file);
            });
        }

        if (clearBackground) {
            clearBackground.addEventListener('change', function () {
                if (clearBackground.checked) {
                    state.backgroundUrl = '';
                } else {
                    state.backgroundUrl = state.uploadedBackgroundUrl || state.originalBackgroundUrl;
                }
                refreshSheetBackground();
            });
        }

        stage.addEventListener('click', function (event) {
            var objectNode = event.target.closest('.print-object');
            if (!objectNode) {
                state.selectedId = null;
                renderCanvas();
                updatePropertiesPanel();
                return;
            }

            selectObject(objectNode.dataset.objectId || null);
        });

        stage.addEventListener('pointerdown', function (event) {
            var objectNode = event.target.closest('.print-object');
            if (!objectNode) {
                return;
            }

            event.preventDefault();
            var object = state.objects.find(function (candidate) {
                return candidate.id === objectNode.dataset.objectId;
            });
            if (!object) {
                return;
            }

            selectObject(object.id);
            var isResizing = !!event.target.closest('[data-resizer]');

            state.drag = {
                id: object.id,
                action: isResizing ? 'resize' : 'move',
                startX: event.clientX,
                startY: event.clientY,
                originX: Number(object.x_mm || 0),
                originY: Number(object.y_mm || 0),
                originW: Number(object.w_mm || 0),
                originH: Number(object.h_mm || 0)
            };
        });

        document.addEventListener('pointermove', function (event) {
            if (!state.drag) {
                return;
            }

            var object = selectedObject();
            if (!object || object.id !== state.drag.id) {
                return;
            }

            var box = pageBox();
            var deltaXmm = ((event.clientX - state.drag.startX) / stage.clientWidth) * box.widthMm;
            var deltaYmm = ((event.clientY - state.drag.startY) / stage.clientHeight) * box.heightMm;
            var snapStep = Number((state.pageConfig.grid && state.pageConfig.grid.step_mm) || 2);
            var snapEnabled = !!(state.pageConfig.grid && state.pageConfig.grid.snap);

            if (state.drag.action === 'resize') {
                var nextW = state.drag.originW + deltaXmm;
                var nextH = state.drag.originH + deltaYmm;

                if (snapEnabled) {
                    nextW = Math.round(nextW / snapStep) * snapStep;
                    nextH = Math.round(nextH / snapStep) * snapStep;
                }

                object.w_mm = Math.max(1, nextW);
                object.h_mm = Math.max(1, nextH);
            } else {
                var nextX = state.drag.originX + deltaXmm;
                var nextY = state.drag.originY + deltaYmm;

                if (snapEnabled) {
                    nextX = Math.round(nextX / snapStep) * snapStep;
                    nextY = Math.round(nextY / snapStep) * snapStep;
                }

                object.x_mm = clamp(nextX, 0, Math.max(0, box.widthMm - Number(object.w_mm || 0)));
                object.y_mm = clamp(nextY, 0, Math.max(0, box.heightMm - Number(object.h_mm || 0)));
            }
            renderCanvas();
            updatePropertiesPanel();
        });

        document.addEventListener('pointerup', function () {
            state.drag = null;
        });

        form.addEventListener('submit', function () {
            syncHiddenInputs();
        });

        if (state.objects.length) {
            state.selectedId = state.objects[0].id;
        }

        renderCanvas();
        updatePropertiesPanel();
    }

    document.addEventListener('DOMContentLoaded', setupEditor);
})();


