<?php
declare(strict_types=1);

namespace App\Modules\CrmClientes\Services;

use App\Core\Context;
use App\Modules\CrmClientes\CrmClienteRepository;
use App\Modules\Tango\Repositories\TangoSyncLogRepository;
use App\Modules\Tango\TangoService;

class CrmClienteSyncService
{
    private TangoService $tangoService;
    private TangoSyncLogRepository $logRepository;
    private CrmClienteRepository $clienteRepository;

    public function __construct()
    {
        $this->tangoService = TangoService::forCrm();
        $this->logRepository = new TangoSyncLogRepository();
        $this->clienteRepository = new CrmClienteRepository();
    }

    public function syncClientes(): array
    {
        $empresaId = (int) Context::getEmpresaId();
        if ($empresaId <= 0) {
            throw new \RuntimeException('Sincronización abortada: no hay empresa activa en el contexto.');
        }

        $logId = $this->logRepository->startLog($empresaId, 'CLIENTES_CRM');
        $stats = ['recibidos' => 0, 'insertados' => 0, 'actualizados' => 0, 'omitidos' => 0];

        try {
            $page = 1;

            do {
                $dto = $this->tangoService->fetchClientes($page);
                if (!$dto->isSuccess) {
                    throw new \RuntimeException('Respuesta fallida de Tango para clientes: ' . $dto->errorMessage);
                }

                $items = $this->extractItems($dto->payload);
                foreach ($items as $item) {
                    $stats['recibidos']++;
                    $mapped = $this->mapCliente($item);
                    if ($mapped === null) {
                        $stats['omitidos']++;
                        continue;
                    }

                    $result = $this->clienteRepository->upsertFromTango($empresaId, $mapped);
                    if ($result === 'inserted') {
                        $stats['insertados']++;
                    } elseif ($result === 'updated') {
                        $stats['actualizados']++;
                    } else {
                        $stats['omitidos']++;
                    }
                }

                $page++;
            } while ($items !== []);

            $this->logRepository->endLog($logId, $stats, 'SUCCESS');
            return $stats;
        } catch (\Throwable $e) {
            $this->logRepository->endLog($logId, $stats, 'ERROR', $e->getMessage());
            throw $e;
        }
    }

    private function extractItems(mixed $payload): array
    {
        if (!is_array($payload)) {
            return [];
        }

        if (isset($payload['resultData']['list']) && is_array($payload['resultData']['list'])) {
            return $payload['resultData']['list'];
        }

        if (isset($payload['value']) && is_array($payload['value'])) {
            return $payload['value'];
        }

        if (isset($payload['list']) && is_array($payload['list'])) {
            return $payload['list'];
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            return $payload['data'];
        }

        return [];
    }

    private function mapCliente(array $item): ?array
    {
        $codigo = $this->firstNonEmpty($item, ['COD_GVA14', 'CODIGO', 'COD_CLIENTE']);
        $razonSocial = $this->firstNonEmpty($item, ['RAZON_SOCI', 'RAZON_SOCIAL', 'NOMBRE', 'NOMBRE_CLIENTE']);
        $idGva14 = $this->firstNonEmpty($item, ['ID_GVA14', 'ID']);

        if ($codigo === null && $idGva14 === null) {
            return null;
        }

        return [
            'id_gva14_tango' => $idGva14,
            'codigo_tango' => $codigo,
            'razon_social' => $razonSocial ?? ($codigo !== null ? 'Cliente ' . $codigo : 'Cliente Tango'),
            'documento' => $this->firstNonEmpty($item, ['CUIT', 'N_COMP', 'NRO_DOC', 'DOCUMENTO']),
            'email' => $this->firstNonEmpty($item, ['E_MAIL', 'EMAIL', 'MAIL']),
            'telefono' => $this->firstNonEmpty($item, ['TELEFONO_1', 'TELEFONO', 'TEL']),
            'direccion' => $this->firstNonEmpty($item, ['DOMICILIO', 'DIRECCION']),
            'activo' => $this->resolveActivo($item),
            'id_gva01_condicion_venta' => $this->firstNonEmpty($item, ['GVA01_COND_VTA']),
            'id_gva10_lista_precios' => $this->firstNonEmpty($item, ['GVA10_NRO_DE_LIS']),
            'id_gva23_vendedor' => $this->firstNonEmpty($item, ['GVA23_CODIGO']),
            'id_gva24_transporte' => $this->firstNonEmpty($item, ['GVA24_CODIGO']),
            'id_gva01_tango' => $this->firstNonEmpty($item, ['ID_GVA01']),
            'id_gva10_tango' => $this->firstNonEmpty($item, ['ID_GVA10']),
            'id_gva23_tango' => $this->firstNonEmpty($item, ['ID_GVA23']),
            'id_gva24_tango' => $this->firstNonEmpty($item, ['ID_GVA24']),
        ];
    }

    private function firstNonEmpty(array $item, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $item) && $item[$key] !== null && trim((string) $item[$key]) !== '') {
                return $item[$key];
            }
        }

        return null;
    }

    private function resolveActivo(array $item): int
    {
        if (array_key_exists('ACTIVO', $item)) {
            return $this->isTruthy($item['ACTIVO']) ? 1 : 0;
        }

        if (array_key_exists('HABILITADO', $item)) {
            return $this->isTruthy($item['HABILITADO']) ? 1 : 0;
        }

        if (array_key_exists('INHABILITADO', $item)) {
            return $this->isTruthy($item['INHABILITADO']) ? 0 : 1;
        }

        return 1;
    }

    private function isTruthy(mixed $value): bool
    {
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 't', 's', 'si', 'y', 'yes'], true);
    }
}
