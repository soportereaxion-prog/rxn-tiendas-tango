<?php
/**
 * Partial: banner de consentimiento de geo-tracking.
 *
 * Incluido desde admin_layout.php al final del body. Solo se renderiza si:
 *   - Hay sesión activa (user_id + empresa_id).
 *   - El usuario NO respondió la versión vigente del consentimiento.
 *   - El módulo está habilitado para la empresa.
 *
 * Si alguna de esas condiciones falla, no imprime NADA (el helper corta temprano).
 */

use App\Core\Context;
use App\Modules\RxnGeoTracking\GeoTrackingService;

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$empresaId = (int) Context::getEmpresaId();

if ($userId <= 0 || $empresaId <= 0) {
    return;
}

try {
    $service = new GeoTrackingService();
    if ($service->tieneConsentimientoVigente($userId, $empresaId)) {
        return;
    }
    $consentVersion = $service->currentConsentVersion($empresaId);
} catch (\Throwable) {
    // Silent fail — no queremos romper el layout por un issue de geo tracking.
    return;
}
?>
<div id="rxn-geo-consent-banner"
     class="position-fixed bottom-0 start-50 translate-middle-x m-3 shadow-lg"
     style="z-index: 1080; max-width: 640px; width: calc(100% - 2rem);"
     role="dialog"
     aria-labelledby="rxn-geo-consent-title"
     data-consent-version="<?= htmlspecialchars($consentVersion, ENT_QUOTES, 'UTF-8') ?>">
    <div class="card border-0" style="border-left: 4px solid var(--bs-primary) !important;">
        <div class="card-body p-3">
            <div class="d-flex align-items-start gap-2 mb-2">
                <i class="bi bi-geo-alt-fill text-primary fs-5"></i>
                <h6 id="rxn-geo-consent-title" class="card-title mb-0 flex-grow-1">
                    Tu ubicación mientras usás la suite
                </h6>
            </div>
            <p class="card-text small text-muted mb-3">
                Para auditoría comercial y cumplimiento interno, la empresa registra tu ubicación
                aproximada al iniciar sesión y al crear presupuestos, tratativas y pedidos de servicio.
                Se guarda IP y (si lo autorizás) la posición que reporte tu navegador.
                Los datos son visibles solamente por el administrador de tu empresa y se retienen
                según la política de privacidad vigente.
            </p>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <button type="button"
                        class="btn btn-sm btn-outline-secondary"
                        data-rxn-geo-consent="later">
                    Decidir después
                </button>
                <button type="button"
                        class="btn btn-sm btn-outline-danger"
                        data-rxn-geo-consent="denied">
                    No acepto
                </button>
                <button type="button"
                        class="btn btn-sm btn-primary"
                        data-rxn-geo-consent="accepted">
                    <i class="bi bi-check-circle me-1"></i>Acepto
                </button>
            </div>
        </div>
    </div>
</div>
