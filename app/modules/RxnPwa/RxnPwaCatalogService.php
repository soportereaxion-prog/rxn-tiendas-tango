<?php

declare(strict_types=1);

namespace App\Modules\RxnPwa;

use App\Core\Database;
use PDO;

/**
 * Consolida el catálogo offline para la PWA mobile (Presupuestos).
 *
 * Entidades incluidas (Bloque A — Fase 1):
 *   - clientes (crm_clientes activos)
 *   - articulos (crm_articulos)
 *   - precios (crm_articulo_precios — todas las listas)
 *   - stocks (crm_articulo_stocks — todos los depósitos)
 *   - condiciones de venta (crm_catalogo_comercial_items, tipo='condicion_venta')
 *   - listas de precio (tipo='lista_precio')
 *   - vendedores (tipo='vendedor')
 *   - transportes (tipo='transporte')
 *   - depósitos (tipo='deposito')
 *   - clasificaciones PDS (tipo='clasificacion_pds')
 *
 * Hash: SHA-1 sobre el JSON ordenado del payload completo. Persistido en
 * `rxnpwa_catalog_versions`. Se invalida desde los syncs (artículos, clientes,
 * catálogos comerciales) llamando `RxnPwaCatalogVersionRepository::invalidate()`.
 */
class RxnPwaCatalogService
{
    private PDO $db;
    private RxnPwaCatalogVersionRepository $versions;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->versions = new RxnPwaCatalogVersionRepository();
    }

    /**
     * Devuelve {hash, generated_at, items_count, size_bytes}. Si el hash actual está
     * vacío (nunca calculado o invalidado), recalcula y persiste antes de devolver.
     */
    public function ensureVersion(int $empresaId): array
    {
        $row = $this->versions->findByEmpresa($empresaId);
        if ($row !== null && !empty($row['hash'])) {
            return [
                'hash' => (string) $row['hash'],
                'generated_at' => $row['generated_at'],
                'items_count' => (int) ($row['payload_items_count'] ?? 0),
                'size_bytes' => (int) ($row['payload_size_bytes'] ?? 0),
            ];
        }

        $payload = $this->buildPayload($empresaId);
        $json = $this->encode($payload);
        $hash = sha1($json);
        $size = strlen($json);
        $items = $this->countItems($payload);

        $this->versions->save($empresaId, $hash, $size, $items);

        return [
            'hash' => $hash,
            'generated_at' => date('Y-m-d H:i:s'),
            'items_count' => $items,
            'size_bytes' => $size,
        ];
    }

    /**
     * Devuelve el catálogo completo + hash. Si el hash está stale, recalcula.
     */
    public function getFullCatalog(int $empresaId): array
    {
        $payload = $this->buildPayload($empresaId);
        $json = $this->encode($payload);
        $hash = sha1($json);
        $size = strlen($json);
        $items = $this->countItems($payload);

        $row = $this->versions->findByEmpresa($empresaId);
        $persistedHash = $row['hash'] ?? null;

        if ($persistedHash !== $hash) {
            $this->versions->save($empresaId, $hash, $size, $items);
        }

        return [
            'hash' => $hash,
            'generated_at' => date('Y-m-d H:i:s'),
            'items_count' => $items,
            'size_bytes' => $size,
            'data' => $payload,
        ];
    }

    private function buildPayload(int $empresaId): array
    {
        return [
            'empresa_id' => $empresaId,
            'clientes' => $this->fetchClientes($empresaId),
            'articulos' => $this->fetchArticulos($empresaId),
            'precios' => $this->fetchPrecios($empresaId),
            'stocks' => $this->fetchStocks($empresaId),
            'condiciones_venta' => $this->fetchCommercialItems($empresaId, 'condicion_venta'),
            'listas_precio' => $this->fetchCommercialItems($empresaId, 'lista_precio'),
            'vendedores' => $this->fetchCommercialItems($empresaId, 'vendedor'),
            'transportes' => $this->fetchCommercialItems($empresaId, 'transporte'),
            'depositos' => $this->fetchCommercialItems($empresaId, 'deposito'),
            'clasificaciones_pds' => $this->fetchCommercialItems($empresaId, 'clasificacion_pds'),
        ];
    }

    private function fetchClientes(int $empresaId): array
    {
        $stmt = $this->db->prepare('SELECT id, codigo_tango, id_gva14_tango, razon_social, documento, email, telefono
            FROM crm_clientes
            WHERE empresa_id = :empresa_id AND deleted_at IS NULL
            ORDER BY id ASC');
        $stmt->execute([':empresa_id' => $empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function fetchArticulos(int $empresaId): array
    {
        $stmt = $this->db->prepare('SELECT id, codigo_externo, nombre, descripcion, precio, precio_lista_1, precio_lista_2
            FROM crm_articulos
            WHERE empresa_id = :empresa_id
            ORDER BY id ASC');
        $stmt->execute([':empresa_id' => $empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function fetchPrecios(int $empresaId): array
    {
        $stmt = $this->db->prepare('SELECT articulo_id, lista_codigo, precio
            FROM crm_articulo_precios
            WHERE empresa_id = :empresa_id
            ORDER BY articulo_id ASC, lista_codigo ASC');
        $stmt->execute([':empresa_id' => $empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function fetchStocks(int $empresaId): array
    {
        $stmt = $this->db->prepare('SELECT articulo_id, deposito_codigo, stock_actual
            FROM crm_articulo_stocks
            WHERE empresa_id = :empresa_id
            ORDER BY articulo_id ASC, deposito_codigo ASC');
        $stmt->execute([':empresa_id' => $empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function fetchCommercialItems(int $empresaId, string $tipo): array
    {
        $stmt = $this->db->prepare('SELECT codigo, descripcion, id_interno, payload_json
            FROM crm_catalogo_comercial_items
            WHERE empresa_id = :empresa_id AND tipo = :tipo
            ORDER BY codigo ASC');
        $stmt->execute([':empresa_id' => $empresaId, ':tipo' => $tipo]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            if (isset($row['payload_json']) && is_string($row['payload_json'])) {
                $decoded = json_decode($row['payload_json'], true);
                $row['payload'] = $decoded;
                unset($row['payload_json']);
            }
        }
        return $rows;
    }

    private function countItems(array $payload): int
    {
        $count = 0;
        foreach ($payload as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                $count += count($value);
            }
        }
        return $count;
    }

    /**
     * JSON estable: ordena las claves de cada array asociativo para que el hash
     * no cambie por reordenamientos del PDO/PHP. Las listas (arrays sin claves)
     * se serializan tal cual ya vienen ordenadas por la query.
     */
    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
