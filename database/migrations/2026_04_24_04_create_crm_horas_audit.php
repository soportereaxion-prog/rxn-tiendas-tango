<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Audit log de mutaciones administrativas sobre crm_horas.
    //
    // Se inserta UNA fila por cada mutación hecha por alguien que NO es el dueño
    // del turno (ej: super admin anulando un turno ajeno). Si el dueño edita su
    // propio turno, NO se loggea — porque eso es operación normal del usuario.
    //
    // accion: 'anular' | 'editar' (futuro) | 'restaurar' (futuro)
    // before_json / after_json: snapshot del turno antes/después del cambio.
    //   En 'anular' before contiene el row completo, after contiene el motivo.
    // performed_by: id del usuario que hizo el cambio.
    // performed_at: timestamp del cambio.
    //
    // El audit log es VISIBLE solo para super admin (rxn admin). El dueño del
    // turno también recibe una notificación in-app via NotificationService al
    // momento del cambio.
    $db->exec("
    CREATE TABLE IF NOT EXISTS crm_horas_audit (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        hora_id INT NOT NULL,
        owner_user_id INT NOT NULL,
        accion VARCHAR(40) NOT NULL,
        before_json JSON NULL,
        after_json JSON NULL,
        motivo TEXT NULL,
        performed_by INT NOT NULL,
        performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_horas_audit_empresa (empresa_id, performed_at),
        KEY idx_horas_audit_hora (hora_id, performed_at),
        KEY idx_horas_audit_owner (empresa_id, owner_user_id, performed_at),
        CONSTRAINT fk_horas_audit_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
