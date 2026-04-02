<?php

declare(strict_types=1);

namespace App\Modules\CrmLlamadas;

class CrmLlamada
{
    public int $id;
    public int $empresa_id;
    public ?int $usuario_id = null;
    public ?string $fecha = null;
    public ?string $origen = null;
    public ?string $numero_origen = null;
    public ?string $destino = null;
    public ?string $duracion = null;
    public ?string $interno = null;
    public ?string $atendio = null;
    public ?string $link_mp = null;
    public ?string $mp3 = null;
    public ?float $precio = null;
    public ?string $json_bruto = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $deleted_at = null;

    // Relacional
    public ?string $usuario_nombre = null;
}
