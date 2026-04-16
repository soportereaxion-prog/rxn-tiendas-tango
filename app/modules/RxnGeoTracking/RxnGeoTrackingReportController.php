<?php

declare(strict_types=1);

namespace App\Modules\RxnGeoTracking;

use App\Modules\Auth\AuthService;

/**
 * Endpoint POST /geo-tracking/report.
 *
 * Recibe lat/lng/accuracy obtenidos por navigator.geolocation.getCurrentPosition()
 * en el browser y los agrega al evento ya creado server-side.
 *
 * Payload esperado (JSON body):
 *   - evento_id: int   (devuelto por el servidor cuando el evento se creó)
 *   - lat: float|null
 *   - lng: float|null
 *   - accuracy: int|null   (en metros, devuelto por el browser)
 *   - source: 'gps' | 'wifi' | 'denied' | 'error'
 *
 * Seguridad: el service valida que el user_id del evento === user_id de la sesión
 * antes de actualizar. Un atacante no puede reportar posición de eventos ajenos.
 */
class RxnGeoTrackingReportController extends \App\Core\Controller
{
    private GeoTrackingService $service;

    public function __construct()
    {
        $this->service = new GeoTrackingService();
    }

    public function store(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $raw = file_get_contents('php://input');
        $payload = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        if (!is_array($payload)) {
            // Fallback: POST form-encoded.
            $payload = $_POST;
        }

        $eventoId = isset($payload['evento_id']) ? (int) $payload['evento_id'] : 0;
        $lat = isset($payload['lat']) && is_numeric($payload['lat']) ? (float) $payload['lat'] : null;
        $lng = isset($payload['lng']) && is_numeric($payload['lng']) ? (float) $payload['lng'] : null;
        $accuracy = isset($payload['accuracy']) && is_numeric($payload['accuracy']) ? (int) $payload['accuracy'] : null;
        $source = isset($payload['source']) ? strtolower((string) $payload['source']) : 'error';

        if ($eventoId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'evento_id requerido.']);
            return;
        }

        $ok = $this->service->reportarPosicionBrowser($eventoId, $lat, $lng, $accuracy, $source);

        echo json_encode(['success' => $ok]);
    }
}
