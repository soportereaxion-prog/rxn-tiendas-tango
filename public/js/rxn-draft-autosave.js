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
 *     Si el server rechaza el guardado real, el draft quedaría descartado
 *     prematuramente — preferimos eso a guardar para siempre un draft que
 *     ya no aplica. El usuario ve el error inline y puede recargar (el draft
 *     anterior ya no está, pero el form mantiene los valores actuales).
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
            // datetime-local con wrapper Flatpickr: usar la API global si existe.
            if (type === 'datetime-local' && window.RxnDateTime && typeof window.RxnDateTime.setValue === 'function') {
                window.RxnDateTime.setValue(el, String(value));
            } else {
                el.value = String(value);
                // Disparar input/change para que pickers o calculadores enganchados se enteren.
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

    function setupForm(form) {
        const key = parseDraftKey(form);
        if (!key) return;

        let lastSavedJson = '';
        let debounceTimer = null;

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

        // 2) Listeners de cambio → autoguardado.
        const trigger = () => {
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const payload = serializeForm(form);
                const json = JSON.stringify(payload);
                if (json === lastSavedJson) return;
                lastSavedJson = json;
                fetch(SAVE_URL, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: buildSavePayload(key.modulo, key.ref, payload).toString(),
                }).catch(() => {});
            }, DEBOUNCE_MS);
        };

        form.addEventListener('input', trigger);
        form.addEventListener('change', trigger);

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
