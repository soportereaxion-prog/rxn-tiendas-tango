<?php

/**
 * Migración: 2026_04_05_clear_clientes_legacy_views
 *
 * Elimina las vistas guardadas del dataset 'clientes' que tienen configuraciones
 * legacy incompatibles (groupCol vacío, o groupCol = fecha_registro con tipo pie/doughnut
 * que genera gráficos invisibles por exceso de segmentos).
 *
 * Las vistas guardadas serán recreadas por el usuario desde cero con la configuración correcta.
 * Los defaults del dataset (estado / cantidad / pie) se aplican automáticamente desde el Service.
 */

return function (\PDO $pdo): void {

    // Limpiar TODAS las vistas guardadas de clientes para este entorno de prueba.
    // En producción con datos reales del usuario, cambiar a un WHERE más selectivo.
    $pdo->exec("DELETE FROM rxn_live_vistas WHERE dataset = 'clientes'");
};
