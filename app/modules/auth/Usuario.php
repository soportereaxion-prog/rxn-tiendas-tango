<?php

declare(strict_types=1);

namespace App\Modules\Auth;

class Usuario
{
    public ?int $id = null;
    public int $empresa_id;
    public string $nombre;
    public string $email;
    public string $password_hash;
    public int $activo;
    public int $es_admin;
    public ?string $created_at = null;
    public ?string $updated_at = null;
}
