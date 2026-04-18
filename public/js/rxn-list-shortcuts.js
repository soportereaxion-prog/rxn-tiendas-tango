/**
 * rxn-list-shortcuts.js — Atajos para listados (ALT+O = copiar fila activa).
 *
 * Cualquier <tr> o elemento con `data-copy-url="<POST URL>"` queda habilitado
 * para copiarse con ALT+O cuando es la fila "activa" (focused o con mouse encima).
 *
 * No agrega listeners propios para el keydown — delega en RxnShortcuts, que
 * centraliza el overlay Shift+? y el dispatching.
 */
(function () {
    'use strict';

    if (!window.RxnShortcuts) {
        console.warn('[rxn-list-shortcuts] RxnShortcuts no está disponible.');
        return;
    }

    const HOVER_ATTR = 'data-rxn-row-hover';

    function getActiveCopyRow() {
        const focused = document.activeElement;
        if (focused && focused.closest) {
            const row = focused.closest('[data-copy-url]');
            if (row) return row;
        }
        return document.querySelector('[' + HOVER_ATTR + '="1"][data-copy-url]');
    }

    function postTo(url) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.style.display = 'none';

        // CSRF token si el meta existe (mismo patrón que otros forms del proyecto)
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta && csrfMeta.content) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf_token';
            input.value = csrfMeta.content;
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
    }

    RxnShortcuts.register({
        id: 'rxn-copy-active-row',
        keys: ['Alt+O'],
        description: 'Copiar la fila activa (hover o foco)',
        group: 'Listados',
        scope: 'global',
        when: () => !document.querySelector('.modal.show') && !!getActiveCopyRow(),
        action: (e) => {
            const row = getActiveCopyRow();
            if (!row) return;
            const url = row.getAttribute('data-copy-url');
            if (!url) return;
            e.preventDefault();
            postTo(url);
        }
    });

    // Tracking del hover para filas con data-copy-url.
    // Usamos delegación en document para cubrir filas renderizadas dinámicamente.
    document.addEventListener('mouseover', function (e) {
        const target = e.target && e.target.closest ? e.target.closest('[data-copy-url]') : null;
        if (!target) return;
        target.setAttribute(HOVER_ATTR, '1');
    });

    document.addEventListener('mouseout', function (e) {
        const target = e.target && e.target.closest ? e.target.closest('[data-copy-url]') : null;
        if (!target) return;
        // Sólo limpiar si realmente salimos de la fila (no si fuimos a un hijo)
        if (!target.contains(e.relatedTarget)) {
            target.removeAttribute(HOVER_ATTR);
        }
    });
})();
