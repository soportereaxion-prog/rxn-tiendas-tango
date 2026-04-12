<?php
declare(strict_types=1);

namespace App\Modules\CrmAgenda;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * Push-only sync de eventos de la agenda al Google Calendar.
 * Soporta multi-auth (modo 'ambos'): un evento puede replicarse a
 * multiples Google Calendars simultaneamente (empresa-wide + usuario personal).
 *
 * El tracking de sincronizaciones multiples se guarda en la columna JSON
 * google_syncs de crm_agenda_eventos.
 */
class GoogleCalendarSyncService
{
    private const API_BASE = 'https://www.googleapis.com/calendar/v3';

    // Google Calendar colorId mapping (1-11, predefined by Google).
    // Estos colores aparecen como "categoría" visual en el Google Calendar del usuario.
    private const GOOGLE_COLOR_IDS = [
        'pds' => '9',        // Blueberry (azul oscuro)
        'presupuesto' => '2', // Sage (verde)
        'tratativa' => '5',   // Banana (amarillo)
        'llamada' => '3',     // Grape (violeta)
        'manual' => '8',      // Graphite (gris)
        'tratativa_accion' => '6', // Tangerine (naranja)
    ];

    private AgendaRepository $repository;
    private GoogleOAuthService $oauth;

    public function __construct(?AgendaRepository $repository = null, ?GoogleOAuthService $oauth = null)
    {
        $this->repository = $repository ?? new AgendaRepository();
        $this->oauth = $oauth ?? new GoogleOAuthService();
    }

    /**
     * Push multi-auth: sincroniza un evento local a TODOS los Google Calendars
     * activos segun el modo de la empresa (usuario, empresa, ambos).
     */
    public function pushMulti(array $event): bool
    {
        $empresaId = (int) $event['empresa_id'];
        $usuarioId = isset($event['usuario_id']) && $event['usuario_id'] !== null ? (int) $event['usuario_id'] : null;

        $auths = $this->oauth->getActiveAuths($empresaId, $usuarioId);
        if ($auths === []) {
            return false; // Sin auth activo, skip silencioso
        }

        $payload = $this->buildEventPayload($event);
        $eventId = (int) $event['id'];

        // Leer syncs existentes
        $existingSyncs = $this->loadSyncs($event);
        $newSyncs = [];
        $anySuccess = false;

        foreach ($auths as $auth) {
            $authId = (int) $auth['id'];
            $calendarId = (string) ($auth['calendar_id'] ?? 'primary');

            // Buscar si ya hay un sync previo para este auth
            $existingGoogleEventId = null;
            foreach ($existingSyncs as $sync) {
                if ((int) ($sync['auth_id'] ?? 0) === $authId) {
                    $existingGoogleEventId = $sync['google_event_id'] ?? null;
                    break;
                }
            }

            try {
                $accessToken = $this->oauth->getValidAccessToken($auth);

                if ($existingGoogleEventId) {
                    // Actualizar evento existente
                    $this->httpRequest(
                        'PUT',
                        self::API_BASE . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($existingGoogleEventId),
                        $accessToken,
                        $payload
                    );
                    $newSyncs[] = [
                        'auth_id' => $authId,
                        'google_event_id' => $existingGoogleEventId,
                        'calendar_id' => $calendarId,
                        'synced_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                    ];
                } else {
                    // Crear evento nuevo
                    $response = $this->httpRequest(
                        'POST',
                        self::API_BASE . '/calendars/' . rawurlencode($calendarId) . '/events',
                        $accessToken,
                        $payload
                    );
                    $googleEventId = (string) ($response['id'] ?? '');
                    $newSyncs[] = [
                        'auth_id' => $authId,
                        'google_event_id' => $googleEventId,
                        'calendar_id' => $calendarId,
                        'synced_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                    ];
                }

                $anySuccess = true;
            } catch (\Throwable $e) {
                // Registrar el error para este auth pero seguir con los demas
                $newSyncs[] = [
                    'auth_id' => $authId,
                    'google_event_id' => $existingGoogleEventId,
                    'calendar_id' => $calendarId,
                    'synced_at' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Persistir el tracking JSON + actualizar los campos legacy (primer sync exitoso)
        $this->repository->updateGoogleSyncs($eventId, $empresaId, $newSyncs);

        // Actualizar los campos legacy con el primer sync exitoso (para compat)
        foreach ($newSyncs as $sync) {
            if (!empty($sync['google_event_id']) && !empty($sync['synced_at'])) {
                $this->repository->markSynced($eventId, $empresaId, $sync['google_event_id'], $sync['calendar_id']);
                break;
            }
        }

        return $anySuccess;
    }

    /**
     * Push single-auth (compat con la API anterior).
     */
    public function push(array $event): bool
    {
        return $this->pushMulti($event);
    }

    /**
     * Borra un evento remoto de TODOS los Google Calendars donde fue sincronizado.
     */
    public function deleteRemote(array $event): bool
    {
        $empresaId = (int) $event['empresa_id'];
        $syncs = $this->loadSyncs($event);
        $anySuccess = false;

        foreach ($syncs as $sync) {
            $googleEventId = $sync['google_event_id'] ?? null;
            if (empty($googleEventId)) continue;

            $authId = (int) ($sync['auth_id'] ?? 0);
            if ($authId <= 0) continue;

            // Buscar el auth por ID para obtener el access_token
            $stmt = \App\Core\Database::getConnection()->prepare('SELECT * FROM crm_google_auth WHERE id = :id AND empresa_id = :empresa_id LIMIT 1');
            $stmt->execute([':id' => $authId, ':empresa_id' => $empresaId]);
            $auth = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$auth) continue;

            try {
                $accessToken = $this->oauth->getValidAccessToken($auth);
                $calendarId = $sync['calendar_id'] ?? 'primary';
                $this->httpRequest(
                    'DELETE',
                    self::API_BASE . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($googleEventId),
                    $accessToken,
                    null
                );
                $anySuccess = true;
            } catch (\Throwable) {
                // 404/410 = ya no existe en Google, tratamos como exito silencioso
            }
        }

        // Fallback: si no habia syncs JSON pero habia google_event_id legacy
        if ($syncs === [] && !empty($event['google_event_id'])) {
            $usuarioId = isset($event['usuario_id']) ? (int) $event['usuario_id'] : null;
            $auth = $this->oauth->getActiveAuth($empresaId, $usuarioId);
            if ($auth) {
                try {
                    $accessToken = $this->oauth->getValidAccessToken($auth);
                    $calendarId = $auth['calendar_id'] ?? 'primary';
                    $this->httpRequest(
                        'DELETE',
                        self::API_BASE . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode((string) $event['google_event_id']),
                        $accessToken,
                        null
                    );
                    $anySuccess = true;
                } catch (\Throwable) {}
            }
        }

        return $anySuccess;
    }

    private function loadSyncs(array $event): array
    {
        $raw = $event['google_syncs'] ?? null;
        if ($raw === null || $raw === '') return [];
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        return is_array($decoded) ? $decoded : [];
    }

    private function buildEventPayload(array $event): array
    {
        $allDay = !empty($event['all_day']);
        $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
        $inicio = new DateTimeImmutable((string) $event['inicio'], $tz);
        $fin = new DateTimeImmutable((string) $event['fin'], $tz);

        $payload = [
            'summary' => (string) ($event['titulo'] ?? 'Evento CRM'),
            'description' => (string) ($event['descripcion'] ?? ''),
        ];

        if (!empty($event['ubicacion'])) {
            $payload['location'] = (string) $event['ubicacion'];
        }

        if ($allDay) {
            $payload['start'] = ['date' => $inicio->format('Y-m-d')];
            $payload['end'] = ['date' => $fin->format('Y-m-d')];
        } else {
            $payload['start'] = [
                'dateTime' => $inicio->format('c'),
                'timeZone' => 'America/Argentina/Buenos_Aires',
            ];
            $payload['end'] = [
                'dateTime' => $fin->format('c'),
                'timeZone' => 'America/Argentina/Buenos_Aires',
            ];
        }

        // Asignar colorId de Google según tipo de origen (aparece como categoría visual en Google Calendar)
        $origen = (string) ($event['origen_tipo'] ?? 'manual');
        $googleColorId = self::GOOGLE_COLOR_IDS[$origen] ?? self::GOOGLE_COLOR_IDS['manual'];
        $payload['colorId'] = $googleColorId;

        $payload['extendedProperties'] = [
            'private' => [
                'rxn_suite_origen' => $origen,
                'rxn_suite_origen_id' => (string) ($event['origen_id'] ?? ''),
                'rxn_suite_local_id' => (string) ($event['id'] ?? ''),
            ],
        ];

        return $payload;
    }

    private function httpRequest(string $method, string $url, string $bearerToken, ?array $payload): array
    {
        $ch = curl_init();
        $headers = [
            'Authorization: Bearer ' . $bearerToken,
            'Accept: application/json',
        ];

        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ];

        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
        }

        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlErr !== '') {
            throw new RuntimeException('Error cURL al contactar Google Calendar: ' . $curlErr);
        }

        if ($method === 'DELETE' && $httpCode >= 200 && $httpCode < 300) {
            return [];
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Respuesta Google Calendar no es JSON valido (HTTP ' . $httpCode . '): ' . substr((string) $body, 0, 200));
        }

        if ($httpCode >= 400) {
            $msg = $decoded['error']['message'] ?? 'HTTP ' . $httpCode;
            throw new RuntimeException('Google Calendar API error: ' . $msg);
        }

        return $decoded;
    }
}
