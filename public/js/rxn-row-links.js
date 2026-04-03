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
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                var allRows = Array.from(document.querySelectorAll(rowSelector));
                var currentIndex = allRows.indexOf(row);
                var nextIndex = currentIndex + 1;
                if (nextIndex < allRows.length) {
                    allRows[nextIndex].focus();
                }
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                var allRows = Array.from(document.querySelectorAll(rowSelector));
                var currentIndex = allRows.indexOf(row);
                var prevIndex = currentIndex - 1;
                if (prevIndex >= 0) {
                    allRows[prevIndex].focus();
                } else {
                    var searchInput = document.querySelector('[data-search-input]');
                    if (searchInput) searchInput.focus();
                }
                return;
            }

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

    if (sessionStorage.getItem('rxn_focus_first_row') === '1') {
        sessionStorage.removeItem('rxn_focus_first_row');
        setTimeout(function() {
            var firstRow = document.querySelector(rowSelector);
            if (firstRow) {
                firstRow.focus();
            }
        }, 50);
    }
});
