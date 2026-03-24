<?php
declare(strict_types=1);
namespace App\Modules\Articulos;
class Articulo
{
    public ?int $id = null;
    public int $empresa_id;
    public string $codigo_externo;
    public string $nombre;
    public ?string $descripcion = null;
    public ?float $precio = null;
    public ?float $precio_lista_1 = null;
    public ?float $precio_lista_2 = null;
    public ?float $stock_actual = null;
    public int $activo = 1;
    public ?string $fecha_ultima_sync = null;
    public ?string $imagen_principal = null;
}
