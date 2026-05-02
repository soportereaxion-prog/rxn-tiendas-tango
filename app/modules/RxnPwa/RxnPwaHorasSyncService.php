<?php

declare(strict_types=1);

namespace App\Modules\RxnPwa;

use App\Modules\CrmHoras\HoraRepository;
use App\Modules\CrmHoras\HoraService;
use DateTimeImmutable;
use RuntimeException;

/**
 * Sync service de la PWA de Horas (turnero CrmHoras).
 *
 * Recibe el draft tal como vive en IndexedDB del cliente y lo persiste como
 * turno real reusando la lógica de `HoraService::cargarDiferido()` (los turnos
 * sincronizados desde la PWA son siempre cerrados — el cronómetro mobile graba
 * inicio + fin antes de subirlos).
 *
 * Idempotencia por `tmp_uuid_pwa`: si llega un draft con un UUID ya existente
 * para la empresa, se devuelve el id existente.
 */
class RxnPwaHorasSyncService
{
    private HoraRepository $repository;
    private HoraService $service;

    public function __construct()
    {
        $this->repository = new HoraRepository();
        $this->service = new HoraService($this->repository);
    }

    /**
     * @return array{ok:bool, id_server:int, tmp_uuid:string, created:bool}
     */
    public function syncDraft(array $draft, int $empresaId, ?int $usuarioId, string $usuarioNombre): array
    {
        $tmpUuid = $this->extractTmpUuid($draft);

        // Idempotencia.
        $existing = $this->repository->findByTmpUuidPwa($tmpUuid, $empresaId);
        if ($existing !== null) {
            return [
                'ok' => true,
                'id_server' => (int) $existing['id'],
                'tmp_uuid' => $tmpUuid,
                'created' => false,
            ];
        }

        if ($usuarioId === null || $usuarioId <= 0) {
            throw new RuntimeException('Sesión sin usuario_id válido.');
        }

        $cabecera = is_array($draft['cabecera'] ?? null) ? $draft['cabecera'] : $draft;

        $startedAt = trim((string) ($cabecera['fecha_inicio'] ?? ''));
        $endedAt   = trim((string) ($cabecera['fecha_finalizado'] ?? ''));
        if ($startedAt === '' || $endedAt === '') {
            throw new RuntimeException('El draft debe tener inicio y fin (cerrá el cronómetro antes de sincronizar).');
        }

        $concepto = trim((string) ($cabecera['concepto'] ?? ''));
        $tratativaId = (int) ($cabecera['tratativa_id'] ?? 0) ?: null;
        $clienteId = (int) ($cabecera['cliente_id'] ?? 0) ?: null;

        $descuentoSegundos = (int) ($cabecera['descuento_segundos'] ?? 0);
        if ($descuentoSegundos < 0) $descuentoSegundos = 0;
        $motivoDescuento = trim((string) ($cabecera['motivo_descuento'] ?? '')) ?: null;

        // Geo
        $geo = is_array($draft['geo'] ?? null) ? $draft['geo'] : null;
        $lat = $geo && isset($geo['lat']) && is_numeric($geo['lat']) ? (float) $geo['lat'] : null;
        $lng = $geo && isset($geo['lng']) && is_numeric($geo['lng']) ? (float) $geo['lng'] : null;
        $consent = $geo && (($geo['source'] ?? '') === 'gps' || ($geo['source'] ?? '') === 'wifi');

        try {
            $newId = $this->service->cargarDiferido(
                $empresaId,
                $usuarioId,
                $startedAt,
                $endedAt,
                $concepto !== '' ? $concepto : null,
                $lat,
                $lng,
                $consent,
                $tratativaId,
                null, // pdsId no aplica en PWA Horas
                $clienteId,
                $usuarioId, // actor = owner (la PWA siempre carga al usuario logueado)
                $descuentoSegundos,
                $motivoDescuento,
                $tmpUuid
            );
        } catch (\Throwable $e) {
            // Race: doble tap antes del primer create.
            $existing = $this->repository->findByTmpUuidPwa($tmpUuid, $empresaId);
            if ($existing !== null) {
                return [
                    'ok' => true,
                    'id_server' => (int) $existing['id'],
                    'tmp_uuid' => $tmpUuid,
                    'created' => false,
                ];
            }
            throw $e;
        }

        return [
            'ok' => true,
            'id_server' => $newId,
            'tmp_uuid' => $tmpUuid,
            'created' => true,
        ];
    }

    private function extractTmpUuid(array $draft): string
    {
        $tmpUuid = trim((string) ($draft['tmp_uuid'] ?? ''));
        if ($tmpUuid === '' || !preg_match('/^TMP-[A-Za-z0-9-]{1,64}$/', $tmpUuid)) {
            throw new RuntimeException('tmp_uuid inválido o ausente.');
        }
        return $tmpUuid;
    }
}
