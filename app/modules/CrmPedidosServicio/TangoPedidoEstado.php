<?php

declare(strict_types=1);

namespace App\Modules\CrmPedidosServicio;

final class TangoPedidoEstado
{
    public const APROBADO = 2;
    public const CUMPLIDO = 3;
    public const CERRADO  = 4;
    public const ANULADO  = 5;

    private const MAP = [
        self::APROBADO => ['code' => 'aprobado', 'label' => 'Aprobado', 'color' => 'success', 'icon' => 'bi-check-circle'],
        self::CUMPLIDO => ['code' => 'cumplido', 'label' => 'Cumplido', 'color' => 'info',    'icon' => 'bi-receipt'],
        self::CERRADO  => ['code' => 'cerrado',  'label' => 'Cerrado',  'color' => 'secondary','icon' => 'bi-lock'],
        self::ANULADO  => ['code' => 'anulado',  'label' => 'Anulado',  'color' => 'danger',  'icon' => 'bi-x-octagon'],
    ];

    public static function isValid(?int $estado): bool
    {
        return $estado !== null && array_key_exists($estado, self::MAP);
    }

    public static function meta(?int $estado): array
    {
        if ($estado === null || !array_key_exists($estado, self::MAP)) {
            return ['code' => 'desconocido', 'label' => 'Sin sync', 'color' => 'light', 'icon' => 'bi-question-circle'];
        }

        return self::MAP[$estado];
    }

    public static function label(?int $estado): string
    {
        return self::meta($estado)['label'];
    }

    public static function color(?int $estado): string
    {
        return self::meta($estado)['color'];
    }

    public static function code(?int $estado): string
    {
        return self::meta($estado)['code'];
    }
}
