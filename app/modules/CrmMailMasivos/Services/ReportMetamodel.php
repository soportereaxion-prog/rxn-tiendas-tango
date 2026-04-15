<?php

declare(strict_types=1);

namespace App\Modules\CrmMailMasivos\Services;

use RuntimeException;

/**
 * ReportMetamodel
 *
 * Capa de acceso y validación al metamodelo declarativo del módulo
 * CrmMailMasivos (archivo `config/entities.php`).
 *
 * El metamodelo declara qué entidades (tablas), campos y relaciones son
 * alcanzables desde el diseñador "Links" de reportes. Todo lo que no esté
 * declarado ACÁ está automáticamente fuera de alcance del usuario y del
 * Query Builder — esto es la principal defensa contra SQL injection y
 * contra acceso a tablas sensibles.
 *
 * Esta clase es puramente de lectura/validación. No ejecuta SQL.
 */
class ReportMetamodel
{
    /** @var array<string, array<string, mixed>> */
    private array $entities;

    public function __construct(?array $entities = null)
    {
        if ($entities === null) {
            $path = __DIR__ . '/../config/entities.php';
            if (!is_file($path)) {
                throw new RuntimeException('Metamodelo no encontrado: ' . $path);
            }
            /** @psalm-suppress UnresolvableInclude */
            $entities = require $path;
        }

        if (!is_array($entities) || empty($entities)) {
            throw new RuntimeException('Metamodelo inválido o vacío');
        }

        $this->entities = $entities;
    }

    /**
     * Lista los nombres de todas las entidades declaradas (claves del array).
     * @return list<string>
     */
    public function listEntityNames(): array
    {
        return array_keys($this->entities);
    }

    /**
     * Devuelve el metamodelo completo en una forma apta para enviar al frontend
     * (el diseñador visual lo usa para construir la UI).
     * @return array<string, mixed>
     */
    public function toArrayForFrontend(): array
    {
        $out = [];
        foreach ($this->entities as $name => $def) {
            $out[$name] = [
                'label' => $def['label'] ?? $name,
                'table' => $def['table'] ?? null,
                'mail_field' => $def['mail_field'] ?? null,
                'empresa_scope' => (bool) ($def['empresa_scope'] ?? false),
                'fields' => $def['fields'] ?? [],
                'relations' => $def['relations'] ?? [],
            ];
        }
        return $out;
    }

    /**
     * Devuelve la definición de una entidad. Lanza si no existe.
     * @return array<string, mixed>
     */
    public function getEntity(string $name): array
    {
        if (!isset($this->entities[$name])) {
            throw new RuntimeException("Entidad no declarada en el metamodelo: {$name}");
        }
        return $this->entities[$name];
    }

    /**
     * @return array<string, mixed>
     */
    public function getField(string $entity, string $field): array
    {
        $def = $this->getEntity($entity);
        $fields = $def['fields'] ?? [];
        if (!isset($fields[$field]) || !is_array($fields[$field])) {
            throw new RuntimeException("Campo no declarado: {$entity}.{$field}");
        }
        return $fields[$field];
    }

    /**
     * @return array<string, mixed>
     */
    public function getRelation(string $entity, string $relation): array
    {
        $def = $this->getEntity($entity);
        $relations = $def['relations'] ?? [];
        if (!isset($relations[$relation]) || !is_array($relations[$relation])) {
            throw new RuntimeException("Relación no declarada: {$entity} → {$relation}");
        }
        return $relations[$relation];
    }

    public function hasEntity(string $name): bool
    {
        return isset($this->entities[$name]);
    }

    public function hasField(string $entity, string $field): bool
    {
        if (!$this->hasEntity($entity)) {
            return false;
        }
        $fields = $this->entities[$entity]['fields'] ?? [];
        return isset($fields[$field]);
    }

    public function hasRelation(string $entity, string $relation): bool
    {
        if (!$this->hasEntity($entity)) {
            return false;
        }
        $relations = $this->entities[$entity]['relations'] ?? [];
        return isset($relations[$relation]);
    }

    /**
     * Encuentra el primer campo de una entidad marcado como `is_mail_target => true`.
     * Útil para autodetectar el destinatario cuando el reporte no lo especifica.
     */
    public function getDefaultMailField(string $entity): ?string
    {
        $def = $this->getEntity($entity);

        // 1. Preferir el `mail_field` declarado a nivel entidad
        if (!empty($def['mail_field']) && is_string($def['mail_field'])) {
            return $def['mail_field'];
        }

        // 2. Buscar el primer campo con flag is_mail_target
        foreach ($def['fields'] ?? [] as $fieldName => $fieldDef) {
            if (is_array($fieldDef) && !empty($fieldDef['is_mail_target'])) {
                return (string) $fieldName;
            }
        }

        return null;
    }

    /**
     * Lista operadores soportados por tipo de campo.
     * El Query Builder consulta esto para validar que el filtro es aplicable.
     * @return array<string, list<string>>
     */
    public static function operatorsByType(): array
    {
        return [
            'string' => ['=', '!=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL'],
            'email'  => ['=', '!=', 'LIKE', 'NOT LIKE', 'IS NULL', 'IS NOT NULL'],
            'int'    => ['=', '!=', '<', '<=', '>', '>=', 'IN', 'NOT IN', 'BETWEEN', 'IS NULL', 'IS NOT NULL'],
            'decimal'=> ['=', '!=', '<', '<=', '>', '>=', 'BETWEEN', 'IS NULL', 'IS NOT NULL'],
            'date'   => ['=', '!=', '<', '<=', '>', '>=', 'BETWEEN', 'IS NULL', 'IS NOT NULL'],
            'datetime'=>['=', '!=', '<', '<=', '>', '>=', 'BETWEEN', 'IS NULL', 'IS NOT NULL'],
            'bool'   => ['=', '!=', 'IS NULL', 'IS NOT NULL'],
        ];
    }

    public static function isOperatorAllowed(string $type, string $operator): bool
    {
        $map = self::operatorsByType();
        $list = $map[$type] ?? $map['string'];
        return in_array($operator, $list, true);
    }
}
