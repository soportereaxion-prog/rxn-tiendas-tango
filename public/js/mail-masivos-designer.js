/* ─────────────────────────────────────────────────────────────────
   Mail Masivos — Diseñador visual de Reportes (estilo n8n-like)

   Entrada:
     - `#mm-designer`    → contenedor raíz
     - dataset.metamodel → URL al endpoint del metamodelo
     - dataset.initial   → JSON string con config inicial (edit mode)

   Salida:
     - Al hacer click en "Guardar", completa el input hidden
       `#config_json` con el JSON del diseño y dispara submit del form padre.

   Sin dependencias externas — JS puro + SVG + CSS del tema.
   ───────────────────────────────────────────────────────────────── */
(function () {
    'use strict';

    const root = document.getElementById('mm-designer');
    if (!root) return;

    const metamodelUrl = root.dataset.metamodelUrl;
    const previewUrl = root.dataset.previewUrl;
    const initialRaw = root.dataset.initial || '';

    // ── Estado ──────────────────────────────────────────────────────
    const state = {
        metamodel: null,         // { entities: {...}, operators_by_type: {...}, dynamic_tokens: [...] }
        rootEntity: '',          // nombre de la entidad raíz
        placed: {},              // { EntityName: { x, y, fields: Set<string>, el: HTMLElement } }
        relations: [],           // [{from, relation, targetEntity}]
        filters: [],             // [{entity, field, op, value}]
        filtersLogic: 'AND',     // 'AND' | 'OR' — conector entre filtros del usuario
        mailTarget: null,        // {entity, field} | null
        previewPage: 1,          // página actual del preview (1-based)
        previewPerPage: 25,      // por página
        previewLast: null,       // snapshot del último response (para repaginar sin reejecutar el form)
    };

    // ── Referencias DOM ─────────────────────────────────────────────
    const dom = {
        rootEntitySelect: document.getElementById('mm-root-entity'),
        sidebarEntities: document.getElementById('mm-sidebar-entities'),
        canvas: document.getElementById('mm-canvas'),
        svg: document.getElementById('mm-canvas-svg'),
        emptyState: document.getElementById('mm-canvas-empty'),
        filtersList: document.getElementById('mm-filters-list'),
        filterAddBtn: document.getElementById('mm-filter-add'),
        jsonPanel: document.getElementById('mm-json-panel'),
        jsonToggle: document.getElementById('mm-json-toggle'),
        previewBtn: document.getElementById('mm-preview-btn'),
        previewPanel: document.getElementById('mm-preview-panel'),
        previewContent: document.getElementById('mm-preview-content'),
        saveBtn: document.getElementById('mm-save-btn'),
        configInput: document.getElementById('config_json'),
        rootEntityInput: document.getElementById('root_entity'),
    };

    // ── Bootstrap ───────────────────────────────────────────────────
    init();

    async function init() {
        try {
            const res = await fetch(metamodelUrl);
            if (!res.ok) throw new Error('No se pudo cargar el metamodelo');
            state.metamodel = await res.json();

            populateRootEntitySelect();
            populateSidebarEntities();
            bindEvents();

            if (initialRaw) {
                loadInitialConfig(initialRaw);
            }

            render();
        } catch (err) {
            console.error('Designer init failed:', err);
            root.innerHTML = '<div class="alert alert-danger">No se pudo inicializar el diseñador: ' + escapeHtml(err.message) + '</div>';
        }
    }

    // ── Población de UI base ────────────────────────────────────────
    function populateRootEntitySelect() {
        if (!dom.rootEntitySelect) return;
        dom.rootEntitySelect.innerHTML = '<option value="">— Elegí una entidad raíz —</option>';
        Object.entries(state.metamodel.entities).forEach(([key, def]) => {
            const opt = document.createElement('option');
            opt.value = key;
            opt.textContent = def.label + (def.mail_field ? ' — mail: ' + def.mail_field : '');
            dom.rootEntitySelect.appendChild(opt);
        });
    }

    function populateSidebarEntities() {
        if (!dom.sidebarEntities) return;
        dom.sidebarEntities.innerHTML = '';
        Object.entries(state.metamodel.entities).forEach(([key, def]) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'mm-add-entity-btn';
            btn.dataset.entity = key;
            btn.innerHTML = '<i class="bi bi-plus-circle"></i><span>' + escapeHtml(def.label) + '</span>';
            btn.addEventListener('click', () => addEntityToCanvas(key));
            dom.sidebarEntities.appendChild(btn);
        });
    }

    function bindEvents() {
        dom.rootEntitySelect.addEventListener('change', () => {
            const newRoot = dom.rootEntitySelect.value;
            if (!newRoot) return;
            if (state.rootEntity && state.placed[state.rootEntity]) {
                // si cambió la raíz, limpiar canvas
                if (!confirm('Cambiar la entidad raíz reiniciará el diseño actual. ¿Continuar?')) {
                    dom.rootEntitySelect.value = state.rootEntity;
                    return;
                }
                resetCanvas();
            }
            state.rootEntity = newRoot;
            dom.rootEntityInput.value = newRoot;
            addEntityToCanvas(newRoot, 60, 60);
            render();
        });

        dom.filterAddBtn.addEventListener('click', () => {
            state.filters.push({ entity: '', field: '', op: '=', value: '' });
            renderFilters();
        });

        // Toggle AND/OR
        document.querySelectorAll('input[name="mm-filters-logic"]').forEach(radio => {
            radio.addEventListener('change', () => {
                if (radio.checked) {
                    state.filtersLogic = radio.value === 'OR' ? 'OR' : 'AND';
                    refreshJsonPanel();
                    renderFilters(); // refresca el separador visual entre filas
                }
            });
        });

        dom.jsonToggle.addEventListener('click', () => {
            const isVisible = dom.jsonPanel.style.display !== 'none';
            dom.jsonPanel.style.display = isVisible ? 'none' : 'block';
            if (!isVisible) refreshJsonPanel();
        });

        dom.previewBtn.addEventListener('click', runPreview);
        dom.saveBtn.addEventListener('click', saveDesign);
    }

    // ── Canvas: gestión de nodos ────────────────────────────────────
    function addEntityToCanvas(entity, x, y) {
        if (state.placed[entity]) return;
        if (!state.metamodel.entities[entity]) return;

        // Posición: si no se pasó, ubicar relativo a la cantidad actual
        if (x == null || y == null) {
            const n = Object.keys(state.placed).length;
            x = 60 + (n * 290);
            y = 60 + ((n % 2) * 80);
        }

        state.placed[entity] = {
            x, y,
            fields: new Set(),
            el: null,
        };

        render();
    }

    function removeEntityFromCanvas(entity) {
        if (entity === state.rootEntity) {
            alert('No se puede eliminar la entidad raíz. Cambiála desde el selector de arriba.');
            return;
        }
        delete state.placed[entity];
        // Eliminar cualquier relación que apunte a o venga de esta entidad
        state.relations = state.relations.filter(r => r.from !== entity && r.targetEntity !== entity);
        // Eliminar filtros de esta entidad
        state.filters = state.filters.filter(f => f.entity !== entity);
        // Si era el mail target, limpiarlo
        if (state.mailTarget && state.mailTarget.entity === entity) {
            state.mailTarget = null;
        }
        render();
    }

    function toggleField(entity, field) {
        const p = state.placed[entity];
        if (!p) return;
        if (p.fields.has(field)) {
            p.fields.delete(field);
            // si era el mail target, limpiarlo
            if (state.mailTarget && state.mailTarget.entity === entity && state.mailTarget.field === field) {
                state.mailTarget = null;
            }
        } else {
            p.fields.add(field);
        }
        render();
    }

    function toggleMailTarget(entity, field) {
        const p = state.placed[entity];
        if (!p) return;
        // Asegurar que el campo esté prendido como output
        p.fields.add(field);

        if (state.mailTarget && state.mailTarget.entity === entity && state.mailTarget.field === field) {
            state.mailTarget = null;
        } else {
            state.mailTarget = { entity, field };
        }
        render();
    }

    function toggleRelation(fromEntity, relationName) {
        const existing = state.relations.find(r => r.from === fromEntity && r.relation === relationName);
        if (existing) {
            // Apagar relación + remover entidad destino
            state.relations = state.relations.filter(r => !(r.from === fromEntity && r.relation === relationName));
            if (existing.targetEntity && existing.targetEntity !== state.rootEntity) {
                // Remover solo si ninguna otra relación la usa
                const stillUsed = state.relations.some(r => r.targetEntity === existing.targetEntity);
                if (!stillUsed) {
                    delete state.placed[existing.targetEntity];
                    state.filters = state.filters.filter(f => f.entity !== existing.targetEntity);
                }
            }
        } else {
            const relDef = state.metamodel.entities[fromEntity]?.relations?.[relationName];
            if (!relDef) return;
            const target = relDef.target_entity;
            state.relations.push({ from: fromEntity, relation: relationName, targetEntity: target });
            if (!state.placed[target]) {
                addEntityToCanvas(target);
                return; // addEntity llama render
            }
        }
        render();
    }

    function resetCanvas() {
        state.placed = {};
        state.relations = [];
        state.filters = [];
        state.mailTarget = null;
    }

    // ── Render ──────────────────────────────────────────────────────
    function render() {
        renderCanvas();
        renderFilters();
        refreshSidebarState();
        refreshJsonPanel();
    }

    function renderCanvas() {
        // Limpiar nodos anteriores (conservando el SVG y el empty state)
        Array.from(dom.canvas.querySelectorAll('.mm-node')).forEach(n => n.remove());

        const hasNodes = Object.keys(state.placed).length > 0;
        if (dom.emptyState) dom.emptyState.style.display = hasNodes ? 'none' : '';

        // Renderizar nodos
        Object.entries(state.placed).forEach(([entity, p]) => {
            const node = buildNodeElement(entity, p);
            p.el = node;
            dom.canvas.appendChild(node);
            makeDraggable(node, p);
        });

        updateCanvasSize();
        renderSvgLines();
    }

    // Recalcula el tamaño del canvas en base a la posición de los nodos.
    // Arranca con un tamaño mínimo cómodo (ajustado al wrap) y crece lo
    // justo para contener los nodos con un pad de 80px.
    function updateCanvasSize() {
        if (!dom.canvas) return;

        const wrap = dom.canvas.parentElement;
        const minW = wrap ? wrap.clientWidth - 2 : 600;
        const minH = 400;

        let maxX = minW;
        let maxY = minH;

        Object.values(state.placed).forEach(p => {
            const w = (p.el ? p.el.offsetWidth : 0) || 260;
            const h = (p.el ? p.el.offsetHeight : 0) || 200;
            maxX = Math.max(maxX, p.x + w + 80);
            maxY = Math.max(maxY, p.y + h + 80);
        });

        dom.canvas.style.width = maxX + 'px';
        dom.canvas.style.height = maxY + 'px';
    }

    function buildNodeElement(entity, p) {
        const def = state.metamodel.entities[entity];
        const isRoot = entity === state.rootEntity;

        const node = document.createElement('div');
        node.className = 'mm-node' + (isRoot ? ' is-root' : '');
        node.dataset.entity = entity;
        node.style.left = p.x + 'px';
        node.style.top = p.y + 'px';

        // Header
        const header = document.createElement('div');
        header.className = 'mm-node-header';
        header.innerHTML =
            '<span class="mm-node-title">' + escapeHtml(def.label) + '</span>' +
            (isRoot ? '<span class="mm-node-badge">RAÍZ</span>' : '') +
            (!isRoot ? '<button type="button" class="mm-node-remove" title="Quitar del canvas">×</button>' : '');
        node.appendChild(header);

        const removeBtn = header.querySelector('.mm-node-remove');
        if (removeBtn) {
            removeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                removeEntityFromCanvas(entity);
            });
        }

        // Body con campos
        const body = document.createElement('div');
        body.className = 'mm-node-body';
        Object.entries(def.fields).forEach(([fname, fdef]) => {
            const row = document.createElement('div');
            row.className = 'mm-field-row';
            const isOutput = p.fields.has(fname);
            const isMailTgt = state.mailTarget && state.mailTarget.entity === entity && state.mailTarget.field === fname;
            if (isMailTgt) row.classList.add('is-mail-target');

            const cbId = 'mm-f-' + entity + '-' + fname;
            const typeLabel = fdef.type || 'string';
            const isEmailField = typeLabel === 'email' || fdef.is_mail_target;

            row.innerHTML =
                '<input type="checkbox" id="' + cbId + '"' + (isOutput ? ' checked' : '') + '>' +
                '<label for="' + cbId + '">' + escapeHtml(fname) +
                ' <span class="mm-field-type">' + escapeHtml(typeLabel) + '</span></label>' +
                (isEmailField
                    ? '<button type="button" class="mm-field-mail" title="Marcar como destinatario de mail"><i class="bi bi-envelope' + (isMailTgt ? '-check-fill' : '') + '"></i></button>'
                    : '');

            row.querySelector('input[type="checkbox"]').addEventListener('change', () => toggleField(entity, fname));
            const mailBtn = row.querySelector('.mm-field-mail');
            if (mailBtn) mailBtn.addEventListener('click', () => toggleMailTarget(entity, fname));

            body.appendChild(row);
        });
        node.appendChild(body);

        // Footer con relaciones
        const relations = def.relations || {};
        if (Object.keys(relations).length > 0) {
            const footer = document.createElement('div');
            footer.className = 'mm-node-footer';
            footer.innerHTML = '<div class="mm-relations-label">Relaciones</div>';
            Object.entries(relations).forEach(([rname, rdef]) => {
                const isActive = state.relations.some(r => r.from === entity && r.relation === rname);
                const chip = document.createElement('span');
                chip.className = 'mm-relation-chip' + (isActive ? ' is-active' : '');
                chip.textContent = rdef.label || rname;
                chip.title = rdef.type + ' → ' + rdef.target_entity;
                chip.addEventListener('click', () => toggleRelation(entity, rname));
                footer.appendChild(chip);
            });
            node.appendChild(footer);
        }

        return node;
    }

    function makeDraggable(nodeEl, p) {
        const header = nodeEl.querySelector('.mm-node-header');
        if (!header) return;

        let startX, startY, origX, origY;

        header.addEventListener('mousedown', (e) => {
            if (e.target.closest('.mm-node-remove')) return;
            startX = e.clientX;
            startY = e.clientY;
            origX = p.x;
            origY = p.y;
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
            e.preventDefault();
        });

        function onMove(e) {
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            p.x = Math.max(0, origX + dx);
            p.y = Math.max(0, origY + dy);
            nodeEl.style.left = p.x + 'px';
            nodeEl.style.top = p.y + 'px';
            updateCanvasSize();
            renderSvgLines();
        }

        function onUp() {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
            refreshJsonPanel();
        }
    }

    // ── SVG Lines ───────────────────────────────────────────────────
    function renderSvgLines() {
        if (!dom.svg) return;
        // Limpiar paths
        dom.svg.innerHTML = '';

        state.relations.forEach(rel => {
            const fromP = state.placed[rel.from];
            const toP = state.placed[rel.targetEntity];
            if (!fromP || !toP || !fromP.el || !toP.el) return;

            const fromRect = {
                x: fromP.x + fromP.el.offsetWidth,
                y: fromP.y + fromP.el.offsetHeight / 2,
            };
            const toRect = {
                x: toP.x,
                y: toP.y + toP.el.offsetHeight / 2,
            };

            const dx = Math.max(60, Math.abs(toRect.x - fromRect.x) / 2);
            const d = `M ${fromRect.x} ${fromRect.y} C ${fromRect.x + dx} ${fromRect.y}, ${toRect.x - dx} ${toRect.y}, ${toRect.x} ${toRect.y}`;

            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', d);
            path.setAttribute('data-from', rel.from);
            path.setAttribute('data-to', rel.targetEntity);
            dom.svg.appendChild(path);
        });
    }

    // ── Sidebar state ───────────────────────────────────────────────
    function refreshSidebarState() {
        if (!dom.sidebarEntities) return;
        Array.from(dom.sidebarEntities.querySelectorAll('.mm-add-entity-btn')).forEach(btn => {
            const entity = btn.dataset.entity;
            btn.classList.toggle('is-placed', !!state.placed[entity]);
        });
    }

    // ── Filtros ─────────────────────────────────────────────────────
    function renderFilters() {
        if (!dom.filtersList) return;
        dom.filtersList.innerHTML = '';

        if (state.filters.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'mm-filter-empty';
            empty.textContent = 'No hay filtros. Los clientes sin filtros aplicados entrarán todos.';
            dom.filtersList.appendChild(empty);
            return;
        }

        state.filters.forEach((f, idx) => {
            // Separador con el conector entre filas (a partir de la segunda)
            if (idx > 0) {
                const sep = document.createElement('div');
                sep.className = 'mm-filter-connector';
                sep.textContent = state.filtersLogic === 'OR' ? 'O' : 'Y';
                dom.filtersList.appendChild(sep);
            }

            const row = document.createElement('div');
            row.className = 'mm-filter-row';

            // Entidad
            const selEnt = document.createElement('select');
            selEnt.className = 'form-select form-select-sm';
            selEnt.innerHTML = '<option value="">Entidad...</option>' + Object.keys(state.placed).map(e =>
                `<option value="${escapeAttr(e)}"${f.entity === e ? ' selected' : ''}>${escapeHtml(e)}</option>`
            ).join('');
            selEnt.addEventListener('change', () => {
                f.entity = selEnt.value;
                f.field = '';
                renderFilters();
                refreshJsonPanel();
            });

            // Campo
            const selField = document.createElement('select');
            selField.className = 'form-select form-select-sm';
            selField.disabled = !f.entity;
            selField.innerHTML = '<option value="">Campo...</option>';
            if (f.entity && state.metamodel.entities[f.entity]) {
                const fields = state.metamodel.entities[f.entity].fields || {};
                Object.entries(fields).forEach(([fname, fdef]) => {
                    if (fdef.filterable === false) return;
                    selField.innerHTML += `<option value="${escapeAttr(fname)}"${f.field === fname ? ' selected' : ''}>${escapeHtml(fname)} (${escapeHtml(fdef.type || 'string')})</option>`;
                });
            }
            selField.addEventListener('change', () => {
                f.field = selField.value;
                renderFilters();
                refreshJsonPanel();
            });

            // Operador
            const selOp = document.createElement('select');
            selOp.className = 'form-select form-select-sm';
            selOp.disabled = !f.field;
            if (f.entity && f.field) {
                const ftype = state.metamodel.entities[f.entity].fields[f.field].type || 'string';
                const ops = state.metamodel.operators_by_type[ftype] || state.metamodel.operators_by_type.string;
                selOp.innerHTML = ops.map(op => `<option value="${escapeAttr(op)}"${f.op === op ? ' selected' : ''}>${escapeHtml(op)}</option>`).join('');
                if (!f.op || !ops.includes(f.op)) f.op = ops[0];
            } else {
                selOp.innerHTML = '<option value="=">=</option>';
            }
            selOp.addEventListener('change', () => {
                f.op = selOp.value;
                refreshJsonPanel();
            });

            // Valor — wrap con botón calendario para insertar tokens dinámicos
            const valWrap = document.createElement('div');
            valWrap.className = 'mm-filter-value-wrap';

            const inpVal = document.createElement('input');
            inpVal.type = 'text';
            inpVal.className = 'form-control form-control-sm';
            inpVal.placeholder = 'Valor';
            const nullaryOps = ['IS NULL', 'IS NOT NULL'];
            if (nullaryOps.includes(f.op)) {
                inpVal.disabled = true;
                inpVal.value = '';
            } else if (f.op === 'IN' || f.op === 'NOT IN') {
                inpVal.placeholder = 'valor1, valor2, ...';
                inpVal.value = Array.isArray(f.value) ? f.value.join(', ') : (f.value || '');
            } else if (f.op === 'BETWEEN') {
                inpVal.placeholder = 'min, max — soporta {{HOY}}, {{INICIO_MES}}, etc.';
                inpVal.value = Array.isArray(f.value) ? f.value.join(', ') : (f.value || '');
            } else {
                inpVal.value = f.value != null ? String(f.value) : '';
            }
            inpVal.addEventListener('input', () => {
                f.value = inpVal.value;
                refreshJsonPanel();
            });
            valWrap.appendChild(inpVal);

            // Botón "📅" — abre menú de tokens dinámicos. Habilitado salvo en operadores nularios.
            const ftype = f.entity && f.field
                ? (state.metamodel.entities[f.entity]?.fields?.[f.field]?.type || 'string')
                : 'string';
            const isDateLike = ftype === 'date' || ftype === 'datetime';
            const tokens = state.metamodel.dynamic_tokens || [];
            if (!nullaryOps.includes(f.op) && tokens.length > 0) {
                const btnTok = document.createElement('button');
                btnTok.type = 'button';
                btnTok.className = 'mm-filter-token-btn' + (isDateLike ? ' is-suggested' : '');
                btnTok.title = isDateLike
                    ? 'Insertar valor dinámico (se resuelve al ejecutar el reporte)'
                    : 'Insertar valor dinámico — útil para fechas';
                btnTok.innerHTML = '<i class="bi bi-calendar-event"></i>';
                btnTok.addEventListener('click', (ev) => {
                    ev.stopPropagation();
                    openTokenMenu(btnTok, inpVal, f);
                });
                valWrap.appendChild(btnTok);
            }

            // Remove
            const btnRemove = document.createElement('button');
            btnRemove.type = 'button';
            btnRemove.className = 'mm-filter-remove';
            btnRemove.innerHTML = '×';
            btnRemove.addEventListener('click', () => {
                state.filters.splice(idx, 1);
                renderFilters();
                refreshJsonPanel();
            });

            row.appendChild(selEnt);
            row.appendChild(selField);
            row.appendChild(selOp);
            row.appendChild(valWrap);
            row.appendChild(btnRemove);
            dom.filtersList.appendChild(row);
        });
    }

    // ── Menú flotante de tokens dinámicos ───────────────────────────
    let openTokenMenuEl = null;
    function closeTokenMenu() {
        if (openTokenMenuEl) {
            openTokenMenuEl.remove();
            openTokenMenuEl = null;
            document.removeEventListener('click', closeTokenMenu);
        }
    }
    function openTokenMenu(anchor, input, filter) {
        closeTokenMenu();
        const tokens = state.metamodel.dynamic_tokens || [];
        if (tokens.length === 0) return;

        const menu = document.createElement('div');
        menu.className = 'mm-token-menu';
        menu.innerHTML = '<div class="mm-token-menu-header">Insertar valor dinámico</div>';

        tokens.forEach(t => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'mm-token-menu-item';
            item.innerHTML = '<span class="mm-token-label">' + escapeHtml(t.label) + '</span>'
                + '<code class="mm-token-code">' + escapeHtml(t.token) + '</code>';
            item.addEventListener('click', (ev) => {
                ev.stopPropagation();
                insertToken(input, filter, t.token);
                closeTokenMenu();
            });
            menu.appendChild(item);
        });

        const hint = document.createElement('div');
        hint.className = 'mm-token-menu-hint';
        hint.innerHTML = 'Para BETWEEN podés combinar dos: ej. <code>{{INICIO_MES}}, {{FIN_MES}}</code>.';
        menu.appendChild(hint);

        document.body.appendChild(menu);
        openTokenMenuEl = menu;

        // Posicionar relativo al botón
        const r = anchor.getBoundingClientRect();
        const menuRect = menu.getBoundingClientRect();
        let top = r.bottom + window.scrollY + 4;
        let left = r.right - menuRect.width + window.scrollX;
        if (left < 8) left = 8;
        // Si se sale por abajo, abrir hacia arriba
        if (top + menuRect.height > window.scrollY + window.innerHeight - 8) {
            top = r.top + window.scrollY - menuRect.height - 4;
        }
        menu.style.top = top + 'px';
        menu.style.left = left + 'px';

        // Cerrar con click afuera (en el próximo tick para no autocerrarse)
        setTimeout(() => document.addEventListener('click', closeTokenMenu), 0);
    }
    function insertToken(input, filter, token) {
        const cur = input.value || '';
        // Si el operador es BETWEEN/IN y ya hay un valor sin coma final, sumar coma + token.
        // En todos los otros casos: si hay valor sin token, reemplazar; si no, insertar.
        let next;
        if (filter.op === 'BETWEEN' || filter.op === 'IN' || filter.op === 'NOT IN') {
            if (cur.trim() === '') next = token;
            else if (cur.trim().endsWith(',')) next = cur + ' ' + token;
            else next = cur + ', ' + token;
        } else {
            next = token;
        }
        input.value = next;
        filter.value = next;
        input.focus();
        refreshJsonPanel();
    }

    // ── Serialización ───────────────────────────────────────────────
    function buildConfig() {
        const fields = [];
        Object.entries(state.placed).forEach(([entity, p]) => {
            p.fields.forEach(field => fields.push({ entity, field }));
        });

        const relations = state.relations.map(r => ({
            from: r.from,
            relation: r.relation,
        }));

        // Normalizar filtros (parsear IN/BETWEEN desde string)
        const filters = state.filters
            .filter(f => f.entity && f.field && f.op)
            .map(f => {
                let value = f.value;
                if (f.op === 'IN' || f.op === 'NOT IN') {
                    value = String(value || '').split(',').map(v => v.trim()).filter(v => v !== '');
                } else if (f.op === 'BETWEEN') {
                    const parts = String(value || '').split(',').map(v => v.trim());
                    value = parts.length >= 2 ? [parts[0], parts[1]] : parts;
                }
                const out = { entity: f.entity, field: f.field, op: f.op };
                if (!['IS NULL', 'IS NOT NULL'].includes(f.op)) out.value = value;
                return out;
            });

        const cfg = {
            root_entity: state.rootEntity,
            relations,
            fields,
            filters,
            filters_logic: state.filtersLogic || 'AND',
        };
        if (state.mailTarget) {
            cfg.mail_field = { ...state.mailTarget };
        }
        return cfg;
    }

    function refreshJsonPanel() {
        if (!dom.jsonPanel) return;
        const pre = dom.jsonPanel.querySelector('pre');
        if (pre) pre.textContent = JSON.stringify(buildConfig(), null, 2);
    }

    // ── Preview & Save ──────────────────────────────────────────────
    async function runPreview(page) {
        const cfg = buildConfig();
        if (!cfg.root_entity) {
            alert('Elegí una entidad raíz primero.');
            return;
        }
        if (cfg.fields.length === 0) {
            alert('Marcá al menos un campo en algún nodo.');
            return;
        }
        if (typeof page === 'number' && page > 0) {
            state.previewPage = page;
        } else {
            state.previewPage = 1; // reset al ejecutar de nuevo desde el botón
        }
        cfg._page = state.previewPage;
        cfg._per_page = state.previewPerPage;

        dom.previewPanel.style.display = '';
        dom.previewContent.innerHTML = '<div class="text-info">⏳ Ejecutando preview...</div>';
        dom.previewBtn.disabled = true;

        try {
            const res = await fetch(previewUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(cfg),
            });
            const data = await res.json();
            state.previewLast = data;
            dom.previewContent.innerHTML = renderPreviewResult(data);
            wirePreviewPagination();
        } catch (err) {
            dom.previewContent.innerHTML = '<div class="alert alert-danger">Error de red: ' + escapeHtml(err.message) + '</div>';
        } finally {
            dom.previewBtn.disabled = false;
        }
    }

    function renderPreviewResult(data) {
        if (!data.success) {
            return '<div class="alert alert-danger">'
                + '<strong>' + (data.kind === 'validation' ? 'Validación' : 'Servidor') + ':</strong> '
                + escapeHtml(data.message || 'sin detalle')
                + '</div>';
        }

        const total = data.total != null ? data.total : data.row_count;
        const page = data.page || 1;
        const perPage = data.per_page || 25;
        const totalPages = data.total_pages || 1;

        let html = '<div class="alert alert-success py-2 small mb-3">';
        if (data.is_content_report) {
            html += '<strong>✓ OK</strong> — ' + total + ' fila(s) totales · página ' + page + ' de ' + totalPages + '. ';
            html += '<span class="text-muted">Este reporte es de <strong>contenido broadcast</strong> — se elige en el paso "Bloque de contenido" al crear un envío y sus filas se renderizan dentro del cuerpo del mail.</span>';
        } else {
            html += '<strong>✓ OK</strong> — ' + total + ' fila(s) totales · ' + data.mail_count + ' mail(s) único(s) · página ' + page + ' de ' + totalPages + '. ';
            if (data.mail_target) {
                html += 'Destinatario: <code>' + escapeHtml(data.mail_target.entity + '.' + data.mail_target.field) + '</code>';
            }
        }
        html += '</div>';

        if (data.rows && data.rows.length > 0) {
            const cols = Object.keys(data.rows[0]);
            html += '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr>';
            cols.forEach(c => { html += '<th>' + escapeHtml(c) + '</th>'; });
            html += '</tr></thead><tbody>';
            data.rows.forEach(row => {
                html += '<tr>';
                cols.forEach(c => { html += '<td>' + escapeHtml(row[c] == null ? '' : String(row[c])) + '</td>'; });
                html += '</tr>';
            });
            html += '</tbody></table></div>';

            html += renderPaginationControls(page, totalPages, perPage, total);
        } else {
            html += '<div class="text-muted small">El reporte no devolvió filas en esta página.</div>';
        }

        html += '<details class="mt-3"><summary class="small fw-semibold">SQL generado</summary>';
        html += '<pre class="p-2 mt-2 rounded mm-json-panel">' + escapeHtml(data.sql_debug) + '</pre>';
        html += '</details>';

        return html;
    }

    function renderPaginationControls(page, totalPages, perPage, total) {
        if (totalPages <= 1) {
            return '<div class="mm-preview-pager-info small text-muted mt-2">Mostrando ' + total + ' fila(s).</div>';
        }
        const start = (page - 1) * perPage + 1;
        const end = Math.min(page * perPage, total);

        return '<nav class="mm-preview-pager mt-2" aria-label="Paginación del preview">'
            + '<div class="mm-preview-pager-info small text-muted">Mostrando ' + start + '–' + end + ' de ' + total + '.</div>'
            + '<div class="mm-preview-pager-buttons">'
            + '  <button type="button" class="btn btn-sm btn-outline-secondary" data-page="1" ' + (page <= 1 ? 'disabled' : '') + '>«</button>'
            + '  <button type="button" class="btn btn-sm btn-outline-secondary" data-page="' + (page - 1) + '" ' + (page <= 1 ? 'disabled' : '') + '>‹</button>'
            + '  <span class="mx-2 small">Página '
            + '    <input type="number" min="1" max="' + totalPages + '" value="' + page + '" class="mm-preview-pager-input">'
            + '    de ' + totalPages
            + '  </span>'
            + '  <button type="button" class="btn btn-sm btn-outline-secondary" data-page="' + (page + 1) + '" ' + (page >= totalPages ? 'disabled' : '') + '>›</button>'
            + '  <button type="button" class="btn btn-sm btn-outline-secondary" data-page="' + totalPages + '" ' + (page >= totalPages ? 'disabled' : '') + '>»</button>'
            + '  <select class="form-select form-select-sm ms-2 mm-preview-pager-pp" style="width:auto;">'
            + ['10','25','50','100','200'].map(n => '<option value="' + n + '"' + (Number(n) === perPage ? ' selected' : '') + '>' + n + ' / pág</option>').join('')
            + '  </select>'
            + '</div>'
            + '</nav>';
    }

    function wirePreviewPagination() {
        const pager = dom.previewContent.querySelector('.mm-preview-pager');
        if (!pager) return;
        pager.querySelectorAll('button[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                const p = parseInt(btn.dataset.page, 10);
                if (p > 0) runPreview(p);
            });
        });
        const inp = pager.querySelector('.mm-preview-pager-input');
        if (inp) {
            inp.addEventListener('change', () => {
                const p = parseInt(inp.value, 10);
                if (p > 0) runPreview(p);
            });
        }
        const ppSel = pager.querySelector('.mm-preview-pager-pp');
        if (ppSel) {
            ppSel.addEventListener('change', () => {
                state.previewPerPage = parseInt(ppSel.value, 10) || 25;
                runPreview(1);
            });
        }
    }

    function saveDesign() {
        const cfg = buildConfig();
        if (!cfg.root_entity) {
            alert('Elegí una entidad raíz primero.');
            return;
        }
        if (cfg.fields.length === 0) {
            alert('Marcá al menos un campo de salida.');
            return;
        }
        dom.configInput.value = JSON.stringify(cfg, null, 2);
        dom.rootEntityInput.value = cfg.root_entity;

        // Disparar el submit del form contenedor
        const form = root.closest('form');
        if (form) form.submit();
    }

    // ── Carga de config existente ───────────────────────────────────
    function loadInitialConfig(raw) {
        let cfg;
        try {
            cfg = JSON.parse(raw);
        } catch (e) {
            console.warn('Config inicial inválido:', e);
            return;
        }

        if (!cfg.root_entity || !state.metamodel.entities[cfg.root_entity]) return;

        state.rootEntity = cfg.root_entity;
        dom.rootEntitySelect.value = state.rootEntity;
        dom.rootEntityInput.value = state.rootEntity;

        addEntityToCanvas(state.rootEntity, 60, 60);

        // Relaciones → prender entidades
        (cfg.relations || []).forEach(r => {
            if (!r.from || !r.relation) return;
            const relDef = state.metamodel.entities[r.from]?.relations?.[r.relation];
            if (!relDef) return;
            state.relations.push({ from: r.from, relation: r.relation, targetEntity: relDef.target_entity });
            if (!state.placed[relDef.target_entity]) {
                addEntityToCanvas(relDef.target_entity);
            }
        });

        // Campos prendidos
        (cfg.fields || []).forEach(f => {
            if (state.placed[f.entity]) {
                state.placed[f.entity].fields.add(f.field);
            }
        });

        // Lógica de filtros (default AND si el reporte es viejo)
        state.filtersLogic = (cfg.filters_logic === 'OR') ? 'OR' : 'AND';
        const logicRadio = document.querySelector('input[name="mm-filters-logic"][value="' + state.filtersLogic + '"]');
        if (logicRadio) logicRadio.checked = true;

        // Filtros
        state.filters = (cfg.filters || []).map(f => {
            let value = f.value;
            if (f.op === 'IN' || f.op === 'NOT IN' || f.op === 'BETWEEN') {
                if (Array.isArray(value)) value = value.join(', ');
            }
            return { entity: f.entity, field: f.field, op: f.op, value: value };
        });

        // Mail target
        if (cfg.mail_field && cfg.mail_field.entity && cfg.mail_field.field) {
            state.mailTarget = { ...cfg.mail_field };
        }
    }

    // ── Utils ───────────────────────────────────────────────────────
    function escapeHtml(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }
    function escapeAttr(str) {
        return escapeHtml(str);
    }
})();
