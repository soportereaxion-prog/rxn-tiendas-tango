<?php
declare(strict_types=1);

namespace App\Modules\CrmPedidosServicio;

use App\Modules\EmpresaConfig\EmpresaConfigRepository;
use App\Modules\Empresas\EmpresaRepository;

class PedidoServicioPrintContextBuilder
{
    private EmpresaRepository $empresaRepository;
    private EmpresaConfigRepository $configRepository;

    public function __construct()
    {
        $this->empresaRepository = new EmpresaRepository();
        $this->configRepository  = EmpresaConfigRepository::forCrm();
    }

    public function build(int $empresaId, array $pedido): array
    {
        $empresa = $this->empresaRepository->findById($empresaId);
        $config  = $this->configRepository->findByEmpresaId($empresaId);

        $empresaNombre = trim((string) ($config?->nombre_fantasia ?? ''));
        if ($empresaNombre === '') {
            $empresaNombre = trim((string) ($empresa?->razon_social ?? ''));
        }
        if ($empresaNombre === '') {
            $empresaNombre = trim((string) ($empresa?->nombre ?? ''));
        }

        $duracionBruta  = isset($pedido['duracion_bruta_segundos']) ? (int) $pedido['duracion_bruta_segundos'] : null;
        $duracionNeta   = isset($pedido['duracion_neta_segundos'])  ? (int) $pedido['duracion_neta_segundos']  : null;
        $descuentoSeg   = isset($pedido['descuento_segundos'])      ? (int) $pedido['descuento_segundos']      : null;
        $tiempoDecimal  = isset($pedido['tiempo_decimal']) && $pedido['tiempo_decimal'] !== null
            ? (float) $pedido['tiempo_decimal']
            : PedidoServicioTangoService::decimalHoursFromSeconds($duracionNeta);

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
                'nombre'    => trim((string) ($pedido['cliente_nombre'] ?? '')),
                'documento' => trim((string) ($pedido['cliente_documento'] ?? '')),
                'email'     => trim((string) ($pedido['cliente_email'] ?? '')),
            ],
            'articulo' => [
                'codigo' => trim((string) ($pedido['articulo_codigo'] ?? '')),
                'nombre' => trim((string) ($pedido['articulo_nombre'] ?? '')),
                'precio' => $this->formatMoney(
                    isset($pedido['articulo_precio_unitario']) && $pedido['articulo_precio_unitario'] !== null
                        ? (float) $pedido['articulo_precio_unitario']
                        : 0.0
                ),
            ],
            'pedido' => [
                'numero'            => $this->formatNumero($pedido['numero'] ?? null),
                'solicito'          => trim((string) ($pedido['solicito'] ?? '')),
                'fecha_inicio'      => $this->formatDate($pedido['fecha_inicio'] ?? null),
                'fecha_finalizado'  => $this->formatDate($pedido['fecha_finalizado'] ?? null),
                'clasificacion'     => $this->formatClasificacion(
                    (string) ($pedido['clasificacion_codigo'] ?? ''),
                    (string) ($pedido['clasificacion_descripcion'] ?? '')
                ),
                'nro_pedido_tango'  => trim((string) ($pedido['nro_pedido'] ?? '')),
                'diagnostico'       => trim((string) ($pedido['diagnostico'] ?? '')),
                'motivo_descuento'             => trim((string) ($pedido['motivo_descuento'] ?? '')),
                'estado'            => empty($pedido['fecha_finalizado']) ? 'Abierto' : 'Finalizado',
            ],
            'tiempos' => [
                'duracion_bruta' => $duracionBruta !== null ? $this->formatDuration($duracionBruta) : '--:--:--',
                'duracion_neta'  => $duracionNeta  !== null ? $this->formatDuration($duracionNeta)  : '--:--:--',
                'descuento'      => $descuentoSeg  !== null ? $this->formatDuration($descuentoSeg)  : '00:00:00',
                'decimal'        => $tiempoDecimal !== null ? number_format($tiempoDecimal, 2, ',', '.') . ' hs' : '--',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Formatters
    // -------------------------------------------------------------------------

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
            return '';
        }

        try {
            $dt = new \DateTimeImmutable($date);
            return $dt->format('d/m/Y H:i');
        } catch (\Throwable) {
            return trim($date);
        }
    }

    private function formatMoney(float $value): string
    {
        return '$ ' . number_format($value, 2, ',', '.');
    }

    private function formatDuration(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    private function formatClasificacion(string $codigo, string $descripcion): string
    {
        $codigo      = strtoupper(trim($codigo));
        $descripcion = trim($descripcion);

        if ($codigo === '' && $descripcion === '') {
            return '';
        }

        if ($codigo === '') {
            return $descripcion;
        }

        if ($descripcion === '') {
            return $codigo;
        }

        return $codigo . ' — ' . $descripcion;
    }
}
