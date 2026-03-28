(function () {
    var modalEl;
    var modalInstance;
    var currentAction = null;

    function getTypeConfig(type) {
        var normalized = (type || 'warning').toLowerCase();
        var map = {
            success: { icon: 'bi-check-circle-fill', badge: 'rxn-confirm-badge-success', title: 'Confirmar acción' },
            warning: { icon: 'bi-exclamation-triangle-fill', badge: 'rxn-confirm-badge-warning', title: 'Confirmar acción' },
            danger: { icon: 'bi-x-circle-fill', badge: 'rxn-confirm-badge-danger', title: 'Confirmar acción' },
            info: { icon: 'bi-info-circle-fill', badge: 'rxn-confirm-badge-info', title: 'Confirmar acción' }
        };

        return map[normalized] || map.warning;
    }

    function ensureModal() {
        if (modalEl) {
            return modalEl;
        }

        modalEl = document.createElement('div');
        modalEl.className = 'modal fade rxn-confirm-modal';
        modalEl.id = 'rxnConfirmModal';
        modalEl.tabIndex = -1;
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.innerHTML = '' +
            '<div class="modal-dialog modal-dialog-centered">' +
            '  <div class="modal-content">' +
            '    <div class="modal-body p-4">' +
            '      <div class="d-flex align-items-start gap-3">' +
            '        <div class="rxn-confirm-badge" data-confirm-badge><i class="bi"></i></div>' +
            '        <div class="flex-grow-1">' +
            '          <h5 class="modal-title fw-bold mb-2" data-confirm-title>Confirmar acción</h5>' +
            '          <p class="mb-0 text-muted" data-confirm-message></p>' +
            '        </div>' +
            '      </div>' +
            '    </div>' +
            '    <div class="modal-footer px-4 pb-4 pt-0">' +
            '      <button type="button" class="btn btn-outline-secondary" data-confirm-cancel>Cancelar</button>' +
            '      <button type="button" class="btn btn-primary" data-confirm-ok>Aceptar</button>' +
            '    </div>' +
            '  </div>' +
            '</div>';

        document.body.appendChild(modalEl);
        modalInstance = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: true });

        modalEl.querySelector('[data-confirm-cancel]').addEventListener('click', function () {
            modalInstance.hide();
        });

        modalEl.querySelector('[data-confirm-ok]').addEventListener('click', function () {
            if (typeof currentAction === 'function') {
                currentAction();
            }
            modalInstance.hide();
        });

        return modalEl;
    }

    function openConfirm(options) {
        var modal = ensureModal();
        var config = getTypeConfig(options.type);

        modal.querySelector('[data-confirm-badge]').className = 'rxn-confirm-badge ' + config.badge;
        modal.querySelector('[data-confirm-badge] i').className = 'bi ' + config.icon;
        modal.querySelector('[data-confirm-title]').textContent = options.title || config.title;
        modal.querySelector('[data-confirm-message]').textContent = options.message || '¿Confirmar esta acción?';
        modal.querySelector('[data-confirm-ok]').className = 'btn ' + (options.okClass || 'btn-primary');
        modal.querySelector('[data-confirm-ok]').textContent = options.okText || 'Aceptar';
        modal.querySelector('[data-confirm-cancel]').textContent = options.cancelText || 'Cancelar';

        currentAction = typeof options.onConfirm === 'function' ? options.onConfirm : null;
        modalInstance.show();
    }

    function findConfirmTarget(target) {
        return target && target.closest('[data-rxn-confirm]');
    }

    document.addEventListener('click', function (event) {
        var target = findConfirmTarget(event.target);
        if (!target) {
            return;
        }

        var href = target.getAttribute('href');
        var message = target.getAttribute('data-rxn-confirm') || target.getAttribute('data-confirm-message') || '¿Confirmar esta acción?';
        var type = target.getAttribute('data-confirm-type') || 'warning';
        var okText = target.getAttribute('data-confirm-ok-text') || target.textContent.trim() || 'Aceptar';
        var okClass = target.getAttribute('data-confirm-ok-class') || 'btn-primary';

        if (target.tagName === 'A') {
            event.preventDefault();
            openConfirm({
                type: type,
                message: message,
                okText: okText,
                okClass: okClass,
                onConfirm: function () {
                    window.location.href = href;
                }
            });
            return;
        }

        var form = target.closest('form');
        if (form) {
            event.preventDefault();
            openConfirm({
                type: type,
                message: message,
                okText: okText,
                okClass: okClass,
                onConfirm: function () {
                    if (target.getAttribute('type') === 'submit') {
                        if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit(target);
                        } else {
                            form.submit();
                        }
                        return;
                    }
                    form.submit();
                }
            });
        }
    }, true);
}());
