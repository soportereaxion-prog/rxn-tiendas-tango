<?php
declare(strict_types=1);

namespace App\Modules\CrmPresupuestos;

use App\Modules\EmpresaConfig\EmpresaConfigRepository;
use App\Modules\Empresas\EmpresaRepository;

class CrmPresupuestoPrintContextBuilder
{
    private EmpresaRepository $empresaRepository;
    private EmpresaConfigRepository $configRepository;

    public function __construct()
    {
        $this->empresaRepository = new EmpresaRepository();
        $this->configRepository = EmpresaConfigRepository::forCrm();
    }

    public function build(int $empresaId, array $presupuesto, array $items): array
    {
        $empresa = $this->empresaRepository->findById($empresaId);
        $config = $this->configRepository->findByEmpresaId($empresaId);

        $empresaNombre = trim((string) ($config?->nombre_fantasia ?? ''));
        if ($empresaNombre === '') {
            $empresaNombre = trim((string) ($empresa?->razon_social ?? ''));
        }
        if ($empresaNombre === '') {
            $empresaNombre = trim((string) ($empresa?->nombre ?? ''));
        }

        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host;

        $headerUrl = trim((string) ($config?->impresion_header_url ?? ''));
        $footerUrl = trim((string) ($config?->impresion_footer_url ?? ''));

        if ($headerUrl !== '' && !str_starts_with($headerUrl, 'http')) {
            $headerUrl = $baseUrl . (str_starts_with($headerUrl, '/') ? '' : '/') . $headerUrl;
        }

        if ($footerUrl !== '' && !str_starts_with($footerUrl, 'http')) {
            $footerUrl = $baseUrl . (str_starts_with($footerUrl, '/') ? '' : '/') . $footerUrl;
        }

        return [
            'empresa' => [
                'nombre'     => $empresaNombre,
                'cuit'       => trim((string) ($empresa?->cuit ?? '')),
                'header_url' => $headerUrl,
                'footer_url' => $footerUrl,
            ],
            'cliente' => [
                'nombre' => trim((string) ($presupuesto['cliente_nombre_snapshot'] ?? '')),
                'documento' => trim((string) ($presupuesto['cliente_documento_snapshot'] ?? '')),
            ],
            'presupuesto' => [
                'numero' => $this->formatNumero($presupuesto['numero'] ?? null),
                'fecha' => $this->formatDate($presupuesto['fecha'] ?? null),
                'deposito' => trim((string) ($presupuesto['deposito_nombre_snapshot'] ?? ($presupuesto['deposito_codigo'] ?? ''))),
                'condicion' => trim((string) ($presupuesto['condicion_nombre_snapshot'] ?? ($presupuesto['condicion_codigo'] ?? ''))),
                'transporte' => trim((string) ($presupuesto['transporte_nombre_snapshot'] ?? ($presupuesto['transporte_codigo'] ?? ''))),
                'lista' => trim((string) ($presupuesto['lista_nombre_snapshot'] ?? ($presupuesto['lista_codigo'] ?? ''))),
                'vendedor' => trim((string) ($presupuesto['vendedor_nombre_snapshot'] ?? ($presupuesto['vendedor_codigo'] ?? ''))),
                'usuario' => trim((string) ($presupuesto['usuario_nombre'] ?? 'Sin asignar')),
                'estado' => $this->formatEstado((string) ($presupuesto['estado'] ?? 'borrador')),
            ],
            'totales' => [
                'subtotal' => $this->formatMoney((float) ($presupuesto['subtotal'] ?? 0)),
                'descuento' => $this->formatMoney((float) ($presupuesto['descuento_total'] ?? 0)),
                'total' => $this->formatMoney((float) ($presupuesto['total'] ?? 0)),
            ],
            'items' => array_map(function (array $item): array {
                return [
                    'codigo' => trim((string) ($item['articulo_codigo'] ?? '')),
                    'descripcion' => trim((string) ($item['articulo_descripcion_snapshot'] ?? $item['articulo_descripcion'] ?? '')),
                    'cantidad' => $this->formatQuantity((float) ($item['cantidad'] ?? 0)),
                    'precio_unitario' => $this->formatMoney((float) ($item['precio_unitario'] ?? 0)),
                    'bonificacion' => $this->formatPercent((float) ($item['bonificacion_porcentaje'] ?? 0)),
                    'importe' => $this->formatMoney((float) ($item['importe_neto'] ?? 0)),
                ];
            }, $items),
        ];
    }

    private function formatNumero(mixed $numero): string
    {
        $numero = (int) $numero;
        if ($numero <= 0) {
            return '';
        }

        return str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }

    private function formatDate(?string $date): string
    {
        if (!is_string($date) || trim($date) === '') {
            return '--/--/---- --:--:--';
        }

        try {
            $dt = new \DateTimeImmutable($date);
            return $dt->format('d/m/Y H:i:s');
        } catch (\Throwable) {
            return trim($date);
        }
    }

    private function formatMoney(float $value): string
    {
        return '$ ' . number_format($value, 2, ',', '.');
    }

    private function formatQuantity(float $value): string
    {
        if (abs($value - round($value)) < 0.0001) {
            return number_format($value, 0, ',', '.');
        }

        return number_format($value, 4, ',', '.');
    }

    private function formatPercent(float $value): string
    {
        return number_format($value, 2, ',', '.') . ' %';
    }

    private function formatEstado(string $estado): string
    {
        return match (strtolower(trim($estado))) {
            'emitido' => 'Emitido',
            'anulado' => 'Anulado',
            default => 'Borrador',
        };
    }
}
