<?php
define('BASE_PATH', __DIR__);
require 'vendor/autoload.php';

$envFile = BASE_PATH . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (trim($line) && !str_starts_with(trim($line), '#')) {
            putenv($line);
        }
    }
}

try {
    $db = App\Core\Database::getConnection();

    $sqlCategorias = "
    CREATE TABLE IF NOT EXISTS categorias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        nombre VARCHAR(120) NOT NULL,
        slug VARCHAR(160) NOT NULL,
        descripcion_corta VARCHAR(255) NULL,
        imagen_portada VARCHAR(255) NULL,
        orden_visual INT NOT NULL DEFAULT 0,
        activa TINYINT(1) NOT NULL DEFAULT 1,
        visible_store TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_categorias_empresa_slug (empresa_id, slug),
        KEY idx_categorias_store (empresa_id, visible_store, activa, orden_visual),
        KEY idx_categorias_nombre (empresa_id, nombre)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($sqlCategorias);
    echo "Tabla categorias creada o verificada.\n";

    $sqlMap = "
    CREATE TABLE IF NOT EXISTS articulo_categoria_map (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        articulo_codigo_externo VARCHAR(191) NOT NULL,
        categoria_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_articulo_categoria_empresa_codigo (empresa_id, articulo_codigo_externo),
        KEY idx_articulo_categoria_categoria (empresa_id, categoria_id),
        CONSTRAINT fk_articulo_categoria_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($sqlMap);
    echo "Tabla articulo_categoria_map creada o verificada.\n";
} catch (Exception $e) {
    echo 'ERROR MIGRANDO CATEGORIAS: ' . $e->getMessage() . "\n";
}
