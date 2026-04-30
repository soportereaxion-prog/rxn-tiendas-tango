/**
 * rxn-draft-autosave — autoguardado debounced de borradores de formularios.
 *
 * Uso: cualquier <form data-rxn-draft="modulo:ref"> queda autoguardado.
 *  - "modulo" debe estar en la whitelist de DraftsController (pds, presupuesto).
 *  - "ref" es 'new' para creación o el id del registro al editar.
 *
 * Comportamiento:
 *  1) Al cargar el form: GET /api/internal/drafts/get → si hay borrador,
 *     mostrar banner con [Retomar] [Descartar].
 *  2) Cada cambio: debounce 5s → POST /api/internal/drafts/save con todos los
 *     pares clave/valor del form serializados a JSON.
 *  3) Al submit válido del form: POST /api/internal/drafts/discard.
 *
 * Opt-in: indicador "no guardado" + hotkey Ctrl+S
 *  Si la vista incluye un <span data-rxn-draft-status> dentro del form (o como
 *  hermano), el JS lo pinta con 3 estados semánticos:
 *      🟢 clean         — el form está igual que cuando se cargó (en DB).
 *      🟡 draft-only    — hay cambios y están persistidos como draft (red de seguridad activa)
 *                         pero todavía no fueron submitidos al registro real.
 *      🔴 dirty-unsynced — hay cambios y el último save al server falló o todavía
 *                         no salió (debounce pendiente).
 *  Adicionalmente registra Ctrl+S = Submit del form. Solo se activa si el form
 *  tiene el slot data-rxn-draft-status presente — los forms que solo usan el
 *  autosave básico (PDS hoy) no se ven afectados.
 *
 * No se autoguardan archivos (input file) — el server no los recibiría desde
 * un POST JSON. Tampoco passwords (por seguridad). El resto sí.
 */
(function () {
    'use strict';

    const SAVE_URL = '/api/internal/drafts/save';
    const GET_URL = '/api/internal/drafts/get';
    const DISCARD_URL = '/api/internal/drafts/discard';
    const DEBOUNCE_MS = 5000;

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function parseDraftKey(form) {
        const raw = form.getAttribute('data-rxn-draft') || '';
        const idx = raw.indexOf(':');
        if (idx < 0) return null;
        const modulo = raw.slice(0, idx).trim();
        const ref = raw.slice(idx + 1).trim() || 'new';
        if (!modulo) return null;
        return { modulo, ref };
    }

    function serializeForm(form) {
        const data = {};
        const elements = form.querySelectorAll('input, textarea, select');
        elements.forEach((el) => {
            if (!el.name) return;
            if (el.disabled) return;
            const type = (el.type || '').toLowerCase();
            if (type === 'file' || type === 'password') return;
            if (type === 'checkbox' || type === 'radio') {
                if (!el.checked) return;
            }
            // Soporte arrays (name="capturas[]").
            if (el.name.endsWith('[]')) {
                if (!Array.isArray(data[el.name])) data[el.name] = [];
                data[el.name].push(el.value);
            } else {
                data[el.name] = el.value;
            }
        });
        return data;
    }

    function applyPayload(form, payload) {
        if (!payload || typeof payload !== 'object') return;
        const elements = form.querySelectorAll('input, textarea, select');
        elements.forEach((el) => {
            if (!el.name) return;
            const type = (el.type || '').toLowerCase();
            if (type === 'file' || type === 'password') return;

            if (el.name.endsWith('[]')) {
                // Para arrays: se restauran solo si el form los expone como inputs idénticos.
                // No es el caso del PDS (las capturas son hidden generadas dinámicamente),
                // así que las dejamos pasar y que el flujo normal de la vista las regenere.
                return;
            }

            const value = payload[el.name];
            if (value === undefined || value === null) return;

            if (type === 'checkbox' || type === 'radio') {
                if (String(el.value) === String(value)) el.checked = true;
                return;
            }
            if (type === 'datetime-local' && window.RxnDateTime && typeof window.RxnDateTime.setValue === 'function') {
                window.RxnDateTime.setValue(el, String(value));
            } else {
                el.value = String(value);
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    function showResumeBanner(form, draft, onResume, onDiscard) {
        const banner = document.createElement('div');
        banner.className = 'alert alert-info d-flex align-items-center justify-content-between gap-3 shadow-sm mb-3';
        banner.setAttribute('data-rxn-draft-banner', '1');
        const updated = draft.updated_at || '';
        banner.innerHTML = `
            <div>
                <i class="bi bi-arrow-counterclockwise me-1"></i>
                Tenés un borrador autoguardado del <strong>${updated}</strong>. ¿Lo retomamos?
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-rxn-draft-action="discard">Descartar</button>
                <button type="button" class="btn btn-sm btn-primary" data-rxn-draft-action="resume">Retomar</button>
            </div>
        `;
        form.parentNode.insertBefore(banner, form);
        banner.querySelector('[data-rxn-draft-action="resume"]').addEventListener('click', () => {
            onResume();
            banner.remove();
        });
        banner.querySelector('[data-rxn-draft-action="discard"]').addEventListener('click', () => {
            onDiscard();
            banner.remove();
        });
    }

    function buildSavePayload(modulo, ref, payload) {
        const params = new URLSearchParams();
        params.append('csrf_token', csrfToken());
        params.append('modulo', modulo);
        params.append('ref', ref);
        params.append('payload', JSON.stringify(payload));
        return params;
    }

    function buildSimpleParams(modulo, ref) {
        const params = new URLSearchParams();
        params.append('csrf_token', csrfToken());
        params.append('modulo', modulo);
        params.append('ref', ref);
        return params;
    }

    /**
     * Busca el slot del badge de status. Acepta cualquiera que pertenezca al
     * mismo "scope" del form (parent containers comunes). Retorna null si no
     * existe — en ese caso el feature de status + Ctrl+S queda desactivado.
     */
    function findStatusSlot(form) {
        // Primero buscar dentro del form.
        let slot = form.querySelector('[data-rxn-draft-status]');
        if (slot) return slot;
        // Si no, buscar en todo el documento. El uso esperado es "uno por página".
        return document.querySelector('[data-rxn-draft-status]');
    }

    function paintStatusSlot(slot, state, savedAt) {
        if (!slot) return;
        const cfg = STATUS_LABELS[state] || STATUS_LABELS.clean;
        let label = cfg.label;
        if (state === 'draft-only' && savedAt) {
            const hhmm = savedAt.slice(11, 16); // 'YYYY-MM-DD HH:MM:SS' → 'HH:MM'
            label = cfg.label.replace('{time}', hhmm);
        }
        slot.innerHTML = `<i class="bi ${cfg.icon} me-1"></i>${label}`;
        slot.className = `badge ${cfg.cls} align-middle ms-1 rxn-draft-status`;
        slot.setAttribute('title', cfg.title);
        slot.setAttribute('data-rxn-draft-status-state', state);
    }

    const STATUS_LABELS = {
        'clean': {
            label: 'Sin cambios',
            icon: 'bi-check-circle-fill',
            cls: 'bg-success-subtle text-success-emphasis',
            title: 'No hay cambios pendientes desde la última vez que guardaste',
        },
        'draft-only': {
            label: 'Borrador autoguardado {time} · falta Guardar',
            icon: 'bi-cloud-check-fill',
            cls: 'bg-warning-subtle text-warning-emphasis',
            title: 'Tus cambios están autoguardados como borrador en el server. Tocá Guardar para impactarlos al registro real.',
        },
        'dirty-unsynced': {
            label: 'Cambios sin guardar',
            icon: 'bi-exclamation-triangle-fill',
            cls: 'bg-danger-subtle text-danger-emphasis',
            title: 'Hay cambios que todavía no llegaron al server. Esperá unos segundos o tocá Guardar.',
        },
        'error': {
            label: 'Error al autoguardar',
            icon: 'bi-x-circle-fill',
            cls: 'bg-danger-subtle text-danger-emphasis',
            title: 'No pudimos guardar el borrador. Reintenta automáticamente al próximo cambio.',
        },
    };

    function setupForm(form) {
        const key = parseDraftKey(form);
        if (!key) return;

        let lastSavedJson = '';     // Última versión persistida exitosamente como draft.
        let baselineJson = '';      // Versión cuando se cargó el form (lo que está en DB).
        let lastObservedJson = '';  // Última serialización observada (para detectar cambios silenciosos).
        let debounceTimer = null;
        let lastSavedAt = '';
        let currentState = 'clean';

        const statusSlot = findStatusSlot(form);
        const hasStatusFeatures = statusSlot !== null;

        // Baseline = serialización INICIAL del form en setupForm (DOMContentLoaded).
        // Se captura inmediato, antes de que cualquier JS del módulo prepopule
        // valores via `el.value = X`. De esta forma:
        //  - En modo EDIT con datos del server: baseline === datos en DB → 🟢 al cargar.
        //  - En modo CREATE limpio: baseline === form vacío → 🟢 al cargar.
        //  - En modo CREATE post-copia / nueva versión: el server ya pintó los datos
        //    en el HTML inicial → baseline incluye esos datos. Si el operador no toca
        //    nada, queda 🟢. Si toca algo, pasa a 🔴/🟡.
        //  - Si el JS del módulo agrega inputs DESPUÉS del baseline (ej: applyClientContext
        //    al elegir cliente, appendItem al cargar renglón), el poll de abajo lo detecta.
        function captureBaseline() {
            baselineJson = JSON.stringify(serializeForm(form));
            lastObservedJson = baselineJson;
            updateState();
        }

        function dispatchState(state, savedAt) {
            currentState = state;
            if (savedAt) lastSavedAt = savedAt;
            paintStatusSlot(statusSlot, state, lastSavedAt);
            form.dispatchEvent(new CustomEvent('rxn-draft-state', {
                bubbles: true,
                detail: { state, savedAt: lastSavedAt, modulo: key.modulo, ref: key.ref },
            }));
        }

        function updateState() {
            const currentJson = JSON.stringify(serializeForm(form));
            if (currentJson === baselineJson) {
                dispatchState('clean');
            } else if (currentJson === lastSavedJson) {
                dispatchState('draft-only');
            } else {
                dispatchState('dirty-unsynced');
            }
        }

        // 1) Pedir draft existente al server.
        const qs = new URLSearchParams({ modulo: key.modulo, ref: key.ref });
        fetch(GET_URL + '?' + qs.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
        }).then((res) => res.ok ? res.json() : null)
          .then((data) => {
              if (!data || !data.ok || !data.draft || !data.draft.payload) return;
              showResumeBanner(form, data.draft, () => {
                  applyPayload(form, data.draft.payload);
                  // Tras retomar, lo aplicado YA está en el server como draft.
                  // El form está "draft-only" respecto al baseline.
                  lastSavedJson = JSON.stringify(serializeForm(form));
                  lastSavedAt = data.draft.updated_at || lastSavedAt;
                  updateState();
              }, () => {
                  fetch(DISCARD_URL, {
                      method: 'POST',
                      credentials: 'same-origin',
                      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                      body: buildSimpleParams(key.modulo, key.ref).toString(),
                  });
              });
          })
          .catch(() => {});

        // 2) Listeners de cambio → autoguardado + estado en vivo.
        const trigger = () => {
            lastObservedJson = JSON.stringify(serializeForm(form));
            // Update inmediato del estado (sin esperar al debounce) para reflejar
            // dirty-unsynced apenas el operador escribe.
            updateState();

            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const payload = serializeForm(form);
                const json = JSON.stringify(payload);
                if (json === lastSavedJson) return;
                fetch(SAVE_URL, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: buildSavePayload(key.modulo, key.ref, payload).toString(),
                }).then((res) => res.ok ? res.json() : null)
                  .then((data) => {
                      if (data && data.ok) {
                          lastSavedJson = json;
                          lastSavedAt = data.saved_at || '';
                          updateState();
                      } else {
                          dispatchState('error');
                      }
                  })
                  .catch(() => {
                      dispatchState('error');
                  });
            }, DEBOUNCE_MS);
        };

        form.addEventListener('input', trigger);
        form.addEventListener('change', trigger);

        // Poll de fallback: muchos módulos modifican valores via `el.value = X`
        // sin disparar `input`/`change` (ej: applyClientContext, appendItem en
        // CrmPresupuestos). Sin este poll, esos cambios silenciosos NO disparan
        // el autosave y el draft nunca se persiste. Cada 2s comparamos la
        // serialización con la última observada — si difiere, disparamos
        // trigger() como si hubiera sido un evento real. Costo: ~1ms cada 2s.
        setInterval(() => {
            const currentJson = JSON.stringify(serializeForm(form));
            if (currentJson !== lastObservedJson) {
                trigger();
            }
        }, 2000);

        // 3) Al submit: descartar el draft.
        form.addEventListener('submit', () => {
            navigator.sendBeacon
                ? navigator.sendBeacon(
                    DISCARD_URL,
                    new Blob([buildSimpleParams(key.modulo, key.ref).toString()],
                        { type: 'application/x-www-form-urlencoded' })
                  )
                : fetch(DISCARD_URL, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: buildSimpleParams(key.modulo, key.ref).toString(),
                    keepalive: true,
                  }).catch(() => {});
        });

        // 4) Hotkey Ctrl+S = Submit del form (opt-in via slot del status).
        if (hasStatusFeatures && window.RxnShortcuts && typeof window.RxnShortcuts.register === 'function') {
            window.RxnShortcuts.register({
                id: 'draft-' + key.modulo + '-submit',
                keys: ['Ctrl+S', 'Meta+S'],
                description: 'Guardar el formulario',
                group: 'Formulario',
                scope: 'global',
                when: () => document.body.contains(form),
                action: (e) => {
                    e.preventDefault();
                    // Si form.requestSubmit existe, lo usamos para que dispare
                    // las validaciones HTML5 antes (no las usa el form pero
                    // queda más natural). Si no, submit() raw.
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                },
            });
        }

        // Capturar baseline INMEDIATO. Cualquier mutación posterior del JS del
        // módulo (applyClientContext, appendItem, etc) la detecta el poll y
        // arma el debounce de save.
        captureBaseline();
    }

    function init() {
        document.querySelectorAll('form[data-rxn-draft]').forEach(setupForm);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.RxnDraftAutosave = { init };
})();
