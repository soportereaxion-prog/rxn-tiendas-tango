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

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') || '' : '';
    }

    const $selReport = document.getElementById('sel-report');
    const $selTemplate = document.getElementById('sel-template');
    const $selContentReport = document.getElementById('sel-content-report');
    const $tplHint = document.getElementById('tpl-hint');
    const $asuntoPreview = document.getElementById('asunto-preview');
    const $btnPreview = document.getElementById('btn-preview');
    const $previewStatus = document.getElementById('preview-status');
    const $previewResults = document.getElementById('preview-results');
    const $chkConfirm = document.getElementById('chk-confirm');
    const $btnDisparar = document.getElementById('btn-disparar');
    const $form = document.getElementById('disparo-form');

    // Preview del mail final
    const $btnMailPreview = document.getElementById('btn-preview-mail');
    const $btnFullscreen = document.getElementById('btn-preview-fullscreen');
    const $btnNewTab = document.getElementById('btn-preview-newtab');
    const $mailStatus = document.getElementById('preview-mail-status');
    const $mailAsunto = document.getElementById('preview-mail-asunto');
    const $mailIframe = document.getElementById('preview-mail-iframe');
    const $mailIframeModal = document.getElementById('preview-mail-iframe-modal');
    const $mailModal = document.getElementById('preview-mail-modal');

    let lastMailHtml = '';
    let lastMailAsunto = '';

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
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken(),
                },
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

    // ───── Preview del mail final ─────
    async function doMailPreview() {
        if (!cfg.apiPreviewRender) return;
        const reportId = parseInt($selReport.value || '0', 10);
        const templateId = parseInt($selTemplate.value || '0', 10);
        const contentReportId = parseInt(($selContentReport && $selContentReport.value) || '0', 10);

        if (templateId <= 0) {
            setMailStatus('Elegí primero una plantilla.', 'warning');
            return;
        }

        setMailStatus('⏳ Renderizando preview...', 'loading');
        $btnMailPreview.disabled = true;

        try {
            const resp = await fetch(cfg.apiPreviewRender, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    template_id: templateId,
                    report_id: reportId > 0 ? reportId : 0,
                    content_report_id: contentReportId > 0 ? contentReportId : 0,
                }),
            });
            const data = await resp.json();
            if (!resp.ok || !data.success) {
                setMailStatus(data.message || ('Error HTTP ' + resp.status), 'error');
                clearMailPreview();
                return;
            }

            lastMailHtml = data.body_html_rendered || '';
            lastMailAsunto = data.asunto_rendered || '';

            $mailAsunto.innerHTML = lastMailAsunto
                ? '<strong>Asunto:</strong> ' + escapeHtml(lastMailAsunto)
                : '';

            // Render via srcdoc — iframe sandboxed para evitar JS arbitrario.
            $mailIframe.srcdoc = lastMailHtml;

            const noteParts = [];
            if (data.note) noteParts.push(data.note);
            if (Array.isArray(data.missing_tokens) && data.missing_tokens.length > 0) {
                noteParts.push('Variables sin datos: ' + data.missing_tokens.join(', '));
            }
            setMailStatus(noteParts.length ? '⚠ ' + noteParts.join(' · ') : '✓ Preview generado.', noteParts.length ? 'warning' : 'ok');

            $btnFullscreen.disabled = false;
            $btnNewTab.disabled = false;
        } catch (err) {
            setMailStatus('Error de red: ' + err.message, 'error');
            clearMailPreview();
        } finally {
            $btnMailPreview.disabled = false;
        }
    }

    function clearMailPreview() {
        lastMailHtml = '';
        lastMailAsunto = '';
        $mailIframe.srcdoc = '';
        $mailAsunto.innerHTML = '';
        $btnFullscreen.disabled = true;
        $btnNewTab.disabled = true;
    }

    function setMailStatus(msg, kind) {
        const cls = {
            loading: 'text-primary',
            ok: 'text-success',
            error: 'text-danger',
            warning: 'text-warning',
        }[kind] || 'text-muted';
        $mailStatus.className = 'small mb-2 ' + cls;
        $mailStatus.textContent = msg;
    }

    if ($btnMailPreview) {
        $btnMailPreview.addEventListener('click', doMailPreview);
    }
    if ($btnFullscreen && $mailModal) {
        $btnFullscreen.addEventListener('click', () => {
            if (!lastMailHtml) return;
            $mailIframeModal.srcdoc = lastMailHtml;
            // Bootstrap 5 modal — global bootstrap.Modal esperado por el resto de la suite
            if (window.bootstrap && window.bootstrap.Modal) {
                const modal = window.bootstrap.Modal.getOrCreateInstance($mailModal);
                modal.show();
            } else {
                $mailModal.classList.add('show');
                $mailModal.style.display = 'block';
            }
        });
    }
    if ($btnNewTab) {
        $btnNewTab.addEventListener('click', () => {
            if (!lastMailHtml) return;
            const w = window.open('', '_blank');
            if (!w) return;
            w.document.open();
            w.document.write(lastMailHtml);
            w.document.close();
            w.document.title = lastMailAsunto || 'Preview del mail';
        });
    }

    // Si cambia plantilla, reporte o bloque de contenido invalidamos el preview existente
    [$selReport, $selTemplate, $selContentReport].forEach(sel => {
        if (sel) {
            sel.addEventListener('change', () => {
                if (lastMailHtml) {
                    setMailStatus('Cambiaste la selección — refrescá el preview.', 'warning');
                }
            });
        }
    });

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
