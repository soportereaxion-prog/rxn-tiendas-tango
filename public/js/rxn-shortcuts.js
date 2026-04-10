/**
 * rxn-shortcuts.js
 * Atajos de teclado recomendados para rxn_suite (Enfoque ERP)
 * - F10 o Ctrl+Enter: Guardar (Realiza click en el boton de guardado principal del formulario)
 * - Escape: Cancelar o Volver al listado (click en flecha volver o menu anterior)
 * - Insert o Alt+N: Nuevo registro (click en botón crear/nuevo)
 * - F3, / o Alt+B: Foco rápido en campo de búsqueda de grillas
 */

document.addEventListener('keydown', function(e) {
    const activeEl = document.activeElement;
    const isInput = activeEl && ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeEl.tagName);
    
    const isEscape = e.key === 'Escape';
    const isF10 = e.key === 'F10';
    const isCtrlEnter = e.ctrlKey && e.key === 'Enter';
    const isInsert = e.key === 'Insert' || (e.altKey && e.key.toLowerCase() === 'n');
    const isFocusSearch = (!isInput && e.key === '/') || (e.altKey && e.key.toLowerCase() === 'b') || e.key === 'F3';

    // 0. Estandarización de Modales
    const activeModal = document.querySelector('.modal.show');
    if (activeModal) {
        if (e.key === 'Enter') {
            // Permitir saltos de línea en textareas dentro del modal
            if (activeEl && activeEl.tagName === 'TEXTAREA') {
                return; 
            }
            e.preventDefault();
            const btnAccept = activeModal.querySelector('.modal-footer .btn-primary') || 
                              activeModal.querySelector('.modal-footer .btn-success') || 
                              activeModal.querySelector('.modal-footer button[type="submit"]') ||
                              activeModal.querySelector('.btn-primary');
            if (btnAccept && !btnAccept.disabled) {
                btnAccept.click();
            }
            return; // Bloquea propagación al resto de atajos
        }
        if (isEscape || e.key === 'ArrowLeft') {
            // Bootstrap puede cerrarlo solo, pero forzamos el click en el dismiss para ser explícitos
            // y metemos return para no ejecutar el window.history.back de abajo.
            const btnCancel = activeModal.querySelector('[data-bs-dismiss="modal"]') || activeModal.querySelector('[data-confirm-cancel]');
            if (btnCancel && !btnCancel.disabled) {
                btnCancel.click();
            }
            return; // Bloquea propagación
        }
    }

    // 1. F10 o Ctrl+Enter -> Guardar (Submit)
    if (isF10 || isCtrlEnter) {
        // Buscar el boton submit principal en el header de acciones, o un submit primario
        // Prioridad absoluta a botones que referencian al formulario principal via atributo `form`
        const saveBtn = document.querySelector('button[type="submit"][name="action"][value="save"]') ||
                        document.querySelector('button[type="submit"][form]:not([name="action"][value="tango"])') || 
                        document.querySelector('.rxn-module-actions button[type="submit"]:not([formnovalidate]):not([name="action"][value="tango"])') || 
                        document.querySelector('button[type="submit"].btn-primary');
                        
        if (saveBtn) {
            e.preventDefault();
            saveBtn.click();
            return;
        }
    }

    // 2. Escape -> Volver / Cancelar
    if (isEscape) {
        // Si esta en un buscador u otro input, quita el foco activo y no hace back (para no irse por error)
        if (isInput) {
            activeEl.blur();
            return;
        }

        // Return to previous page in history si es del mismo sistema, 
        // para persistir estado, filtros, y recien si no hay referrer usa los botones de "Volver al Panel"
        if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) {
            // Check si estamos en una URL de listado o formulario
            e.preventDefault();
            window.history.back();
            return;
        }

        // Buscar boton tipico de volver como fallback
        const backBtn = document.querySelector('.rxn-module-actions a.btn-outline-secondary') || 
                        document.querySelector('a.btn-outline-secondary i.bi-arrow-left')?.closest('a') ||
                        document.querySelector('.rxn-module-actions a[href*="/mi-empresa"]');
                        
        if (backBtn) {
            e.preventDefault();
            backBtn.click();
            return;
        }
    }

    // 3. Insert o Alt+N -> Nuevo Registro
    if (isInsert) {
        const newBtn = document.querySelector('.rxn-module-actions a.btn-primary i.bi-plus-circle')?.closest('a') ||
                       document.querySelector('a.btn-primary[href*="/crear"]');
                       
        if (newBtn) {
            e.preventDefault();
            newBtn.click();
            return;
        }
    }

    // 4. Slash (/), F3 o Alt+B -> Foco en Busqueda
    if (isFocusSearch) {
        const searchInput = document.querySelector('input[data-search-input]');
        if (searchInput) {
            e.preventDefault(); 
            searchInput.focus();
            
            // Magia para mover el cursor al final del texto si ya tenia algo
            const val = searchInput.value;
            searchInput.value = '';
            searchInput.value = val;
            return;
        }
    }
});
