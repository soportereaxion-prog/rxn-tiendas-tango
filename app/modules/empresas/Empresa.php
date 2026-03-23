<?php

declare(strict_types=1);

namespace App\Modules\Empresas;

class Empresa
{
    public ?int $id = null;
    public string $codigo = '';
    public string $nombre = '';
    public ?string $razon_social = null;
    public ?string $cuit = null;
    public int $activa = 1;
    public ?string $created_at = null;
    public ?string $updated_at = null;
}
