/**
 * CRM Notas — controlador del layout split (master-detail).
 *
 * Responsabilidades:
 *   - Click en un item de la lista → fetch del panel derecho + update URL (?n=ID).
 *   - Navegación por teclado j/k (siguiente/anterior nota), Enter para editar.
 *   - Búsqueda en vivo (debounce) → recarga sólo la lista, preserva panel si la nota sigue visible.
 *   - Paginación AJAX de la columna izquierda.
 *   - Copiar contenido de la nota al portapapeles desde el panel.
 *
 * Dependencias externas:
 *   - `RxnShortcuts` (registro de hotkeys; carga _después_ de este script).
 *   - Delegación global de `rxn-confirm-modal.js` cubre los forms reinjectados.
 */
(function () {
    'use strict';

    const rootSelector = '.notas-split';
    const root = document.querySelector(rootSelector);
    if (!root) return;

    const indexPath = root.dataset.indexPath || '/mi-empresa/crm/notas';
    const listContainer = root.querySelector('[data-notas-list-container]');
    const panelContainer = root.querySelector('[data-notas-panel]');
    const searchForm = root.querySelector('[data-notas-search-form]');
    const searchInput = root.querySelector('[data-notas-search-input]');

    const empresaId = parseInt(root.dataset.empresaId || '0', 10) || 0;
    const explicitN = root.dataset.explicitN === '1';
    const statusKey = root.dataset.status || 'activos';
    // Scope por empresa y por tab (activos/papelera) para que cada vista recuerde su último seleccionado.
    const STORAGE_KEY = empresaId > 0 ? `rxn_crm_notas_active::${empresaId}::${statusKey}` : null;

    let activeNotaId = parseInt(root.dataset.activeNotaId || '', 10) || null;
    let searchDebounce = null;

    // ────────────────────────────────────────────────────────────────────────
    // Persistencia del últimamente seleccionado (localStorage, scope por empresa)
    // ────────────────────────────────────────────────────────────────────────

    function saveLastActive(id) {
        if (!STORAGE_KEY || !id) return;
        try { localStorage.setItem(STORAGE_KEY, String(id)); } catch (_) { /* storage lleno o bloqueado */ }
    }

    function readLastActive() {
        if (!STORAGE_KEY) return null;
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            const n = parseInt(raw || '', 10);
            return n > 0 ? n : null;
        } catch (_) { return null; }
    }

    function clearLastActive() {
        if (!STORAGE_KEY) return;
        try { localStorage.removeItem(STORAGE_KEY); } catch (_) {}
    }

    // ────────────────────────────────────────────────────────────────────────
    // URL + state helpers
    // ────────────────────────────────────────────────────────────────────────

    function currentQueryParams() {
        const params = new URLSearchParams();
        const search = (searchInput && searchInput.value) || '';
        const status = root.dataset.status || 'activos';
        const sort = root.dataset.sort || 'created_at';
        const dir = root.dataset.dir || 'DESC';
        const tratativaId = root.dataset.tratativaId || '';

        if (search) params.set('search', search);
        params.set('status', status);
        params.set('sort', sort);
        params.set('dir', dir);
        if (tratativaId) params.set('tratativa_id', tratativaId);
        return params;
    }

    function updateDeepLink(notaId) {
        const url = new URL(window.location.href);
        if (notaId) {
            url.searchParams.set('n', String(notaId));
        } else {
            url.searchParams.delete('n');
        }
        window.history.replaceState({}, '', url.toString());
    }

    // ────────────────────────────────────────────────────────────────────────
    // Panel derecho
    // ────────────────────────────────────────────────────────────────────────

    function setActiveItemVisual(notaId) {
        root.querySelectorAll('.notas-list-item').forEach(li => {
            li.classList.toggle('notas-list-item--active', parseInt(li.dataset.notaId, 10) === notaId);
        });
    }

    async function loadPanel(notaId, { persist = true } = {}) {
        if (!notaId || !panelContainer) return { ok: false };
        panelContainer.setAttribute('aria-busy', 'true');
        panelContainer.style.opacity = '0.55';

        try {
            const res = await fetch(`${indexPath}/panel/${notaId}`, {
                headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const html = await res.text();
            panelContainer.innerHTML = html;

            if (!res.ok) {
                // 404 o similar → nota no existe en esta empresa. Limpiar storage stale.
                clearLastActive();
                return { ok: false, status: res.status };
            }

            activeNotaId = notaId;
            root.dataset.activeNotaId = String(notaId);
            setActiveItemVisual(notaId);
            updateDeepLink(notaId);
            if (persist) saveLastActive(notaId);

            if (window.RxnDateTime && typeof window.RxnDateTime.initAll === 'function') {
                window.RxnDateTime.initAll(panelContainer);
            }
            return { ok: true };
        } catch (err) {
            panelContainer.innerHTML = '<div class="alert alert-danger m-3">No se pudo cargar la nota. Revisá tu conexión.</div>';
            console.error('[crm-notas-split] loadPanel error:', err);
            return { ok: false, error: err };
        } finally {
            panelContainer.style.opacity = '';
            panelContainer.removeAttribute('aria-busy');
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // Lista (búsqueda en vivo + paginación AJAX)
    // ────────────────────────────────────────────────────────────────────────

    async function loadList(page = 1) {
        if (!listContainer) return;
        const params = currentQueryParams();
        params.set('page', String(page));

        listContainer.setAttribute('aria-busy', 'true');
        listContainer.style.opacity = '0.55';

        try {
            const res = await fetch(`${indexPath}/lista?${params.toString()}`, {
                headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const html = await res.text();
            listContainer.innerHTML = html;

            // Si la nota activa ya no está en la lista, cargar la primera (si hay).
            if (activeNotaId && !listContainer.querySelector(`.notas-list-item[data-nota-id="${activeNotaId}"]`)) {
                const first = listContainer.querySelector('.notas-list-item');
                if (first) {
                    loadPanel(parseInt(first.dataset.notaId, 10));
                } else {
                    // Sin resultados → placeholder
                    panelContainer.innerHTML = `
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                            <h5 class="text-muted">Sin resultados</h5>
                            <p class="small mb-0">Ajustá la búsqueda o limpiala para ver las notas.</p>
                        </div>`;
                    activeNotaId = null;
                    updateDeepLink(null);
                }
            } else {
                setActiveItemVisual(activeNotaId);
            }
        } catch (err) {
            console.error('[crm-notas-split] loadList error:', err);
        } finally {
            listContainer.style.opacity = '';
            listContainer.removeAttribute('aria-busy');
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // Event delegation
    // ────────────────────────────────────────────────────────────────────────

    function handleListClick(e) {
        // Paginación
        const pageBtn = e.target.closest('[data-notas-page]');
        if (pageBtn) {
            e.preventDefault();
            const page = parseInt(pageBtn.dataset.notasPage, 10) || 1;
            loadList(page);
            return;
        }

        // Click en una fila → cargar panel (ignora checkbox que tiene stopPropagation propio)
        const row = e.target.closest('.notas-list-row');
        if (row) {
            const li = row.closest('.notas-list-item');
            if (li) {
                const id = parseInt(li.dataset.notaId, 10);
                if (id && id !== activeNotaId) loadPanel(id);
            }
        }
    }

    function handleListKeydown(e) {
        // Enter o Space sobre una row enfocada = cargar esa nota
        if (e.key === 'Enter' || e.key === ' ') {
            const row = e.target.closest('.notas-list-row');
            if (row) {
                e.preventDefault();
                const li = row.closest('.notas-list-item');
                if (li) loadPanel(parseInt(li.dataset.notaId, 10));
            }
        }
    }

    function handlePanelClick(e) {
        // Copiar contenido de la nota
        if (e.target.closest('[data-nota-copy-content]')) {
            const btn = e.target.closest('[data-nota-copy-content]');
            const content = panelContainer.querySelector('[data-nota-content]');
            if (content && navigator.clipboard) {
                navigator.clipboard.writeText(content.innerText).then(() => {
                    const original = btn.innerHTML;
                    btn.innerHTML = '<i class="bi bi-check2"></i> Copiado';
                    setTimeout(() => { btn.innerHTML = original; }, 1500);
                }).catch(err => console.error('[crm-notas-split] copy error:', err));
            }
        }
    }

    function handleSearchInput() {
        if (searchDebounce) clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => loadList(1), 250);
    }

    function handleSearchKeydown(e) {
        // ArrowDown desde el input → bajar al primer item de la lista
        // (patrón combobox/omnibox, UX estándar).
        if (e.key === 'ArrowDown') {
            const firstItem = listContainer && listContainer.querySelector('.notas-list-item');
            if (firstItem) {
                e.preventDefault();
                const row = firstItem.querySelector('.notas-list-row');
                if (searchInput) searchInput.blur();
                if (row) row.focus();
            }
        }
    }

    async function handleSearchSubmit(e) {
        e.preventDefault();
        // Flush del debounce pendiente y forzar la carga ya.
        if (searchDebounce) { clearTimeout(searchDebounce); searchDebounce = null; }
        await loadList(1);

        // Saltar al primer resultado: activamos la nota, le damos foco al row y
        // sacamos el foco del input para que ArrowUp/Down operen sobre la lista.
        const firstItem = listContainer && listContainer.querySelector('.notas-list-item');
        if (firstItem) {
            const id = parseInt(firstItem.dataset.notaId, 10);
            if (id && id !== activeNotaId) await loadPanel(id);
            if (searchInput) searchInput.blur();
            const row = firstItem.querySelector('.notas-list-row');
            if (row) row.focus();
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // Navegación con teclado (j/k/Enter)
    // ────────────────────────────────────────────────────────────────────────

    function navigateRelative(delta) {
        const items = Array.from(listContainer.querySelectorAll('.notas-list-item'));
        if (items.length === 0) return;

        const currentIdx = items.findIndex(li => parseInt(li.dataset.notaId, 10) === activeNotaId);
        let nextIdx = currentIdx + delta;
        if (nextIdx < 0) nextIdx = 0;
        if (nextIdx >= items.length) nextIdx = items.length - 1;

        const target = items[nextIdx];
        if (target) {
            const id = parseInt(target.dataset.notaId, 10);
            if (id !== activeNotaId) loadPanel(id);
            target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    function registerShortcuts() {
        if (!window.RxnShortcuts || typeof window.RxnShortcuts.register !== 'function') {
            // rxn-shortcuts.js carga después de este archivo (orden en admin_layout);
            // reintentar en el próximo tick.
            setTimeout(registerShortcuts, 50);
            return;
        }

        const whenInSplit = () => !!document.querySelector(rootSelector);

        window.RxnShortcuts.register({
            id: 'crm-notas-split-next',
            keys: ['ArrowDown', 'j'],
            description: 'Siguiente nota',
            group: 'Notas CRM',
            scope: 'no-input',
            when: whenInSplit,
            action: (e) => { if (e && e.preventDefault) e.preventDefault(); navigateRelative(1); },
        });

        window.RxnShortcuts.register({
            id: 'crm-notas-split-prev',
            keys: ['ArrowUp', 'k'],
            description: 'Nota anterior',
            group: 'Notas CRM',
            scope: 'no-input',
            when: whenInSplit,
            action: (e) => { if (e && e.preventDefault) e.preventDefault(); navigateRelative(-1); },
        });

        window.RxnShortcuts.register({
            id: 'crm-notas-split-edit',
            keys: ['Enter'],
            description: 'Editar nota activa',
            group: 'Notas CRM',
            scope: 'no-input',
            when: () => whenInSplit() && activeNotaId !== null,
            action: () => {
                if (activeNotaId) {
                    window.location.href = `${indexPath}/${activeNotaId}/editar`;
                }
            },
        });
    }

    // ────────────────────────────────────────────────────────────────────────
    // Init
    // ────────────────────────────────────────────────────────────────────────

    if (listContainer) {
        listContainer.addEventListener('click', handleListClick);
        listContainer.addEventListener('keydown', handleListKeydown);
    }
    if (panelContainer) {
        panelContainer.addEventListener('click', handlePanelClick);
    }
    if (searchInput) {
        searchInput.addEventListener('input', handleSearchInput);
        searchInput.addEventListener('keydown', handleSearchKeydown);
    }
    if (searchForm) {
        searchForm.addEventListener('submit', handleSearchSubmit);
    }

    // Estado visual inicial
    setActiveItemVisual(activeNotaId);
    registerShortcuts();

    // ────────────────────────────────────────────────────────────────────────
    // Persistencia: recuperar última nota vista si el usuario volvió sin ?n=
    // ────────────────────────────────────────────────────────────────────────
    (function restoreLastActive() {
        if (explicitN) {
            // Vino con ?n= explícito → ese es el nuevo "último visto" en este scope.
            if (activeNotaId) saveLastActive(activeNotaId);
            return;
        }

        const stored = readLastActive();
        if (stored && stored !== activeNotaId) {
            // Usuario venía mirando `stored`. loadPanel() devuelve {ok:false} si fue borrada
            // o está fuera de scope — en ese caso limpia el storage solo. Si ok, sincroniza
            // activeNotaId, URL y storage automáticamente.
            loadPanel(stored).then(result => {
                if (!result.ok && activeNotaId) {
                    // Re-mostrar la nota que eligió el server (primera del listado actual).
                    loadPanel(activeNotaId);
                }
            });
        } else if (activeNotaId) {
            // Server y storage coinciden (o no había storage) → refrescar el valor.
            saveLastActive(activeNotaId);
        }
    })();
})();
