<div class="mb-4 position-relative">
    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
    <input type="text" id="rxn-dashboard-search" class="form-control form-control-lg bg-light border-0 rounded-pill shadow-sm" style="padding-left: 2.5rem;" placeholder="Buscar módulos... (Presiona '/' o F3)" autocomplete="off">
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('rxn-dashboard-search');
    if(!searchInput) return;

    // Filter logic
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        const cards = document.querySelectorAll('.rxn-module-card');
        
        cards.forEach(cardInner => {
            const wrapper = cardInner.closest('[class*="col-"]') || cardInner.parentElement;
            const titleElement = cardInner.querySelector('h4, h5');
            const title = titleElement ? titleElement.textContent.toLowerCase() : '';
            const desc = cardInner.querySelector('p')?.textContent.toLowerCase() || '';
            
            if (title.includes(query) || desc.includes(query)) {
                wrapper.style.display = '';
            } else {
                wrapper.style.display = 'none';
            }
        });
    });

    // Keyboard shortcuts: '/' or 'F3'
    document.addEventListener('keydown', function(e) {
        const isSearchFocused = document.activeElement === searchInput;
        const isCardFocused = document.activeElement && document.activeElement.classList.contains('stretched-link');
        
        // Navigation logic for modules
        if (isSearchFocused || isCardFocused) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                
                // Prevent scrolling
                if (['ArrowDown', 'ArrowUp'].includes(e.key)) {
                    e.preventDefault();
                }

                // Get all visible links
                const cards = document.querySelectorAll('.rxn-module-card');
                const visibleLinks = [];
                cards.forEach(card => {
                    const wrapper = card.closest('[class*="col-"]') || card.parentElement;
                    if (wrapper.style.display !== 'none') {
                        const link = card.querySelector('.stretched-link');
                        if (link) visibleLinks.push(link);
                    }
                });
                
                if (visibleLinks.length === 0) return;

                if (isSearchFocused && (e.key === 'ArrowDown' || e.key === 'ArrowRight')) {
                    e.preventDefault(); // prevent moving caret
                    visibleLinks[0].focus();
                } else if (isCardFocused) {
                    e.preventDefault();
                    const currentIndex = visibleLinks.indexOf(document.activeElement);
                    if (currentIndex === -1) return;

                    let nextIndex = currentIndex;
                    if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
                        nextIndex = currentIndex + 1;
                    } else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
                        nextIndex = currentIndex - 1;
                    }

                    if (nextIndex < 0) {
                        // Vuelve al buscador
                        searchInput.focus();
                        // Put cursor at end of text
                        const len = searchInput.value.length;
                        searchInput.setSelectionRange(len, len);
                    } else if (nextIndex < visibleLinks.length) {
                        visibleLinks[nextIndex].focus();
                    }
                }
            }
        }

        // Avoid intercepting F3/'/' if user is already typing in an input (except search navigation)
        const tag = e.target.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
            return;
        }

        if (e.key === '/' || e.key === 'F3') {
            e.preventDefault(); 
            searchInput.focus();
        }
    });

    // Añadir clase manual para forzar la animación idéntica a hover cuando se navega con el teclado
    document.addEventListener('focusin', function(e) {
        if (e.target && e.target.classList && e.target.classList.contains('stretched-link')) {
            const card = e.target.closest('.rxn-module-card');
            if (card) {
                card.classList.add('rxn-module-card-focus');
            }
        }
    });

    document.addEventListener('focusout', function(e) {
        if (e.target && e.target.classList && e.target.classList.contains('stretched-link')) {
            const card = e.target.closest('.rxn-module-card');
            if (card) {
                card.classList.remove('rxn-module-card-focus');
            }
        }
    });

    // Auto-enfocar la primera tarjeta visible al cargar la vista
    setTimeout(() => {
        const primaryFocusCard = document.querySelector('.rxn-module-card .stretched-link');
        if (primaryFocusCard && !document.activeElement.classList.contains('form-control')) {
            primaryFocusCard.focus();
        }
    }, 50);
});
</script>
