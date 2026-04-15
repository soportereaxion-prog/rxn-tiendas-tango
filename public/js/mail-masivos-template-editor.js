/* ============================================================
   Mail Masivos — Template Editor (Fase 3)
   Editor side-by-side con variables dinámicas + preview en vivo.
   Vanilla JS, sin dependencias.
   ============================================================ */
(function () {
    'use strict';

    const cfg = window.MailTemplateEditor || {};
    if (!cfg.apiAvailableVars || !cfg.apiPreviewRender) {
        console.error('[MailTemplateEditor] Falta config global — revisar editor.php');
        return;
    }

    // ────────────── Elementos del DOM ──────────────
    const $selectReport = document.getElementById('select-report');
    const $inputAsunto = document.getElementById('input-asunto');
    const $textareaHtml = document.getElementById('textarea-html');
    const $varsContainer = document.getElementById('tpl-vars');
    const $hiddenAvailable = document.getElementById('hidden-available-vars');
    const $previewIframe = document.getElementById('preview-iframe');
    const $previewSubject = document.getElementById('preview-subject');
    const $previewFooter = document.getElementById('preview-footer');
    const $previewStatus = document.getElementById('tpl-preview-status');
    const $btnRefresh = document.getElementById('btn-refresh-preview');

    if (!$selectReport || !$textareaHtml || !$inputAsunto) {
        console.error('[MailTemplateEditor] Faltan elementos requeridos del DOM');
        return;
    }

    // Último campo que tuvo foco para saber dónde insertar una variable
    let lastFocusedField = $textareaHtml;
    [$inputAsunto, $textareaHtml].forEach((el) => {
        el.addEventListener('focus', () => { lastFocusedField = el; });
    });

    // ────────────── Carga de variables del reporte ──────────────

    let currentVariables = [];

    async function loadVariablesForReport(reportId) {
        if (!reportId || reportId <= 0) {
            currentVariables = [];
            renderVars([]);
            $hiddenAvailable.value = '';
            return;
        }

        renderVarsEmpty('<i class="bi bi-hourglass-split"></i> Cargando variables...');

        try {
            const resp = await fetch(cfg.apiAvailableVars + encodeURIComponent(reportId), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });

            if (!resp.ok) {
                renderVarsEmpty(
                    '<span class="rxn-tpl-error">Error al cargar variables (HTTP '
                    + resp.status + ')</span>'
                );
                return;
            }

            const data = await resp.json();
            if (!data.success) {
                renderVarsEmpty(
                    '<span class="rxn-tpl-error">' + escapeHtml(data.message || 'Error') + '</span>'
                );
                return;
            }

            currentVariables = Array.isArray(data.variables) ? data.variables : [];
            $hiddenAvailable.value = JSON.stringify(currentVariables);
            renderVars(currentVariables);
        } catch (err) {
            renderVarsEmpty(
                '<span class="rxn-tpl-error">Error de red: ' + escapeHtml(err.message) + '</span>'
            );
        }
    }

    function renderVarsEmpty(html) {
        $varsContainer.innerHTML = '<div class="rxn-tpl-vars-empty">' + html + '</div>';
    }

    function renderVars(vars) {
        if (!vars || vars.length === 0) {
            renderVarsEmpty(
                '<i class="bi bi-info-circle"></i> El reporte seleccionado no tiene campos de salida todavía. '
                + 'Editá el reporte y prendé al menos un campo.'
            );
            return;
        }

        // Agrupar por entity_label
        const groups = new Map();
        for (const v of vars) {
            const key = v.entity_label || 'Otros';
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(v);
        }

        const parts = [];
        for (const [groupTitle, list] of groups.entries()) {
            parts.push('<div class="rxn-tpl-vars-group">');
            parts.push('<div class="rxn-tpl-vars-group-title">' + escapeHtml(groupTitle) + '</div>');
            parts.push('<div class="rxn-tpl-vars-chips">');
            for (const v of list) {
                parts.push(
                    '<span class="rxn-tpl-var-chip" data-token="' + escapeHtml(v.token) + '" '
                    + 'title="Insertar {{' + escapeHtml(v.token) + '}}">'
                    + '<i class="bi bi-braces"></i> '
                    + escapeHtml(v.field_label)
                    + ' <span class="rxn-tpl-var-type">' + escapeHtml(v.type) + '</span>'
                    + '</span>'
                );
            }
            parts.push('</div></div>');
        }

        $varsContainer.innerHTML = parts.join('');

        // Bind click en los chips
        $varsContainer.querySelectorAll('.rxn-tpl-var-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                const token = chip.getAttribute('data-token');
                if (token) insertTokenAtCursor('{{' + token + '}}');
            });
        });
    }

    // ────────────── Insertar variable en el cursor ──────────────

    function insertTokenAtCursor(text) {
        const target = lastFocusedField || $textareaHtml;
        const start = target.selectionStart ?? target.value.length;
        const end = target.selectionEnd ?? target.value.length;
        const before = target.value.substring(0, start);
        const after = target.value.substring(end);

        target.value = before + text + after;
        const newPos = start + text.length;
        target.focus();
        try { target.setSelectionRange(newPos, newPos); } catch (e) { /* ok */ }

        // Disparar input para trigger del preview
        target.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // ────────────── Preview con debounce ──────────────

    let previewTimer = null;
    const PREVIEW_DEBOUNCE_MS = 500;

    function schedulePreview() {
        clearTimeout(previewTimer);
        setStatus('loading', 'renderizando...');
        previewTimer = setTimeout(refreshPreview, PREVIEW_DEBOUNCE_MS);
    }

    async function refreshPreview() {
        const reportId = parseInt($selectReport.value || '0', 10) || 0;
        const asunto = $inputAsunto.value || '';
        const bodyHtml = $textareaHtml.value || '';

        try {
            const resp = await fetch(cfg.apiPreviewRender, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    report_id: reportId,
                    asunto: asunto,
                    body_html: bodyHtml,
                }),
            });

            const data = await resp.json();

            if (!resp.ok || !data.success) {
                setStatus('error', 'error');
                $previewFooter.innerHTML =
                    '<span class="rxn-tpl-error">'
                    + '<i class="bi bi-exclamation-triangle"></i> '
                    + escapeHtml(data.message || 'Error HTTP ' + resp.status)
                    + '</span>';
                return;
            }

            // Asunto
            $previewSubject.textContent = data.asunto_rendered || '(vacío)';

            // Body — iframe con sandbox sin permisos + srcdoc
            const safeHtml = wrapHtmlForIframe(data.body_html_rendered || '');
            $previewIframe.srcdoc = safeHtml;

            // Footer: missing tokens + nota
            const footerParts = [];
            if (data.note) {
                footerParts.push(
                    '<span class="rxn-tpl-note"><i class="bi bi-info-circle"></i> '
                    + escapeHtml(data.note) + '</span>'
                );
            }
            if (Array.isArray(data.missing_tokens) && data.missing_tokens.length > 0) {
                footerParts.push(
                    '<div class="mt-1"><strong style="font-size: 0.72rem;">⚠️ Tokens sin valor:</strong> '
                    + data.missing_tokens.map((t) =>
                        '<span class="rxn-tpl-missing-chip">{{' + escapeHtml(t) + '}}</span>'
                    ).join('')
                    + '</div>'
                );
            }
            $previewFooter.innerHTML = footerParts.join('');

            setStatus('ok', data.sample_row ? 'OK con datos de muestra' : 'OK (sin datos)');
        } catch (err) {
            setStatus('error', 'error de red');
            $previewFooter.innerHTML =
                '<span class="rxn-tpl-error">'
                + '<i class="bi bi-wifi-off"></i> ' + escapeHtml(err.message)
                + '</span>';
        }
    }

    function wrapHtmlForIframe(innerHtml) {
        // Envoltura mínima para que el preview se vea consistente.
        // NO inyectamos el CSS del sitio — el usuario tiene que ver cómo se ve
        // "crudo", igual que lo va a ver en el cliente de mail.
        return '<!DOCTYPE html><html><head><meta charset="utf-8">'
            + '<style>'
            + 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; '
            + 'color: #212529; line-height: 1.5; padding: 1rem; margin: 0; background: #fff; }'
            + 'img { max-width: 100%; height: auto; }'
            + 'table { border-collapse: collapse; }'
            + '</style></head><body>'
            + innerHtml
            + '</body></html>';
    }

    function setStatus(kind, label) {
        if (!$previewStatus) return;
        const dot = '<span class="rxn-tpl-status-dot is-' + kind + '"></span>';
        $previewStatus.innerHTML = dot + '<span>' + escapeHtml(label) + '</span>';
    }

    // ────────────── Utils ──────────────

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ────────────── Wire up de eventos ──────────────

    $selectReport.addEventListener('change', () => {
        const rid = parseInt($selectReport.value || '0', 10) || 0;
        loadVariablesForReport(rid);
        schedulePreview();
    });

    $inputAsunto.addEventListener('input', schedulePreview);
    $textareaHtml.addEventListener('input', schedulePreview);
    if ($btnRefresh) {
        $btnRefresh.addEventListener('click', (ev) => {
            ev.preventDefault();
            refreshPreview();
        });
    }

    // ────────────── Init ──────────────

    const initialReportId = parseInt(cfg.initialReportId || 0, 10) || 0;
    if (initialReportId > 0) {
        loadVariablesForReport(initialReportId);
    }
    // Primer preview
    refreshPreview();
})();
