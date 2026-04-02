<?php

declare(strict_types=1);

namespace App\Modules\CrmNotas;

class CrmNota
{
    public int $id;
    public int $empresa_id;
    public ?int $cliente_id = null; // Relación opcional/obligatoria con crm_clientes
    public string $titulo;
    public string $contenido;
    public ?string $tags = null; // Ej: "importante, seguimiento" (JSON o str_comma)
    public int $activo = 1;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $deleted_at = null;

    // Campos virtuales
    public ?string $cliente_nombre = null;
    public ?string $cliente_codigo = null;
}
