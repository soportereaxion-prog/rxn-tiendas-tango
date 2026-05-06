<?php

declare(strict_types=1);

/**
 * Suma toggles a nivel empresa para los módulos contratables que faltaban
 * cubrir en la matriz de permisos. Patrón idéntico a los flags ya existentes
 * (modulo_crm, crm_modulo_notas, crm_modulo_llamadas, etc).
 *
 * DEFAULT 0: una empresa nueva NO tiene contratados estos módulos hasta que
 * el super admin (es_rxn_admin) los habilite explícito en /empresas/{id}.
 *
 * Idempotente: chequea information_schema antes de cada ALTER.
 */
return function (\PDO $db) {
    $columnas = [
        'crm_modulo_pedidos_servicio'   => 'crm_modulo_rxn_live',
        'crm_modulo_agenda'             => 'crm_modulo_pedidos_servicio',
        'crm_modulo_mail_masivos'       => 'crm_modulo_agenda',
        'crm_modulo_horas_turnero'      => 'crm_modulo_mail_masivos',
        'crm_modulo_geo_tracking'       => 'crm_modulo_horas_turnero',
        'crm_modulo_presupuestos_pwa'   => 'crm_modulo_geo_tracking',
        'crm_modulo_horas_pwa'          => 'crm_modulo_presupuestos_pwa',
    ];

    foreach ($columnas as $columna => $afterCol) {
        $sqlExists = "
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'empresas'
              AND column_name = :col
        ";
        $stmt = $db->prepare($sqlExists);
        $stmt->execute([':col' => $columna]);
        $exists = (int) $stmt->fetchColumn();

        if ($exists === 0) {
            $db->exec(sprintf(
                "ALTER TABLE empresas ADD COLUMN %s TINYINT(1) NOT NULL DEFAULT 0 AFTER %s",
                $columna,
                $afterCol
            ));
        }
    }
};
