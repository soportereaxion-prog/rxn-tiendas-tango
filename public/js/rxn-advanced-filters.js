document.addEventListener('DOMContentLoaded', () => {
    const filterCols = document.querySelectorAll('.rxn-filter-col');
    if (filterCols.length === 0) return;

    // Obtener los filtros aplicados desde la URL params ?f[campo][op]=...
    const urlParams = new URLSearchParams(window.location.search);
    const activeFilters = {};
    for (const [key, value] of urlParams.entries()) {
        const match = key.match(/^f\[(.*?)\]\[(.*?)\]$/);
        if (match) {
            const field = match[1];
            const type = match[2]; // op or val
            if (!activeFilters[field]) activeFilters[field] = {};
            activeFilters[field][type] = value;
        }
    }

    // Inyectar CSS minimo si no existe
    if (!document.getElementById('rxn-advanced-filters-css')) {
        const style = document.createElement('style');
        style.id = 'rxn-advanced-filters-css';
        style.innerHTML = `
            .rxn-filter-col .rxn-filter-icon {
                cursor: pointer;
                opacity: 0.3;
                font-size: 0.85rem;
                margin-left: 5px;
                transition: opacity 0.2s;
            }
            .rxn-filter-col:hover .rxn-filter-icon { opacity: 0.7; }
            .rxn-filter-col .rxn-filter-icon.active { opacity: 1; color: #0d6efd; }
            .rxn-filter-popover {
                position: absolute;
                top: 100%;
                right: 0;
                z-index: 1050;
                min-width: 250px;
                background: white;
                border: 1px solid rgba(0,0,0,0.15);
                box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
                border-radius: 0.375rem;
                padding: 1rem;
                display: none;
                font-weight: normal;
                font-size: 0.9rem;
            }
            .rxn-filter-popover.show { display: block; }
        `;
        document.head.appendChild(style);
    }

    // Cerrar popovers al cliquear afuera
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.rxn-filter-popover') && !e.target.closest('.rxn-filter-icon')) {
            document.querySelectorAll('.rxn-filter-popover.show').forEach(el => el.classList.remove('show'));
        }
    });

    filterCols.forEach(th => {
        const field = th.getAttribute('data-filter-field');
        if (!field) return;

        th.style.position = 'relative'; // Ensure absolute positioning works

        const isActive = activeFilters[field] && activeFilters[field].val;
        const currentOp = isActive ? activeFilters[field].op : 'contiene';
        const currentVal = isActive ? activeFilters[field].val : '';

        // Boton icon
        const icon = document.createElement('i');
        icon.className = `bi bi-funnel${isActive ? '-fill active' : ''} rxn-filter-icon float-end mt-1`;
        icon.title = "Filtrar columna";
        
        // Popover
        const popover = document.createElement('div');
        popover.className = 'rxn-filter-popover text-start';
        popover.onclick = (e) => e.stopPropagation(); // Evitar q click propague y ordene columna

        popover.innerHTML = `
            <div class="mb-2 fw-bold text-dark">Filtrar columna</div>
            <select class="form-select form-select-sm mb-2" id="filter_op_${field}">
                <option value="contiene" ${currentOp==='contiene'?'selected':''}>Contiene</option>
                <option value="no_contiene" ${currentOp==='no_contiene'?'selected':''}>No contiene</option>
                <option value="empieza_con" ${currentOp==='empieza_con'?'selected':''}>Empieza con</option>
                <option value="termina_con" ${currentOp==='termina_con'?'selected':''}>Termina con</option>
                <option value="igual" ${currentOp==='igual'?'selected':''}>Igual</option>
                <option value="distinto" ${currentOp==='distinto'?'selected':''}>Distinto</option>
            </select>
            <input type="text" class="form-control form-control-sm mb-3" id="filter_val_${field}" value="${currentVal}" placeholder="Valor...">
            <div class="d-flex justify-content-between gap-2">
                <button type="button" class="btn btn-sm btn-outline-danger w-50 btn-clear-filter">Eliminar</button>
                <button type="button" class="btn btn-sm btn-primary w-50 btn-apply-filter">Aplicar</button>
            </div>
        `;

        icon.onclick = (e) => {
            e.stopPropagation(); // Prevent column sorting
            document.querySelectorAll('.rxn-filter-popover.show').forEach(el => {
                if (el !== popover) el.classList.remove('show');
            });
            popover.classList.toggle('show');
            if (popover.classList.contains('show')) {
                // Inteligencia espacial basada en el contenedor (para evitar overflow hidden del table-responsive)
                const tableContainer = th.closest('.table-responsive');
                const thRect = th.getBoundingClientRect();
                let isNearLeftEdge = false;

                if (tableContainer) {
                    const containerRect = tableContainer.getBoundingClientRect();
                    const distLeft = thRect.left - containerRect.left;
                    isNearLeftEdge = distLeft < 250;
                } else {
                    isNearLeftEdge = thRect.left < 250; // Fallback
                }

                if (isNearLeftEdge) {
                    // Si esta muy a la izquierda, que nazca hacia la derecha
                    popover.style.right = 'auto';
                    popover.style.left = '0';
                } else {
                    // Sigue naciendo hacia la izquierda (comportamiento normal)
                    popover.style.right = '0';
                    popover.style.left = 'auto';
                }
                popover.querySelector('input').focus();
            }
        };

        // Eventos Apply/Clear
        popover.querySelector('.btn-clear-filter').onclick = () => {
            const params = new URLSearchParams(window.location.search);
            params.delete(`f[${field}][op]`);
            params.delete(`f[${field}][val]`);
            params.delete('page');

            let hasFilters = false;
            for (const key of params.keys()) {
                if (key.startsWith('f[')) {
                    hasFilters = true;
                    break;
                }
            }
            if (!hasFilters) {
                params.set('reset_filters', '1');
            }

            window.location.search = params.toString();
        };

        popover.querySelector('.btn-apply-filter').onclick = () => {
            const op = popover.querySelector(`#filter_op_${field}`).value;
            const val = popover.querySelector(`#filter_val_${field}`).value;
            
            const params = new URLSearchParams(window.location.search);

            if (!val.trim()) {
                params.delete(`f[${field}][op]`);
                params.delete(`f[${field}][val]`);
            } else {
                params.set(`f[${field}][op]`, op);
                params.set(`f[${field}][val]`, val);
            }
            
            params.delete('page');

            let hasFilters = false;
            for (const key of params.keys()) {
                if (key.startsWith('f[')) {
                    hasFilters = true;
                    break;
                }
            }
            if (!hasFilters) {
                params.set('reset_filters', '1');
            }

            window.location.search = params.toString();
        };

        // Soporte tecla enter en input
        popover.querySelector('input').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                popover.querySelector('.btn-apply-filter').click();
            }
        });

        // En lugar de meterlo dentro del enlace (lo cual rompía el sort indicator y disparaba el click),
        // Lo ponemos en el th directamente y le hacemos lugar.
        icon.style.position = 'absolute';
        icon.style.right = '5px';
        icon.style.top = '50%';
        icon.style.transform = 'translateY(-50%)';
        icon.classList.remove('float-end', 'mt-1'); // Limpiamos las clases que molestaban

        const aLink = th.querySelector('a');
        if (aLink) {
            aLink.style.paddingRight = '20px';
            aLink.style.display = 'inline-block';
        }

        th.appendChild(icon);
        th.appendChild(popover);
    });
});
