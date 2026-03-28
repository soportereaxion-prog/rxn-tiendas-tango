document.addEventListener('DOMContentLoaded', function () {
    var rowSelector = '[data-row-link]';

    var isInteractiveTarget = function (target) {
        if (!target) {
            return false;
        }

        return Boolean(target.closest('a, button, input, select, textarea, label, summary, [data-row-link-ignore]'));
    };

    var navigateRow = function (row) {
        var href = row.getAttribute('data-row-link');
        if (!href) {
            return;
        }

        window.location.href = href;
    };

    document.querySelectorAll(rowSelector).forEach(function (row) {
        row.classList.add('rxn-row-link');

        if (!row.hasAttribute('tabindex')) {
            row.setAttribute('tabindex', '0');
        }

        row.setAttribute('role', 'link');

        row.addEventListener('click', function (event) {
            if (isInteractiveTarget(event.target)) {
                return;
            }

            navigateRow(row);
        });

        row.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            if (isInteractiveTarget(event.target)) {
                return;
            }

            event.preventDefault();
            navigateRow(row);
        });
    });
});
