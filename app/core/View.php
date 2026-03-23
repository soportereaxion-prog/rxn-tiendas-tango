<?php

declare(strict_types=1);

namespace App\Core;

class View
{
    /**
     * Renderiza un archivo de vista PHP.
     *
     * @param string $path  Ruta relativa a BASE_PATH, ej: 'app/modules/dashboard/views/index.php'
     * @param array  $data  Variables a pasar a la vista
     */
    public static function render(string $path, array $data = []): void
    {
        $file = BASE_PATH . '/' . ltrim($path, '/');

        if (!is_file($file)) {
            http_response_code(500);
            echo 'Vista no encontrada: ' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
            return;
        }

        // Extrae el array $data como variables locales disponibles en la vista.
        extract($data, EXTR_SKIP);

        require $file;
    }
}
