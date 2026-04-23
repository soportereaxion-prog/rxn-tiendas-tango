<?php
$pageTitle = 'Nuevo Envío Masivo - rxn_suite';
ob_start();
$flash = \App\Core\Flash::get();
$reports = $reports ?? [];
$contentReports = $contentReports ?? [];
$templates = $templates ?? [];
$smtp = $smtp ?? null;
?>
<link rel="stylesheet" href="/css/mail-masivos-envios.css">

<div class="container-fluid mt-2 mb-5 rxn-responsive-container">
    <div class="rxn-module-header mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-rocket-takeoff"></i> Nuevo Envío Masivo</h2>
            <p class="text-muted mb-0 small">
                Elegí reporte + plantilla, revisá los destinatarios, confirmá y disparo. n8n se encarga del resto.
            </p>
        </div>
        <div class="rxn-module-actions">
            <a href="/mi-empresa/crm/mail-masivos/envios" class="btn btn-outline-secondary btn-sm" title="Volver al listado de Envíos"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <?php
            $flashClass = match ($flash['type'] ?? 'info') {
                'success' => 'alert-success',
                'error', 'danger' => 'alert-danger',
                'warning' => 'alert-warning',
                default => 'alert-info',
            };
        ?>
        <div class="alert <?= $flashClass ?> py-2 small"><?= nl2br(htmlspecialchars($flash['message'] ?? '')) ?></div>
    <?php endif; ?>

    <?php if (!$smtp): ?>
        <div class="alert alert-warning">
            <strong>Te falta configurar el SMTP para envíos masivos.</strong>
            Andá a <a href="/mi-perfil" class="alert-link">Mi Perfil</a> y completá la sección "SMTP para Mail Masivos" antes de disparar envíos.
        </div>
    <?php elseif (empty($smtp['activo'])): ?>
        <div class="alert alert-warning">
            Tu SMTP de envíos masivos está <strong>inactivo</strong>. Activalo en <a href="/mi-perfil" class="alert-link">Mi Perfil</a>.
        </div>
    <?php endif; ?>

    <form method="post" action="/mi-empresa/crm/mail-masivos/envios" id="disparo-form">
        <div class="row g-4">
            <!-- Columna izquierda: selectors -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-1-circle-fill text-primary"></i> Elegí el reporte (destinatarios)</h6>
                        <select name="report_id" id="sel-report" class="form-select" required <?= !$smtp ? 'disabled' : '' ?>>
                            <option value="">— Seleccioná un reporte —</option>
                            <?php foreach ($reports as $r): ?>
                                <option value="<?= (int) $r['id'] ?>">
                                    <?= htmlspecialchars($r['nombre']) ?> (<?= htmlspecialchars($r['root_entity']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">El reporte define quiénes reciben el envío.</div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-2-circle-fill text-primary"></i> Elegí la plantilla (contenido)</h6>
                        <select name="template_id" id="sel-template" class="form-select" required <?= !$smtp ? 'disabled' : '' ?>>
                            <option value="">— Seleccioná una plantilla —</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?= (int) $t['id'] ?>"
                                        data-report-id="<?= (int) ($t['report_id'] ?? 0) ?>"
                                        data-asunto="<?= htmlspecialchars((string) $t['asunto']) ?>">
                                    <?= htmlspecialchars($t['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" id="tpl-hint">Soporta variables que se van a reemplazar con los datos de cada destinatario.</div>
                        <div id="asunto-preview" class="mt-2 small"></div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">
                            <i class="bi bi-3-circle-fill text-primary"></i>
                            Bloque de contenido <span class="text-muted fw-normal small">(opcional)</span>
                        </h6>
                        <select name="content_report_id" id="sel-content-report" class="form-select" <?= (!$smtp || empty($contentReports)) ? 'disabled' : '' ?>>
                            <option value="">— Sin bloque (el mail viaja solo con la plantilla) —</option>
                            <?php foreach ($contentReports as $r): ?>
                                <option value="<?= (int) $r['id'] ?>">
                                    <?= htmlspecialchars($r['nombre']) ?> (<?= htmlspecialchars($r['root_entity']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            Reemplaza el placeholder <code>{{Bloque.html}}</code> del cuerpo por el render de las filas
                            del reporte de contenido. Ideal para novedades, promos o listas de precios.
                            <?php if (empty($contentReports)): ?>
                                <br><em class="text-muted">Todavía no hay reportes de contenido creados.</em>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-4-circle-fill text-primary"></i> SMTP que se va a usar</h6>
                        <?php if ($smtp): ?>
                            <div class="rxn-envios-smtp-card">
                                <div><strong>From:</strong> <?= htmlspecialchars((string) $smtp['from_email']) ?>
                                    <?php if (!empty($smtp['from_name'])): ?>
                                        &lt;<?= htmlspecialchars((string) $smtp['from_name']) ?>&gt;
                                    <?php endif; ?>
                                </div>
                                <div><strong>Host:</strong> <?= htmlspecialchars((string) $smtp['host']) ?>:<?= (int) $smtp['port'] ?> (<?= htmlspecialchars((string) $smtp['encryption']) ?>)</div>
                                <div><strong>Batch:</strong> <?= (int) $smtp['max_per_batch'] ?> por tanda, con pausa de <?= (int) $smtp['pause_seconds'] ?>s</div>
                                <?php if ($smtp['last_test_status'] === 'ok'): ?>
                                    <div class="text-success small"><i class="bi bi-check-circle"></i> Último test OK el <?= htmlspecialchars((string) $smtp['last_test_at']) ?></div>
                                <?php elseif ($smtp['last_test_status'] === 'fail'): ?>
                                    <div class="text-danger small"><i class="bi bi-x-circle"></i> Último test FALLÓ — revisá antes de disparar</div>
                                <?php else: ?>
                                    <div class="text-muted small"><i class="bi bi-info-circle"></i> Nunca se testeó — probalo en Mi Perfil antes.</div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-muted small fst-italic">Sin SMTP configurado. Ver alerta arriba.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Columna derecha: preview destinatarios + disparo -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-5-circle-fill text-primary"></i> Destinatarios</h6>

                        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-preview" <?= !$smtp ? 'disabled' : '' ?>>
                            <i class="bi bi-people-fill"></i> Ver destinatarios
                        </button>
                        <span id="preview-status" class="ms-2 small text-muted"></span>

                        <div id="preview-results" class="mt-3"></div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-6-circle-fill text-primary"></i> Confirmar y disparar</h6>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="confirm" value="yes" id="chk-confirm" required <?= !$smtp ? 'disabled' : '' ?>>
                            <label class="form-check-label" for="chk-confirm">
                                Entiendo que esto <strong>dispara un envío real</strong> a todos los destinatarios del reporte usando mi SMTP.
                            </label>
                        </div>

                        <button type="submit" id="btn-disparar" class="btn btn-danger fw-bold w-100" disabled>
                            <i class="bi bi-rocket-takeoff-fill"></i> DISPARAR ENVÍO
                        </button>

                        <div class="form-text mt-2">
                            <i class="bi bi-info-circle"></i>
                            Después del disparo vas a ir directo al monitor del envío para ver el progreso en vivo.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
window.MailEnviosCrear = {
    apiPreviewRecipients: '/mi-empresa/crm/mail-masivos/envios/preview-recipients',
};
</script>
<script src="/js/mail-masivos-envios-crear.js" defer></script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
