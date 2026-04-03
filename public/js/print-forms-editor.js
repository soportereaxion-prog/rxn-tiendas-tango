/**
 * Rxn Print Forms Editor
 * Reescritura Orientada a Objetos 2026
 * Manejo de transformaciones mediante origin-math y CSS Transforms en vez de zoom.
 */

(function () {
    if (!window.printFormsEditorConfig) {
        return;
    }

    class PrintFormsState {
        constructor(config) {
            this.documentKey = config.documentKey || '';
            this.pageConfig = config.pageConfig || { orientation: 'portrait', grid: { snap: false, step_mm: 5 } };
            this.objects = config.objects || [];
            this.objects.forEach(o => {
                if (o.type === 'variable') o.type = 'TEXT';
                else if (typeof o.type === 'string') o.type = o.type.toUpperCase();
            });
            this.fonts = config.fonts || [];
            this.variables = config.variables || [];
            this.availableFonts = config.availableFonts || [];
            this.sampleContext = config.sampleContext || {};
            this.backgroundUrl = config.backgroundUrl || '';
            this.selectedIds = new Set(this.objects.length > 0 ? [this.objects[0].id] : []);
            this.zoomLevel = 100;
            this.isPanningMode = false;
            this.undoStack = [];
        }

        saveUndo() {
            const currentJson = JSON.stringify({ objects: this.objects, pageConfig: this.pageConfig });
            if (this.undoStack.length === 0 || this.undoStack[this.undoStack.length - 1] !== currentJson) {
                this.undoStack.push(currentJson);
                if (this.undoStack.length > 20) this.undoStack.shift();
            }
        }

        undo() {
            if (this.undoStack.length === 0) return false;
            const stateJson = this.undoStack.pop();
            const previous = JSON.parse(stateJson);
            this.objects = previous.objects;
            this.pageConfig = previous.pageConfig;
            this.selectedIds = new Set([...this.selectedIds].filter(id => this.objects.some(o => o.id === id)));
            return true;
        }

        getSelectedObjects() {
            return this.objects.filter(o => this.selectedIds.has(o.id));
        }

        getPageBox() {
            const isLandscape = this.pageConfig.orientation === 'landscape';
            const portraitW = 210, portraitH = 297;
            return {
                widthMm: isLandscape ? portraitH : portraitW,
                heightMm: isLandscape ? portraitW : portraitH
            };
        }

        parseVariable(obj) {
            // Retrocompatibilidad: antes se guardaba en obj.source en vez de obj.content
            const isOldVar = !!obj.source;
            const content = isOldVar ? `{{${obj.source}}}` : (obj.content || '');

            if (!content.startsWith('{{') || !content.endsWith('}}')) {
                return { isVar: false, text: content };
            }
            const varName = content.substring(2, content.length - 2).trim();
            for (const group of this.variables) {
                if (!group.items) continue;
                for (const item of group.items) {
                    if (item.source === varName) {
                        return { isVar: true, original: varName, label: item.label || varName };
                    }
                }
            }
            return { isVar: true, original: varName, label: varName };
        }
    }

    class PrintFormsRenderer {
        constructor(state, rootEl) {
            this.state = state;
            this.rootEl = rootEl;
            this.sheetEl = rootEl.querySelector('[data-print-sheet]');
            this.stageEl = rootEl.querySelector('[data-print-sheet-stage]');
            this.wrapEl = rootEl.querySelector('.print-sheet-wrap');
        }

        applyZoom() {
            const zoomLabel = document.getElementById('print-zoom-label');
            if (zoomLabel) zoomLabel.textContent = this.state.zoomLevel + '%';
            
            const scale = this.state.zoomLevel / 100;
            if (this.sheetEl) {
                // Replacing CSS Zoom with CSS Scale!
                this.sheetEl.style.transform = `scale(${scale})`;
                this.sheetEl.style.transformOrigin = 'top left';
                
                // When scaling physically via transform, the wrap element doesn't know the new height.
                // We add a margin bottom equivalent to the scaled difference to fix scrolling.
                const originalHeight = this.sheetEl.offsetHeight || 0;
                const scaledHeight = originalHeight * scale;
                const marginBottom = scaledHeight - originalHeight;
                this.sheetEl.style.marginBottom = `${marginBottom}px`;

                // Calculate center margin
                const wrapW = this.wrapEl.clientWidth;
                const sheetW = this.sheetEl.offsetWidth || 820;
                const scaledWidth = sheetW * scale;
                const leftMargin = Math.max(0, (wrapW - scaledWidth) / 2);
                this.sheetEl.style.marginLeft = `${leftMargin}px`;
            }
        }

        fitZoom() {
            const wrapW = this.wrapEl.clientWidth - 40; // 40px padding
            const sheetBaseWidthPx = 820; 
            const fitScale = Math.floor((wrapW / sheetBaseWidthPx) * 100);
            this.state.zoomLevel = Math.max(30, Math.min(100, fitScale));
            this.applyZoom();
        }

        render() {
            if (!this.sheetEl || !this.stageEl) return;

            const box = this.state.getPageBox();
            if (this.state.pageConfig.orientation === 'landscape') {
                this.sheetEl.classList.add('is-landscape');
            } else {
                this.sheetEl.classList.remove('is-landscape');
            }

            if (this.state.pageConfig.grid_enabled) {
                this.stageEl.classList.add('has-grid');
            } else {
                this.stageEl.classList.remove('has-grid');
            }
            
            if (this.state.pageConfig.transparent_bg) {
                this.stageEl.style.backgroundColor = 'transparent';
            } else {
                this.stageEl.style.backgroundColor = this.state.pageConfig.background_color || '#ffffff';
            }
            if (this.state.backgroundUrl) {
                this.stageEl.style.setProperty('--print-sheet-background-image', `url('${this.state.backgroundUrl}')`);
            } else {
                this.stageEl.style.removeProperty('--print-sheet-background-image');
            }

            this.stageEl.innerHTML = '';

            
            this.state.objects.forEach(obj => {
                const node = document.createElement('div');
                node.className = 'print-object' + (this.state.selectedIds.has(obj.id) ? ' is-selected' : '');
                if (obj.type === 'LINE') node.classList.add('is-line');
                
                node.dataset.objectId = obj.id;
                
                if (this.state.selectedIds.has(obj.id) && this.state.selectedIds.size === 1 && obj.type !== 'LINE') {
                    // Inject resizers
                    node.innerHTML = `
                        <div class="print-object__resizer" data-resizer="bottom-right" style="position:absolute; right:-4px; bottom:-4px; width:10px; height:10px; background:#0d6efd; border-radius:50%; cursor:se-resize; z-index:10; pointer-events:auto;"></div>
                    `;
                }

                const pctX = (Number(obj.x_mm || 0) / box.widthMm) * 100;
                const pctY = (Number(obj.y_mm || 0) / box.heightMm) * 100;
                const pctW = (Number(obj.w_mm || 0) / box.widthMm) * 100;
                const pctH = (Number(obj.h_mm || 0) / box.heightMm) * 100;

                node.style.left = `${pctX}%`;
                node.style.top = `${pctY}%`;
                node.style.width = `${pctW}%`;
                node.style.height = `${pctH}%`;

                const inner = document.createElement('div');
                inner.className = 'print-object__inner';
                inner.style.display = 'flex';
                // Line specifics
                if (obj.type === 'LINE') {
                    node.style.height = `${Math.max(Number(obj.style?.stroke_width_mm || 0.5), 0.5)}mm`;
                    node.style.transform = `translateY(-50%)`; 
                    inner.style.borderBottom = `${obj.style?.stroke_width_mm || 0.5}mm solid ${obj.style?.stroke || '#000000'}`;
                } else if (obj.type === 'RECT') {
                    inner.style.border = `${obj.style?.stroke_width_mm || 0.5}mm solid ${obj.style?.stroke || '#000000'}`;
                    inner.style.backgroundColor = obj.style?.fill || 'transparent';
                } else if (obj.type === 'IMAGE' || obj.content === '{{empresa.header_url}}' || obj.content === '{{empresa.footer_url}}') {
                    if (obj.content && obj.content.startsWith('http')) {
                        inner.style.backgroundImage = `url('${obj.content}')`;
                        inner.style.backgroundSize = '100% 100%';
                        inner.style.backgroundRepeat = 'no-repeat';
                        inner.style.backgroundPosition = 'center';
                    } else if (obj.content === '{{empresa.header_url}}' && this.state.sampleContext.empresa && this.state.sampleContext.empresa.header_url) {
                        inner.style.backgroundImage = `url('${this.state.sampleContext.empresa.header_url}')`;
                        inner.style.backgroundSize = '100% 100%';
                        inner.style.backgroundRepeat = 'no-repeat';
                        inner.style.backgroundPosition = 'center';
                    } else if (obj.content === '{{empresa.footer_url}}' && this.state.sampleContext.empresa && this.state.sampleContext.empresa.footer_url) {
                        inner.style.backgroundImage = `url('${this.state.sampleContext.empresa.footer_url}')`;
                        inner.style.backgroundSize = '100% 100%';
                        inner.style.backgroundRepeat = 'no-repeat';
                        inner.style.backgroundPosition = 'center';
                    } else {
                        inner.style.backgroundColor = '#f1f5f9';
                        inner.style.border = `1px dashed #cbd5e1`;
                        inner.innerHTML = `<span style="margin:auto;font-size:0.75rem;color:#94a3b8;"><i class="bi bi-image"></i></span>`;
                    }
                } else {
                    const parsed = this.state.parseVariable(obj);
                    const fontFamily = obj.style?.font_family || 'Helvetica';
                    const fontSize = obj.style?.font_size_pt || 11;
                    const fontWeight = obj.style?.font_weight || '400';
                    const color = obj.style?.color || '#000000';
                    const align = obj.style?.align || 'left';

                    inner.style.fontFamily = `'${fontFamily}', sans-serif`;
                    inner.style.fontSize = `${fontSize}pt`;
                    inner.style.fontWeight = fontWeight;
                    inner.style.color = color;
                    
                    const jcMap = { 'left': 'flex-start', 'center': 'center', 'right': 'flex-end', 'justify': 'flex-start' };
                    inner.style.justifyContent = jcMap[align] || 'flex-start';
                    inner.style.textAlign = align;

                    let textval = parsed.text || '[Texto Vacío]';
                    if (parsed.isVar) {
                        const contentToMatch = obj.content || (obj.source ? `{{${obj.source}}}` : '');
                        if (contentToMatch === '{{empresa.header_url}}' || contentToMatch === '{{empresa.footer_url}}') {
                             textval = '<URL IMAGEN>';
                        } else {
                             const sampleVal = this.state.sampleContext[parsed.original.split('.')[0]]?.[parsed.original.split('.')[1]];
                             textval = sampleVal || `[${parsed.label}]`;
                        }
                    }
                    inner.innerHTML = textval; // Usa innerHTML para permitir etiquetas como <b> o <br> antiguos

                    if (obj.type === 'TEXT_MULTILINE') {
                        inner.style.whiteSpace = 'pre-wrap';
                        inner.style.alignItems = 'flex-start';
                    } else {
                        inner.style.whiteSpace = 'nowrap';
                        inner.style.alignItems = 'center';
                        inner.style.overflow = 'hidden';
                    }
                }

                node.insertBefore(inner, node.firstChild);
                this.stageEl.appendChild(node);
            });
            
            // Sync sidebars
            this.syncObjectList();
            this.syncPropertiesPanel();
            this.syncInputs();
        }

        syncObjectList() {
            const listEl = document.querySelector('[data-object-list]');
            if (!listEl) return;
            let html = '';
            [...this.state.objects].reverse().forEach(obj => {
                const isSel = this.state.selectedIds.has(obj.id);
                let icon = 'bi-type';
                if (obj.type === 'IMAGE') icon = 'bi-image';
                if (obj.type === 'LINE') icon = 'bi-slash-lg';
                if (obj.type === 'RECT') icon = 'bi-square';
                if (obj.content && obj.content.startsWith('{{')) icon = 'bi-braces';
                
                const label = obj.type === 'LINE' || obj.type === 'RECT' ? obj.type : (obj.content ? obj.content.substring(0, 15) : 'Vacío');
                html += `
                    <button type="button" class="btn btn-sm ${isSel ? 'btn-primary' : 'btn-light border'} text-start text-truncate" data-select-id="${obj.id}">
                        <i class="bi ${icon} me-1"></i> ${label}
                    </button>
                `;
            });
            listEl.innerHTML = html;
        }

        syncPropertiesPanel() {
            const objs = this.state.getSelectedObjects();
            const emptyEl = document.querySelector('[data-object-empty-state]');
            const propsEl = document.querySelector('[data-object-properties]');
            if (!emptyEl || !propsEl) return;

            if (objs.length === 0) {
                emptyEl.classList.remove('d-none');
                propsEl.classList.add('d-none');
                document.querySelector('[data-delete-object]')?.setAttribute('disabled', 'true');
                document.querySelector('[data-action="z-backward"]')?.setAttribute('disabled', 'true');
                document.querySelector('[data-action="z-forward"]')?.setAttribute('disabled', 'true');
                return;
            }

            emptyEl.classList.add('d-none');
            propsEl.classList.remove('d-none');
            
            document.querySelector('[data-delete-object]')?.removeAttribute('disabled');
            document.querySelector('[data-action="z-backward"]')?.removeAttribute('disabled');
            document.querySelector('[data-action="z-forward"]')?.removeAttribute('disabled');

            const setVal = (sel, val) => { const e = document.querySelector(sel); if(e){ if (document.activeElement !== e) { e.value = val !== undefined && val !== null ? val : ''; } e.disabled = false; }};
            const disable = (sel) => { const e = document.querySelector(sel); if(e){ e.value = ''; e.disabled = true; }};

            if (objs.length > 1) {
                document.querySelector('[data-selected-type]').textContent = 'Grupo (' + objs.length + ')';
                ['x_mm', 'y_mm', 'w_mm', 'h_mm', 'content', 'source'].forEach(p => disable(`[data-object-prop="${p}"]`));
                ['font_family', 'font_size_pt', 'color', 'align', 'stroke', 'stroke_width_mm', 'fill'].forEach(p => setVal(`[data-style-prop="${p}"]`, ''));
                return;
            }

            const obj = objs[0];
            document.querySelector('[data-selected-type]').textContent = obj.type;

            // Geometry
            setVal('[data-object-prop="x_mm"]', obj.x_mm);
            setVal('[data-object-prop="y_mm"]', obj.y_mm);
            setVal('[data-object-prop="w_mm"]', obj.w_mm);
            setVal('[data-object-prop="h_mm"]', obj.type === 'LINE' ? '' : obj.h_mm);
            if (obj.type === 'LINE') disable('[data-object-prop="h_mm"]');

            // Content
            if (obj.type === 'TEXT' || obj.type === 'IMAGE') {
                const parsed = this.state.parseVariable(obj);
                if (parsed.isVar) {
                    setVal('[data-object-prop="source"]', parsed.original);
                    disable('[data-object-prop="content"]');
                } else {
                    setVal('[data-object-prop="source"]', '');
                    setVal('[data-object-prop="content"]', obj.content);
                }
            } else {
                disable('[data-object-prop="content"]');
                disable('[data-object-prop="source"]');
            }

            // Typography
            if (obj.type !== 'LINE' && obj.type !== 'RECT' && obj.type !== 'IMAGE') {
                setVal('[data-style-prop="font_family"]', obj.style?.font_family || 'Arial');
                setVal('[data-style-prop="font_size_pt"]', obj.style?.font_size_pt || 11);
                setVal('[data-style-prop="font_weight"]', obj.style?.font_weight || '400');
                setVal('[data-style-prop="color"]', obj.style?.color || '#000000');
                setVal('[data-style-prop="align"]', obj.style?.align || 'left');
            } else {
                disable('[data-style-prop="font_family"]');
                disable('[data-style-prop="font_size_pt"]');
                disable('[data-style-prop="font_weight"]');
                disable('[data-style-prop="color"]');
                disable('[data-style-prop="align"]');
            }

            // Stroke/Fill
            if (obj.type === 'LINE' || obj.type === 'RECT') {
                setVal('[data-style-prop="stroke"]', obj.style?.stroke || '#000000');
                setVal('[data-style-prop="stroke_width_mm"]', obj.style?.stroke_width_mm || 0.5);
                setVal('[data-style-prop="fill"]', obj.style?.fill || 'transparent');
                if (obj.type === 'LINE') disable('[data-style-prop="fill"]');
            } else {
                disable('[data-style-prop="stroke"]');
                disable('[data-style-prop="stroke_width_mm"]');
                disable('[data-style-prop="fill"]');
            }
        }

        syncInputs() {
            document.getElementById('print-page-config-json').value = JSON.stringify(this.state.pageConfig);
            document.getElementById('print-objects-json').value = JSON.stringify(this.state.objects);
            document.getElementById('print-fonts-json').value = JSON.stringify(this.state.fonts);
        }
    }

    class PrintFormsInteraction {
        constructor(state, renderer) {
            this.state = state;
            this.renderer = renderer;
            this.drag = null;
            this.preventClick = false;
            
            this.setupListeners();
        }

        setupListeners() {
            // Document mouse flows
            document.addEventListener('mousedown', this.onMouseDown.bind(this));
            document.addEventListener('mousemove', this.onMouseMove.bind(this));
            document.addEventListener('mouseup', this.onMouseUp.bind(this));
            
            // Delegate clicks
            document.addEventListener('click', e => {
                if (this.preventClick) {
                    e.stopImmediatePropagation();
                    return;
                }
                const selectBtn = e.target.closest('[data-select-id]');
                if (selectBtn) {
                    const id = selectBtn.dataset.selectId;
                    if (e.shiftKey || e.ctrlKey || e.metaKey) {
                        if (this.state.selectedIds.has(id)) {
                            this.state.selectedIds.delete(id);
                        } else {
                            this.state.selectedIds.add(id);
                        }
                    } else {
                        this.state.selectedIds = new Set([id]);
                    }
                    this.renderer.render();
                }
                const delBtn = e.target.closest('[data-delete-object]');
                if (delBtn) {
                    this.state.saveUndo();
                    this.state.objects = this.state.objects.filter(o => !this.state.selectedIds.has(o.id));
                    this.state.selectedIds.clear();
                    this.renderer.render();
                }
                const addObjBtn = e.target.closest('[data-add-object]');
                if (addObjBtn) {
                    this.addObject(addObjBtn.dataset.addObject);
                }
                const addVarBtn = e.target.closest('[data-add-variable]');
                if (addVarBtn) {
                    this.addVariable(addVarBtn.dataset.addVariable);
                }
                
                const zBackBtn = e.target.closest('[data-action="z-backward"]');
                if (zBackBtn && this.state.selectedIds.size > 0) {
                    this.state.saveUndo();
                    // Solo permitido si hay un solo objeto (para no romper el array mutando en bucle) o mover todo el grupo requiere mas logica.
                    // Simplified: only allow z-index for single selections for safety.
                    if (this.state.selectedIds.size === 1) {
                        const id = Array.from(this.state.selectedIds)[0];
                        const idx = this.state.objects.findIndex(o => o.id === id);
                        if (idx > 0) {
                            const movingObj = this.state.objects.splice(idx, 1)[0];
                            this.state.objects.splice(idx - 1, 0, movingObj);
                            this.renderer.render();
                        }
                    }
                }
                const zFwdBtn = e.target.closest('[data-action="z-forward"]');
                if (zFwdBtn && this.state.selectedIds.size > 0) {
                    if (this.state.selectedIds.size === 1) {
                        const id = Array.from(this.state.selectedIds)[0];
                        const idx = this.state.objects.findIndex(o => o.id === id);
                        if (idx !== -1 && idx < this.state.objects.length - 1) {
                            this.state.saveUndo();
                            const movingObj = this.state.objects.splice(idx, 1)[0];
                            this.state.objects.splice(idx + 1, 0, movingObj);
                            this.renderer.render();
                        }
                    }
                }
                const clrBtn = e.target.closest('[data-action="clear-canvas"]');
                if (clrBtn) {
                    if (confirm('¿Seguro que deseas eliminar todos los objetos del canvas?')) {
                        this.state.saveUndo();
                        this.state.objects = [];
                        this.state.selectedIds.clear();
                        this.renderer.render();
                    }
                }
                
                const undoBtn = e.target.closest('[data-action="undo"]');
                if (undoBtn) {
                    if (this.state.undo()) this.renderer.render();
                }
            });

            // Z Key tracking for Wheel Zoom
            this.keyZ = false;
            document.addEventListener('keydown', e => {
                const tag = document.activeElement?.tagName;
                if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
                if ((e.key === 'z' || e.key === 'Z') && !e.ctrlKey && !e.metaKey) this.keyZ = true;
            });
            document.addEventListener('keyup', e => {
                if (e.key === 'z' || e.key === 'Z') this.keyZ = false;
            });

            // Wheel listener for Z + Wheel Zoom
            this.renderer.wrapEl.addEventListener('wheel', e => {
                if (this.keyZ || e.ctrlKey) {
                    e.preventDefault();
                    if (e.deltaY < 0) {
                        this.state.zoomLevel = Math.min(200, this.state.zoomLevel + 5);
                    } else {
                        this.state.zoomLevel = Math.max(30, this.state.zoomLevel - 5);
                    }
                    this.renderer.applyZoom();
                }
            }, { passive: false });

            // Keyboard arrows & Panning Mode
            document.addEventListener('keydown', e => {
                const tag = document.activeElement?.tagName;
                
                // Panning mode con 'm' o 'M'
                if (tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') {
                    if (e.key === 'm' || e.key === 'M') {
                        this.state.isPanningMode = !this.state.isPanningMode;
                        if (this.state.isPanningMode) {
                            this.renderer.wrapEl.style.cursor = 'grab';
                            document.querySelector('.print-editor-shell').classList.add('is-panning');
                            
                            let ind = document.getElementById('pan-indicator');
                            if (!ind) {
                                ind = document.createElement('div');
                                ind.id = 'pan-indicator';
                                ind.style.cssText = 'position: sticky; top: 1rem; left: 50%; transform: translateX(-50%); width: max-content; background: rgba(51, 65, 85, 0.95); color: white; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; z-index: 1000; pointer-events: none; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
                                ind.innerHTML = '<i class="bi bi-arrows-move me-2"></i>Modo Desplazamiento Activo (Press M)';
                                this.renderer.wrapEl.style.position = 'relative';
                                this.renderer.wrapEl.appendChild(ind);
                            }

                        } else {
                            this.renderer.wrapEl.style.cursor = '';
                            document.querySelector('.print-editor-shell').classList.remove('is-panning');
                            const ind = document.getElementById('pan-indicator');
                            if (ind) ind.remove();
                        }
                        return;
                    }
                    
                    // Ctrl+Z Undo
                    if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z')) {
                        e.preventDefault();
                        if (this.state.undo()) this.renderer.render();
                        return;
                    }

                    // Teclas de Zoom: + y -
                    if (e.key === '+' || e.key === 'Add') {
                        e.preventDefault();
                        this.state.zoomLevel = Math.min(200, this.state.zoomLevel + 10);
                        this.renderer.applyZoom();
                        return;
                    }
                    if (e.key === '-' || e.key === 'Subtract') {
                        e.preventDefault();
                        this.state.zoomLevel = Math.max(30, this.state.zoomLevel - 10);
                        this.renderer.applyZoom();
                        return;
                    }
                }

                // Teclado
                if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
                
                const step = e.shiftKey ? 5 : 1;
                const objs = this.state.getSelectedObjects();
                const box = this.state.getPageBox();
                
                if (objs.length > 0 && !this.drag) {
                    let handled = false;
                    const c = (val, max) => Math.max(0, Math.min(val, max));
                    
                    if (e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                        objs.forEach(obj => {
                            if (e.key === 'ArrowLeft')  { obj.x_mm = c(Number(obj.x_mm||0) - step, box.widthMm - Number(obj.w_mm||0)); }
                            if (e.key === 'ArrowRight') { obj.x_mm = c(Number(obj.x_mm||0) + step, box.widthMm - Number(obj.w_mm||0)); }
                            if (e.key === 'ArrowUp')    { obj.y_mm = c(Number(obj.y_mm||0) - step, box.heightMm - Number(obj.h_mm||0)); }
                            if (e.key === 'ArrowDown')  { obj.y_mm = c(Number(obj.y_mm||0) + step, box.heightMm - Number(obj.h_mm||0)); }
                        });
                        handled = true;
                    }
                    
                    // Delete via keyboard
                    if (e.key === 'Delete' || e.key === 'Backspace') {
                        this.state.saveUndo();
                        this.state.objects = this.state.objects.filter(o => !this.state.selectedIds.has(o.id));
                        this.state.selectedIds.clear();
                        handled = true;
                    }
                    if (handled) {
                        e.preventDefault();
                        this.renderer.render();
                    }
                }
            });

            // Zoom handling
            document.querySelector('.print-editor-sheet-toolbar')?.addEventListener('click', e => {
                const zBtn = e.target.closest('[data-zoom]');
                if (zBtn) {
                    const act = zBtn.dataset.zoom;
                    if (act === 'in') this.state.zoomLevel = Math.min(200, this.state.zoomLevel + 10);
                    if (act === 'out') this.state.zoomLevel = Math.max(30, this.state.zoomLevel - 10);
                    if (act === 'fit') {
                        this.renderer.fitZoom();
                    } else {
                        this.renderer.applyZoom();
                    }
                }
            });

            // Syncing properties from sidebar to state
            document.querySelector('.print-editor-shell')?.addEventListener('input', e => {
                const el = e.target;
                const prop = el.dataset.objectProp;
                const styleProp = el.dataset.styleProp;
                const pageProp = el.dataset.pageProp;

                if (pageProp) {
                    this.state.pageConfig[pageProp] = el.type === 'checkbox' ? el.checked : el.value;
                    this.renderer.render();
                    return;
                }

                const objs = this.state.getSelectedObjects();
                if (objs.length === 0) return;
                
                objs.forEach(obj => {
                    if (styleProp) {
                        if (!obj.style) obj.style = {};
                        obj.style[styleProp] = el.type === 'number' ? Number(el.value) : el.value;
                    } else if (prop) {
                        if (prop === 'source') {
                            obj.content = el.value ? `{{${el.value}}}` : 'Texto vacío';
                        } else if (prop === 'content') {
                             obj.content = el.value || '';
                        } else {
                            obj[prop] = el.type === 'number' ? Number(el.value) : el.value;
                        }
                    }
                });
                this.renderer.render();
            });
        }

        onMouseDown(e) {
            if (e.button !== 0) return;
            
            if (this.state.isPanningMode && e.target.closest('.print-sheet-wrap')) {
                e.preventDefault();
                this.panState = {
                    isPanning: true,
                    startX: e.clientX,
                    startY: e.clientY,
                    scrollLeft: this.renderer.wrapEl.scrollLeft,
                    scrollTop: this.renderer.wrapEl.scrollTop
                };
                this.renderer.wrapEl.style.cursor = 'grabbing';
                return;
            }

            const node = e.target.closest('.print-object');
            if (!node) {
                // If clicked empty space on stage
                if (e.target.closest('.print-sheet') && !this.state.isPanningMode) {
                     this.state.selectedIds.clear();
                     this.renderer.render();
                }
                return;
            }

            // Important: do not preventDefault on INPUT controls, but we are over stage.
            e.preventDefault();

            const objId = node.dataset.objectId;
            const obj = this.state.objects.find(o => o.id === objId);
            if (!obj) return;

            if (e.shiftKey || e.ctrlKey || e.metaKey) {
                if (this.state.selectedIds.has(objId)) {
                    this.state.selectedIds.delete(objId);
                    this.renderer.render();
                    return;
                } else {
                    this.state.selectedIds.add(objId);
                }
            } else {
                if (!this.state.selectedIds.has(objId)) {
                    this.state.selectedIds = new Set([objId]);
                }
            }
            this.renderer.render();

            this.state.saveUndo();
            const isResizing = !!e.target.closest('[data-resizer]');
            
            // To be totally bulletproof against DOM reflows and CSS Transform scaling,
            // we capture the exact BoundingRect of the unscaled canvas layout via stage offsetWidth.
            // But CSS scale shrinks the stage rect! The real way is offsets.
            const rect = this.renderer.stageEl.getBoundingClientRect();

            const originMap = {};
            this.state.getSelectedObjects().forEach(o => {
                originMap[o.id] = {
                    x: Number(o.x_mm || 0),
                    y: Number(o.y_mm || 0),
                    w: Number(o.w_mm || 0),
                    h: Number(o.h_mm || 0)
                };
            });

            this.drag = {
                id: objId, // Primary interaction id
                action: isResizing ? 'resize' : 'move',
                startX: e.clientX,
                startY: e.clientY,
                boxW: rect.width || 1, // Scaled pixels width of the stage
                boxH: rect.height || 1, // Scaled pixels height of the stage
                originMap: originMap, // Store all origins
                hasMoved: false
            };
        }

        onMouseMove(e) {
            if (this.panState && this.panState.isPanning) {
                const dx = e.clientX - this.panState.startX;
                const dy = e.clientY - this.panState.startY;
                this.renderer.wrapEl.scrollLeft = this.panState.scrollLeft - dx;
                this.renderer.wrapEl.scrollTop = this.panState.scrollTop - dy;
                return;
            }

            if (!this.drag) return;

            if (!this.drag.hasMoved) {
                if (Math.abs(e.clientX - this.drag.startX) > 3 || Math.abs(e.clientY - this.drag.startY) > 3) {
                    this.drag.hasMoved = true;
                } else {
                    return;
                }
            }

            const snapStep = this.state.pageConfig.grid?.snap ? Number(this.state.pageConfig.grid?.step_mm || 2) : 0;
            const applySnap = (val) => snapStep > 0 ? Math.round(val / snapStep) * snapStep : val;
            
            const box = this.state.getPageBox();
            let deltaXmm = ((e.clientX - this.drag.startX) / this.drag.boxW) * box.widthMm;
            let deltaYmm = ((e.clientY - this.drag.startY) / this.drag.boxH) * box.heightMm;
            if (isNaN(deltaXmm)) deltaXmm = 0;
            if (isNaN(deltaYmm)) deltaYmm = 0;

            if (this.drag.action === 'resize') {
                const oMap = this.drag.originMap[this.drag.id];
                const obj = this.state.objects.find(o => o.id === this.drag.id);
                if (obj && oMap) {
                    obj.w_mm = Math.max(1, applySnap(oMap.w + deltaXmm));
                    obj.h_mm = Math.max(1, applySnap(oMap.h + deltaYmm));
                }
            } else {
                this.state.getSelectedObjects().forEach(obj => {
                    const oMap = this.drag.originMap[obj.id];
                    if (oMap) {
                        obj.x_mm = Math.max(0, applySnap(oMap.x + deltaXmm));
                        obj.y_mm = Math.max(0, applySnap(oMap.y + deltaYmm));
                    }
                });
            }
            
            // We re-render fully during mouse move. Modern JS is fast enough for 50 DOM nodes!
            this.renderer.render();
        }

        onMouseUp(e) {
            if (this.panState && this.panState.isPanning) {
                this.panState = null;
                if (this.state.isPanningMode) {
                    this.renderer.wrapEl.style.cursor = 'grab';
                }
                return;
            }

            if (!this.drag) return;
            if (this.drag.hasMoved) {
                this.preventClick = true;
                setTimeout(() => { this.preventClick = false; }, 200);
            }
            this.drag = null;
        }

        generateId() {
            return 'obj_' + Math.random().toString(36).substring(2, 9);
        }

        addObject(type) {
            this.state.saveUndo();
            const baseObj = { id: this.generateId(), type: type, x_mm: 10, y_mm: 10, w_mm: 50, h_mm: 10, style: {} };
            
            if (type === 'text') {
                baseObj.type = 'TEXT';
                baseObj.content = 'Nuevo Texto';
                baseObj.style = { font_family: 'Arial', font_size_pt: 11, font_weight: '400', color: '#000000', align: 'left' };
            } else if (type === 'rect') {
                baseObj.type = 'RECT';
                baseObj.h_mm = 30;
                baseObj.style = { stroke: '#000000', stroke_width_mm: 0.5, fill: 'transparent' };
            } else if (type === 'line') {
                baseObj.type = 'LINE';
                baseObj.h_mm = 1;
                baseObj.style = { stroke: '#000000', stroke_width_mm: 0.5 };
            } else if (type === 'image') {
                baseObj.type = 'IMAGE';
                baseObj.h_mm = 30;
            } else if (type === 'variable') {
                baseObj.type = 'TEXT';
                baseObj.content = '{{variable}}';
                baseObj.style = { font_family: 'Arial', font_size_pt: 11, font_weight: '700', color: '#0f172a', align: 'left' };
            }

            this.state.objects.push(baseObj);
            this.state.selectedIds = new Set([baseObj.id]);
            this.renderer.render();
        }

        addVariable(source) {
            this.state.saveUndo();
            this.state.objects.push({
                id: this.generateId(),
                type: 'TEXT',
                x_mm: 15,
                y_mm: 15,
                w_mm: 60,
                h_mm: 8,
                content: `{{${source}}}`,
                style: { font_family: 'Arial', font_size_pt: 11, font_weight: '700', color: '#0f172a', align: 'left' }
            });
            this.state.selectedIds = new Set([this.state.objects[this.state.objects.length - 1].id]);
            this.renderer.render();
        }
    }

    // Init App
    document.addEventListener('DOMContentLoaded', () => {
        const rootEl = document.querySelector('.print-editor-shell');
        if (!rootEl) return;
        
        const state = new PrintFormsState(window.printFormsEditorConfig);
        const renderer = new PrintFormsRenderer(state, rootEl);
        const interaction = new PrintFormsInteraction(state, renderer);
        
        // Initial setup
        const orientSel = document.getElementById('print-orientation');
        if (orientSel) orientSel.value = state.pageConfig.orientation || 'portrait';
        const gridCheck = document.getElementById('print-grid-enabled');
        if (gridCheck) gridCheck.checked = !!state.pageConfig.grid_enabled;
        
        const bgColor = document.getElementById('print-bg-color');
        if (bgColor) bgColor.value = state.pageConfig.background_color || '#ffffff';
        const bgTransparent = document.getElementById('print-transparent-bg');
        if (bgTransparent) bgTransparent.checked = !!state.pageConfig.transparent_bg;

        // Start perfectly centered and fit
        renderer.fitZoom();
        renderer.render();
        
        // Serialize state to hidden inputs before submit
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', (e) => {
                const addHidden = (name, val) => {
                    let input = form.querySelector(`input[name="${name}"]`);
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        form.appendChild(input);
                    }
                    input.value = typeof val === 'string' ? val : JSON.stringify(val);
                };
                
                addHidden('page_config_json', state.pageConfig);
                addHidden('objects_json', state.objects);
                addHidden('fonts_json', state.fonts);
            });
        }
        
        // Listen to window resizes to automatically re-center
        window.addEventListener('resize', () => {
             renderer.applyZoom();
        });
    });

})();
