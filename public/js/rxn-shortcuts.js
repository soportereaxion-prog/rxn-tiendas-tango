/**
 * rxn-shortcuts.js
 * Atajos de teclado recomendados para rxnTiendasIA (Enfoque ERP)
 * - F10 o Ctrl+Enter: Guardar (Realiza click en el boton de guardado principal del formulario)
 * - Escape: Cancelar o Volver al listado (click en flecha volver)
 * - Insert o Alt+N: Nuevo registro (click en botón crear/nuevo)
 * - / o Alt+B: Foco rápido en campo de búsqueda de grillas
 */

document.addEventListener('keydown', function(e) {
    const activeEl = document.activeElement;
    const isInput = activeEl && ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeEl.tagName);
    
    const isEscape = e.key === 'Escape';
    const isF10 = e.key === 'F10';
    const isCtrlEnter = e.ctrlKey && e.key === 'Enter';
    const isInsert = e.key === 'Insert' || (e.altKey && e.key.toLowerCase() === 'n');
    const isFocusSearch = (!isInput && e.key === '/') || (e.altKey && e.key.toLowerCase() === 'b');

    // 1. F10 o Ctrl+Enter -> Guardar (Submit)
    if (isF10 || isCtrlEnter) {
        // Buscar el boton submit principal en el header de acciones, o un submit primario
        // Prioridad absoluta a botones que referencian al formulario principal via atributo `form`
        const saveBtn = document.querySelector('button[type="submit"][form]') || 
                        document.querySelector('.rxn-module-actions button[type="submit"]:not([formnovalidate])') || 
                        document.querySelector('button[type="submit"].btn-primary');
                        
        if (saveBtn) {
            e.preventDefault();
            saveBtn.click();
            return;
        }
    }

    // 2. Escape -> Volver / Cancelar
    if (isEscape) {
        // Buscar boton tipico de volver
        const backBtn = document.querySelector('.rxn-module-actions a.btn-outline-secondary') || 
                        document.querySelector('a.btn-outline-secondary i.bi-arrow-left')?.closest('a') ||
                        document.querySelector('.rxn-module-actions a[href*="/mi-empresa"]');
                        
        if (backBtn) {
            e.preventDefault();
            backBtn.click();
            return;
        }

        // Si esta en un buscador, se quita el foco
        if (isInput && activeEl.hasAttribute('data-search-input')) {
            activeEl.blur();
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

    // 4. Slash (/) o Alt+B -> Foco en Busqueda
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
