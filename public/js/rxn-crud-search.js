(function () {
    function setupCrudSearch(form) {
        var input = form.querySelector('[data-search-input]');
        var hiddenSearch = form.querySelector('[data-search-hidden]');
        var field = form.querySelector('[data-search-field]');
        var suggestions = form.querySelector('[data-search-suggestions]');
        var suggestionItems = [];
        var activeIndex = -1;
        var requestToken = 0;

        if (!input || !hiddenSearch || !suggestions) {
            return;
        }

        function closeSuggestions() {
            suggestions.innerHTML = '';
            suggestions.classList.add('d-none');
            suggestionItems = [];
            activeIndex = -1;
        }

        function syncCommittedSearch() {
            hiddenSearch.value = input.value;
        }

        function applySuggestion(item, shouldSubmit) {
            input.value = item.value || '';
            syncCommittedSearch();
            closeSuggestions();

            if (shouldSubmit) {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
                return;
            }

            input.focus();
        }

        function setActiveSuggestion(nextIndex) {
            if (!suggestionItems.length) {
                activeIndex = -1;
                return;
            }

            if (nextIndex < 0) {
                nextIndex = suggestionItems.length - 1;
            }
            if (nextIndex >= suggestionItems.length) {
                nextIndex = 0;
            }

            activeIndex = nextIndex;
            suggestionItems.forEach(function (element, index) {
                element.classList.toggle('is-active', index === activeIndex);
            });
        }

        function renderSuggestions(items) {
            closeSuggestions();

            if (!Array.isArray(items) || !items.length) {
                return;
            }

            items.forEach(function (item, index) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'rxn-search-suggestion';
                button.innerHTML = '<strong>' + (item.label || item.value || 'Sin titulo') + '</strong>'
                    + '<small class="text-muted">' + (item.caption || 'Sin datos extra') + '</small>';
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    applySuggestion(item, true);
                });
                button.addEventListener('mouseenter', function () {
                    setActiveSuggestion(index);
                });
                suggestions.appendChild(button);
                suggestionItems.push(button);
            });

            suggestions.classList.remove('d-none');
        }

        async function loadSuggestions() {
            var term = input.value.trim();
            var url = input.getAttribute('data-suggestions-url');
            var token = ++requestToken;

            if (!url || term.length < 2) {
                closeSuggestions();
                return;
            }

            try {
                var query = '?q=' + encodeURIComponent(term);
                if (field && field.value) {
                    query += '&field=' + encodeURIComponent(field.value);
                }

                var response = await fetch(url + query, {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                var payload = await response.json();

                if (token !== requestToken) {
                    return;
                }

                if (!payload.success) {
                    closeSuggestions();
                    return;
                }

                renderSuggestions(payload.data || []);
            } catch (error) {
                if (token === requestToken) {
                    closeSuggestions();
                }
            }
        }

        input.addEventListener('input', loadSuggestions);

        input.addEventListener('keydown', function (event) {
            if (!suggestionItems.length) {
                if (event.key === 'Escape') {
                    closeSuggestions();
                }
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActiveSuggestion(activeIndex + 1);
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActiveSuggestion(activeIndex - 1);
            }

            if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                suggestionItems[activeIndex].dispatchEvent(new MouseEvent('mousedown'));
                return;
            }

            if (event.key === 'Escape') {
                closeSuggestions();
            }
        });

        form.addEventListener('submit', function () {
            syncCommittedSearch();
            closeSuggestions();
        });

        if (field) {
            field.addEventListener('change', function () {
                closeSuggestions();
            });
        }

        document.addEventListener('click', function (event) {
            if (!form.contains(event.target)) {
                closeSuggestions();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-search-form]').forEach(setupCrudSearch);
    });
}());
