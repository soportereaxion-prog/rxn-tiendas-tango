<?php

declare(strict_types=1);

namespace App\Modules\Tango\Services;

use App\Modules\EmpresaConfig\EmpresaConfig;
use App\Modules\Tango\TangoApiClient;
use RuntimeException;

class TangoProfileSnapshotService
{
    public function fetch(EmpresaConfig $config): array
    {
        $profileId = trim((string) ($config->tango_perfil_pedido_id ?? ''));
        if ($profileId === '') {
            throw new RuntimeException('No se puede sincronizar el perfil: falta el ID seleccionado.');
        }

        $client = $this->buildApiClient($config);
        $detail = $client->getPerfilPedidoById($profileId);

        if (empty($detail)) {
            throw new RuntimeException('Tango no devolvió el detalle del perfil seleccionado.');
        }

        return [
            'id_gva43_talonario_pedido' => $this->asInt($detail['ID_GVA43_TALONARIO_PEDIDO'] ?? null),
            'id_gva01' => $this->asInt($detail['ID_GVA01'] ?? null),
            'id_gva23_encabezado' => $this->asInt($detail['ID_GVA23_ENCABEZADO'] ?? null),
            'id_sta22' => $this->asInt($detail['ID_STA22'] ?? null),
            'id_gva10_encabezado' => $this->asInt($detail['ID_GVA10_ENCABEZADO'] ?? null),
            'id_gva24' => $this->asInt($detail['ID_GVA24'] ?? null),
            'moneda_habitual' => $this->normalizeMonedaFlag($detail['MONEDA_HABITUAL'] ?? null),
            'company_id' => trim((string) ($config->tango_connect_company_id ?? '')),
            'raw' => $detail,
        ];
    }

    private function buildApiClient(EmpresaConfig $config): TangoApiClient
    {
        $token = trim((string) ($config->tango_connect_token ?? ''));
        $companyId = trim((string) ($config->tango_connect_company_id ?? ''));
        $clientKey = trim((string) ($config->tango_connect_key ?? ''));
        $apiUrl = $this->resolveApiUrl(trim((string) ($config->tango_api_url ?? '')), $clientKey);

        if ($token === '' || $companyId === '' || $apiUrl === null) {
            throw new RuntimeException('No se pudo armar el cliente de Tango: falta token, empresa o URL.');
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
