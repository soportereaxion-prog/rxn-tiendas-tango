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
    public int $es_rxn_admin = 0;
    
    // Nuvos flags de Auth Unificado B2B
    public int $email_verificado = 0;
    public ?string $email_verificado_at = null;
    public ?string $verification_token = null;
    public ?string $verification_expires = null;
    public ?string $reset_token = null;
    public ?string $reset_expires = null;

    public ?string $created_at = null;
    public ?string $updated_at = null;
}
