<?php

declare(strict_types=1);

namespace App\Modules\Auth;

#[\AllowDynamicProperties]
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
    
    public ?string $anura_interno = null;
    
    // Nuvos flags de Auth Unificado B2B
    public int $email_verificado = 0;
    public ?string $email_verificado_at = null;
    public ?string $verification_token = null;
    public ?string $verification_expires = null;
    public ?string $reset_token = null;
    public ?string $reset_expires = null;

    public ?string $created_at = null;
    public ?string $updated_at = null;

    // Presencia online (actualizado por App.php con throttle 60s en cada request autenticado)
    public ?string $ultimo_acceso = null;

    // Theming B2B Admin
    public string $preferencia_tema = 'light';
    public string $preferencia_fuente = 'md';
    public ?string $color_calendario = '#007bff';
    public ?string $avatar_url = null;
    
    // Dashboard State Arrays
    public ?string $dashboard_order = null;

    // Integración Tango Perfil Pedido
    public ?int $tango_perfil_pedido_id = null;
    public ?string $tango_perfil_pedido_codigo = null;
    public ?string $tango_perfil_pedido_nombre = null;
    
    // Almacenamiento cacheado del perfil de tango en formato JSON para el resolver local (evita latencia API)
    public ?string $tango_perfil_snapshot_json = null;
    public ?string $tango_perfil_snapshot_date = null;
}
