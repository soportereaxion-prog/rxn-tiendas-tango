/**
 * rxn-spotlight.js
 * Spotlight Contextual Search (Advanced Floating Search)
 */
document.addEventListener('DOMContentLoaded', () => {
    // Escuchar dblclick en inputs que tengan data-picker-url o en selects
    document.body.addEventListener('dblclick', (e) => {
        if (e.target.matches('input[data-picker-input]')) {
            const wrap = e.target.closest('[data-picker-url]');
            if (wrap) {
                const url = wrap.getAttribute('data-picker-url');
                openSpotlight(e.target, url);
            }
        } else if (e.target.matches('select')) {
            openSpotlight(e.target, null);
        }
    });

    // Escuchar Enter o F3 para abrir modal (Chau ratón híbrido) usando CAPTURE phase para ganar precedencia
    document.body.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === 'F3') {
            if (e.target.matches('input[data-picker-input]')) {
                // Prevenir si hay una lista flotante nativa (rxn-picker) abierta Y con algun elemento "activo"
                const wrap = e.target.closest('[data-picker-url]'); // rxn-picker wrapper
                if (e.key === 'Enter' && wrap) {
                    const dropdown = wrap.querySelector('.rxn-search-suggestions:not(.d-none)');
                    const hasActive = dropdown ? dropdown.querySelector('.is-active') : null;
                    if (dropdown && hasActive) return; // Dejar que el picker nativo procese el Enter
                }
                
                e.preventDefault();
                e.stopPropagation(); // Evitar submit de formulario o validaciones locales
                const dWrap = e.target.closest('[data-picker-url]');
                if (dWrap) {
                    openSpotlight(e.target, dWrap.getAttribute('data-picker-url'));
                }
            } else if (e.target.matches('select')) {
                e.preventDefault();
                e.stopPropagation();
                openSpotlight(e.target, null);
            }
        }
    }, true); // <-- True: CAPTURE PHASE
});

let spotlightBackdrop = null;
let spotlightDialog = null;
let spotlightInput = null;
let spotlightResults = null;
let currentTargetInput = null;
let currentFetchAborter = null;

function initSpotlightDOM() {
    if (spotlightBackdrop) return;

    spotlightBackdrop = document.createElement('div');
    spotlightBackdrop.className = 'rxn-spotlight-backdrop';
    document.body.appendChild(spotlightBackdrop);

    spotlightDialog = document.createElement('div');
    spotlightDialog.className = 'rxn-spotlight-dialog';

    const header = document.createElement('div');
    header.className = 'rxn-spotlight-header';
    spotlightInput = document.createElement('input');
    spotlightInput.type = 'text';
    spotlightInput.className = 'rxn-spotlight-input';
    spotlightInput.placeholder = 'Buscar... (código o desc)';
    spotlightInput.autocomplete = 'off';
    header.appendChild(spotlightInput);

    spotlightResults = document.createElement('ul');
    spotlightResults.className = 'rxn-spotlight-results';

    spotlightDialog.appendChild(header);
    spotlightDialog.appendChild(spotlightResults);
    document.body.appendChild(spotlightDialog);

    // Eventos de cierre
    spotlightBackdrop.addEventListener('click', closeSpotlight);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && spotlightDialog.classList.contains('show')) {
            e.preventDefault();
            e.stopImmediatePropagation(); // Block the page exit alert!
            closeSpotlight();
        }
    }, true); // Capture phase to beat the CRM page handlers

    // Eventos de búsqueda
    let debounceTimer;
    spotlightInput.addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            fetchSpotlightResults(e.target.value);
        }, 250);
    });

    // Navegación con teclado
    spotlightInput.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            closeSpotlight();
            return;
        }

        const items = Array.from(spotlightResults.querySelectorAll('.rxn-spotlight-result-item'));
        if (items.length === 0) return;

        let currentIndex = items.findIndex(item => item.classList.contains('active'));

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (currentIndex < items.length - 1) {
                if (currentIndex >= 0) items[currentIndex].classList.remove('active');
                items[currentIndex + 1].classList.add('active');
                items[currentIndex + 1].scrollIntoView({ block: 'nearest' });
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (currentIndex > 0) {
                items[currentIndex].classList.remove('active');
                items[currentIndex - 1].classList.add('active');
                items[currentIndex - 1].scrollIntoView({ block: 'nearest' });
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (currentIndex >= 0) {
                items[currentIndex].click();
            }
        }
    });

    // Evento de selección
    spotlightResults.addEventListener('click', (e) => {
        const item = e.target.closest('.rxn-spotlight-result-item');
        if (item) {
            handleSelection(item);
        }
    });
}

function openSpotlight(targetInput, fetchUrl) {
    initSpotlightDOM();
    currentTargetInput = targetInput;
    spotlightDialog.dataset.url = fetchUrl || '';
    spotlightDialog.dataset.mode = targetInput.tagName.toLowerCase() === 'select' ? 'select' : 'fetch';

    // Calcular posicionamiento fijo para no depender de scrollY/overflows relativos
    spotlightDialog.style.position = 'fixed';
    const rect = targetInput.getBoundingClientRect();
    
    let left = rect.left;
    // Si desborda por derecha
    if (left + 450 > window.innerWidth) {
        left = window.innerWidth - 460;
    }

    // Set min width based on target input
    const minWidth = rect.width;
    spotlightDialog.style.minWidth = Math.max(450, minWidth) + 'px';
    spotlightDialog.style.left = left + 'px';

    // Mostrar hacia arriba si está en la mitad inferior de la pantalla
    if (rect.top > window.innerHeight / 1.7) {
        spotlightDialog.style.top = 'auto';
        spotlightDialog.style.bottom = (window.innerHeight - rect.top + 5) + 'px';
        spotlightDialog.style.transformOrigin = 'bottom left';
    } else {
        spotlightDialog.style.bottom = 'auto';
        spotlightDialog.style.top = (rect.bottom + 5) + 'px';
        spotlightDialog.style.transformOrigin = 'top left';
    }

    if (spotlightDialog.dataset.mode === 'select') {
        const text = targetInput.options[targetInput.selectedIndex]?.text || '';
        spotlightInput.value = text;
        spotlightInput.select();
    } else {
        spotlightInput.value = targetInput.value;
    }
    
    spotlightResults.innerHTML = '';
    
    spotlightBackdrop.style.display = 'block';
    spotlightDialog.style.display = 'flex';
    
    // Reflow
    spotlightBackdrop.offsetHeight;
    
    spotlightBackdrop.classList.add('show');
    spotlightDialog.classList.add('show');
    spotlightInput.focus();
    
    if (spotlightInput.value.trim().length > 0) {
        fetchSpotlightResults(spotlightInput.value);
    } else {
        // Fetch vacio (trae recientes o defaults dependiendo del endpoint)
        fetchSpotlightResults('');
    }
}

function closeSpotlight(restoreFocus = true) {
    if (!spotlightDialog) return;
    spotlightBackdrop.classList.remove('show');
    spotlightDialog.classList.remove('show');
    
    setTimeout(() => {
        spotlightBackdrop.style.display = 'none';
        spotlightDialog.style.display = 'none';
        if (restoreFocus && currentTargetInput) {
            currentTargetInput.focus();
        }
    }, 200);
}

async function fetchSpotlightResults(query) {
    if (spotlightDialog.dataset.mode === 'select') {
        // Source is a static <select>
        const queryLower = query.toLowerCase();
        const options = Array.from(currentTargetInput.options);
        
        let filtered = options.filter(opt => {
            if (!opt.value) return false;
            return opt.text.toLowerCase().includes(queryLower);
        });
        
        const dataArr = filtered.map(opt => ({
            id: opt.value,
            label: opt.text,
            value: opt.text,
            caption: 'Opción local'
        }));
        
        renderResults(dataArr);
        return;
    }

    const url = spotlightDialog.dataset.url;
    if (!url) return;

    if (currentFetchAborter) {
        currentFetchAborter.abort();
    }
    currentFetchAborter = new AbortController();

    try {
        const params = new URLSearchParams({ q: query });
        const wrap = currentTargetInput.closest('[data-picker-url]');
        
        // El picker base envía lista_id y cliente_id. Tratamos de capturalos si los precisamos (Artículos)
        if (wrap && wrap.closest('form')) {
            const form = wrap.closest('form');
            const listaSelect = form.querySelector('[data-lista-select]');
            const ctxUrl = wrap.dataset.contextUrl;
            
            // Si el input origen tiene data-context-url, y tenemos cliente_id/lista_id, los pasamos.
            if (ctxUrl) {
               // Ya que usamos typeahead, si el form tiene cliente_id hidden, lo pasamos (comportamiento igual a rxn-picker)
               const clientHidden = form.querySelector('[data-picker-hidden][name="cliente_id"]');
               if (clientHidden) {
                   params.append('cliente_id', clientHidden.value);
               }
               if (listaSelect) {
                   params.append('lista_codigo', listaSelect.value);
               }
            }
        }

        const fetchUrl = `${url}?${params.toString()}`;
        const resp = await fetch(fetchUrl, {
            signal: currentFetchAborter.signal,
            headers: { 'Accept': 'application/json' }
        });

        if (!resp.ok) throw new Error('Network error');
        
        const json = await resp.json();
        // The API returns {success: true, data: [...]}
        const dataArr = json.data || json;
        renderResults(dataArr);
    } catch (err) {
        if (err.name !== 'AbortError') {
            console.error('Error fetching spotlight results:', err);
            spotlightResults.innerHTML = '<li class="p-3 text-danger"><i class="bi bi-exclamation-triangle"></i> Error al cargar resultados</li>';
        }
    }
}

function renderResults(items) {
    spotlightResults.innerHTML = '';
    
    if (!Array.isArray(items) || items.length === 0) {
        spotlightResults.innerHTML = '<li class="p-3 text-muted">No se encontraron resultados</li>';
        return;
    }

    items.forEach((item, index) => {
        const li = document.createElement('li');
        li.className = 'rxn-spotlight-result-item';
        if (index === 0) li.classList.add('active'); // Pre-select primero
        
        // El formato de sugerencias de RXN suele ser {id, label, value, caption}
        // o a veces {id, nombre, descripcion}.
        let title = item.label || item.value || item.nombre || item.text || item.id;
        if (item.model_code) {
             title = `[${item.model_code}] ${title}`;
        }
        
        let sub = item.caption || item.descripcion || '';
        
        if (!sub) {
            if (item.precio_origen !== undefined) {
                 sub = `Precio: $${item.precio || 0} (${item.precio_origen})`;
            } else if (item.documento || item.cuit) {
                 sub = `Doc: ${item.documento || item.cuit}`;
            }
        }
        
        li.innerHTML = `
            <span class="rxn-spotlight-result-title">${title}</span>
            ${sub ? `<span class="rxn-spotlight-result-desc">${sub}</span>` : ''}
        `;
        
        // Guardamos todo el objeto data para el handleSelection
        li.dataset.json = JSON.stringify(item);
        spotlightResults.appendChild(li);
    });
}

function handleSelection(itemEl) {
    if (!currentTargetInput) return;
    
    const itemData = JSON.parse(itemEl.dataset.json);
    
    if (spotlightDialog.dataset.mode === 'select') {
        currentTargetInput.value = itemData.id;
        currentTargetInput.dispatchEvent(new Event('change', { bubbles: true }));
    } else {
        const wrap = currentTargetInput.closest('[data-picker-url]');
        if (wrap) {
            const hiddenInfo = wrap.querySelector('[data-picker-hidden]');
            if (hiddenInfo) {
                hiddenInfo.value = itemData.id || itemData.model_code || '';
            }

            // Propagar extraId al campo data-picker-extra-hidden (ej: clasificacion_id_tango)
            const extraHiddenInfo = wrap.querySelector('[data-picker-extra-hidden]');
            if (extraHiddenInfo) {
                extraHiddenInfo.value = itemData.extraId != null ? itemData.extraId : '';
            }
            
            currentTargetInput.value = itemData.value || itemData.label || itemData.nombre || itemData.text || itemData.id || '';
            
        // Custom events / callbacks just like rxn_picker.js
            const event = new CustomEvent('picker-selected', { bubbles: true, detail: itemData });
            currentTargetInput.dispatchEvent(event);
        }
    }
    
    closeSpotlight(false);
}
