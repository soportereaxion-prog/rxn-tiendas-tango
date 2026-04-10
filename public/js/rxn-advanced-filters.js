(function () {
    'use strict';

    // =========================================================
    // CSS — inyectado una sola vez
    // =========================================================
    if (!document.getElementById('rxn-advanced-filters-css')) {
        const style = document.createElement('style');
        style.id = 'rxn-advanced-filters-css';
        style.innerHTML = `
            .rxn-filter-col .rxn-filter-icon {
                cursor: pointer; opacity: 0.3; font-size: 0.85rem;
                transition: opacity 0.2s; position: absolute;
                right: 5px; top: 50%; transform: translateY(-50%);
            }
            .rxn-filter-col:hover .rxn-filter-icon { opacity: 0.7; }
            .rxn-filter-col .rxn-filter-icon.active { opacity: 1; color: #0d6efd; }
            .rxn-filter-popover {
                position: absolute; top: 100%; right: 0; z-index: 1050;
                min-width: 280px; background: white;
                border: 1px solid rgba(0,0,0,0.15);
                box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
                border-radius: 0.375rem; padding: 1rem;
                display: none; font-weight: normal; font-size: 0.875rem;
            }
            .rxn-filter-popover.show { display: block; }
            .rxn-local-list label {
                display: flex; align-items: center; gap: 0.4rem;
                padding: 0.15rem 0.25rem; cursor: pointer;
                border-radius: 0.2rem; user-select: none;
            }
            .rxn-local-list label:hover { background: #f0f4ff; }
            .rxn-local-list .val-text {
                flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            }
            .rxn-local-list .val-count { flex-shrink: 0; color: #6c757d; font-size: 0.8rem; }
        `;
        document.head.appendChild(style);
    }

    // =========================================================
    // Cerrar popovers al cliquear afuera — registrado una sola vez
    // =========================================================
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.rxn-filter-popover') && !e.target.closest('.rxn-filter-icon')) {
            document.querySelectorAll('.rxn-filter-popover.show').forEach(el => el.classList.remove('show'));
        }
    });

    // =========================================================
    // HELPERS
    // =========================================================
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    const lsKey       = (field) => `rxn_lf::${location.pathname}::${field}`;
    const getExcluded = (field) => {
        try { return JSON.parse(sessionStorage.getItem(lsKey(field)) || 'null'); }
        catch { return null; }
    };
    const saveExcluded = (field, excluded) => {
        try {
            if (!excluded || excluded.length === 0) sessionStorage.removeItem(lsKey(field));
            else sessionStorage.setItem(lsKey(field), JSON.stringify(excluded));
        } catch {}
    };

    // Estado de filas ocultas por campo (multi-columna safe)
    const hiddenRowsByField = {};

    const updateRowVisibility = (row) => {
        const isHidden = Object.values(hiddenRowsByField).some(set => set.has(row));
        row.style.display = isHidden ? 'none' : '';
    };

    const applyLocalFilter = (field, colIndex, excluded) => {
        if (!hiddenRowsByField[field]) hiddenRowsByField[field] = new Set();
        const hiddenSet = hiddenRowsByField[field];
        hiddenSet.clear();
        document.querySelectorAll('table tbody tr').forEach(row => {
            const cell  = row.querySelectorAll('td')[colIndex];
            const value = cell ? (cell.textContent.trim() || '(Vacío)') : '(Vacío)';
            if (excluded && excluded.length > 0 && excluded.includes(value)) hiddenSet.add(row);
            updateRowVisibility(row);
        });
    };

    const clearLocalFilter = (field) => {
        if (hiddenRowsByField[field]) hiddenRowsByField[field].clear();
        document.querySelectorAll('table tbody tr').forEach(row => updateRowVisibility(row));
        saveExcluded(field, null);
    };

    const getColumnValues = (colIndex) => {
        const counts = {};
        document.querySelectorAll('table tbody tr').forEach(row => {
            const cell  = row.querySelectorAll('td')[colIndex];
            const value = cell ? (cell.textContent.trim() || '(Vacío)') : '(Vacío)';
            counts[value] = (counts[value] || 0) + 1;
        });
        return counts;
    };

    // =========================================================
    // AJAX helper — recarga contenido via fetch en modo tabla AJAX
    // =========================================================
    function applyBdAjax(ajaxUrl, containerId, params) {
        const container = document.getElementById(containerId);
        if (!container) return;

        container.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span></div>';
        history.pushState(null, '', '?' + params.toString());

        fetch(ajaxUrl + '?' + params.toString())
            .then(r => {
                if (!r.ok) throw new Error('Error ' + r.status);
                return r.text();
            })
            .then(html => {
                container.innerHTML = html;
                if (typeof window.rxnFiltersInit === 'function') window.rxnFiltersInit();
                container.dispatchEvent(new CustomEvent('rxnsync:contentRefreshed', { bubbles: true }));
            })
            .catch(err => {
                container.innerHTML = '<div class="alert alert-danger m-3"><i class="bi bi-exclamation-triangle me-2"></i>' + escapeHtml(err.message) + '</div>';
            });
    }

    // =========================================================
    // INICIALIZACIÓN — re-ejecutable post-AJAX (guard por attr)
    // =========================================================
    function initRxnFilters() {
        const filterCols = document.querySelectorAll('.rxn-filter-col:not([data-rxn-filter-init])');
        if (filterCols.length === 0) return;

        // Filtros Motor BD activos desde la URL actual (re-leído en cada llamada)
        const urlParams = new URLSearchParams(window.location.search);
        const activeBdFilters = {};
        for (const [key, value] of urlParams.entries()) {
            const match = key.match(/^f\[(.*?)\]\[(.*?)\]$/);
            if (match) {
                const field = match[1], type = match[2];
                if (!activeBdFilters[field]) activeBdFilters[field] = {};
                activeBdFilters[field][type] = value;
            }
        }

        filterCols.forEach(th => {
            th.setAttribute('data-rxn-filter-init', '1');

            const field = th.getAttribute('data-filter-field');
            if (!field) return;

            th.style.position = 'relative';

            // Estado Motor BD
            const isBdActive = !!(activeBdFilters[field] && activeBdFilters[field].val);
            const currentOp  = isBdActive ? activeBdFilters[field].op : 'contiene';
            const currentVal = isBdActive ? activeBdFilters[field].val : '';

            // Índice de columna
            const allThs   = Array.from(th.closest('tr').querySelectorAll('th'));
            const colIndex = allThs.indexOf(th);

            // Estado Local
            const excludedFromStorage = getExcluded(field);
            const isLocalActive = !!(excludedFromStorage && excludedFromStorage.length > 0);

            // Detección modo AJAX
            const ajaxTable    = th.closest('table[data-ajax-url]');
            const ajaxUrl      = ajaxTable ? ajaxTable.dataset.ajaxUrl      : null;
            const ajaxContainer = ajaxTable ? ajaxTable.dataset.ajaxContainer : null;

            // --- Ícono ---
            const icon = document.createElement('i');
            icon.className = `bi bi-funnel${(isBdActive || isLocalActive) ? '-fill active' : ''} rxn-filter-icon`;
            icon.title = 'Filtrar columna';

            // --- Popover ---
            const popover = document.createElement('div');
            popover.className = 'rxn-filter-popover text-start';
            popover.onclick = (e) => e.stopPropagation();

            popover.innerHTML = `
                <div class="text-muted small fw-semibold mb-2 d-flex align-items-center gap-1">
                    <i class="bi bi-database-fill-gear"></i> Filtro Motor BD
                </div>
                <select class="form-select form-select-sm mb-2" id="filter_op_${field}">
                    <option value="contiene"    ${currentOp==='contiene'   ?'selected':''}>Contiene</option>
                    <option value="no_contiene" ${currentOp==='no_contiene'?'selected':''}>No contiene</option>
                    <option value="empieza_con" ${currentOp==='empieza_con'?'selected':''}>Empieza con</option>
                    <option value="termina_con" ${currentOp==='termina_con'?'selected':''}>Termina con</option>
                    <option value="igual"       ${currentOp==='igual'      ?'selected':''}>Igual</option>
                    <option value="distinto"    ${currentOp==='distinto'   ?'selected':''}>Distinto</option>
                    <option value="mayor_que"   ${currentOp==='mayor_que'  ?'selected':''}>Mayor a</option>
                    <option value="menor_que"   ${currentOp==='menor_que'  ?'selected':''}>Menor a</option>
                </select>
                <input type="text" class="form-control form-control-sm mb-2"
                    id="filter_val_${field}" value="${escapeHtml(currentVal)}" placeholder="Límite o valor...">
                <div class="d-flex gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-outline-danger w-50 btn-clear-bd">Borrar BD</button>
                    <button type="button" class="btn btn-sm btn-warning w-50 btn-apply-bd">Aplicar BD</button>
                </div>
                <hr class="my-2">
                <div class="text-muted small fw-semibold mb-2 d-flex align-items-center gap-1">
                    <i class="bi bi-funnel-fill"></i> Selección Local
                </div>
                <input type="text" class="form-control form-control-sm mb-2 rxn-local-search"
                    placeholder="Buscar en lista...">
                <div class="d-flex justify-content-between mb-1 small">
                    <a href="#" class="btn-mark-all text-primary text-decoration-none">Marcar Todo</a>
                    <a href="#" class="btn-mark-none text-primary text-decoration-none">Ninguno</a>
                </div>
                <div class="rxn-local-list border rounded px-2 py-1"
                    style="max-height: 160px; overflow-y: auto; margin-bottom: 0.6rem;"></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary w-50 btn-clear-local">Limpiar Local</button>
                    <button type="button" class="btn btn-sm btn-primary w-50 btn-apply-local">Aplicar Local</button>
                </div>
            `;

            // === Motor BD: construir params ===
            const bdInput = popover.querySelector(`#filter_val_${field}`);

            const buildBdParams = (includeFilter) => {
                const params = new URLSearchParams(window.location.search);
                if (includeFilter) {
                    const op  = popover.querySelector(`#filter_op_${field}`).value;
                    const val = bdInput.value.trim();
                    if (!val) {
                        params.delete(`f[${field}][op]`);
                        params.delete(`f[${field}][val]`);
                    } else {
                        params.set(`f[${field}][op]`, op);
                        params.set(`f[${field}][val]`, val);
                    }
                } else {
                    params.delete(`f[${field}][op]`);
                    params.delete(`f[${field}][val]`);
                }
                params.delete('page');
                return params;
            };

            const dispatchBd = (params) => {
                if (ajaxUrl && ajaxContainer) {
                    applyBdAjax(ajaxUrl, ajaxContainer, params);
                } else {
                    if (![...params.keys()].some(k => k.startsWith('f['))) params.set('reset_filters', '1');
                    window.location.search = params.toString();
                }
            };

            popover.querySelector('.btn-clear-bd').onclick = () => dispatchBd(buildBdParams(false));
            popover.querySelector('.btn-apply-bd').onclick = () => dispatchBd(buildBdParams(true));

            bdInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); popover.querySelector('.btn-apply-bd').click(); }
            });

            // === Selección Local ===
            const localList   = popover.querySelector('.rxn-local-list');
            const localSearch = popover.querySelector('.rxn-local-search');

            const populateLocalList = () => {
                const valueCounts = getColumnValues(colIndex);
                const excluded    = getExcluded(field) || [];
                const sorted = Object.entries(valueCounts).sort(([a], [b]) => {
                    if (a === '(Vacío)') return -1;
                    if (b === '(Vacío)') return 1;
                    return a.localeCompare(b, 'es');
                });
                localList.innerHTML = '';
                sorted.forEach(([value, count]) => {
                    const label     = document.createElement('label');
                    const isChecked = !excluded.includes(value);
                    const safe      = escapeHtml(value);
                    label.innerHTML = `
                        <input type="checkbox" class="form-check-input flex-shrink-0"
                            value="${safe}" ${isChecked ? 'checked' : ''}>
                        <span class="val-text">${safe}</span>
                        <span class="val-count">(${count})</span>
                    `;
                    localList.appendChild(label);
                });
                localSearch.value = '';
                localSearch.oninput = () => {
                    const q = localSearch.value.toLowerCase();
                    localList.querySelectorAll('label').forEach(lbl => {
                        lbl.style.display = lbl.querySelector('.val-text').textContent.toLowerCase().includes(q) ? '' : 'none';
                    });
                };
            };

            popover.querySelector('.btn-mark-all').onclick = (e) => {
                e.preventDefault();
                localList.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = true);
            };
            popover.querySelector('.btn-mark-none').onclick = (e) => {
                e.preventDefault();
                localList.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
            };

            popover.querySelector('.btn-apply-local').onclick = () => {
                const excluded = [...localList.querySelectorAll('input[type="checkbox"]:not(:checked)')]
                    .map(cb => cb.value);
                saveExcluded(field, excluded);
                applyLocalFilter(field, colIndex, excluded);
                icon.className = `bi bi-funnel${(isBdActive || excluded.length > 0) ? '-fill active' : ''} rxn-filter-icon`;
                popover.classList.remove('show');
            };

            popover.querySelector('.btn-clear-local').onclick = () => {
                clearLocalFilter(field);
                icon.className = `bi bi-funnel${isBdActive ? '-fill active' : ''} rxn-filter-icon`;
                popover.classList.remove('show');
            };

            // === Abrir/cerrar popover ===
            icon.onclick = (e) => {
                e.stopPropagation();
                document.querySelectorAll('.rxn-filter-popover.show').forEach(el => {
                    if (el !== popover) el.classList.remove('show');
                });
                popover.classList.toggle('show');
                if (popover.classList.contains('show')) {
                    const tableContainer = th.closest('.table-responsive');
                    const thRect = th.getBoundingClientRect();
                    let isNearLeftEdge = false;
                    if (tableContainer) {
                        isNearLeftEdge = (thRect.left - tableContainer.getBoundingClientRect().left) < 280;
                    } else {
                        isNearLeftEdge = thRect.left < 280;
                    }
                    popover.style.right = isNearLeftEdge ? 'auto' : '0';
                    popover.style.left  = isNearLeftEdge ? '0'    : 'auto';
                    populateLocalList();
                    bdInput.focus();
                }
            };

            // Espacio para el link de sort
            const aLink = th.querySelector('a');
            if (aLink) {
                aLink.style.paddingRight = '20px';
                aLink.style.display = 'inline-block';
            }

            th.appendChild(icon);
            th.appendChild(popover);

            // Reaplicar filtro local persistido
            if (isLocalActive) applyLocalFilter(field, colIndex, excludedFromStorage);
        });
    }

    document.addEventListener('DOMContentLoaded', initRxnFilters);
    window.rxnFiltersInit = initRxnFilters;
})();
