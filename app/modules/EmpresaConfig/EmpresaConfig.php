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
    public ?string $created_at = null;
    public ?string $updated_at = null;
}
