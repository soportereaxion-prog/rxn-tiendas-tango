<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Tabla `web_push_subscriptions` — suscripciones del browser para Web Push.
 *
 * Cada navegador que se suscribe genera un objeto único con endpoint + claves
 * de cifrado. Un mismo usuario puede tener N suscripciones (PC trabajo, PC casa,
 * mobile). Por eso UNIQUE va sobre `endpoint`, no sobre (empresa_id, usuario_id).
 */
return function (): void {
    $db = Database::getConnection();

    $stmt = $db->query("SHOW TABLES LIKE 'web_push_subscriptions'");
    if ($stmt->fetch()) {
        return;
    }

    $db->exec(
        "CREATE TABLE web_push_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            usuario_id INT NOT NULL,
            endpoint VARCHAR(500) NOT NULL,
            p256dh VARCHAR(255) NOT NULL,
            auth VARCHAR(255) NOT NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_push_at DATETIME NULL,
            failed_attempts INT NOT NULL DEFAULT 0,
            disabled_at DATETIME NULL,
            UNIQUE KEY uniq_endpoint (endpoint),
            KEY idx_user (empresa_id, usuario_id, disabled_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
