<?php

declare(strict_types=1);

namespace App\Modules\RxnGeoTracking;

use App\Core\Context;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Modules\Auth\UserModuleAccessService;
use App\Modules\Empresas\EmpresaAccessService;
use App\Shared\Services\OperationalAreaService;

/**
 * Dashboard admin de geo-tracking.
 *
 * Responsabilidades:
 *   - index()     → página completa con mapa + listado + filtros.
 *   - mapPoints() → JSON con puntos para que el JS popule el Google Maps asincrónicamente.
 *   - export()    → descarga CSV con los eventos filtrados (máx 10k).
 *
 * Acceso: SOLO admin de empresa (es_admin=1) o rxn_admin (es_rxn_admin=1).
 * Multi-tenant estricto: todas las queries filtran por Context::getEmpresaId().
 */
class RxnGeoTrackingController extends \App\Core\Controller
{
    private const ALLOWED_LIMITS = [25, 50, 100];
    private const DEFAULT_LIMIT = 25;
    private const MAX_MAP_POINTS = 500;
    private const MAX_EXPORT_ROWS = 10000;

    private GeoEventRepository $events;
    private GeoTrackingConfigRepository $config;

    public function __construct()
    {
        $this->events = new GeoEventRepository();
        $this->config = new GeoTrackingConfigRepository();
    }

    private function requireGeoTrackingAccess(): void
    {
        AuthService::requireBackofficeAdmin();
        EmpresaAccessService::requireCrmGeoTrackingAccess();
        UserModuleAccessService::requireUserAccess('geo_tracking', 'Geo Tracking');
    }

    public function index(): void
    {
        $this->requireGeoTrackingAccess();
        $empresaId = (int) Context::getEmpresaId();

        $filters = $this->parseFiltersFromRequest();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = (int) ($_GET['limit'] ?? self::DEFAULT_LIMIT);
        if (!in_array($limit, self::ALLOWED_LIMITS, true)) {
            $limit = self::DEFAULT_LIMIT;
        }

        $totalItems = $this->events->countAll($empresaId, $filters);
        $totalPages = max(1, (int) ceil($totalItems / $limit));
        $page = min($page, $totalPages);

        $eventos = $this->events->findPaginated($empresaId, $filters, $page, $limit);
        $usuariosConEventos = $this->events->findDistinctUsersInRange($empresaId, $filters);
        $config = $this->config->getConfig($empresaId);

        $googleMapsApiKey = $this->getGoogleMapsApiKey();

        View::render('app/modules/RxnGeoTracking/views/dashboard.php', array_merge($this->buildUiContext(), [
            'eventos' => $eventos,
            'usuariosConEventos' => $usuariosConEventos,
            'filters' => $filters,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'config' => $config,
            'googleMapsApiKey' => $googleMapsApiKey,
            'eventTypeLabels' => self::eventTypeLabels(),
        ]));
    }

    /**
     * Endpoint AJAX consumido por el JS del mapa al filtrar.
     * Devuelve JSON con los puntos georreferenciados.
     */
    public function mapPoints(): void
    {
        $this->requireGeoTrackingAccess();
        header('Content-Type: application/json');

        $empresaId = (int) Context::getEmpresaId();
        $filters = $this->parseFiltersFromRequest();

        $points = $this->events->findForMap($empresaId, $filters, self::MAX_MAP_POINTS);

        // Contar el total matcheando para decidir si mostrar banner "hay más de 500".
        $totalMatching = $this->events->countAll($empresaId, array_merge($filters, [
            // No podemos pasar "tiene lat/lng" como filtro genérico porque no aplica al resto —
            // el count total es referencial; el mapa muestra solo los georreferenciados hasta 500.
        ]));

        echo json_encode([
            'success' => true,
            'points' => array_map([$this, 'formatMapPoint'], $points),
            'limited' => count($points) >= self::MAX_MAP_POINTS,
            'total_matching' => $totalMatching,
            'max_points' => self::MAX_MAP_POINTS,
        ], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * Export CSV. Descarga directa, no renderiza vista.
     */
    public function export(): void
    {
        $this->requireGeoTrackingAccess();
        $empresaId = (int) Context::getEmpresaId();
        $filters = $this->parseFiltersFromRequest();

        $rows = $this->events->findForExport($empresaId, $filters, self::MAX_EXPORT_ROWS);

        $filename = 'geo-tracking_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            http_response_code(500);
            echo 'Error abriendo stream de salida.';
            return;
        }

        // BOM UTF-8 para que Excel no se confunda con los acentos.
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'ID', 'Usuario', 'Email', 'Evento', 'Entidad Tipo', 'Entidad ID',
            'IP', 'Lat', 'Lng', 'Precisión (m)', 'Fuente Precisión',
            'Ciudad', 'Región', 'País', 'User-Agent', 'Consent v', 'Fecha',
        ]);

        $eventLabels = self::eventTypeLabels();
        foreach ($rows as $row) {
            fputcsv($out, [
                (int) $row['id'],
                (string) ($row['user_nombre'] ?? ''),
                (string) ($row['user_email'] ?? ''),
                $eventLabels[$row['event_type']] ?? (string) $row['event_type'],
                (string) ($row['entidad_tipo'] ?? ''),
                $row['entidad_id'] !== null ? (int) $row['entidad_id'] : '',
                (string) ($row['ip_address'] ?? ''),
                $row['lat'] !== null ? (string) $row['lat'] : '',
                $row['lng'] !== null ? (string) $row['lng'] : '',
                $row['accuracy_meters'] !== null ? (int) $row['accuracy_meters'] : '',
                (string) ($row['accuracy_source'] ?? ''),
                (string) ($row['resolved_city'] ?? ''),
                (string) ($row['resolved_region'] ?? ''),
                (string) ($row['resolved_country'] ?? ''),
                (string) ($row['user_agent'] ?? ''),
                (string) ($row['consent_version'] ?? ''),
                (string) ($row['created_at'] ?? ''),
            ]);
        }

        fclose($out);
    }

    /**
     * Labels human-readable de los event_type.
     */
    public static function eventTypeLabels(): array
    {
        return [
            GeoTrackingService::EVENT_LOGIN => 'Login',
            GeoTrackingService::EVENT_PRESUPUESTO_CREATED => 'Presupuesto creado',
            GeoTrackingService::EVENT_TRATATIVA_CREATED => 'Tratativa creada',
            GeoTrackingService::EVENT_PDS_CREATED => 'PDS creado',
        ];
    }

    private function parseFiltersFromRequest(): array
    {
        // Default: últimos 7 días si no viene fecha_desde en la URL.
        $defaultDateFrom = (new \DateTimeImmutable('-7 days'))->format('Y-m-d');

        $dateFrom = trim((string) ($_GET['date_from'] ?? $defaultDateFrom));
        $dateTo = trim((string) ($_GET['date_to'] ?? ''));
        $userId = (int) ($_GET['user_id'] ?? 0);
        $eventType = trim((string) ($_GET['event_type'] ?? ''));
        $entidadTipo = trim((string) ($_GET['entidad_tipo'] ?? ''));

        return [
            'date_from' => $dateFrom !== '' ? $dateFrom : null,
            'date_to' => $dateTo !== '' ? $dateTo : null,
            'user_id' => $userId > 0 ? $userId : null,
            'event_type' => $eventType !== '' ? $eventType : null,
            'entidad_tipo' => $entidadTipo !== '' ? $entidadTipo : null,
        ];
    }

    private function formatMapPoint(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'lat' => (float) $row['lat'],
            'lng' => (float) $row['lng'],
            'event_type' => (string) $row['event_type'],
            'entidad_tipo' => $row['entidad_tipo'] !== null ? (string) $row['entidad_tipo'] : null,
            'entidad_id' => $row['entidad_id'] !== null ? (int) $row['entidad_id'] : null,
            'accuracy_source' => (string) $row['accuracy_source'],
            'accuracy_meters' => $row['accuracy_meters'] !== null ? (int) $row['accuracy_meters'] : null,
            'user_nombre' => (string) ($row['user_nombre'] ?? ''),
            'city' => (string) ($row['resolved_city'] ?? ''),
            'country' => (string) ($row['resolved_country'] ?? ''),
            'ip' => (string) ($row['ip_address'] ?? ''),
            'created_at' => (string) $row['created_at'],
        ];
    }

    private function getGoogleMapsApiKey(): ?string
    {
        $key = getenv('GOOGLE_MAPS_API_KEY');
        if (is_string($key) && trim($key) !== '') {
            return trim($key);
        }

        // Fallback: algunos proyectos tienen GMAPS_API_KEY por compatibilidad con env legacy.
        $legacy = getenv('GMAPS_API_KEY');
        if (is_string($legacy) && trim($legacy) !== '') {
            return trim($legacy);
        }

        return null;
    }

    private function buildUiContext(): array
    {
        return [
            'basePath' => '/mi-empresa/geo-tracking',
            'indexPath' => '/mi-empresa/geo-tracking',
            'dashboardPath' => OperationalAreaService::dashboardPath(OperationalAreaService::AREA_CRM),
            'moduleNotesKey' => 'rxn_geo_tracking',
            'moduleNotesLabel' => 'RXN Geo Tracking',
        ];
    }
}
