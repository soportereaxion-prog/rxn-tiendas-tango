<?php

declare(strict_types=1);

namespace App\Modules\RxnGeoTracking;

use App\Core\Context;
use App\Core\Flash;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Shared\Services\OperationalAreaService;

/**
 * Configuración del módulo RxnGeoTracking por empresa.
 *
 * Rutas:
 *   GET  /mi-empresa/geo-tracking/config  → show()
 *   POST /mi-empresa/geo-tracking/config  → update()
 *
 * Acceso: admin de empresa o rxn_admin.
 */
class RxnGeoTrackingConfigController extends \App\Core\Controller
{
    private GeoTrackingConfigRepository $repository;

    public function __construct()
    {
        $this->repository = new GeoTrackingConfigRepository();
    }

    public function show(): void
    {
        AuthService::requireBackofficeAdmin();
        $empresaId = (int) Context::getEmpresaId();
        $config = $this->repository->getConfig($empresaId);

        View::render('app/modules/RxnGeoTracking/views/config.php', [
            'basePath' => '/mi-empresa/geo-tracking',
            'dashboardPath' => OperationalAreaService::dashboardPath(OperationalAreaService::AREA_CRM),
            'moduleNotesKey' => 'rxn_geo_tracking',
            'moduleNotesLabel' => 'RXN Geo Tracking',
            'config' => $config,
            'minRetention' => GeoTrackingConfigRepository::MIN_RETENTION_DAYS,
            'maxRetention' => GeoTrackingConfigRepository::MAX_RETENTION_DAYS,
        ]);
    }

    public function update(): void
    {
        AuthService::requireBackofficeAdmin();
        $empresaId = (int) Context::getEmpresaId();

        $habilitado = !empty($_POST['habilitado']);
        $retentionDays = (int) ($_POST['retention_days'] ?? GeoTrackingConfigRepository::DEFAULT_RETENTION_DAYS);
        $requiresGps = !empty($_POST['requires_gps']);
        $consentVersion = trim((string) ($_POST['consent_version_current'] ?? ''));

        $errors = [];

        if ($retentionDays < GeoTrackingConfigRepository::MIN_RETENTION_DAYS
            || $retentionDays > GeoTrackingConfigRepository::MAX_RETENTION_DAYS) {
            $errors['retention_days'] = 'La retención debe estar entre '
                . GeoTrackingConfigRepository::MIN_RETENTION_DAYS . ' y '
                . GeoTrackingConfigRepository::MAX_RETENTION_DAYS . ' días.';
        }

        if ($consentVersion === '' || mb_strlen($consentVersion) > 16) {
            $errors['consent_version_current'] = 'La versión del consentimiento es obligatoria (máx 16 caracteres).';
        } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $consentVersion)) {
            $errors['consent_version_current'] = 'La versión solo puede contener letras, números, puntos, guiones y guiones bajos.';
        }

        if ($errors !== []) {
            Flash::set('danger', implode(' ', $errors));
            header('Location: /mi-empresa/geo-tracking/config');
            exit;
        }

        $this->repository->upsert($empresaId, [
            'habilitado' => $habilitado,
            'retention_days' => $retentionDays,
            'requires_gps' => $requiresGps,
            'consent_version_current' => $consentVersion,
        ]);

        Flash::set('success', 'Configuración actualizada correctamente.');
        header('Location: /mi-empresa/geo-tracking/config');
        exit;
    }
}
