<?php

declare(strict_types=1);

namespace App\Modules\Empresas;

class Empresa
{
    public ?int $id = null;
    public string $codigo = '';
    public string $nombre = '';
    public ?string $titulo_pestana = null;
    public ?string $razon_social = null;
    public ?string $cuit = null;
    public ?string $slug = null;
    public int $activa = 1;
    public int $modulo_tiendas = 0;
    public int $tiendas_modulo_notas = 0;
    public int $modulo_crm = 0;
    public int $crm_modulo_notas = 0;
    public int $crm_modulo_llamadas = 0;
    public int $crm_modulo_monitoreo = 0;
    public int $tiendas_modulo_rxn_live = 0;
    public int $crm_modulo_rxn_live = 0;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $deleted_at = null;

    // Theming y Branding properties
    public ?string $logo_url = null;
    public ?string $favicon_url = null;
    public ?string $color_primary = null;
    public ?string $color_secondary = null;
    public ?string $footer_text = null;
    public ?string $footer_address = null;
    public ?string $footer_phone = null;
    public ?string $footer_socials = null;
}
