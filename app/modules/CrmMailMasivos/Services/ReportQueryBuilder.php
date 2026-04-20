<?php

declare(strict_types=1);

namespace App\Modules\CrmMailMasivos\Services;

use InvalidArgumentException;

/**
 * ReportQueryBuilder
 *
 * Traduce la definición JSON de un reporte ("diseño Links") a un SQL
 * SELECT seguro con placeholders, validado contra el metamodelo.
 *
 * Formato esperado del input $config:
 * [
 *   'root_entity' => 'CrmClientes',            // obligatorio
 *   'relations'   => [                          // opcional; relaciones "prendidas"
 *     ['from' => 'CrmClientes', 'relation' => 'CrmPresupuestos'],
 *     ...
 *   ],
 *   'fields'      => [                          // obligatorio (≥1); campos de salida
 *     ['entity' => 'CrmClientes', 'field' => 'razon_social'],
 *     ['entity' => 'CrmClientes', 'field' => 'email'],
 *     ['entity' => 'CrmPresupuestos', 'field' => 'total'],
 *   ],
 *   'filters'     => [                          // opcional; lista de condiciones AND
 *     ['entity' => 'CrmPresupuestos', 'field' => 'fecha', 'op' => '>=', 'value' => '2026-01-01'],
 *     ['entity' => 'CrmClientes', 'field' => 'activo', 'op' => '=', 'value' => 1],
 *     ['entity' => 'CrmClientes', 'field' => 'provincia', 'op' => 'IN', 'value' => ['CABA','Buenos Aires']],
 *   ],
 *   'mail_field'  => ['entity' => 'CrmClientes', 'field' => 'email'],  // opcional; si se omite, se autodetecta
 * ]
 *
 * Salida: ['sql' => 'SELECT ...', 'params' => [':p0' => ..., ...], 'aliases' => [...]]
 *
 * Garantías de seguridad:
 * - Sólo entidades/campos/relaciones/operadores declarados en el metamodelo son aceptados.
 * - Valores de filtros SIEMPRE viajan como placeholders (`:p0`, `:p1`, ...).
 * - Identificadores de tabla/columna se whitelist-matchean contra el metamodelo antes de escribirse.
 * - `empresa_id = :empresa_id` se inyecta automáticamente en cada entidad con `empresa_scope`.
 * - Soft delete (`deleted_at IS NULL`) se inyecta automáticamente en entidades que lo declaran.
 */
class ReportQueryBuilder
{
    private ReportMetamodel $meta;

    /** @var array<string, string> map entity => alias */
    private array $aliases = [];

    /** @var array<string, mixed> */
    private array $params = [];

    private int $paramCounter = 0;

    public function __construct(?ReportMetamodel $meta = null)
    {
        $this->meta = $meta ?? new ReportMetamodel();
    }

    /**
     * Construye SELECT completo para un reporte.
     *
     * $requireMailTarget:
     *   true (default)  → el reporte actúa como fuente de destinatarios; si no
     *                     se puede resolver un mail_field, se lanza excepción.
     *   false           → el reporte actúa como fuente de CONTENIDO (bloque
     *                     broadcast); mail_target puede quedar null sin tirar.
     *
     * @param array<string, mixed> $config
     * @return array{sql: string, params: array<string, mixed>, aliases: array<string, string>, mail_target: array{entity: string, field: string, alias: string}|null, field_aliases: array<string, string>}
     */
    public function build(array $config, int $empresaId, int $limit = 0, bool $requireMailTarget = true): array
    {
        $this->aliases = [];
        $this->params = [];
        $this->paramCounter = 0;

        $rootEntity = (string) ($config['root_entity'] ?? '');
        if ($rootEntity === '' || !$this->meta->hasEntity($rootEntity)) {
            throw new InvalidArgumentException("root_entity inválido o no declarado: {$rootEntity}");
        }

        // Reservar alias root
        $this->allocateAlias($rootEntity);

        // Construir JOINs
        $joinSql = $this->buildJoins($rootEntity, $config['relations'] ?? []);

        // Construir SELECT
        [$selectSql, $fieldAliases] = $this->buildSelect($config['fields'] ?? []);

        // Construir WHERE (filtros + empresa_scope + soft_delete)
        $whereSql = $this->buildWhere($config['filters'] ?? [], $empresaId);

        // Resolver campo de mail (null para reportes de contenido)
        $mailTarget = $this->resolveMailTarget(
            $config['mail_field'] ?? null,
            $rootEntity,
            $requireMailTarget
        );

        // FROM
        $rootTable = $this->meta->getEntity($rootEntity)['table'];
        $rootAlias = $this->aliases[$rootEntity];
        $fromSql = sprintf('`%s` AS `%s`', $rootTable, $rootAlias);

        $sql = 'SELECT ' . $selectSql
             . ' FROM ' . $fromSql
             . $joinSql
             . ' WHERE ' . $whereSql;

        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }

        return [
            'sql' => $sql,
            'params' => $this->params,
            'aliases' => $this->aliases,
            'mail_target' => $mailTarget,
            'field_aliases' => $fieldAliases,
        ];
    }

    // ────────────────────────────────────────────────────────────────────
    // INTERNO
    // ────────────────────────────────────────────────────────────────────

    private function allocateAlias(string $entity): string
    {
        if (isset($this->aliases[$entity])) {
            return $this->aliases[$entity];
        }
        // alias simple: e0, e1, e2...
        $alias = 'e' . count($this->aliases);
        $this->aliases[$entity] = $alias;
        return $alias;
    }

    /**
     * Construye los LEFT JOIN para cada relación prendida.
     * @param list<array{from?: string, relation?: string}> $relations
     */
    private function buildJoins(string $rootEntity, array $relations): string
    {
        $sql = '';

        foreach ($relations as $rel) {
            if (!is_array($rel)) {
                continue;
            }
            $from = (string) ($rel['from'] ?? $rootEntity);
            $relName = (string) ($rel['relation'] ?? '');
            if ($relName === '') {
                continue;
            }

            if (!$this->meta->hasEntity($from)) {
                throw new InvalidArgumentException("Entidad 'from' no declarada: {$from}");
            }
            if (!$this->meta->hasRelation($from, $relName)) {
                throw new InvalidArgumentException("Relación no declarada: {$from} → {$relName}");
            }

            $relDef = $this->meta->getRelation($from, $relName);
            $targetEntity = (string) ($relDef['target_entity'] ?? $relName);

            if (!$this->meta->hasEntity($targetEntity)) {
                throw new InvalidArgumentException("Entidad destino de relación no declarada: {$targetEntity}");
            }

            if (isset($this->aliases[$targetEntity])) {
                continue; // ya joinedo
            }

            $fromAlias = $this->aliases[$from] ?? $this->allocateAlias($from);
            $targetAlias = $this->allocateAlias($targetEntity);
            $targetTable = $this->meta->getEntity($targetEntity)['table'];

            $foreignKey = (string) ($relDef['foreign_key'] ?? '');
            $localKey = (string) ($relDef['local_key'] ?? 'id');
            $type = (string) ($relDef['type'] ?? 'hasMany');

            if ($foreignKey === '') {
                throw new InvalidArgumentException("foreign_key faltante en relación {$from}.{$relName}");
            }

            // hasMany: target.foreign_key = from.local_key
            // belongsTo: from.foreign_key = target.local_key
            if ($type === 'belongsTo') {
                $on = sprintf(
                    '`%s`.`%s` = `%s`.`%s`',
                    $fromAlias, $foreignKey,
                    $targetAlias, $localKey
                );
            } else {
                $on = sprintf(
                    '`%s`.`%s` = `%s`.`%s`',
                    $targetAlias, $foreignKey,
                    $fromAlias, $localKey
                );
            }

            $sql .= sprintf(' LEFT JOIN `%s` AS `%s` ON %s', $targetTable, $targetAlias, $on);
        }

        return $sql;
    }

    /**
     * Construye el SELECT. Devuelve [sql, fieldAliases] donde fieldAliases
     * mapea "Entity.field" → alias de columna de salida (útil para el frontend).
     * @param list<array{entity?: string, field?: string}> $fields
     * @return array{0: string, 1: array<string, string>}
     */
    private function buildSelect(array $fields): array
    {
        if (empty($fields)) {
            throw new InvalidArgumentException('El reporte debe declarar al menos un campo');
        }

        $parts = [];
        $fieldAliases = [];

        foreach ($fields as $idx => $f) {
            if (!is_array($f)) {
                continue;
            }
            $entity = (string) ($f['entity'] ?? '');
            $field = (string) ($f['field'] ?? '');

            if (!$this->meta->hasField($entity, $field)) {
                throw new InvalidArgumentException("Campo no declarado en metamodelo: {$entity}.{$field}");
            }

            $alias = $this->aliases[$entity] ?? $this->allocateAlias($entity);
            $colAlias = sprintf('%s_%s', $entity, $field); // ej "CrmClientes_razon_social"
            $parts[] = sprintf('`%s`.`%s` AS `%s`', $alias, $field, $colAlias);
            $fieldAliases["{$entity}.{$field}"] = $colAlias;
        }

        return [implode(', ', $parts), $fieldAliases];
    }

    /**
     * Construye el WHERE combinando filtros del usuario + empresa_scope + soft_delete.
     * @param list<array<string, mixed>> $filters
     */
    private function buildWhere(array $filters, int $empresaId): string
    {
        $clauses = [];

        // 1. empresa_scope automático en cada entidad del plan.
        //    IMPORTANTE: generamos un placeholder único por cada uso (aunque el
        //    valor sea el mismo). Con prepares nativos de MySQL (default del
        //    proyecto) un placeholder repetido dispara SQLSTATE[HY093].
        foreach ($this->aliases as $entity => $alias) {
            $def = $this->meta->getEntity($entity);
            if (!empty($def['empresa_scope'])) {
                $clauses[] = sprintf(
                    '`%s`.`empresa_id` = %s',
                    $alias,
                    $this->nextParam($empresaId)
                );
            }
        }

        // 2. soft_delete automático
        foreach ($this->aliases as $entity => $alias) {
            $def = $this->meta->getEntity($entity);
            $sdCol = $def['soft_delete'] ?? null;
            if ($sdCol && is_string($sdCol)) {
                $clauses[] = sprintf('`%s`.`%s` IS NULL', $alias, $sdCol);
            }
        }

        // 3. filtros del usuario
        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }
            $clauses[] = $this->buildFilterClause($filter);
        }

        if (empty($clauses)) {
            return '1 = 1';
        }

        return implode(' AND ', $clauses);
    }

    /**
     * @param array<string, mixed> $filter
     */
    private function buildFilterClause(array $filter): string
    {
        $entity = (string) ($filter['entity'] ?? '');
        $field = (string) ($filter['field'] ?? '');
        $op = strtoupper(trim((string) ($filter['op'] ?? '=')));

        if (!$this->meta->hasField($entity, $field)) {
            throw new InvalidArgumentException("Filtro sobre campo no declarado: {$entity}.{$field}");
        }

        $fieldDef = $this->meta->getField($entity, $field);
        $type = (string) ($fieldDef['type'] ?? 'string');

        if (!ReportMetamodel::isOperatorAllowed($type, $op)) {
            throw new InvalidArgumentException("Operador '{$op}' no permitido para campo tipo '{$type}' ({$entity}.{$field})");
        }

        $alias = $this->aliases[$entity] ?? $this->allocateAlias($entity);
        $colRef = sprintf('`%s`.`%s`', $alias, $field);

        // Operadores nularios
        if ($op === 'IS NULL' || $op === 'IS NOT NULL') {
            return $colRef . ' ' . $op;
        }

        // IN / NOT IN
        if ($op === 'IN' || $op === 'NOT IN') {
            $value = $filter['value'] ?? [];
            if (!is_array($value) || empty($value)) {
                throw new InvalidArgumentException("Operador {$op} requiere un array no vacío en 'value'");
            }
            $placeholders = [];
            foreach ($value as $v) {
                $placeholders[] = $this->nextParam($v);
            }
            return sprintf('%s %s (%s)', $colRef, $op, implode(', ', $placeholders));
        }

        // BETWEEN
        if ($op === 'BETWEEN') {
            $value = $filter['value'] ?? null;
            if (!is_array($value) || count($value) !== 2) {
                throw new InvalidArgumentException("Operador BETWEEN requiere array de 2 elementos en 'value'");
            }
            $p1 = $this->nextParam($value[0]);
            $p2 = $this->nextParam($value[1]);
            return sprintf('%s BETWEEN %s AND %s', $colRef, $p1, $p2);
        }

        // LIKE / NOT LIKE — wrappear con % si no los tiene
        if ($op === 'LIKE' || $op === 'NOT LIKE') {
            $value = (string) ($filter['value'] ?? '');
            if ($value === '') {
                throw new InvalidArgumentException("Operador {$op} requiere 'value' no vacío");
            }
            if (!str_contains($value, '%')) {
                $value = '%' . $value . '%';
            }
            $p = $this->nextParam($value);
            return sprintf('%s %s %s', $colRef, $op, $p);
        }

        // Operadores binarios simples: =, !=, <, <=, >, >=
        $value = $filter['value'] ?? null;
        $p = $this->nextParam($value);
        return sprintf('%s %s %s', $colRef, $op, $p);
    }

    private function nextParam($value): string
    {
        $name = ':p' . $this->paramCounter++;
        $this->params[$name] = $value;
        return $name;
    }

    /**
     * @param array{entity?: string, field?: string}|null $userSpec
     * @return array{entity: string, field: string, alias: string}
     */
    /**
     * @return array{entity: string, field: string, alias: string}|null
     */
    private function resolveMailTarget(?array $userSpec, string $rootEntity, bool $require = true): ?array
    {
        // Si el usuario especificó uno, validarlo
        if (is_array($userSpec) && !empty($userSpec['entity']) && !empty($userSpec['field'])) {
            $entity = (string) $userSpec['entity'];
            $field = (string) $userSpec['field'];
            if (!$this->meta->hasField($entity, $field)) {
                throw new InvalidArgumentException("mail_field no declarado: {$entity}.{$field}");
            }
            if (!isset($this->aliases[$entity])) {
                throw new InvalidArgumentException("mail_field referencia entidad no prendida: {$entity}");
            }
            return [
                'entity' => $entity,
                'field' => $field,
                'alias' => sprintf('%s_%s', $entity, $field),
            ];
        }

        // Autodetección en la root entity
        $auto = $this->meta->getDefaultMailField($rootEntity);
        if ($auto !== null && $this->meta->hasField($rootEntity, $auto)) {
            return [
                'entity' => $rootEntity,
                'field' => $auto,
                'alias' => sprintf('%s_%s', $rootEntity, $auto),
            ];
        }

        if (!$require) {
            return null;
        }

        throw new InvalidArgumentException(
            "No se pudo resolver el campo de destinatario. Declarar 'mail_field' explícitamente o marcar un campo con is_mail_target."
        );
    }
}
