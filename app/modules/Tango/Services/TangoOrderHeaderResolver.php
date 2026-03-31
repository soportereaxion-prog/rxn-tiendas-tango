<?php

declare(strict_types=1);

namespace App\Modules\Tango\Services;

use App\Modules\EmpresaConfig\EmpresaConfigService;
use App\Modules\Tango\TangoApiClient;

class TangoOrderHeaderResolver
{
    private const LEGACY_ID_GVA43_TALON_PED = 6;
    private const LEGACY_ID_STA22 = 1;
    private const LEGACY_ID_PERFIL_PEDIDO = 1;
    private const LEGACY_ID_MONEDA = 1;
    private const MONEDA_LOCAL_FLAG = 'C';
    private const MONEDA_LOCAL_ID = 1;
    private const MONEDA_EXTRANJERA_ID = 0;
    private const MONEDA_LOCAL_OVERRIDE_BY_COMPANY = [
        '351' => 2,
    ];

    private EmpresaConfigService $configService;

    public function __construct(string $area = 'tiendas')
    {
        $this->configService = EmpresaConfigService::forArea($this->normalizeArea($area));
    }

    public function resolveForCurrentContext(array $cliente): array
    {
        $currentUser = \App\Modules\Auth\AuthService::getCurrentUser();
        return $this->resolveFromConfig($this->configService->getConfig(), $cliente, $currentUser);
    }

    public function resolveFromConfig(object $config, array $cliente, ?\App\Modules\Auth\Usuario $user = null): array
    {
        $profileId = $this->normalizePositiveInt($user->tango_perfil_pedido_id ?? $config->tango_perfil_pedido_id ?? null);
        $profileDetail = $this->fetchProfileDetail($config, $profileId, $user);

        $resolved = [
            'ID_PERFIL_PEDIDO' => $this->firstPositiveInt([
                $profileId,
                self::LEGACY_ID_PERFIL_PEDIDO,
            ]),
            'ID_GVA43_TALON_PED' => $this->firstPositiveInt([
                $profileDetail['ID_GVA43_TALONARIO_PEDIDO'] ?? null,
                $config->tango_pds_talonario_id ?? null,
                self::LEGACY_ID_GVA43_TALON_PED,
            ]),
            'ID_GVA01' => $this->firstPositiveInt([
                $profileDetail['ID_GVA01'] ?? null,
                $cliente['id_gva01_tango'] ?? null,
            ]),
            'ID_GVA23' => $this->firstPositiveInt([
                $profileDetail['ID_GVA23_ENCABEZADO'] ?? null,
                $cliente['id_gva23_tango'] ?? null,
            ]),
            'ID_STA22' => $this->firstPositiveInt([
                $profileDetail['ID_STA22'] ?? null,
                $config->deposito_codigo ?? null,
                self::LEGACY_ID_STA22,
            ]),
            'ID_GVA10' => $this->firstPositiveInt([
                $profileDetail['ID_GVA10_ENCABEZADO'] ?? null,
                $cliente['id_gva10_tango'] ?? null,
            ]),
            'ID_GVA24' => $this->firstPositiveInt([
                $profileDetail['ID_GVA24'] ?? null,
                $cliente['id_gva24_tango'] ?? null,
            ]),
            'ID_MONEDA' => $this->resolveCurrencyId($profileDetail, $config),
        ];

        if (!array_key_exists('ID_MONEDA', $resolved) || $resolved['ID_MONEDA'] === null) {
            $resolved['ID_MONEDA'] = self::LEGACY_ID_MONEDA;
        }

        return array_filter($resolved, static fn (mixed $value): bool => $value !== null);
    }

    private function resolveCurrencyId(array $profileDetail, object $config): ?int
    {
        $companyId = trim((string) ($config->tango_connect_company_id ?? ''));
        if ($companyId !== '' && isset(self::MONEDA_LOCAL_OVERRIDE_BY_COMPANY[$companyId])) {
            return self::MONEDA_LOCAL_OVERRIDE_BY_COMPANY[$companyId];
        }

        $flag = strtoupper(trim((string) ($profileDetail['MONEDA_HABITUAL'] ?? '')));

        if ($flag === self::MONEDA_LOCAL_FLAG) {
            return self::MONEDA_LOCAL_ID;
        }

        if ($flag !== '') {
            return self::MONEDA_EXTRANJERA_ID;
        }

        return null;
    }

    private function fetchProfileDetail(object $config, ?int $profileId, ?\App\Modules\Auth\Usuario $user = null): array
    {
        if ($profileId === null) {
            return [];
        }

        // DESACTIVADO TEMPORALMENTE (Consumo al vuelo para pruebas)
        /*
        $cached = $this->getCachedProfileDetail($config, $profileId, $user);
        if ($cached !== null) {
            return $cached;
        }
        */

        $client = $this->buildApiClient($config);
        if ($client === null) {
            return [];
        }

        try {
            return $client->getPerfilPedidoById($profileId) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function getCachedProfileDetail(object $config, int $profileId, ?\App\Modules\Auth\Usuario $user = null): ?array
    {
        if ($user !== null && $this->normalizePositiveInt($user->tango_perfil_pedido_id) === $profileId) {
            $raw = $this->decodeSnapshotJson($user->tango_perfil_snapshot_json ?? null);
            if (!empty($raw)) {
                return $raw;
            }
        }

        $snapshotCompany = trim((string) ($config->tango_perfil_snapshot_company_id ?? ''));
        $currentCompany = trim((string) ($config->tango_connect_company_id ?? ''));
        if ($snapshotCompany === '' || $snapshotCompany !== $currentCompany) {
            return null;
        }

        $storedProfile = $this->normalizePositiveInt($config->tango_perfil_pedido_id ?? null);
        if ($storedProfile === null || $storedProfile !== $profileId) {
            return null;
        }

        $typedValues = array_filter([
            'ID_GVA43_TALONARIO_PEDIDO' => $this->normalizePositiveInt($config->tango_perfil_id_gva43_talon ?? null),
            'ID_GVA01' => $this->normalizePositiveInt($config->tango_perfil_id_gva01 ?? null),
            'ID_GVA23_ENCABEZADO' => $this->normalizePositiveInt($config->tango_perfil_id_gva23_encabezado ?? null),
            'ID_STA22' => $this->normalizePositiveInt($config->tango_perfil_id_sta22 ?? null),
            'ID_GVA10_ENCABEZADO' => $this->normalizePositiveInt($config->tango_perfil_id_gva10_encabezado ?? null),
            'ID_GVA24' => $this->normalizePositiveInt($config->tango_perfil_id_gva24 ?? null),
            'MONEDA_HABITUAL' => $config->tango_perfil_moneda_habitual ?? null,
        ], static fn ($value) => $value !== null && $value !== '');

        $raw = $this->decodeSnapshotJson($config->tango_perfil_snapshot_json ?? null);
        if (empty($typedValues) && empty($raw)) {
            return null;
        }

        return array_merge($raw, $typedValues);
    }

    private function decodeSnapshotJson(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function buildApiClient(object $config): ?TangoApiClient
    {
        $token = trim((string) ($config->tango_connect_token ?? ''));
        $companyId = trim((string) ($config->tango_connect_company_id ?? ''));
        $clientKey = trim((string) ($config->tango_connect_key ?? ''));
        $apiUrl = $this->resolveApiUrl(
            trim((string) ($config->tango_api_url ?? '')),
            $clientKey
        );

        if ($token === '' || $companyId === '' || $apiUrl === null) {
            return null;
        }

        return new TangoApiClient($apiUrl, $token, $companyId, $clientKey !== '' ? $clientKey : null);
    }

    private function resolveApiUrl(string $rawUrl, string $clientKey): ?string
    {
        if ($rawUrl !== '') {
            $normalized = rtrim($rawUrl, '/');
            if (!preg_match('/\/api$/i', $normalized)) {
                $normalized .= '/Api';
            }
            return $normalized;
        }

        if ($clientKey !== '') {
            return sprintf('https://%s.connect.axoft.com/Api', str_replace('/', '-', $clientKey));
        }

        return null;
    }

    private function firstPositiveInt(array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $normalized = $this->normalizePositiveInt($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizePositiveInt(mixed $value): ?int
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '' || !preg_match('/^\d+$/', $normalized)) {
            return null;
        }

        $intValue = (int) $normalized;

        return $intValue > 0 ? $intValue : null;
    }

    private function normalizeArea(string $area): string
    {
        return strtolower(trim($area)) === 'crm' ? 'crm' : 'tiendas';
    }
}
