<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Sumar 'hora' al ENUM origen_tipo de crm_agenda_eventos para que los turnos
    // del módulo CrmHoras puedan proyectarse como eventos en el calendario unificado.
    //
    // ALTER de ENUM en MySQL: se reescribe la definición completa preservando
    // los valores existentes. La operación es ONLINE en MySQL 8 (sin lock prolongado).
    $db->exec("
        ALTER TABLE crm_agenda_eventos
        MODIFY COLUMN origen_tipo
        ENUM('manual','pds','presupuesto','tratativa','llamada','tratativa_accion','hora')
        NOT NULL DEFAULT 'manual'
    ");
};
