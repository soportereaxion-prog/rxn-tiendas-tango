/**
 * rxn-shortcuts.js — Sistema centralizado de atajos de teclado
 *
 * ARQUITECTURA:
 *   - Registry declarativo global (`window.RxnShortcuts`).
 *   - Overlay tipo GitHub disparado con `Shift + ?` que lista todos los atajos
 *     agrupados por sección con teclas en <kbd>.
 *   - Cada módulo puede registrar sus propios atajos con `RxnShortcuts.register({...})`.
 *   - Los módulos consumen un único keydown handler global — no hay múltiples listeners
 *     compitiendo por el mismo evento.
 *
 * USO (desde cualquier módulo):
 *   RxnShortcuts.register({
 *       id: 'guardar-presupuesto',     // único
 *       keys: ['F10', 'Ctrl+Enter'],   // al menos una; display en overlay
 *       description: 'Guardar el presupuesto actual',
 *       group: 'Presupuestos',         // agrupador visual en el overlay
 *       scope: 'global',               // 'global' | 'modal' | 'no-input'
 *       when: () => !!document.getElementById('form-presupuesto'), // opcional
 *       action: (e) => { document.getElementById('btnGuardar').click(); }
 *   });
 *
 *   RxnShortcuts.open();   // abre overlay programáticamente
 *   RxnShortcuts.close();  // cierra overlay
 *   RxnShortcuts.list();   // retorna array de shortcuts registrados
 */
(function () {
    'use strict';

    if (window.RxnShortcuts) return; // idempotente

    // ---------- Registry ----------
    const registry = [];

    function register(def) {
        if (!def || !def.id) { console.warn('[RxnShortcuts] register: falta id', def); return; }
        if (registry.some(s => s.id === def.id)) {
            // Actualizar en vez de duplicar
            const idx = registry.findIndex(s => s.id === def.id);
            registry[idx] = normalize(def);
            return;
        }
        registry.push(normalize(def));
    }

    function normalize(def) {
        return {
            id: def.id,
            keys: Array.isArray(def.keys) ? def.keys.slice() : [def.keys].filter(Boolean),
            description: def.description || '',
            group: def.group || 'General',
            scope: def.scope || 'global',
            when: typeof def.when === 'function' ? def.when : null,
            action: typeof def.action === 'function' ? def.action : function () {},
            hidden: !!def.hidden, // excluir del overlay pero sigue activo
        };
    }

    function list() { return registry.slice(); }

    // ---------- Matching de combinaciones ----------
    // Normaliza un KeyboardEvent a un string comparable: "Ctrl+Shift+A", "F10", "?", "/"
    function eventToCombo(e) {
        const parts = [];
        if (e.ctrlKey) parts.push('Ctrl');
        if (e.altKey) parts.push('Alt');
        if (e.shiftKey && !isPrintableSingleChar(e.key)) parts.push('Shift');
        // Caso especial: Shift+? (en teclado ES/US, ? sale con Shift)
        if (e.key === '?') { parts.push('Shift'); parts.push('?'); return parts.join('+'); }
        parts.push(normalizeKey(e.key));
        return parts.join('+');
    }

    function normalizeKey(key) {
        if (!key) return '';
        if (key.length === 1) return key.toUpperCase(); // a→A, /→/
        // F1-F12, Escape, Enter, Insert, etc. quedan como están
        return key;
    }

    function isPrintableSingleChar(key) {
        return typeof key === 'string' && key.length === 1;
    }

    // Dado un string "Ctrl+Enter" lo convierte al formato canónico para comparar
    function canonicalizeCombo(combo) {
        const parts = combo.split('+').map(p => p.trim());
        const mods = [];
        const rest = [];
        parts.forEach(p => {
            const pl = p.toLowerCase();
            if (pl === 'ctrl' || pl === 'control') mods.push('Ctrl');
            else if (pl === 'alt') mods.push('Alt');
            else if (pl === 'shift') mods.push('Shift');
            else if (pl === 'cmd' || pl === 'meta') mods.push('Meta');
            else rest.push(normalizeKey(p));
        });
        // Orden fijo: Ctrl, Alt, Shift, Meta, luego tecla
        const order = ['Ctrl', 'Alt', 'Shift', 'Meta'];
        const sortedMods = order.filter(m => mods.includes(m));
        return sortedMods.concat(rest).join('+');
    }

    function matches(shortcut, eventCombo) {
        return shortcut.keys.some(k => canonicalizeCombo(k) === canonicalizeCombo(eventCombo));
    }

    // ---------- Overlay ----------
    let overlayEl = null;

    function buildOverlay() {
        if (overlayEl) return overlayEl;
        const wrap = document.createElement('div');
        wrap.id = 'rxnShortcutsOverlay';
        wrap.className = 'rxn-shortcuts-overlay';
        wrap.setAttribute('role', 'dialog');
        wrap.setAttribute('aria-modal', 'true');
        wrap.setAttribute('aria-labelledby', 'rxnShortcutsTitle');
        wrap.innerHTML = `
            <div class="rxn-shortcuts-backdrop" data-rxn-shortcuts-close="1"></div>
            <div class="rxn-shortcuts-panel" role="document">
                <header class="rxn-shortcuts-header">
                    <h2 id="rxnShortcutsTitle" class="rxn-shortcuts-title">
                        <i class="bi bi-keyboard me-2"></i>Atajos de teclado
                    </h2>
                    <button type="button" class="rxn-shortcuts-close-btn" data-rxn-shortcuts-close="1" aria-label="Cerrar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </header>
                <div class="rxn-shortcuts-body" id="rxnShortcutsBody">
                    <!-- Generado dinámicamente -->
                </div>
                <footer class="rxn-shortcuts-footer">
                    <span class="rxn-shortcuts-hint">Presioná <kbd>Shift</kbd> + <kbd>?</kbd> en cualquier pantalla para abrir este panel.</span>
                    <span class="rxn-shortcuts-hint">Cerrar con <kbd>Esc</kbd></span>
                </footer>
            </div>
        `;
        document.body.appendChild(wrap);
        overlayEl = wrap;

        // Cerrar al clickear backdrop o botón
        wrap.addEventListener('click', function (e) {
            const t = e.target.closest('[data-rxn-shortcuts-close]');
            if (t) { e.preventDefault(); close(); }
        });

        return wrap;
    }

    function renderOverlayContent() {
        const body = document.getElementById('rxnShortcutsBody');
        if (!body) return;

        // Agrupar por `group`
        const groups = {};
        registry.forEach(s => {
            if (s.hidden) return;
            if (s.when && !s.when()) return;
            if (!groups[s.group]) groups[s.group] = [];
            groups[s.group].push(s);
        });

        const groupNames = Object.keys(groups).sort((a, b) => {
            // "General" y "Ayuda" primero, resto alfabético
            const prio = (g) => (g === 'General' ? 0 : g === 'Ayuda' ? 1 : 2);
            const p = prio(a) - prio(b);
            return p !== 0 ? p : a.localeCompare(b);
        });

        if (groupNames.length === 0) {
            body.innerHTML = '<p class="rxn-shortcuts-empty text-muted">No hay atajos registrados en esta pantalla.</p>';
            return;
        }

        body.innerHTML = groupNames.map(g => `
            <section class="rxn-shortcuts-group">
                <h3 class="rxn-shortcuts-group-title">${escapeHtml(g)}</h3>
                <ul class="rxn-shortcuts-list">
                    ${groups[g].map(s => `
                        <li class="rxn-shortcuts-item">
                            <span class="rxn-shortcuts-desc">${escapeHtml(s.description)}</span>
                            <span class="rxn-shortcuts-keys">
                                ${s.keys.map(k => renderCombo(k)).join('<span class="rxn-shortcuts-or">o</span>')}
                            </span>
                        </li>
                    `).join('')}
                </ul>
            </section>
        `).join('');
    }

    function renderCombo(combo) {
        const parts = combo.split('+').map(p => p.trim());
        return '<span class="rxn-shortcuts-combo">' +
            parts.map(p => `<kbd>${escapeHtml(p)}</kbd>`).join('<span class="rxn-shortcuts-plus">+</span>') +
            '</span>';
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function open() {
        const el = buildOverlay();
        renderOverlayContent();
        el.classList.add('is-open');
        document.body.classList.add('rxn-shortcuts-lock');
        // Focus al panel para capturar Esc
        const panel = el.querySelector('.rxn-shortcuts-panel');
        if (panel) panel.focus();
    }

    function close() {
        if (!overlayEl) return;
        overlayEl.classList.remove('is-open');
        document.body.classList.remove('rxn-shortcuts-lock');
    }

    function isOverlayOpen() {
        return !!overlayEl && overlayEl.classList.contains('is-open');
    }

    // ---------- Keydown dispatcher global ----------
    function dispatch(e) {
        // Si el overlay está abierto, Esc lo cierra (prioridad absoluta)
        if (isOverlayOpen()) {
            if (e.key === 'Escape') {
                e.preventDefault();
                close();
                return;
            }
            // Dentro del overlay no dejamos pasar ningún otro shortcut
            return;
        }

        const activeEl = document.activeElement;
        const isInput = activeEl && ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeEl.tagName);
        const isContentEditable = activeEl && activeEl.isContentEditable;

        const combo = eventToCombo(e);

        // Iterar registry en orden y disparar el primer match cuyo scope/when permita
        for (const s of registry) {
            if (!matches(s, combo)) continue;
            if (s.scope === 'no-input' && (isInput || isContentEditable)) continue;
            if (s.when && !s.when()) continue;
            try {
                s.action(e);
            } catch (err) {
                console.error('[RxnShortcuts] Error ejecutando', s.id, err);
            }
            return; // primer match gana
        }
    }

    document.addEventListener('keydown', dispatch);

    // ---------- API pública ----------
    window.RxnShortcuts = {
        register: register,
        list: list,
        open: open,
        close: close,
        isOpen: isOverlayOpen,
    };

    // ---------- Shortcuts por defecto (preserva comportamiento previo) ----------

    // AYUDA
    register({
        id: 'rxn-help-overlay',
        keys: ['Shift+?'],
        description: 'Mostrar este panel de atajos',
        group: 'Ayuda',
        scope: 'no-input',
        action: (e) => { e.preventDefault(); open(); }
    });

    // MODALES — Enter/Escape standardization (preserva lógica previa)
    register({
        id: 'rxn-modal-accept',
        keys: ['Enter'],
        description: 'Aceptar modal abierto',
        group: 'Modales',
        scope: 'global',
        hidden: false,
        when: () => {
            const m = document.querySelector('.modal.show');
            if (!m) return false;
            const a = document.activeElement;
            // Permitir saltos de línea en textareas
            if (a && a.tagName === 'TEXTAREA') return false;
            return true;
        },
        action: (e) => {
            const activeModal = document.querySelector('.modal.show');
            if (!activeModal) return;
            e.preventDefault();
            const btn = activeModal.querySelector('.modal-footer .btn-primary') ||
                        activeModal.querySelector('.modal-footer .btn-success') ||
                        activeModal.querySelector('.modal-footer button[type="submit"]') ||
                        activeModal.querySelector('.btn-primary');
            if (btn && !btn.disabled) btn.click();
        }
    });

    register({
        id: 'rxn-modal-cancel',
        keys: ['Escape', 'ArrowLeft'],
        description: 'Cancelar modal abierto',
        group: 'Modales',
        scope: 'global',
        when: () => !!document.querySelector('.modal.show'),
        action: (e) => {
            const activeModal = document.querySelector('.modal.show');
            if (!activeModal) return;
            const btn = activeModal.querySelector('[data-bs-dismiss="modal"]') ||
                        activeModal.querySelector('[data-confirm-cancel]');
            if (btn && !btn.disabled) btn.click();
        }
    });

    // ACCIONES globales
    register({
        id: 'rxn-save',
        keys: ['F10', 'Ctrl+Enter'],
        description: 'Guardar el formulario actual',
        group: 'Acciones',
        scope: 'global',
        when: () => !document.querySelector('.modal.show'),
        action: (e) => {
            const saveBtn = document.querySelector('button[type="submit"][name="action"][value="save"]') ||
                            document.querySelector('button[type="submit"][form]:not([name="action"][value="tango"])') ||
                            document.querySelector('.rxn-module-actions button[type="submit"]:not([formnovalidate]):not([name="action"][value="tango"])') ||
                            document.querySelector('button[type="submit"].btn-primary');
            if (saveBtn) { e.preventDefault(); saveBtn.click(); }
        }
    });

    register({
        id: 'rxn-back',
        keys: ['Escape'],
        description: 'Volver al listado / cancelar',
        group: 'Navegación',
        scope: 'global',
        when: () => !document.querySelector('.modal.show'),
        action: (e) => {
            const activeEl = document.activeElement;
            const isInput = activeEl && ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeEl.tagName);
            if (isInput) { activeEl.blur(); return; }
            if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) {
                e.preventDefault(); window.history.back(); return;
            }
            const backBtn = document.querySelector('.rxn-module-actions a.btn-outline-secondary') ||
                            document.querySelector('a.btn-outline-secondary i.bi-arrow-left')?.closest('a') ||
                            document.querySelector('.rxn-module-actions a[href*="/mi-empresa"]');
            if (backBtn) { e.preventDefault(); backBtn.click(); }
        }
    });

    register({
        id: 'rxn-new',
        keys: ['Insert', 'Alt+N'],
        description: 'Nuevo registro',
        group: 'Acciones',
        scope: 'no-input',
        when: () => !document.querySelector('.modal.show'),
        action: (e) => {
            const newBtn = document.querySelector('.rxn-module-actions a.btn-primary i.bi-plus-circle')?.closest('a') ||
                           document.querySelector('a.btn-primary[href*="/crear"]');
            if (newBtn) { e.preventDefault(); newBtn.click(); }
        }
    });

    register({
        id: 'rxn-focus-search',
        keys: ['/', 'F3', 'Alt+B'],
        description: 'Foco en el buscador',
        group: 'Navegación',
        scope: 'no-input',
        when: () => !document.querySelector('.modal.show'),
        action: (e) => {
            const searchInput = document.querySelector('input[data-search-input]');
            if (searchInput) {
                e.preventDefault();
                searchInput.focus();
                // Mover cursor al final del texto existente
                const val = searchInput.value;
                searchInput.value = '';
                searchInput.value = val;
            }
        }
    });
})();
