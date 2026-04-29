<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Sumar columna articulo_descripcion_original que conserva el nombre del
    // artículo al momento de selección desde el catálogo. NUNCA se pisa después
    // (al contrario de articulo_descripcion_snapshot, que sí refleja la edición
    // del operador). Sirve para detectar modificaciones y enviar
    // DESCRIPCION_ADICIONAL_ARTICULO a Tango sólo cuando el operador editó.
    $stmt = $db->query("SHOW COLUMNS FROM crm_presupuesto_items LIKE 'articulo_descripcion_original'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE crm_presupuesto_items
            ADD COLUMN articulo_descripcion_original VARCHAR(255) NULL
            AFTER articulo_descripcion_snapshot");

        // Backfill: para items existentes copiamos el snapshot actual como original.
        // Esto es conservador: items históricos quedan marcados como "no modificados"
        // (snapshot == original) y NO van a inyectar DESCRIPCION_ADICIONAL_ARTICULO
        // al re-enviar a Tango. El operador puede después editar y eso sí marcará
        // como modificado.
        $db->exec("UPDATE crm_presupuesto_items
            SET articulo_descripcion_original = articulo_descripcion_snapshot
            WHERE articulo_descripcion_original IS NULL");
    }
};
