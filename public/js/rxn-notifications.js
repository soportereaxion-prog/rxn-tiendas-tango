/**
 * rxn-notifications.js
 *
 * Hidrata la campanita del topbar.
 * - Al cargar la página: trae el contador de no-leídas y muestra el badge si > 0.
 * - Al abrir el dropdown por primera vez: trae las últimas N y las pinta.
 * - Click en una notificación: marca como leída + navega al link (si tiene).
 * - Botón "Marcar todas como leídas": llama al endpoint y refresca el dropdown.
 *
 * No hace polling automático — el contador se actualiza al recargar la página
 * o al abrir/cerrar el dropdown.
 */
(function () {
    'use strict';

    const wrapper = document.querySelector('.rxn-notif-wrapper');
    if (!wrapper) return;

    const trigger = wrapper.querySelector('.rxn-notif-trigger');
    const badge = wrapper.querySelector('.rxn-notif-badge');
    const itemsContainer = wrapper.querySelector('.rxn-notif-items');
    const footer = wrapper.querySelector('.rxn-notif-footer');
    const markAllBtn = wrapper.querySelector('.rxn-notif-mark-all');

    // CSRF token: lo tomamos del meta si existe (lo seteamos en una iteración
    // posterior si hace falta), sino de cualquier input csrf_token en la página.
    function getCsrf() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.content;
        const input = document.querySelector('input[name="csrf_token"]');
        return input ? input.value : '';
    }

    function fmtRelative(iso) {
        if (!iso) return '';
        try {
            const d = new Date(iso.replace(' ', 'T'));
            const diffMs = Date.now() - d.getTime();
            const diffMin = Math.floor(diffMs / 60000);
            if (diffMin < 1) return 'recién';
            if (diffMin < 60) return diffMin + ' min';
            const diffHs = Math.floor(diffMin / 60);
            if (diffHs < 24) return diffHs + ' h';
            const diffDays = Math.floor(diffHs / 24);
            if (diffDays < 7) return diffDays + ' d';
            return d.toLocaleDateString('es-AR');
        } catch (_) { return ''; }
    }

    function renderBadge(unread) {
        if (!badge) return;
        if (unread > 0) {
            badge.textContent = unread > 99 ? '99+' : String(unread);
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
    }

    function renderItems(items) {
        if (!itemsContainer) return;
        if (!items || items.length === 0) {
            itemsContainer.innerHTML = '<div class="text-center text-muted small py-4">No tenés notificaciones.</div>';
            if (footer) footer.style.display = 'none';
            return;
        }

        const html = items.map(n => {
            const isUnread = !n.is_read;
            const link = n.link ? n.link : '/notifications';
            const title = (n.title || '').replace(/[<>&]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c]));
            const body = n.body ? '<div class="small text-muted">' + n.body.replace(/[<>&]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c])) + '</div>' : '';
            return `
                <a href="${link}" class="rxn-notif-item d-block px-3 py-2 text-decoration-none border-bottom ${isUnread ? 'rxn-notif-item-unread' : ''}" data-notif-id="${n.id}">
                    <div class="d-flex gap-2 align-items-start">
                        <div class="rxn-notif-dot ${isUnread ? '' : 'is-read'} mt-1"></div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold small">${title}</div>
                            ${body}
                            <div class="text-muted" style="font-size: 0.75rem;">${fmtRelative(n.created_at)}</div>
                        </div>
                    </div>
                </a>
            `;
        }).join('');
        itemsContainer.innerHTML = html;

        if (footer) {
            const anyUnread = items.some(n => !n.is_read);
            footer.style.display = anyUnread ? '' : 'none';
        }

        // Mark-as-read on click (antes de navegar). El navigate sigue su curso normal.
        itemsContainer.querySelectorAll('.rxn-notif-item-unread').forEach(a => {
            a.addEventListener('click', function () {
                const id = a.dataset.notifId;
                if (!id) return;
                // fire-and-forget — no esperamos al fetch, el browser navega igual.
                fetch('/notifications/' + id + '/leer', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'csrf_token=' + encodeURIComponent(getCsrf()),
                    keepalive: true
                }).catch(() => {});
            });
        });
    }

    let loaded = false;
    function loadFeed() {
        return fetch('/notifications/feed.json?limit=8', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data || !data.ok) return;
                renderBadge(data.unread || 0);
                renderItems(data.items || []);
                loaded = true;
            })
            .catch(() => {
                if (itemsContainer) {
                    itemsContainer.innerHTML = '<div class="text-center text-danger small py-4">Error cargando notificaciones.</div>';
                }
            });
    }

    // Carga inicial: solo el contador (no items pesados hasta que abra el dropdown).
    loadFeed();

    // Cuando se abre el dropdown, refrescamos por si llegaron nuevas.
    if (trigger) {
        trigger.addEventListener('shown.bs.dropdown', loadFeed);
    } else {
        // Fallback: el dropdown bootstrap dispara el evento sobre el toggle anchor.
        wrapper.addEventListener('shown.bs.dropdown', loadFeed);
    }

    if (markAllBtn) {
        markAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            fetch('/notifications/marcar-todas-leidas', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'csrf_token=' + encodeURIComponent(getCsrf())
            }).then(() => loadFeed());
        });
    }
})();
