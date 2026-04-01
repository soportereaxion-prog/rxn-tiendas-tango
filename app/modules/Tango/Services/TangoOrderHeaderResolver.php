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
        
        $cached = $this->getCachedProfileDetail($config, $profileId, $user);
        if ($cached !== null) {
            $profileDetail = $cached;
        } else {
            $profileDetail = $this->fetchProfileDetail($config, $profileId, $user);
        }

        $profileDetailLower = array_change_key_case($profileDetail, CASE_LOWER);
        $rawLower = isset($profileDetail['raw']) && is_array($profileDetail['raw']) 
            ? array_change_key_case($profileDetail['raw'], CASE_LOWER) 
            : [];

        $getProp = function(...$keys) use ($profileDetailLower, $rawLower) {
            foreach ($keys as $key) {
                $lower = strtolower($key);
                if (isset($profileDetailLower[$lower])) {
                    return $profileDetailLower[$lower];
                }
                if (isset($rawLower[$lower])) {
                    return $rawLower[$lower];
                }
            }
            return null;
        };

        $resolved = [
            'ID_PERFIL_PEDIDO' => $this->firstPositiveInt([
                $profileId,
                self::LEGACY_ID_PERFIL_PEDIDO,
            ]),
            'ID_GVA43_TALON_PED' => $this->firstPositiveInt([
                $getProp('ID_GVA43_TALONARIO_PEDIDO', 'ID_GVA43_TALON_PED', 'ID_GVA43'),
                $config->tango_pds_talonario_id ?? null,
                $profileId ? null : self::LEGACY_ID_GVA43_TALON_PED,
            ]),
            'ID_GVA01' => $this->firstPositiveInt([
                $getProp('ID_GVA01_VENDEDOR', 'ID_GVA01'),
                $cliente['id_gva01_tango'] ?? null,
            ]),
            'ID_GVA23' => $this->firstPositiveInt([
                $getProp('ID_GVA23_ENCABEZADO', 'ID_GVA23_CONDICION_VENTA', 'ID_GVA23'),
                $cliente['id_gva23_tango'] ?? null,
            ]),
            'ID_STA22' => $this->firstPositiveInt([
                $getProp('ID_STA22_DEPOSITO', 'ID_STA22'),
                $config->deposito_codigo ?? null,
                $profileId ? null : self::LEGACY_ID_STA22,
            ]),
            'ID_GVA10' => $this->firstPositiveInt([
                $getProp('ID_GVA10_ENCABEZADO', 'ID_GVA10_ZONA', 'ID_GVA10'),
                $cliente['id_gva10_tango'] ?? null,
            ]),
            'ID_GVA24' => $this->firstPositiveInt([
                $getProp('ID_GVA24_TRANSPORTE', 'ID_GVA24'),
                $cliente['id_gva24_tango'] ?? null,
            ]),
            'ID_GVA81' => $this->firstPositiveInt([
                $getProp('ID_GVA81_ENCABEZADO', 'ID_GVA81_CLASIFICACION', 'ID_GVA81'),
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

        $val = $profileDetail['MONEDA_HABITUAL'] ?? $profileDetail['moneda_habitual'] ?? ($profileDetail['raw']['MONEDA_HABITUAL'] ?? '');
        $flag = strtoupper(trim((string) $val));

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

    private function getCachedProfileDetail(object $config, ?int $profileId, ?\App\Modules\Auth\Usuario $user = null): ?array
    {
        if ($profileId === null) {
            return null;
        }

        if ($user !== null && $this->normalizePositiveInt($user->tango_perfil_pedido_id) === $profileId) {
            $snap = $this->decodeSnapshotJson($user->tango_perfil_snapshot_json ?? null);
            if (!empty($snap)) {
                // Si el snapshot tiene el raw anidado, mergearlo al nivel raíz
                // para que getProp encuentre ID_GVA43_TALONARIO_PEDIDO directamente
                if (!empty($snap['raw']) && is_array($snap['raw'])) {
                    return array_merge($snap['raw'], $snap);
                }
                return $snap;
            }
        }

        $raw = $this->decodeSnapshotJson($config->tango_perfil_snapshot_json ?? null);
        $snapshotCompany = trim((string) ($raw['company_id'] ?? ''));
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

        $configSnap = $this->decodeSnapshotJson($config->tango_perfil_snapshot_json ?? null);
        if (empty($typedValues) && empty($configSnap)) {
            return null;
        }
        // Mergear raw al nivel raíz para que getProp encuentre keys exactas de la API
        $configRaw = (!empty($configSnap['raw']) && is_array($configSnap['raw'])) ? $configSnap['raw'] : [];
        return array_merge($configRaw, $configSnap, $typedValues);
    }

    private function decodeSnapshotJson(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            // Recovere from corrupted cache that stored the whole AJAX response instead of just the payload
            if (isset($decoded['success']) && isset($decoded['data']) && is_array($decoded['data'])) {
                return $decoded['data'];
            }
            return $decoded;
        }
        return [];
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
