<?php

declare(strict_types=1);

namespace App\Modules\CrmNotas;

class CrmNota
{
    public int $id;
    public int $empresa_id;
    public ?int $cliente_id = null; // Relación opcional/obligatoria con crm_clientes
    public ?int $tratativa_id = null; // Vínculo opcional con una tratativa (FK blanda)
    public string $titulo;
    public string $contenido;
    public ?string $tags = null; // Ej: "importante, seguimiento" (JSON o str_comma)
    public int $activo = 1;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $deleted_at = null;

    // Campos virtuales (resueltos por JOIN en findByIdAndEmpresa)
    public ?string $cliente_nombre = null;
    public ?string $cliente_codigo = null;
    // OJO: t.numero es INT en crm_tratativas — si se declara ?string, el foreach de asignación
    // en findByIdAndEmpresa tira TypeError con strict_types=1. Histórico del hotfix 1.20.1.
    public ?int $tratativa_numero = null;
    public ?string $tratativa_titulo = null;
}
