<?php

declare(strict_types=1);

namespace App\Modules\EmpresaConfig;

class EmpresaConfig
{
    public ?int $id = null;
    public int $empresa_id;
    public ?string $nombre_fantasia = null;
    public ?string $email_contacto = null;
    public ?string $telefono = null;
    public ?string $tango_api_url = null;
    public ?string $tango_connect_key = null;
    public ?string $tango_connect_token = null;
    public ?string $tango_connect_company_id = null;
    public int $cantidad_articulos_sync = 50;
    public ?string $lista_precio_1 = null;
    public ?string $lista_precio_2 = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
}
