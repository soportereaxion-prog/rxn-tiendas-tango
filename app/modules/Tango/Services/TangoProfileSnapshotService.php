<?php

declare(strict_types=1);

namespace App\Modules\Tango\Services;

use App\Modules\EmpresaConfig\EmpresaConfig;
use App\Modules\Tango\TangoApiClient;
use RuntimeException;

class TangoProfileSnapshotService
{
    public function fetch(EmpresaConfig $config, ?int $explicitProfileId = null): array
    {
        $profileId = trim((string) ($explicitProfileId ?? $config->tango_perfil_pedido_id ?? ''));
        if ($profileId === '') {
            throw new RuntimeException('No se puede sincronizar el perfil: falta el ID seleccionado.');
        }

        $client = $this->buildApiClient($config);
        $detail = $client->getPerfilPedidoById($profileId);

        if (empty($detail)) {
            throw new RuntimeException('Tango no devolvió el detalle del perfil seleccionado.');
        }

        // Lookup case-insensitive, buscando múltiples variantes de cada key
        $detailLower = array_change_key_case($detail, CASE_LOWER);
        $getProp = function(array $keys) use ($detailLower) {
            foreach ($keys as $k) {
                $v = $detailLower[strtolower($k)] ?? null;
                if ($v !== null && $v !== '') return $v;
            }
            return null;
        };

        return [
            'id_gva43_talonario_pedido' => $this->asInt($getProp([
                'ID_GVA43_TALONARIO_PEDIDO', 'ID_GVA43_TALON_PED', 'ID_GVA43',
            ])),
            'id_gva01' => $this->asInt($getProp([
                'ID_GVA01_VENDEDOR', 'ID_GVA01',
            ])),
            'id_gva23_encabezado' => $this->asInt($getProp([
                'ID_GVA23_ENCABEZADO', 'ID_GVA23_CONDICION_VENTA', 'ID_GVA23',
            ])),
            'id_sta22' => $this->asInt($getProp([
                'ID_STA22_DEPOSITO', 'ID_STA22',
            ])),
            'id_gva10_encabezado' => $this->asInt($getProp([
                'ID_GVA10_ENCABEZADO', 'ID_GVA10_ZONA', 'ID_GVA10',
            ])),
            'id_gva24' => $this->asInt($getProp([
                'ID_GVA24_TRANSPORTE', 'ID_GVA24',
            ])),
            'id_gva81_encabezado' => $this->asInt($getProp([
                'ID_GVA81_ENCABEZADO', 'ID_GVA81_CLASIFICACION', 'ID_GVA81',
            ])),
            'moneda_habitual' => $this->normalizeMonedaFlag($getProp([
                'MONEDA_HABITUAL',
            ])),
            'company_id' => trim((string) ($config->tango_connect_company_id ?? '')),
            'raw' => $detail,
            // Debug: keys reales que devolvió la API (para diagnóstico)
            'raw_keys' => array_keys($detail),
        ];
    }

    private function buildApiClient(EmpresaConfig $config): TangoApiClient
    {
        $token = trim((string) ($config->tango_connect_token ?? ''));
        $companyId = trim((string) ($config->tango_connect_company_id ?? ''));
        if ($companyId === '') {
            $companyId = '-1';
        }
        $clientKey = trim((string) ($config->tango_connect_key ?? ''));
        $apiUrl = $this->resolveApiUrl(trim((string) ($config->tango_api_url ?? '')), $clientKey);

        if ($token === '' || $apiUrl === null) {
            throw new RuntimeException('No se pudo armar el cliente de Tango: falta token o URL.');
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

    private function asInt(mixed $value): ?int
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '' || !preg_match('/^-?\d+$/', $normalized)) {
            return null;
        }

        return (int) $normalized;
    }

    private function normalizeMonedaFlag(?string $flag): ?string
    {
        $normalized = strtoupper(trim((string) $flag));
        return $normalized !== '' ? $normalized : null;
    }
}
