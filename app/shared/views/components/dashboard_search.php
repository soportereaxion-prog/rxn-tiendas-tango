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
        // Avoid intercepting if user is already typing in an input
        const tag = e.target.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
            return;
        }

        if (e.key === '/' || e.key === 'F3') {
            e.preventDefault(); 
            searchInput.focus();
        }
    });
});
</script>
