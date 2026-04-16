<?php
/**
 * Configuración del módulo RxnGeoTracking (por empresa).
 *
 * Recibe de RxnGeoTrackingConfigController::show():
 *   - config (array)
 *   - minRetention (int)
 *   - maxRetention (int)
 */

$pageTitle = 'Configuración Geo Tracking';
$usePageHeader = true;
$pageHeaderTitle = 'Configuración RXN Geo Tracking';
$pageHeaderSubtitle = 'Ajustes del módulo para tu empresa.';
$pageHeaderIcon = 'bi-gear-fill';
$pageHeaderBackUrl = '/mi-empresa/geo-tracking';
$pageHeaderBackLabel = 'Volver al dashboard';

$config = $config ?? [];
$habilitado = !empty($config['habilitado']);
$retention = (int) ($config['retention_days'] ?? 90);
$requiresGps = !empty($config['requires_gps']);
$consentVersion = (string) ($config['consent_version_current'] ?? 'v1');
$minRetention = $minRetention ?? 30;
$maxRetention = $maxRetention ?? 730;

ob_start();
?>

<?php if (\App\Core\Flash::has('success')): ?>
    <div class="alert alert-success"><?= htmlspecialchars((string) \App\Core\Flash::get('success')) ?></div>
<?php endif; ?>
<?php if (\App\Core\Flash::has('danger')): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) \App\Core\Flash::get('danger')) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="post" action="/mi-empresa/geo-tracking/config">
                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" id="habilitado"
                                   name="habilitado" value="1" <?= $habilitado ? 'checked' : '' ?>>
                            <label class="form-check-label" for="habilitado">
                                <strong>Módulo habilitado</strong>
                            </label>
                        </div>
                        <small class="text-muted">
                            Si se deshabilita, no se registrarán eventos nuevos. Los eventos históricos quedan intactos
                            y visibles en el dashboard.
                        </small>
                    </div>

                    <div class="mb-4">
                        <label for="retention_days" class="form-label"><strong>Retención de eventos (días)</strong></label>
                        <input type="number" class="form-control" id="retention_days" name="retention_days"
                               value="<?= (int) $retention ?>"
                               min="<?= (int) $minRetention ?>"
                               max="<?= (int) $maxRetention ?>" required>
                        <small class="text-muted">
                            Rango permitido: <?= (int) $minRetention ?> a <?= (int) $maxRetention ?> días.
                            El job de purga borra automáticamente eventos más viejos que este valor.
                        </small>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" id="requires_gps"
                                   name="requires_gps" value="1" <?= $requiresGps ? 'checked' : '' ?>>
                            <label class="form-check-label" for="requires_gps">
                                <strong>Requerir GPS del navegador</strong>
                            </label>
                        </div>
                        <small class="text-muted">
                            Si está activo, el banner de consentimiento no ofrece la opción "No acepto" — el usuario
                            debe aceptar o cerrar sesión. <span class="text-warning">⚠️ Usar con cuidado: puede
                            generar rechazo de usuarios.</span>
                        </small>
                    </div>

                    <div class="mb-4">
                        <label for="consent_version_current" class="form-label">
                            <strong>Versión del consentimiento vigente</strong>
                        </label>
                        <input type="text" class="form-control" id="consent_version_current"
                               name="consent_version_current"
                               value="<?= htmlspecialchars($consentVersion, ENT_QUOTES, 'UTF-8') ?>"
                               maxlength="16" pattern="[a-zA-Z0-9._-]+" required>
                        <small class="text-muted">
                            Cuando cambia materialmente lo que se trackea, incrementá esta versión (ej. <code>v2</code>).
                            Todos los usuarios vuelven a ver el banner hasta que respondan la nueva versión.
                            Solo letras, números, puntos, guiones y guiones bajos. Máx 16 caracteres.
                        </small>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="/mi-empresa/geo-tracking" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card bg-body-tertiary">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-info-circle me-1"></i>Sobre este módulo</h6>
                <p class="small mb-2">
                    RXN Geo Tracking registra la ubicación aproximada y la IP desde la cual los usuarios de tu empresa
                    realizan acciones críticas: inicio de sesión, creación de presupuestos, tratativas y pedidos de servicio.
                </p>
                <p class="small mb-0 text-muted">
                    Los datos son visibles solamente para administradores de tu empresa. El usuario debe aceptar
                    explícitamente el banner de consentimiento en su primera sesión — de lo contrario se registra
                    solo la IP (sin GPS). Ver la política de privacidad para más detalles sobre el cumplimiento
                    de la Ley 25.326.
                </p>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
