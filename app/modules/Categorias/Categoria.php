<?php
declare(strict_types=1);

namespace App\Modules\Categorias;

class Categoria
{
    public ?int $id = null;
    public int $empresa_id;
    public string $nombre;
    public string $slug;
    public ?string $descripcion_corta = null;
    public ?string $imagen_portada = null;
    public int $orden_visual = 0;
    public int $activa = 1;
    public int $visible_store = 1;
    public int $articulos_count = 0;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $deleted_at = null;
}
