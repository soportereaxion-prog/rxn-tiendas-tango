<?php

declare(strict_types=1);

namespace App\Shared\Services;

use App\Modules\Empresas\EmpresaRepository;

class BackofficeContextService
{
    public static function currentUserSummary(): array
    {
        $userName = trim((string) ($_SESSION['user_name'] ?? ''));
        $empresaId = (int) ($_SESSION['empresa_id'] ?? 0);

        $summary = [
            'userName' => $userName !== '' ? $userName : 'Usuario',
            'empresaId' => $empresaId,
            'empresaNombre' => '',
            'storeUrl' => null,
            'storeLabel' => 'Sin tienda publica asociada',
        ];

        if ($empresaId <= 0) {
            return $summary;
        }

        $empresa = (new EmpresaRepository())->findById($empresaId);

        if ($empresa === null) {
            return $summary;
        }

        $summary['empresaNombre'] = trim((string) ($empresa->nombre ?? ''));

        if ((int) ($empresa->activa ?? 0) !== 1 || (int) ($empresa->modulo_tiendas ?? 0) !== 1) {
            $summary['storeLabel'] = 'La empresa actual no tiene Tiendas habilitado';
            return $summary;
        }

        $slug = trim((string) ($empresa->slug ?? ''));

        if ($slug === '') {
            $summary['storeLabel'] = 'La empresa actual no tiene slug publico';
            return $summary;
        }

        $summary['storeUrl'] = '/rxnTiendasIA/public/' . rawurlencode($slug);
        $summary['storeLabel'] = $summary['storeUrl'];

        return $summary;
    }
}
