(function () {
    function setupPicker(root) {
        var input = root.querySelector('[data-picker-input]');
        var hidden = root.querySelector('[data-picker-hidden]');
        var extraHidden = root.querySelector('[data-picker-extra-hidden]');
        var results = root.querySelector('[data-picker-results]');
        var meta = root.parentElement.querySelector('[data-picker-meta]');
        var allowManual = root.getAttribute('data-picker-allow-manual') === '1';
        var requestToken = 0;
        var activeIndex = -1;
        var items = [];
        var debounceTimer = null;

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
                if (extraHidden) {
                    extraHidden.value = '';
                }
            }
            updateMeta(allowManual ? 'Valor manual habilitado.' : 'Selecciona un valor valido desde las sugerencias.');
        }

        function applyItem(item) {
            input.value = item.value || item.label || '';
            hidden.value = item.id || item.value || '';
            if (extraHidden) {
                extraHidden.value = item.extraId || '';
            }
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
                if (idx === activeIndex) {
                    button.scrollIntoView({ block: 'nearest' });
                }
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
                button.tabIndex = -1; // No capturar Tab: el foco salta limpio al siguiente input del form
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

        async function load(forceEmpty = false) {
            var url = root.getAttribute('data-picker-url');
            var term = forceEmpty ? '' : input.value.trim();
            var token = ++requestToken;

            if (!url) {
                closeResults();
                return;
            }

            if (term === '' && !forceEmpty) {
                clearSelection();
            }

            try {
                var response = await fetch(url + '?q=' + encodeURIComponent(term), {
                    credentials: 'same-origin',
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
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
            debounceTimer = window.setTimeout(function () { load(false); }, 120);
        });

        input.addEventListener('click', function () {
            if (results.classList.contains('d-none')) {
                input.select();
                load(true);
            }
        });

        input.addEventListener('focus', function () {
            // Si el Spotlight Modal acaba de seleccionar un item y está restaurando el foco,
            // no re-abrimos la lista inline (el flag lo limpia el spotlight con setTimeout).
            if (root.dataset.suppressNextFocus === '1') {
                return;
            }
            if (results.classList.contains('d-none')) {
                input.select();
                load(true);
            }
        });

        input.addEventListener('keydown', function (event) {
            if (!items.length) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
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

            if (event.key === 'Enter') {
                event.preventDefault();
                if (activeIndex < 0) {
                    setActive(0);
                }
                items[activeIndex].dispatchEvent(new MouseEvent('mousedown'));
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                closeResults();
            }
        });

        document.addEventListener('click', function (event) {
            if (!root.contains(event.target)) {
                closeResults();
            }
        });

        // Escuchar el evento emitido por Spotlight Modal (compatibilidad con el flujo de Presupuestos)
        input.addEventListener('picker-selected', function (event) {
            applyItem(event.detail);
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
        var decimalOut = document.querySelector('[data-calc-decimal]');

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
            if (decimalOut) {
                decimalOut.textContent = netSeconds >= 0 ? (netSeconds / 3600).toFixed(4) : '0.0000';
            }
        }

        start.addEventListener('input', recalc);
        end.addEventListener('input', recalc);
        discount.addEventListener('input', recalc);
        recalc();
    }

    function setupCheckboxAhora() {
        var btn = document.getElementById('btn-finalizado-ahora');
        var endInput = document.getElementById('fecha_finalizado');
        if (!btn || !endInput) return;

        btn.addEventListener('click', function() {
            var now = new Date();
            var pad = function (n) { return String(n).padStart(2, '0'); };
            var localValue = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate())
                + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
            if (window.RxnDateTime && typeof window.RxnDateTime.setValue === 'function') {
                window.RxnDateTime.setValue(endInput, localValue);
            } else {
                endInput.value = localValue;
                endInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    }

    function setupImagePaste() {
        var textarea = document.getElementById('diagnostico');
        var container = document.getElementById('diagnostico-capturas');
        if (!textarea || !container) return;
        
        var adjuntosCounter = container.querySelectorAll('.diagnostico-adjunto').length;

        function appendThumbnail(base64Data, extension, filenameLabel) {
            var col = document.createElement('div');
            col.className = 'diagnostico-adjunto position-relative border rounded p-1 bg-white shadow-sm';
            col.style.width = '100px';
            col.style.height = '100px';
            
            var img = document.createElement('img');
            img.src = base64Data;
            img.className = 'w-100 h-100 object-fit-cover rounded';
            col.appendChild(img);
            
            var label = document.createElement('div');
            label.className = 'position-absolute bottom-0 start-0 end-0 bg-dark bg-opacity-75 text-white text-center rounded-bottom" style="font-size:0.6rem; padding: 0.1rem;';
            label.textContent = filenameLabel;
            col.appendChild(label);
            
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'capturas_diagnostico_base64[]';
            hidden.value = JSON.stringify({ data: base64Data, extension: extension, label: filenameLabel });
            col.appendChild(hidden);

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-danger btn-sm position-absolute top-0 end-0 p-0 rounded-circle d-flex align-items-center justify-content-center';
            removeBtn.style.width = '20px';
            removeBtn.style.height = '20px';
            removeBtn.style.transform = 'translate(50%, -50%)';
            removeBtn.innerHTML = '<i class="bi bi-x" style="font-size:0.8rem;"></i>';
            removeBtn.onclick = function() {
                col.remove();
            };
            col.appendChild(removeBtn);
            
            container.appendChild(col);
        }

        textarea.addEventListener('paste', function(event) {
            var items = (event.clipboardData || event.originalEvent.clipboardData).items;
            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                if (item.type && item.type.indexOf('image/') === 0) {
                    event.preventDefault();
                    var file = item.getAsFile();
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        adjuntosCounter++;
                        var ext = item.type.split('/')[1] || 'png';
                        var label = '#imagen' + adjuntosCounter;
                        
                        var selStart = textarea.selectionStart;
                        var selEnd = textarea.selectionEnd;
                        var val = textarea.value;
                        textarea.value = val.substring(0, selStart) + ' ' + label + ' ' + val.substring(selEnd);
                        textarea.selectionStart = textarea.selectionEnd = selStart + label.length + 2;
                        
                        appendThumbnail(e.target.result, ext, label);
                    };
                    reader.readAsDataURL(file);
                }
            }
        });
    }

    function setupDirtyCheckAndEmailControl() {
        var mainForm = document.getElementById('crm-pedido-servicio-form');
        var emailForms = document.querySelectorAll('form[action$="/enviar-correo"]');

        if (!mainForm) return;

        var isDirty = false;

        // Registrar cualquier cambio en inputs, selects, textareas
        mainForm.addEventListener('input', function() {
            isDirty = true;
        });
        mainForm.addEventListener('change', function() {
            isDirty = true;
        });

        // Interceptar el clic en el botón de enviar por correo ANTES de que se lance el confirm nativo
        if (emailForms.length > 0) {
            emailForms.forEach(function(form) {
                var btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    btn.addEventListener('click', function(e) {
                        if (isDirty) {
                            e.preventDefault();
                            e.stopImmediatePropagation();
                            (window.rxnAlert || alert)('Has modificado datos en este pedido.\n\nPor favor, hacé clic en el botón azul "Guardar" antes de enviarlo por correo para asegurarte de que el cliente reciba la información actualizada.', 'warning', 'Atención: Cambios sin guardar');
                        }
                    }, true); // Capture phase para ganar prioridad
                }
            });
        }

        // Interceptar Escape globalmente para mostrar modal de confirmación antes de salir.
        // Alineado con el comportamiento de crm-presupuestos-form.js: evita que Escape dispare
        // una salida accidental del formulario con cambios sin guardar.
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Ignorar si hay un modal Bootstrap activo (ej: un confirm, una alerta)
                if (document.querySelector('.modal.show')) return;
                // Ignorar si el Spotlight Modal está abierto (tiene su propio manejo de Escape)
                if (document.querySelector('.rxn-spotlight-dialog.show')) return;

                e.preventDefault();
                e.stopImmediatePropagation();
                if (window.rxnConfirm) {
                    window.rxnConfirm({
                        title: 'Confirmar salida',
                        message: '¿Querés salir del proceso sin guardar?',
                        type: 'warning',
                        okText: 'Sí, salir',
                        cancelText: 'Cancelar',
                        onConfirm: function() {
                            // Navegar directo al href del botón "Volver al listado"
                            var backBtn = document.querySelector('.rxn-module-actions a.btn-outline-secondary') ||
                                          document.querySelector('a.btn-outline-secondary[href*="/mi-empresa"]');
                            if (backBtn && backBtn.href) {
                                window.location.href = backBtn.href;
                            } else {
                                window.history.back();
                            }
                        }
                    });
                }
            }
        }, true);

        // Interceptar click en el botón "Volver al listado" con el mismo confirm que Escape.
        // Evita salidas accidentales cuando el usuario hizo cambios o está armando un PDS nuevo.
        const backBtn = document.querySelector('.rxn-module-actions a.btn-outline-secondary')
                     || document.querySelector('a.btn-outline-secondary[href*="/mi-empresa"]');
        if (backBtn) {
            backBtn.addEventListener('click', function(e) {
                if (document.querySelector('.modal.show')) return;
                e.preventDefault();
                e.stopImmediatePropagation();
                const href = backBtn.href;
                if (window.rxnConfirm) {
                    window.rxnConfirm({
                        title: 'Confirmar salida',
                        message: '¿Querés salir del proceso sin guardar?',
                        type: 'warning',
                        okText: 'Sí, salir',
                        cancelText: 'Cancelar',
                        onConfirm: function() {
                            window.location.href = href;
                        }
                    });
                } else {
                    window.location.href = href;
                }
            }, true);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-picker]').forEach(setupPicker);
        setupCalculator();
        setupCheckboxAhora();
        setupImagePaste();
        setupDirtyCheckAndEmailControl();
    });
}());
