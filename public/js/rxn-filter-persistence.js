/**
 * rxn-filter-persistence.js
 * ----------------------------------------------------------------
 * Persistencia global de filtros de listado via localStorage.
 *
 * Alcance: todos los listados con [data-search-input] y/o columnas
 * con [data-filter-field]. Scope: por pathname + empresa_id, así
 * cada listado del sistema recuerda sus filtros de forma aislada.
 *
 * Qué persiste (ver FILTER_KEYS más abajo):
 *   - search        → input de búsqueda F3
 *   - field         → select del campo donde buscar (all / id / nombre / ...)
 *   - limit         → cantidad de registros por página (25 / 50 / 100)
 *   - estado        → filtro de estado de negocio (abierto / enviado / ...)
 *   - categoria_id  → filtro de categoría (solo Articulos Tiendas)
 *   - sort / dir    → ordenamiento por columna (release 1.29.x — antes no
 *                     se persistía pero Charly lo pidió, es lo que el operador
 *                     espera al volver al listado).
 *   - f[<campo>][op|val] → filtros Motor BD por columna
 *
 * Qué NO persiste por diseño:
 *   - status → activos vs papelera (es navegación semántica)
 *   - area → contexto tiendas/crm (se hereda por pathname)
 *   - page → paginación (se reinicia en 1 al restaurar)
 *   - Los filtros "locales" (selección por columna) ya viven en
 *     sessionStorage via rxn-advanced-filters.js con key rxn_lf::
 *
 * Cómo funciona:
 *   1. Si la URL actual trae algun filtro, se pisan los persistidos para
 *      ese path.
 *   2. Si la URL actual NO trae filtros y hay algo guardado, redirigimos
 *      con los params restaurados (sin `page`, para arrancar en 1).
 *   3. Si llega ?reset_filters=1 (lo envia rxn-advanced-filters.js cuando
 *      se borran todos los filtros BD), limpiamos los f[...] del storage
 *      y recargamos la URL limpia.
 *
 * Cómo ampliar: si un módulo agrega un nuevo select de filtro con name=X,
 * sumá 'X' al array FILTER_KEYS de abajo. El resto funciona solo.
 *
 * Se ejecuta inline desde <head> para evitar flash. No depende del DOM.
 * ----------------------------------------------------------------
 */
(function () {
    'use strict';

    function empresaScopeSuffix() {
        try {
            var m = document.querySelector('meta[name="rxn-empresa-id"]');
            return (m && m.content) ? '::e' + m.content : '';
        } catch (e) {
            return '';
        }
    }

    function buildStorageKey(path) {
        var p = (path || window.location.pathname).replace(/\/+$/, '') || '/';
        return 'rxn_filters::' + p + empresaScopeSuffix();
    }

    // --- API pública: siempre expuesta, incluso si el bloque de redirect
    //     aborta por un return temprano ---------------------------------
    window.rxnFilterPersistence = {
        clear: function (path) {
            try { window.localStorage.removeItem(buildStorageKey(path)); } catch (e) {}
        },
        clearAll: function () {
            try {
                var keys = [];
                for (var i = 0; i < window.localStorage.length; i++) {
                    var k = window.localStorage.key(i);
                    if (k && k.indexOf('rxn_filters::') === 0) keys.push(k);
                }
                keys.forEach(function (k) { window.localStorage.removeItem(k); });
            } catch (e) {}
        }
    };

    try {
        if (typeof window === 'undefined' || !window.localStorage) {
            return;
        }

        var pathname = window.location.pathname.replace(/\/+$/, '') || '/';

        // --- Paths excluidos: módulos que gestionan filtros por su cuenta ---
        // Listar acá módulos con tabs AJAX y estado interno propio (JS outer
        // scope) para evitar que la persistencia global les pise los filtros.
        var EXCLUDED_PATH_PREFIXES = [
            '/mi-empresa/rxn-sync',
            '/mi-empresa/crm/rxn-sync'
        ];
        for (var i = 0; i < EXCLUDED_PATH_PREFIXES.length; i++) {
            var ex = EXCLUDED_PATH_PREFIXES[i];
            if (pathname === ex || pathname.indexOf(ex + '/') === 0) {
                return;
            }
        }

        var storageKey = buildStorageKey();
        var params = new URLSearchParams(window.location.search);

        // Lista simple de filter keys. Ampliar si un módulo suma un select nuevo.
        var FILTER_KEYS = ['search', 'field', 'limit', 'estado', 'categoria_id', 'sort', 'dir'];

        function isFilterKey(key) {
            if (key.indexOf('f[') === 0) return true;
            for (var k = 0; k < FILTER_KEYS.length; k++) {
                if (key === FILTER_KEYS[k]) return true;
            }
            return false;
        }

        function readSaved() {
            try {
                var raw = window.localStorage.getItem(storageKey);
                if (!raw) return null;
                var parsed = JSON.parse(raw);
                return (parsed && typeof parsed === 'object') ? parsed : null;
            } catch (e) {
                return null;
            }
        }

        function writeSaved(obj) {
            try {
                if (!obj || Object.keys(obj).length === 0) {
                    window.localStorage.removeItem(storageKey);
                } else {
                    window.localStorage.setItem(storageKey, JSON.stringify(obj));
                }
            } catch (e) {}
        }

        // --- Caso 1: reset explícito de filtros BD ----------------------
        if (params.has('reset_filters')) {
            var saved = readSaved();
            if (saved) {
                var cleaned = {};
                Object.keys(saved).forEach(function (k) {
                    if (k.indexOf('f[') !== 0) {
                        cleaned[k] = saved[k];
                    }
                });
                writeSaved(cleaned);
            }
            params.delete('reset_filters');
            var remaining = params.toString();
            var cleanUrl = pathname + (remaining ? '?' + remaining : '');
            window.location.replace(cleanUrl);
            return;
        }

        // --- Caso 2: la URL actual ya trae filtros -> guardar -----------
        // Detectamos si la URL mencionó keys de filtro (aunque vengan vacías),
        // porque eso significa que el usuario acaba de submitear un filtro
        // (posiblemente vacío para limpiar). En ese caso, pisamos el storage
        // con los valores actuales no vacíos.
        var urlMentionedFilterKey = false;
        var toSave = {};
        params.forEach(function (value, key) {
            if (!isFilterKey(key)) return;
            urlMentionedFilterKey = true;
            if (value !== '' && value !== null && value !== undefined) {
                toSave[key] = value;
            }
        });

        if (urlMentionedFilterKey) {
            writeSaved(toSave);
            return;
        }

        // --- Caso 3: URL sin filtros -> intentar restaurar --------------
        var stored = readSaved();
        if (!stored || Object.keys(stored).length === 0) {
            return;
        }

        var restored = new URLSearchParams(window.location.search);
        // Al restaurar filtros, arrancamos siempre desde la primera página
        restored.delete('page');
        Object.keys(stored).forEach(function (k) {
            restored.set(k, stored[k]);
        });

        var restoredQuery = restored.toString();
        if (!restoredQuery) {
            return;
        }

        window.location.replace(pathname + '?' + restoredQuery);
    } catch (e) {
        // Nunca bloquear el render si algo falla
        if (window.console && console.warn) {
            console.warn('rxn-filter-persistence: ', e);
        }
    }
})();
