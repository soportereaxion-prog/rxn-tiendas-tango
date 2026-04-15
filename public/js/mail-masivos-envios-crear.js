/* ============================================================
   Mail Masivos — Envíos / Creator (Fase 4)
   Habilita el botón de disparar solo cuando:
     - hay report_id + template_id
     - se hizo al menos un preview con conteo > 0
     - el checkbox de confirmación está tildado
   ============================================================ */
(function () {
    'use strict';

    const cfg = window.MailEnviosCrear || {};
    if (!cfg.apiPreviewRecipients) {
        console.error('[MailEnviosCrear] Falta config global.');
        return;
    }

    const $selReport = document.getElementById('sel-report');
    const $selTemplate = document.getElementById('sel-template');
    const $tplHint = document.getElementById('tpl-hint');
    const $asuntoPreview = document.getElementById('asunto-preview');
    const $btnPreview = document.getElementById('btn-preview');
    const $previewStatus = document.getElementById('preview-status');
    const $previewResults = document.getElementById('preview-results');
    const $chkConfirm = document.getElementById('chk-confirm');
    const $btnDisparar = document.getElementById('btn-disparar');
    const $form = document.getElementById('disparo-form');

    let lastPreview = null; // { count, capped }

    function updateDisparoEnabled() {
        const hasReport = $selReport.value !== '';
        const hasTpl = $selTemplate.value !== '';
        const hasPreview = lastPreview !== null && lastPreview.count > 0;
        const confirmed = $chkConfirm.checked;
        $btnDisparar.disabled = !(hasReport && hasTpl && hasPreview && confirmed);
    }

    function updateTemplateHint() {
        const tplOpt = $selTemplate.selectedOptions[0];
        if (!tplOpt || !tplOpt.value) {
            $tplHint.textContent = 'Soporta variables que se van a reemplazar con los datos de cada destinatario.';
            $asuntoPreview.innerHTML = '';
            return;
        }

        const tplReportId = parseInt(tplOpt.dataset.reportId || '0', 10);
        const selectedReportId = parseInt($selReport.value || '0', 10);

        if (tplReportId && selectedReportId && tplReportId !== selectedReportId) {
            $tplHint.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-triangle"></i> Ojo: esta plantilla fue creada para otro reporte. Las variables pueden no matchear.</span>';
        } else if (tplReportId && !selectedReportId) {
            $tplHint.innerHTML = '<span class="text-muted">Esta plantilla ya tiene un reporte asociado. Elegí el mismo arriba para mejor compatibilidad.</span>';
        } else {
            $tplHint.textContent = 'Asunto con variables — se reemplazan por destinatario.';
        }

        const asunto = tplOpt.dataset.asunto || '';
        if (asunto) {
            $asuntoPreview.innerHTML = '<strong>Asunto:</strong> <code>' + escapeHtml(asunto) + '</code>';
        }
    }

    async function doPreview() {
        const reportId = parseInt($selReport.value || '0', 10);
        if (reportId <= 0) {
            setStatus('Elegí un reporte primero', 'warning');
            return;
        }

        setStatus('Consultando destinatarios...', 'loading');
        $btnPreview.disabled = true;

        try {
            const resp = await fetch(cfg.apiPreviewRecipients, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ report_id: reportId }),
            });

            const data = await resp.json();

            if (!resp.ok || !data.success) {
                setStatus(data.message || 'Error HTTP ' + resp.status, 'error');
                $previewResults.innerHTML = '';
                lastPreview = null;
                updateDisparoEnabled();
                return;
            }

            lastPreview = data;
            renderPreviewResults(data);
            setStatus('OK', 'ok');
            updateDisparoEnabled();
        } catch (err) {
            setStatus('Error de red: ' + err.message, 'error');
            lastPreview = null;
            updateDisparoEnabled();
        } finally {
            $btnPreview.disabled = false;
        }
    }

    function renderPreviewResults(data) {
        const parts = [];
        parts.push('<div class="rxn-envios-preview-box">');
        parts.push(
            '<div class="d-flex align-items-center justify-content-between">'
            + '<div>'
            + '<div class="rxn-envios-preview-count">' + data.count + '</div>'
            + '<div class="rxn-envios-preview-count-lbl">Destinatarios únicos</div>'
            + '</div>'
            + (data.capped
                ? '<div class="text-warning small"><i class="bi bi-exclamation-triangle"></i> Alcanzó el límite máximo (5000).</div>'
                : '')
            + '</div>'
        );

        if (Array.isArray(data.sample) && data.sample.length > 0) {
            parts.push('<div class="rxn-envios-preview-sample">');
            parts.push('<div class="small text-muted mb-1"><strong>Primeros ' + data.sample.length + ':</strong></div>');
            for (const s of data.sample) {
                parts.push(
                    '<div class="rxn-envios-preview-sample-row">'
                    + '<span class="email">' + escapeHtml(s.email) + '</span>'
                    + '<span class="name">' + escapeHtml(s.name || '—') + '</span>'
                    + '</div>'
                );
            }
            parts.push('</div>');
        } else {
            parts.push('<div class="text-danger small mt-2"><i class="bi bi-exclamation-circle"></i> El reporte no devolvió destinatarios válidos.</div>');
        }

        parts.push('</div>');
        $previewResults.innerHTML = parts.join('');
    }

    function setStatus(msg, kind) {
        const cls = {
            loading: 'text-primary',
            ok: 'text-success',
            error: 'text-danger',
            warning: 'text-warning',
        }[kind] || 'text-muted';
        $previewStatus.className = 'ms-2 small ' + cls;
        $previewStatus.textContent = msg;
    }

    function escapeHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ───── Bindings ─────
    $selReport.addEventListener('change', () => {
        lastPreview = null; // invalida el preview anterior
        $previewResults.innerHTML = '';
        setStatus('Tocá "Ver destinatarios" para validar', '');
        updateTemplateHint();
        updateDisparoEnabled();
    });

    $selTemplate.addEventListener('change', () => {
        updateTemplateHint();
        updateDisparoEnabled();
    });

    $btnPreview.addEventListener('click', doPreview);
    $chkConfirm.addEventListener('change', updateDisparoEnabled);

    // Evitar disparar con Enter en un input
    $form.addEventListener('submit', (ev) => {
        if ($btnDisparar.disabled) {
            ev.preventDefault();
        } else if (!confirm('¿Disparar el envío real a ' + (lastPreview?.count || '?') + ' destinatarios?')) {
            ev.preventDefault();
        }
    });

    // Init
    updateTemplateHint();
    updateDisparoEnabled();
})();
