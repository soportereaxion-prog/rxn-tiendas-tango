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

    // Theming B2B Admin
    public string $preferencia_tema = 'light';
    public string $preferencia_fuente = 'md';
    public ?string $avatar_url = null;
    
    // UI Dashboard State Arrays
    public ?string $dashboard_order = null;
}
