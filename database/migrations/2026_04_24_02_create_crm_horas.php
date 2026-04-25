<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Tabla principal del módulo Horas (turnero CRM).
    //
    // Una fila = un turno. started_at se setea al iniciar; ended_at al cerrar.
    // Mientras ended_at IS NULL → estado='abierto'.
    //
    // Geo: capturamos lat/lng al inicio Y al cierre. consent_start/end indican
    //   si el navegador concedió la geolocalización. Si False, lat/lng quedan NULL
    //   pero el turno se guarda igual (decisión: geo opcional con aviso).
    //
    // Modo:
    //   - en_vivo: el turno fue iniciado con un click "Iniciar" desde el turnero.
    //   - diferido: el turno fue cargado a posteriori vía el form "Cargar turno diferido".
    //     En ese caso geo_diferido_lat/lng captura DÓNDE estaba el operador AL CARGAR
    //     (no donde estuvo trabajando). Si esa geo no coincide con un patrón razonable
    //     respecto a la started_at del turno, se setea inconsistencia_geo=1 para que
    //     admin pueda revisar.
    //
    // Vínculos opcionales — pueden coexistir los 3 (decisión 6.1):
    //   - tratativa_id: turno trabajado dentro de una oportunidad comercial.
    //   - pds_id: turno asociado a un Pedido de Servicio específico.
    //   - cliente_id: turno asociado directamente a un cliente (sin tratativa ni PDS).
    //
    // Estado:
    //   - abierto: hay started_at, no hay ended_at.
    //   - cerrado: tiene ambos.
    //   - anulado: el operador o admin lo descartó. motivo_anulacion obligatorio.
    $db->exec("
    CREATE TABLE IF NOT EXISTS crm_horas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        usuario_id INT NOT NULL,
        started_at DATETIME NOT NULL,
        ended_at DATETIME NULL DEFAULT NULL,
        modo ENUM('en_vivo','diferido') NOT NULL DEFAULT 'en_vivo',
        estado ENUM('abierto','cerrado','anulado') NOT NULL DEFAULT 'abierto',
        concepto VARCHAR(255) NULL,
        tratativa_id INT NULL,
        pds_id INT NULL,
        cliente_id INT NULL,
        geo_start_lat DECIMAL(10,7) NULL,
        geo_start_lng DECIMAL(10,7) NULL,
        geo_consent_start TINYINT(1) NOT NULL DEFAULT 0,
        geo_end_lat DECIMAL(10,7) NULL,
        geo_end_lng DECIMAL(10,7) NULL,
        geo_consent_end TINYINT(1) NOT NULL DEFAULT 0,
        geo_diferido_lat DECIMAL(10,7) NULL,
        geo_diferido_lng DECIMAL(10,7) NULL,
        inconsistencia_geo TINYINT(1) NOT NULL DEFAULT 0,
        motivo_anulacion TEXT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL DEFAULT NULL,
        KEY idx_horas_user_open (empresa_id, usuario_id, estado, deleted_at),
        KEY idx_horas_user_range (empresa_id, usuario_id, started_at, deleted_at),
        KEY idx_horas_tratativa (empresa_id, tratativa_id, deleted_at),
        KEY idx_horas_pds (empresa_id, pds_id, deleted_at),
        KEY idx_horas_cliente (empresa_id, cliente_id, deleted_at),
        CONSTRAINT fk_horas_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
