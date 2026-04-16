/**
 * rxn-datetime.js — Wrapper de Flatpickr para forzar formato 24hs en toda la app.
 *
 * Problema: <input type="datetime-local"> usa el locale del SO del usuario, lo que
 * hace que en Windows en inglés aparezca AM/PM. Eso rompe la UX de cálculos
 * horarios del CRM. Esta capa reemplaza el picker nativo por Flatpickr con
 * config fija: 24hs, con segundos, formato "Y-m-d H:i:S".
 *
 * Uso:
 *   - Auto-inicializa en DOMContentLoaded sobre todos los input[type="datetime-local"].
 *   - Para inputs agregados dinámicamente: RxnDateTime.initAll(containerEl).
 *   - Para setear valor programáticamente (sin romper la sync con el picker):
 *       RxnDateTime.setValue(inputEl, 'YYYY-MM-DD HH:MM:SS')
 */
(function () {
    'use strict';

    if (typeof flatpickr === 'undefined') {
        // Flatpickr no cargó (offline / CDN bloqueado). Dejamos el input nativo.
        window.RxnDateTime = {
            init: function () {},
            initAll: function () {},
            setValue: function (input, value) {
                if (!input) return;
                input.value = value;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        };
        return;
    }

    var locale = (flatpickr.l10ns && flatpickr.l10ns.es) ? flatpickr.l10ns.es : 'default';

    var datetimeConfig = {
        enableTime: true,
        time_24hr: true,
        enableSeconds: true,
        dateFormat: 'Y-m-d H:i:S',
        allowInput: true,
        locale: locale,
        onChange: function (selectedDates, dateStr, instance) {
            // Mantener compat con handlers legacy que escuchan 'input'.
            instance.input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };

    var dateConfig = {
        dateFormat: 'Y-m-d',
        allowInput: true,
        locale: locale,
        onChange: function (selectedDates, dateStr, instance) {
            instance.input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };

    function init(el) {
        if (!el || el._flatpickr) return;
        if (el.type === 'date') {
            flatpickr(el, dateConfig);
        } else {
            flatpickr(el, datetimeConfig);
        }
    }

    function initAll(root) {
        var scope = root || document;
        scope.querySelectorAll('input[type="datetime-local"]').forEach(init);
    }

    function setValue(input, value) {
        if (!input) return;
        if (input._flatpickr) {
            input._flatpickr.setDate(value, true);
        } else {
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initAll(document);
    });

    window.RxnDateTime = {
        init: init,
        initAll: initAll,
        setValue: setValue
    };
})();
