<?php
declare(strict_types=1);

namespace App\Shared\Services;

use App\Modules\Empresas\EmpresaAccessService;

class OperationalAreaService
{
    public const AREA_TIENDAS = 'tiendas';
    public const AREA_CRM = 'crm';

    public static function resolveFromRequest(?string $fallback = null): string
    {
        $candidate = self::normalizeArea($_GET['area'] ?? $_POST['area'] ?? null);
        if ($candidate !== null && self::hasAccess($candidate)) {
            return $candidate;
        }

        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if (str_contains($referer, '/mi-empresa/crm/')) {
            return self::AREA_CRM;
        }

        if (str_contains($referer, '/mi-empresa/')) {
            return self::AREA_TIENDAS;
        }

        $fallback = self::normalizeArea($fallback);
        if ($fallback !== null && self::hasAccess($fallback)) {
            return $fallback;
        }

        if (EmpresaAccessService::hasTiendasAccess()) {
            return self::AREA_TIENDAS;
        }

        if (EmpresaAccessService::hasCrmAccess()) {
            return self::AREA_CRM;
        }

        return self::AREA_TIENDAS;
    }

    public static function appendArea(string $path, string $area): string
    {
        $separator = str_contains($path, '?') ? '&' : '?';
        return $path . $separator . 'area=' . rawurlencode($area);
    }

    public static function dashboardPath(string $area): string
    {
        return $area === self::AREA_CRM
            ? '/mi-empresa/crm/dashboard'
            : '/mi-empresa/dashboard';
    }

    public static function helpPath(string $area): string
    {
        return self::appendArea('/mi-empresa/ayuda', $area);
    }

    public static function usersPath(string $area): string
    {
        return self::appendArea('/mi-empresa/usuarios', $area);
    }

    public static function profilePath(string $area): string
    {
        return self::appendArea('/mi-perfil', $area);
    }

    public static function environmentLabel(string $area): string
    {
        return $area === self::AREA_CRM
            ? 'Entorno Operativo de CRM'
            : 'Entorno Operativo de Tiendas';
    }

    private static function hasAccess(string $area): bool
    {
        return $area === self::AREA_CRM
            ? EmpresaAccessService::hasCrmAccess()
            : EmpresaAccessService::hasTiendasAccess();
    }

    private static function normalizeArea(mixed $area): ?string
    {
        if (!is_string($area)) {
            return null;
        }

        $area = strtolower(trim($area));
        return in_array($area, [self::AREA_TIENDAS, self::AREA_CRM], true) ? $area : null;
    }
}
