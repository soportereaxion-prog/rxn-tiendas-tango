<?php
declare(strict_types=1);

namespace App\Modules\CrmAgenda;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * Push-only sync de eventos de la agenda al Google Calendar del usuario (o empresa).
 * Cliente HTTP cURL nativo, sin dependencias.
 *
 * Fase 2 scope:
 *   - create: inserta un evento en Google Calendar y guarda el google_event_id en la tabla local.
 *   - update: actualiza un evento existente por google_event_id.
 *   - delete: borra el evento remoto cuando se hace soft-delete local.
 *
 * NO implementado (fase 3):
 *   - pull / bidireccional / sync tokens / webhooks
 *   - batch requests
 */
class GoogleCalendarSyncService
{
    private const API_BASE = 'https://www.googleapis.com/calendar/v3';

    private AgendaRepository $repository;
    private GoogleOAuthService $oauth;

    public function __construct(?AgendaRepository $repository = null, ?GoogleOAuthService $oauth = null)
    {
        $this->repository = $repository ?? new AgendaRepository();
        $this->oauth = $oauth ?? new GoogleOAuthService();
    }

    /**
     * Sincroniza un evento local hacia Google.
     *   - Si no tiene google_event_id, lo crea.
     *   - Si ya tiene google_event_id, lo actualiza.
     * Retorna true si fue sincronizado, false si no hay auth activo (se omite silenciosamente).
     */
    public function push(array $event): bool
    {
        $empresaId = (int) $event['empresa_id'];
        $usuarioId = isset($event['usuario_id']) && $event['usuario_id'] !== null ? (int) $event['usuario_id'] : null;

        $auth = $this->oauth->getActiveAuth($empresaId, $usuarioId);
        if ($auth === null) {
            return false;
        }

        try {
            $accessToken = $this->oauth->getValidAccessToken($auth);
            $calendarId = (string) ($auth['calendar_id'] ?? 'primary');
            $payload = $this->buildEventPayload($event);

            if (!empty($event['google_event_id'])) {
                $this->httpRequest(
                    'PUT',
                    self::API_BASE . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode((string) $event['google_event_id']),
                    $accessToken,
                    $payload
                );
                $this->repository->markSynced((int) $event['id'], $empresaId, (string) $event['google_event_id'], $calendarId);
            } else {
                $response = $this->httpRequest(
                    'POST',
                    self::API_BASE . '/calendars/' . rawurlencode($calendarId) . '/events',
                    $accessToken,
                    $payload
                );
                $googleEventId = (string) ($response['id'] ?? '');
                if ($googleEventId !== '') {
                    $this->repository->markSynced((int) $event['id'], $empresaId, $googleEventId, $calendarId);
                }
            }

            return true;
        } catch (\Throwable $e) {
            $this->repository->markSyncError((int) $event['id'], $empresaId, $e->getMessage());
            return false;
        }
    }

    /**
     * Borra un evento remoto en Google Calendar.
     * Se invoca desde AgendaProyectorService cuando el evento local entra en papelera.
     */
    public function deleteRemote(array $event): bool
    {
        if (empty($event['google_event_id'])) {
            return true; // nada que borrar
        }

        $empresaId = (int) $event['empresa_id'];
        $usuarioId = isset($event['usuario_id']) && $event['usuario_id'] !== null ? (int) $event['usuario_id'] : null;

        $auth = $this->oauth->getActiveAuth($empresaId, $usuarioId);
        if ($auth === null) {
            return false;
        }

        try {
            $accessToken = $this->oauth->getValidAccessToken($auth);
            $calendarId = (string) ($auth['calendar_id'] ?? 'primary');

            $this->httpRequest(
                'DELETE',
                self::API_BASE . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode((string) $event['google_event_id']),
                $accessToken,
                null
            );
            return true;
        } catch (\Throwable $e) {
            // No re-lanzamos. Si Google devuelve 404 (evento ya no existe) o 410 (gone),
            // lo tratamos como exito silencioso para no frenar el borrado local.
            return false;
        }
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

        // Marca visible en Google con el tipo de origen, para que el operador identifique el evento
        $origen = (string) ($event['origen_tipo'] ?? 'manual');
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

        // DELETE devuelve 204 sin body
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
