<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Rescate de vistas huérfanas de rxn_live_vistas.
 *
 * Contexto: antes de la release 1.16.3, el RxnLiveController leía la clave de sesión
 * incorrecta (`$_SESSION['usuario_id']` — AuthService guarda `user_id`). Efecto: todas
 * las vistas guardadas por cualquier usuario quedaron con `usuario_id = 0`. El bug era
 * silencioso hasta la release 1.16.2 que cambió el scope a empresa: el backfill de
 * empresa_id usaba INNER JOIN usuarios (ON u.id = v.usuario_id), que no matcheaba el id=0,
 * y todas las vistas quedaron con empresa_id NULL — invisibles en la UI.
 *
 * Esta migración:
 *   1) Asigna empresa_id a las vistas con empresa_id IS NULL.
 *   2) Fallback: primera empresa_id no nula encontrada en `usuarios` (típicamente la
 *      empresa del admin principal — empresa_id=1 en rxn_suite).
 *   3) Las vistas se conservan con usuario_id=0. Esto significa que nadie es dueño y
 *      por lo tanto nadie puede sobrescribir ni eliminar (los botones se ocultan en
 *      ajenas/system). Para hacer la vista propia, el usuario debe duplicarla con
 *      "Nueva Vista".
 *
 * Idempotente: corre una sola vez por instancia (condición WHERE empresa_id IS NULL
 * no afecta filas ya resueltas).
 */
return function (): void {
    $db = Database::getConnection();

    // Asegurar que la tabla y la columna existan (instalaciones frescas).
    $stmt = $db->query("SHOW TABLES LIKE 'rxn_live_vistas'");
    if (!$stmt->fetch()) {
        return; // Nada para rescatar.
    }
    $stmt = $db->query("SHOW COLUMNS FROM rxn_live_vistas LIKE 'empresa_id'");
    if (!$stmt->fetch()) {
        return; // La migración anterior (02) debería haber agregado la columna. Si falta, nada para hacer.
    }

    // Contar huérfanas antes.
    $total = (int) $db->query("SELECT COUNT(*) FROM rxn_live_vistas WHERE empresa_id IS NULL")->fetchColumn();
    if ($total === 0) {
        return;
    }

    // Determinar empresa fallback: primera empresa_id no nula en usuarios.
    $fallbackEmpresa = $db->query("SELECT MIN(empresa_id) FROM usuarios WHERE empresa_id IS NOT NULL AND empresa_id > 0")->fetchColumn();
    if (!$fallbackEmpresa) {
        $fallbackEmpresa = 1; // Empresa rxn_admin / suite-reaxion por convención de rxn_suite.
    }

    $stmt = $db->prepare("UPDATE rxn_live_vistas SET empresa_id = ? WHERE empresa_id IS NULL");
    $stmt->execute([(int)$fallbackEmpresa]);
    $affected = $stmt->rowCount();

    error_log("[migration 2026_04_20_03_rescue_orphan_rxn_live_vistas] rescatadas {$affected} vistas huérfanas → empresa_id={$fallbackEmpresa}");
};
