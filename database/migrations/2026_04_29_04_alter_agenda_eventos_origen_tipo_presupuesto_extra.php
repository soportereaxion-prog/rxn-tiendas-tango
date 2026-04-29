<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Extiende el ENUM origen_tipo de crm_agenda_eventos para aceptar dos nuevos
 * valores ligados a Presupuestos:
 *   - 'presupuesto_proximo_contacto' → evento agendado para el próximo contacto
 *     comercial con el cliente del presupuesto.
 *   - 'presupuesto_vigencia' → evento que marca el vencimiento del presupuesto.
 *
 * Ambos campos son nuevos en `crm_presupuestos` (release 1.29.0 — `proximo_contacto`
 * y `vigencia`). El proyector de Presupuestos los emite como eventos independientes
 * en la agenda CRM con colores distintivos (verde y naranja respectivamente).
 *
 * Idempotente: chequea el COLUMN_TYPE antes de alterar.
 */
return function (): void {
    $db = Database::getConnection();

    $stmt = $db->query("
        SELECT COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'crm_agenda_eventos'
          AND COLUMN_NAME = 'origen_tipo'
        LIMIT 1
    ");
    $columnType = (string) ($stmt->fetchColumn() ?: '');

    if ($columnType === '') {
        return;
    }

    $needsMigration = !str_contains($columnType, "'presupuesto_proximo_contacto'")
        || !str_contains($columnType, "'presupuesto_vigencia'");

    if (!$needsMigration) {
        return;
    }

    $db->exec("
        ALTER TABLE crm_agenda_eventos
        MODIFY COLUMN origen_tipo
        ENUM('manual','pds','presupuesto','tratativa','llamada','tratativa_accion','hora','nota','presupuesto_proximo_contacto','presupuesto_vigencia')
        NOT NULL DEFAULT 'manual'
    ");
};
