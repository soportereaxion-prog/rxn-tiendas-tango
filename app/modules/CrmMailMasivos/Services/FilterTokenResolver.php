<?php

declare(strict_types=1);

namespace App\Modules\CrmMailMasivos\Services;

use DateTimeImmutable;

/**
 * FilterTokenResolver
 *
 * Reemplaza tokens dinámicos por su valor literal al momento de ejecutar
 * un reporte. El reporte se guarda en `config_json` con el token literal
 * (ej: "{{HOY}}") y este servicio lo resuelve cada vez que el ReportQueryBuilder
 * lo necesita — preview, envío real, render del bloque de contenido.
 *
 * Por qué string-in / string-out: el value original puede ser una fecha
 * (`{{HOY}}`), un literal numérico, o lo que el usuario haya tipeado.
 * Sólo tocamos los strings que contienen `{{...}}`; el resto se devuelve tal cual.
 *
 * Tokens soportados:
 *   {{HOY}}            → fecha de hoy en Y-m-d
 *   {{AYER}}           → ayer en Y-m-d
 *   {{MAÑANA}}         → mañana en Y-m-d
 *   {{AHORA}}          → datetime de ahora en Y-m-d H:i:s
 *   {{HOY-Nd}}         → hoy menos N días (ej {{HOY-7d}} = una semana atrás)
 *   {{HOY+Nd}}         → hoy más N días
 *   {{HOY-Nm}}         → hoy menos N meses
 *   {{HOY+Nm}}         → hoy más N meses
 *   {{HOY-Ny}}         → hoy menos N años
 *   {{HOY+Ny}}         → hoy más N años
 *   {{INICIO_MES}}     → primer día del mes corriente
 *   {{FIN_MES}}        → último día del mes corriente
 *   {{INICIO_ANIO}}    → 1° de enero del año corriente (alias INICIO_AÑO)
 *   {{FIN_ANIO}}       → 31 de diciembre del año corriente (alias FIN_AÑO)
 *
 * Mantenemos las claves sin tildes en el código para evitar problemas con
 * editores/copy-paste, pero aceptamos AÑO/ANIO como equivalentes.
 */
class FilterTokenResolver
{
    private DateTimeImmutable $now;

    public function __construct(?DateTimeImmutable $now = null)
    {
        $this->now = $now ?? new DateTimeImmutable('now');
    }

    /**
     * Resuelve recursivamente arrays (para IN, BETWEEN). Los valores que
     * no son strings o no contienen tokens se devuelven sin tocar.
     *
     * @param mixed $value
     * @return mixed
     */
    public function resolve($value)
    {
        if (is_array($value)) {
            return array_map(fn($v) => $this->resolve($v), $value);
        }
        if (!is_string($value) || strpos($value, '{{') === false) {
            return $value;
        }
        return preg_replace_callback(
            '/\{\{\s*([A-ZÁÉÍÓÚÑa-záéíóúñ_]+)([+\-]\d+[dmy])?\s*\}\}/u',
            function (array $m): string {
                $name = $this->normalizeName($m[1]);
                $modifier = $m[2] ?? '';
                return $this->resolveToken($name, $modifier) ?? $m[0];
            },
            $value
        );
    }

    /**
     * Lista los tokens disponibles para mostrar en el dropdown del UI.
     * Cada entrada tiene token + label legible + tipo (date|datetime).
     *
     * @return list<array{token: string, label: string, type: string}>
     */
    public static function availableTokens(): array
    {
        return [
            ['token' => '{{HOY}}',         'label' => 'Hoy',                          'type' => 'date'],
            ['token' => '{{AYER}}',        'label' => 'Ayer',                         'type' => 'date'],
            ['token' => '{{MAÑANA}}',      'label' => 'Mañana',                       'type' => 'date'],
            ['token' => '{{AHORA}}',       'label' => 'Ahora (fecha + hora)',         'type' => 'datetime'],
            ['token' => '{{HOY-7d}}',      'label' => 'Hoy − 7 días',                 'type' => 'date'],
            ['token' => '{{HOY-30d}}',     'label' => 'Hoy − 30 días',                'type' => 'date'],
            ['token' => '{{HOY-90d}}',     'label' => 'Hoy − 90 días',                'type' => 'date'],
            ['token' => '{{HOY+7d}}',      'label' => 'Hoy + 7 días',                 'type' => 'date'],
            ['token' => '{{HOY+30d}}',     'label' => 'Hoy + 30 días',                'type' => 'date'],
            ['token' => '{{HOY-1m}}',      'label' => 'Hoy − 1 mes',                  'type' => 'date'],
            ['token' => '{{HOY-3m}}',      'label' => 'Hoy − 3 meses',                'type' => 'date'],
            ['token' => '{{HOY-6m}}',      'label' => 'Hoy − 6 meses',                'type' => 'date'],
            ['token' => '{{HOY+1m}}',      'label' => 'Hoy + 1 mes',                  'type' => 'date'],
            ['token' => '{{HOY-1y}}',      'label' => 'Hoy − 1 año',                  'type' => 'date'],
            ['token' => '{{HOY+1y}}',      'label' => 'Hoy + 1 año',                  'type' => 'date'],
            ['token' => '{{INICIO_MES}}',  'label' => 'Inicio del mes corriente',     'type' => 'date'],
            ['token' => '{{FIN_MES}}',     'label' => 'Fin del mes corriente',        'type' => 'date'],
            ['token' => '{{INICIO_ANIO}}', 'label' => 'Inicio del año corriente',     'type' => 'date'],
            ['token' => '{{FIN_ANIO}}',    'label' => 'Fin del año corriente',        'type' => 'date'],
        ];
    }

    private function normalizeName(string $raw): string
    {
        $upper = mb_strtoupper($raw, 'UTF-8');
        $map = [
            'AÑO'        => 'ANIO',
            'INICIO_AÑO' => 'INICIO_ANIO',
            'FIN_AÑO'    => 'FIN_ANIO',
        ];
        return $map[$upper] ?? $upper;
    }

    private function resolveToken(string $name, string $modifier): ?string
    {
        switch ($name) {
            case 'HOY':
                return $this->applyModifier($this->now, $modifier)->format('Y-m-d');
            case 'AYER':
                return $this->now->modify('-1 day')->format('Y-m-d');
            case 'MANANA':
            case 'MAÑANA':
                return $this->now->modify('+1 day')->format('Y-m-d');
            case 'AHORA':
                return $this->now->format('Y-m-d H:i:s');
            case 'INICIO_MES':
                return $this->now->modify('first day of this month')->format('Y-m-d');
            case 'FIN_MES':
                return $this->now->modify('last day of this month')->format('Y-m-d');
            case 'INICIO_ANIO':
                return $this->now->setDate((int) $this->now->format('Y'), 1, 1)->format('Y-m-d');
            case 'FIN_ANIO':
                return $this->now->setDate((int) $this->now->format('Y'), 12, 31)->format('Y-m-d');
        }
        return null;
    }

    private function applyModifier(DateTimeImmutable $base, string $modifier): DateTimeImmutable
    {
        if ($modifier === '') {
            return $base;
        }
        if (!preg_match('/^([+\-])(\d+)([dmy])$/', $modifier, $m)) {
            return $base;
        }
        $sign = $m[1];
        $n = (int) $m[2];
        $unit = $m[3];
        $unitMap = ['d' => 'day', 'm' => 'month', 'y' => 'year'];
        return $base->modify($sign . $n . ' ' . $unitMap[$unit]);
    }
}
