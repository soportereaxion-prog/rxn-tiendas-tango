(function () {
    function setupPicker(root) {
        var input = root.querySelector('[data-picker-input]');
        var hidden = root.querySelector('[data-picker-hidden]');
        var results = root.querySelector('[data-picker-results]');
        var meta = root.parentElement.querySelector('[data-picker-meta]');
        var allowManual = root.getAttribute('data-picker-allow-manual') === '1';
        var requestToken = 0;
        var activeIndex = -1;
        var items = [];

        if (!input || !results || !hidden) {
            return;
        }

        function closeResults() {
            results.innerHTML = '';
            results.classList.add('d-none');
            items = [];
            activeIndex = -1;
        }

        function updateMeta(message) {
            if (meta) {
                meta.textContent = message || '';
            }
        }

        function clearSelection() {
            if (!allowManual) {
                hidden.value = '';
            }
            updateMeta(allowManual ? 'Valor manual habilitado.' : 'Selecciona un valor valido desde las sugerencias.');
        }

        function applyItem(item) {
            input.value = item.value || item.label || '';
            hidden.value = item.id || item.value || '';
            updateMeta(item.caption || 'Seleccionado');
            closeResults();
        }

        function setActive(index) {
            if (!items.length) {
                activeIndex = -1;
                return;
            }

            if (index < 0) {
                index = items.length - 1;
            }
            if (index >= items.length) {
                index = 0;
            }

            activeIndex = index;
            items.forEach(function (button, idx) {
                button.classList.toggle('is-active', idx === activeIndex);
            });
        }

        function render(payload) {
            closeResults();

            if (!Array.isArray(payload) || !payload.length) {
                return;
            }

            payload.forEach(function (item, index) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'rxn-search-suggestion';
                button.innerHTML = '<strong>' + (item.label || item.value || 'Sin titulo') + '</strong>'
                    + '<small class="text-muted">' + (item.caption || 'Sin datos extra') + '</small>';
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    applyItem(item);
                });
                button.addEventListener('mouseenter', function () {
                    setActive(index);
                });
                results.appendChild(button);
                items.push(button);
            });

            results.classList.remove('d-none');
        }

        async function load() {
            var url = root.getAttribute('data-picker-url');
            var term = input.value.trim();
            var token = ++requestToken;

            if (!url || term.length < 2) {
                closeResults();
                if (term === '') {
                    clearSelection();
                }
                return;
            }

            try {
                var response = await fetch(url + '?q=' + encodeURIComponent(term), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                var payload = await response.json();

                if (token !== requestToken || !payload.success) {
                    return;
                }

                render(payload.data || []);
            } catch (error) {
                if (token === requestToken) {
                    closeResults();
                }
            }
        }

        input.addEventListener('input', function () {
            if (!allowManual) {
                hidden.value = '';
            }
            load();
        });

        input.addEventListener('keydown', function (event) {
            if (!items.length) {
                if (event.key === 'Escape') {
                    closeResults();
                }
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActive(activeIndex + 1);
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActive(activeIndex - 1);
            }

            if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                items[activeIndex].dispatchEvent(new MouseEvent('mousedown'));
            }

            if (event.key === 'Escape') {
                closeResults();
            }
        });

        document.addEventListener('click', function (event) {
            if (!root.contains(event.target)) {
                closeResults();
            }
        });

        if (allowManual && input.value.trim() !== '') {
            hidden.value = input.value.trim();
        }
    }

    function parseDateTime(value) {
        if (!value) {
            return null;
        }

        var normalized = value.replace(' ', 'T');
        var date = new Date(normalized);
        return Number.isNaN(date.getTime()) ? null : date;
    }

    function parseDuration(value) {
        if (!value) {
            return 0;
        }

        var match = /^(\d{2}):(\d{2}):(\d{2})$/.exec(value.trim());
        if (!match) {
            return null;
        }

        var hours = Number(match[1]);
        var minutes = Number(match[2]);
        var seconds = Number(match[3]);
        if (minutes > 59 || seconds > 59) {
            return null;
        }

        return (hours * 3600) + (minutes * 60) + seconds;
    }

    function formatDuration(totalSeconds) {
        if (totalSeconds === null || totalSeconds === undefined || totalSeconds < 0) {
            return '--:--:--';
        }

        var hours = Math.floor(totalSeconds / 3600);
        var minutes = Math.floor((totalSeconds % 3600) / 60);
        var seconds = totalSeconds % 60;

        return [hours, minutes, seconds].map(function (value) {
            return String(value).padStart(2, '0');
        }).join(':');
    }

    function setupCalculator() {
        var start = document.querySelector('[data-calc-start]');
        var end = document.querySelector('[data-calc-end]');
        var discount = document.querySelector('[data-calc-discount]');
        var gross = document.querySelector('[data-calc-gross]');
        var net = document.querySelector('[data-calc-net]');
        var discountPreview = document.querySelector('[data-calc-discount-preview]');
        var sideNet = document.querySelector('[data-calc-side-net]');

        if (!start || !end || !discount || !gross || !net || !discountPreview || !sideNet) {
            return;
        }

        function recalc() {
            var startDate = parseDateTime(start.value);
            var endDate = parseDateTime(end.value);
            var discountSeconds = parseDuration(discount.value);

            discountPreview.textContent = discount.value && discountSeconds !== null ? discount.value : '--:--:--';

            if (!startDate || !endDate || discountSeconds === null || endDate < startDate) {
                gross.textContent = '--:--:--';
                net.textContent = '--:--:--';
                sideNet.textContent = '--:--:--';
                return;
            }

            var grossSeconds = Math.floor((endDate.getTime() - startDate.getTime()) / 1000);
            var netSeconds = grossSeconds - discountSeconds;

            gross.textContent = formatDuration(grossSeconds);
            net.textContent = netSeconds >= 0 ? formatDuration(netSeconds) : '--:--:--';
            sideNet.textContent = net.textContent;
        }

        start.addEventListener('input', recalc);
        end.addEventListener('input', recalc);
        discount.addEventListener('input', recalc);
        recalc();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-picker]').forEach(setupPicker);
        setupCalculator();
    });
}());
