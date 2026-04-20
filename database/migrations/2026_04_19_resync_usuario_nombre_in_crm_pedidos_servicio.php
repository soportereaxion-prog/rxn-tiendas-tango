<?php

declare(strict_types=1);

/**
 * Migración: 2026_04_19_resync_usuario_nombre_in_crm_pedidos_servicio
 *
 * Resincroniza la columna denormalizada `crm_pedidos_servicio.usuario_nombre`
 * con el nombre actual del usuario (`usuarios.nombre`).
 *
 * Por qué existe:
 * Cuando se crea un PDS se graba `usuario_nombre` por valor (snapshot del momento)
 * para que el reporte siga mostrando el nombre histórico aunque el usuario cambie
 * después. El efecto colateral es que si un usuario corrige su nombre (ej: "Charly"
 * → "Charly Yaciofani"), los PDS viejos siguen mostrando el nombre anterior y los
 * filtros de la grilla muestran al mismo usuario como dos opciones distintas.
 *
 * Esta migración trae todos los `usuario_nombre` al valor actual del usuario,
 * SOLO donde hay drift real (`ps.usuario_nombre <> u.nombre`) y respetando el
 * scope multi-tenant (`empresa_id`). Es idempotente: si no hay drift, no
 * actualiza filas. Se puede correr múltiples veces sin daño.
 *
 * Decisión: NO se actualiza si el usuario quedó soft-deleted (`deleted_at IS NOT NULL`)
 * porque su nombre puede haber sido modificado intencionalmente al darlo de baja.
 *
 * Pendiente para futuras iteraciones: revisar si `crm_presupuestos` u otras tablas
 * tienen el mismo patrón de denormalización y necesitan resincro análogo.
 */

return function (\PDO $pdo): void {
    // Guardia: si las tablas no existen en esta instalación (cliente sin CRM o muy viejo),
    // saltar silenciosamente. Mantiene la migración compatible con todas las DBs.
    $tablesNeeded = ['crm_pedidos_servicio', 'usuarios'];
    foreach ($tablesNeeded as $t) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$t}'");
        if (!$stmt || !$stmt->fetch()) {
            return;
        }
    }

    // Guardia adicional: confirmar que las columnas necesarias existen.
    $colsNeeded = [
        'crm_pedidos_servicio' => ['usuario_id', 'usuario_nombre', 'empresa_id'],
        'usuarios'             => ['id', 'nombre', 'empresa_id', 'deleted_at'],
    ];
    foreach ($colsNeeded as $table => $cols) {
        foreach ($cols as $col) {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
            if (!$stmt || !$stmt->fetch()) {
                return;
            }
        }
    }

    $sql = <<<SQL
        UPDATE crm_pedidos_servicio ps
        INNER JOIN usuarios u
            ON ps.usuario_id = u.id
           AND ps.empresa_id = u.empresa_id
        SET ps.usuario_nombre = u.nombre
        WHERE ps.usuario_id IS NOT NULL
          AND ps.usuario_nombre <> u.nombre
          AND u.deleted_at IS NULL
    SQL;

    $affected = $pdo->exec($sql);

    // Log mínimo para que en el listado del Mantenimiento quede registro de cuántas
    // filas tocó. error_log lo escribe al log de PHP del server (visible vía Plesk
    // o el módulo de logs interno).
    error_log(sprintf(
        '[migration 2026_04_19_resync_usuario_nombre] PDS resincronizados: %d',
        (int)$affected
    ));
};
