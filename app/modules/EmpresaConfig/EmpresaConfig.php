<?php

declare(strict_types=1);

namespace App\Modules\EmpresaConfig;

#[\AllowDynamicProperties]
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
    public ?string $deposito_codigo = null;
    public ?string $imagen_default_producto = null;

    // Campos de Configuración de Tango
    public ?int $tango_pds_talonario_id = null;
    public ?int $tango_perfil_pedido_id = null;
    public ?string $tango_perfil_pedido_codigo = null;
    public ?string $tango_perfil_pedido_nombre = null;
    public ?string $tango_perfil_snapshot_json = null;
    public ?string $tango_perfil_snapshot_date = null;
    
    // Configuracion SMTP
    public int $usa_smtp_propio = 0;
    public ?string $smtp_host = null;
    public ?int $smtp_port = null;
    public ?string $smtp_user = null;
    public ?string $smtp_pass = null;
    public ?string $smtp_secure = null;
    public ?string $smtp_from_email = null;
    public ?string $smtp_from_name = null;

    public ?string $created_at = null;
    public ?string $updated_at = null;
}
