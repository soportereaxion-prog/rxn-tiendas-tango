<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Modules\Auth\AuthService;
use App\Shared\Services\DevDbSwitcher;
use App\Core\Flash;

/**
 * Endpoint del DbSwitcher (dev only).
 *
 * En prod el archivo `config/dev_databases.local.php` no existe → DevDbSwitcher::isEnabled()
 * devuelve false → el endpoint responde 404/forbidden aunque alguien encuentre la ruta. El
 * dropdown tampoco se renderiza en prod (ver backoffice_user_banner.php).
 */
final class DevDbSwitchController
{
    public function switch(): void
    {
        AuthService::requireRxnAdmin();

        if (!DevDbSwitcher::isEnabled()) {
            http_response_code(404);
            exit('DbSwitcher no está activo en este entorno.');
        }

        $dbName = trim((string) ($_POST['db'] ?? ''));
        $ok = DevDbSwitcher::setActive($dbName);

        if (!$ok) {
            Flash::set('danger', 'No se pudo cambiar la base de datos: valor inválido.');
        } else {
            $label = DevDbSwitcher::getAvailable()[$dbName] ?? $dbName;
            Flash::set('success', 'Base de datos activa: ' . $label);
        }

        // Volver al referer (o al dashboard admin como fallback).
        $back = (string) ($_SERVER['HTTP_REFERER'] ?? '/admin/dashboard');
        header('Location: ' . $back);
        exit;
    }
}
