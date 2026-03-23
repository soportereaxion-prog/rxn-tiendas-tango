<?php
declare(strict_types=1);
namespace App\Modules\Tango\Mappers;

use App\Modules\Articulos\Articulo;

class ArticuloMapper
{
    /**
     * Mapeador Resiliente frente a los Keys del Payload Connect
     */
    public static function fromConnectJson(array $item, int $empresaId): ?Articulo
    {
        // Tango Connect suele retornar Code / SKUCode
        $sku = $item['SKUCode'] ?? $item['Code'] ?? $item['codigo'] ?? null;
        if (empty($sku)) {
            return null; // Omitimos descartes o payloads estériles
        }

        $art = new Articulo();
        $art->empresa_id = $empresaId;
        $art->codigo_externo = trim((string)$sku);
        
        $art->nombre = trim((string)($item['Description'] ?? $item['Name'] ?? $item['nombre'] ?? 'Articulo ID '.$sku));
        $art->descripcion = $item['AdditionalDescription'] ?? $item['descripcion'] ?? null;
        $art->precio = (float)($item['Price'] ?? $item['precio'] ?? 0);
        $art->activo = 1; // Asumimos vigencia tras arribar desde el endpoint centralizado

        return $art;
    }
}
