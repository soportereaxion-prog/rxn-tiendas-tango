<?php
declare(strict_types=1);
namespace App\Modules\Tango\Mappers;

use App\Modules\Articulos\Articulo;

class ArticuloMapper
{
    /**
     * Convierte el Arreglo Diccionario Dinámico de Axoft Connect [process=87]
     * a nuestra Entidad Articulo.
     */
    public static function fromConnectJson(array $data, int $empresaId): ?Articulo
    {
        // Identificadores base según el payload real del Process 87
        // COD_STA11 (código de producto/SKU), DESCRIPCIO (Nombre), SINONIMO (Descripción larga opcional)
        $codigoExterno = $data['COD_STA11'] ?? null;
        $nombre = $data['DESCRIPCIO'] ?? null;
        
        if (empty($codigoExterno) || empty($nombre)) {
            return null; // Imposible procesar sin clave o nombre
        }

        $articulo = new Articulo();
        $articulo->empresa_id = $empresaId;
        
        // Conservamos fielmente los espacios que la API de Tango deja en el COD_STA11 para no quebrar las primary_keys lógicas
        $articulo->codigo_externo = (string)$codigoExterno;
        $articulo->nombre = (string)$nombre;
        $articulo->descripcion = !empty($data['SINONIMO']) ? (string)$data['SINONIMO'] : null;
        
        // Mapeo defensivo del Precio
        // Buscamos PRICE, PRECIO, o equivalentes en el diccionario
        $precioBruto = $data['PRECIO'] ?? $data['PRICE'] ?? $data['PRECIO_VENTA'] ?? null;
        if ($precioBruto !== null && is_numeric($precioBruto)) {
            $articulo->precio = (float) $precioBruto;
        } else {
            $articulo->precio = null; // No inventarlo
        }
        
        // Tolerancia a campos booleanos o flags 1/0
        $activoFlag = $data['ACTIVO'] ?? $data['HABILITADO'] ?? null;
        if ($activoFlag !== null) {
            $articulo->activo = in_array(strtoupper((string)$activoFlag), ['1', 'S', 'Y', 'TRUE', 'V', 'SI'], true) ? 1 : 0;
        } else {
            // Asumimos activo por default al sincronizarse si no especifica baja lógica
            $articulo->activo = 1; 
        }

        return $articulo;
    }
}
