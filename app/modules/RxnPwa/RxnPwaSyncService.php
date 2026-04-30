<?php

declare(strict_types=1);

namespace App\Modules\RxnPwa;

use App\Modules\CrmClientes\CrmClienteRepository;
use App\Modules\CrmPresupuestos\CommercialCatalogRepository;
use App\Modules\CrmPresupuestos\PresupuestoRepository;
use DateTimeImmutable;
use RuntimeException;

/**
 * Sync service de la PWA mobile (Iteración 42 — Fase 3 / Bloque C).
 *
 * Recibe el draft tal como vive en IndexedDB del cliente y lo persiste como
 * presupuesto real reusando `PresupuestoRepository::create()`.
 *
 * Idempotencia: si llega un draft con un `tmp_uuid_pwa` que YA existe para la
 * empresa, se devuelve el id existente sin crear un duplicado. Esto permite que
 * el cliente reintente N veces ante red intermitente sin generar basura.
 *
 * Validación mínima: se delega lo más posible al server. El cliente PWA solo
 * tiene catálogo en cache; todo lo que llega es "lo que el operador eligió".
 * Si los snapshots de cliente/lista/etc. no se pueden re-resolver server-side
 * (ej: el cliente fue borrado entre la creación offline y el sync), se usa el
 * snapshot que llegó del cliente PWA — la cabecera ya tiene los nombres
 * congelados al momento de la creación.
 */
class RxnPwaSyncService
{
    private PresupuestoRepository $presupuestoRepository;
    private CrmClienteRepository $clienteRepository;
    private CommercialCatalogRepository $catalogRepository;

    public function __construct()
    {
        $this->presupuestoRepository = new PresupuestoRepository();
        $this->clienteRepository = new CrmClienteRepository();
        $this->catalogRepository = new CommercialCatalogRepository();
    }

    /**
     * Sincroniza un draft de la PWA. Idempotente por tmp_uuid_pwa.
     *
     * @param array $draft  Draft tal como llega del cliente (JSON decoded).
     * @param int $empresaId
     * @param ?int $usuarioId
     * @param string $usuarioNombre
     * @return array{ok:bool, id_server:int, numero:int, tmp_uuid:string, created:bool}
     * @throws RuntimeException
     */
    public function syncDraft(array $draft, int $empresaId, ?int $usuarioId, string $usuarioNombre): array
    {
        $tmpUuid = $this->extractTmpUuid($draft);

        // Idempotencia: ¿ya está sincronizado?
        $existing = $this->presupuestoRepository->findByTmpUuidPwa($tmpUuid, $empresaId);
        if ($existing !== null) {
            return [
                'ok' => true,
                'id_server' => (int) $existing['id'],
                'numero' => (int) $existing['numero'],
                'tmp_uuid' => $tmpUuid,
                'created' => false,
            ];
        }

        $payload = $this->buildPayload($draft, $empresaId, $usuarioId, $usuarioNombre, $tmpUuid);

        try {
            $presupuestoId = $this->presupuestoRepository->create($payload);
        } catch (\Throwable $e) {
            // Si entre el findByTmpUuidPwa y el create otro request entró el mismo
            // draft (race condition extrema con doble tap del usuario), el UNIQUE de
            // tmp_uuid_pwa va a tirar excepción. En ese caso reintentamos la búsqueda.
            $existing = $this->presupuestoRepository->findByTmpUuidPwa($tmpUuid, $empresaId);
            if ($existing !== null) {
                return [
                    'ok' => true,
                    'id_server' => (int) $existing['id'],
                    'numero' => (int) $existing['numero'],
                    'tmp_uuid' => $tmpUuid,
                    'created' => false,
                ];
            }
            throw $e;
        }

        $row = $this->presupuestoRepository->findById($presupuestoId, $empresaId);

        return [
            'ok' => true,
            'id_server' => $presupuestoId,
            'numero' => (int) ($row['numero'] ?? 0),
            'tmp_uuid' => $tmpUuid,
            'created' => true,
        ];
    }

    /* ------------------------------------------------------------------ */

    private function extractTmpUuid(array $draft): string
    {
        $tmpUuid = trim((string) ($draft['tmp_uuid'] ?? ''));
        if ($tmpUuid === '' || !preg_match('/^TMP-[A-Za-z0-9-]{1,64}$/', $tmpUuid)) {
            throw new RuntimeException('tmp_uuid inválido o ausente.');
        }
        return $tmpUuid;
    }

    private function buildPayload(array $draft, int $empresaId, ?int $usuarioId, string $usuarioNombre, string $tmpUuid): array
    {
        $cabecera = is_array($draft['cabecera'] ?? null) ? $draft['cabecera'] : [];
        $renglones = is_array($draft['renglones'] ?? null) ? $draft['renglones'] : [];

        // Cliente: re-resolver para hidratar snapshot fresco; si falla, usar el snapshot del draft.
        $clienteId = (int) ($cabecera['cliente_id'] ?? 0);
        if ($clienteId <= 0) {
            throw new RuntimeException('El draft no tiene cliente seleccionado.');
        }
        $cliente = $this->clienteRepository->findById($clienteId, $empresaId);
        $clienteSnapshot = is_array($cabecera['cliente_data'] ?? null) ? $cabecera['cliente_data'] : [];
        $clienteNombre = (string) ($cliente['razon_social'] ?? $clienteSnapshot['razon_social'] ?? '');
        if ($clienteNombre === '') {
            $clienteNombre = 'Cliente #' . $clienteId;
        }
        $clienteDocumento = (string) ($cliente['documento'] ?? $clienteSnapshot['documento'] ?? '');

        // Lista de precios: obligatoria. Resolvemos del catálogo para tener id_interno + descripción.
        $listaCodigo = trim((string) ($cabecera['lista_codigo'] ?? ''));
        if ($listaCodigo === '') {
            throw new RuntimeException('El draft no tiene lista de precios seleccionada.');
        }
        $lista = $this->resolveCatalogItem($empresaId, 'lista_precio', $listaCodigo);

        $deposito = $this->resolveCatalogItem($empresaId, 'deposito', (string) ($cabecera['deposito_codigo'] ?? ''));
        $condicion = $this->resolveCatalogItem($empresaId, 'condicion_venta', (string) ($cabecera['condicion_codigo'] ?? ''));
        $vendedor = $this->resolveCatalogItem($empresaId, 'vendedor', (string) ($cabecera['vendedor_codigo'] ?? ''));
        $transporte = $this->resolveCatalogItem($empresaId, 'transporte', (string) ($cabecera['transporte_codigo'] ?? ''));
        $clasificacionCodigo = trim((string) ($cabecera['clasificacion_codigo'] ?? ''));
        if ($clasificacionCodigo === '') {
            throw new RuntimeException('El draft no tiene clasificación seleccionada.');
        }
        $clasificacionDescripcion = trim((string) ($cabecera['clasificacion_descripcion'] ?? ''));

        // Items: normalizar y calcular totales server-side (no confiar en el cliente).
        $itemsNormalizados = $this->normalizeItems($renglones, $listaCodigo);
        if ($itemsNormalizados === []) {
            throw new RuntimeException('El draft no tiene renglones.');
        }
        $totales = $this->calculateTotals($itemsNormalizados);

        // Fecha del draft o fallback al ahora.
        $fechaInput = trim((string) ($draft['created_at'] ?? ''));
        $fecha = $this->parseDateTime($fechaInput) ?? new DateTimeImmutable();

        return [
            'empresa_id' => $empresaId,
            'tratativa_id' => null,
            'tmp_uuid_pwa' => $tmpUuid,
            'usuario_id' => $usuarioId,
            'usuario_nombre' => $usuarioNombre,
            'fecha' => $fecha->format('Y-m-d H:i:s'),
            'estado' => 'borrador',
            'cliente_id' => $clienteId,
            'cliente_nombre_snapshot' => $clienteNombre,
            'cliente_documento_snapshot' => $clienteDocumento !== '' ? $clienteDocumento : null,
            'deposito_codigo' => $deposito['codigo'] ?? null,
            'deposito_nombre_snapshot' => $deposito['descripcion'] ?? null,
            'condicion_codigo' => $condicion['codigo'] ?: null,
            'condicion_nombre_snapshot' => $condicion['descripcion'] ?: null,
            'condicion_id_interno' => $condicion['id_interno'] ?? null,
            'transporte_codigo' => $transporte['codigo'] ?: null,
            'transporte_nombre_snapshot' => $transporte['descripcion'] ?: null,
            'transporte_id_interno' => $transporte['id_interno'] ?? null,
            'lista_codigo' => $lista['codigo'],
            'lista_nombre_snapshot' => $lista['descripcion'],
            'lista_id_interno' => $lista['id_interno'] ?? null,
            'vendedor_codigo' => $vendedor['codigo'] ?: null,
            'vendedor_nombre_snapshot' => $vendedor['descripcion'] ?: null,
            'vendedor_id_interno' => $vendedor['id_interno'] ?? null,
            'clasificacion_codigo' => $clasificacionCodigo,
            'clasificacion_id_tango' => '',
            'clasificacion_descripcion' => $clasificacionDescripcion,
            'cotizacion' => 1.0,
            'proximo_contacto' => null,
            'vigencia' => null,
            'leyenda_1' => null,
            'leyenda_2' => null,
            'leyenda_3' => null,
            'leyenda_4' => null,
            'leyenda_5' => null,
            'comentarios' => $this->stringOrNull($cabecera['comentarios'] ?? null),
            'observaciones' => $this->stringOrNull($cabecera['observaciones'] ?? null),
            'subtotal' => $totales['subtotal'],
            'descuento_total' => $totales['descuento_total'],
            'impuestos_total' => 0.0,
            'total' => $totales['total'],
            'items' => $totales['items'],
        ];
    }

    /**
     * Resuelve un código de catálogo comercial → fila completa.
     * Si no se encuentra, devuelve un array vacío con el código como descripción
     * (defensivo: el draft viajó offline, el catálogo pudo cambiar entre tanto).
     */
    private function resolveCatalogItem(int $empresaId, string $tipo, string $codigo): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return ['codigo' => '', 'descripcion' => '', 'id_interno' => null];
        }
        $row = $this->catalogRepository->findOption($empresaId, $tipo, $codigo);
        if ($row === null) {
            return ['codigo' => $codigo, 'descripcion' => $codigo, 'id_interno' => null];
        }
        // OJO: `id_interno` es la columna que mapea contra ID_GVA01/10/23/24 de Tango.
        // NO usar $row['id'] (que es el PK auto-increment de crm_catalogo_comercial_items).
        // Bug encontrado en release 1.35.1 — Tango rechazaba con "No existe condición de
        // venta para el ID_GVA01 ingresado: <pk_local>".
        return [
            'codigo' => (string) ($row['codigo'] ?? $codigo),
            'descripcion' => (string) ($row['descripcion'] ?? $codigo),
            'id_interno' => isset($row['id_interno']) && $row['id_interno'] !== null ? (int) $row['id_interno'] : null,
        ];
    }

    /**
     * Normaliza renglones del draft (shape PWA) al shape que espera el repo.
     * Acepta tanto los nombres del PWA (`articulo_id`, `cantidad`, `precio_unitario`,
     * `descuento_pct`, `subtotal`) como los del backend (`bonificacion_porcentaje`,
     * `importe_neto`).
     */
    private function normalizeItems(array $renglones, string $listaCodigo): array
    {
        $items = [];
        $orden = 0;

        foreach ($renglones as $r) {
            if (!is_array($r)) {
                continue;
            }
            $articuloCodigo = trim((string) ($r['codigo'] ?? $r['articulo_codigo'] ?? ''));
            $articuloDescripcion = trim((string) ($r['descripcion'] ?? $r['articulo_descripcion'] ?? ''));

            if ($articuloCodigo === '' && $articuloDescripcion === '') {
                continue;
            }

            $cantidad = (float) ($r['cantidad'] ?? 0);
            if ($cantidad <= 0) {
                continue;
            }
            $precio = (float) ($r['precio_unitario'] ?? 0);
            $bonifPct = (float) ($r['descuento_pct'] ?? $r['bonificacion_porcentaje'] ?? 0);
            if ($bonifPct < 0) $bonifPct = 0;
            if ($bonifPct > 100) $bonifPct = 100;

            $orden++;
            $items[] = [
                'orden' => $orden,
                'articulo_id' => isset($r['articulo_id']) ? (int) $r['articulo_id'] : null,
                'articulo_codigo' => $articuloCodigo,
                'articulo_descripcion' => $articuloDescripcion,
                'articulo_descripcion_original' => $articuloDescripcion,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'bonificacion_porcentaje' => $bonifPct,
                'precio_origen' => 'pwa',
                'lista_codigo_aplicada' => $listaCodigo,
            ];
        }

        return $items;
    }

    /**
     * Calcula totales server-side y devuelve los items con importe_bruto/neto correctos.
     */
    private function calculateTotals(array $items): array
    {
        $subtotal = 0.0;
        $descuentoTotal = 0.0;
        $total = 0.0;
        $out = [];

        foreach ($items as $item) {
            $cantidad = (float) $item['cantidad'];
            $precio = (float) $item['precio_unitario'];
            $bonifPct = (float) $item['bonificacion_porcentaje'];

            $bruto = round($cantidad * $precio, 2);
            $descuento = round($bruto * ($bonifPct / 100), 2);
            $neto = round($bruto - $descuento, 2);

            $subtotal += $bruto;
            $descuentoTotal += $descuento;
            $total += $neto;

            $item['importe_bruto'] = $bruto;
            $item['importe_neto'] = $neto;
            $out[] = $item;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'descuento_total' => round($descuentoTotal, 2),
            'total' => round($total, 2),
            'items' => $out,
        ];
    }

    private function parseDateTime(string $input): ?DateTimeImmutable
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }
        foreach (['Y-m-d\TH:i:s.uP', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'] as $fmt) {
            $dt = DateTimeImmutable::createFromFormat($fmt, $input);
            if ($dt instanceof DateTimeImmutable) {
                return $dt;
            }
        }
        try {
            return new DateTimeImmutable($input);
        } catch (\Throwable) {
            return null;
        }
    }

    private function stringOrNull($value): ?string
    {
        if ($value === null) return null;
        $s = trim((string) $value);
        return $s === '' ? null : $s;
    }
}
