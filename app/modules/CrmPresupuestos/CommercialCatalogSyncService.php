<?php
declare(strict_types=1);

namespace App\Modules\CrmPresupuestos;

use App\Modules\ClientesWeb\Services\ClienteTangoLookupService;
use App\Modules\EmpresaConfig\EmpresaConfigRepository;
use App\Modules\Tango\TangoApiClient;
use RuntimeException;

class CommercialCatalogSyncService
{
    private CommercialCatalogRepository $repository;
    private EmpresaConfigRepository $configRepository;

    public function __construct()
    {
        $this->repository = new CommercialCatalogRepository();
        $this->configRepository = EmpresaConfigRepository::forCrm();
    }

    public function sync(int $empresaId): array
    {
        $config = $this->configRepository->findByEmpresaId($empresaId);
        if ($config === null) {
            throw new RuntimeException('No existe configuracion CRM para la empresa activa.');
        }

        $token = trim((string) ($config->tango_connect_token ?? ''));
        $clientKey = trim((string) ($config->tango_connect_key ?? ''));
        $apiUrl = trim((string) ($config->tango_api_url ?? ''));
        $companyId = trim((string) ($config->tango_connect_company_id ?? '-1'));

        if ($token === '' || ($clientKey === '' && $apiUrl === '')) {
            throw new RuntimeException('La configuracion CRM no tiene Token ni Llave/URL suficientes para sincronizar catalogos comerciales.');
        }

        $finalUrl = $clientKey !== ''
            ? rtrim(sprintf('https://%s.connect.axoft.com/Api', str_replace('/', '-', $clientKey)), '/')
            : rtrim($apiUrl, '/');

        $lookupService = new ClienteTangoLookupService($finalUrl, $token, $companyId !== '' ? $companyId : '-1');
        $apiClient = new TangoApiClient($finalUrl, $token, $companyId !== '' ? $companyId : '-1', $clientKey);

        $relations = $lookupService->getRelacionCatalogs();
        $depositos = $apiClient->getMaestroDepositos();

        $stats = [];
        $stats['condicion_venta'] = $this->repository->upsertMany($empresaId, 'condicion_venta', $relations['condiciones_venta'] ?? []);
        $stats['lista_precio'] = $this->repository->upsertMany($empresaId, 'lista_precio', $relations['listas_precios'] ?? []);
        $stats['vendedor'] = $this->repository->upsertMany($empresaId, 'vendedor', $relations['vendedores'] ?? []);
        $stats['transporte'] = $this->repository->upsertMany($empresaId, 'transporte', $relations['transportes'] ?? []);
        $stats['deposito'] = $this->repository->upsertMany($empresaId, 'deposito', $this->mapDepositos($depositos));

        return $stats;
    }

    private function mapDepositos(array $depositos): array
    {
        $items = [];

        foreach ($depositos as $codigo => $descripcion) {
            $codigo = trim((string) $codigo);
            $descripcion = trim((string) $descripcion);

            if ($codigo === '' || $descripcion === '') {
                continue;
            }

            $items[] = [
                'codigo' => $codigo,
                'descripcion' => $descripcion,
                'id_interno' => is_numeric($codigo) ? (int) $codigo : null,
                'payload_json' => [
                    'codigo' => $codigo,
                    'descripcion' => $descripcion,
                ],
            ];
        }

        return $items;
    }
}
