<?php

declare(strict_types=1);

namespace App\Shared\Services;

/**
 * Helper de cambio de base de datos para desarrollo.
 *
 * Feature ÚNICAMENTE para dev local. Se activa solo si existe el archivo
 * `config/dev_databases.local.php` en la raíz del proyecto (gitignored).
 *
 * ⚠ NO VA A PROD: el directorio `/config/` de la raíz NO está en el whitelist
 * del ReleaseBuilder (ver `app/core/ReleaseBuilder.php` — solo sube
 * `app|public|vendor|database|deploy_db|composer.*`). En prod el archivo
 * `dev_databases.local.php` no existe, `isEnabled()` devuelve false, y todo
 * el feature queda inerte.
 *
 * Formato del archivo:
 *   return [
 *       'rxn_suite'        => 'Dev local (base actual)',
 *       'rxn_suite_prod'   => '⚠ Snapshot prod 20/04',
 *   ];
 * (clave = nombre real de la DB en MySQL; valor = label para el dropdown).
 */
final class DevDbSwitcher
{
    private const SESSION_KEY = 'dev_db_override';
    private const CONFIG_FILE = '/config/dev_databases.local.php';

    /**
     * True si el archivo de config local existe. Único gate del feature.
     */
    public static function isEnabled(): bool
    {
        return is_file(BASE_PATH . self::CONFIG_FILE);
    }

    /**
     * Lista de DBs disponibles [nombre_db => label]. Vacío si el feature no está activo.
     *
     * @return array<string, string>
     */
    public static function getAvailable(): array
    {
        if (!self::isEnabled()) {
            return [];
        }
        $data = require BASE_PATH . self::CONFIG_FILE;
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $dbName => $label) {
            if (!is_string($dbName) || $dbName === '') continue;
            $out[$dbName] = is_string($label) && $label !== '' ? $label : $dbName;
        }
        return $out;
    }

    /**
     * Nombre de DB activo. Si hay override de sesión válido, lo devuelve; sino null
     * (para que `app/config/database.php` caiga al valor del .env).
     */
    public static function getActiveOverride(): ?string
    {
        if (!self::isEnabled()) {
            return null;
        }
        $override = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($override) || $override === '') {
            return null;
        }
        // Validar que el override esté en la lista permitida. Si alguien cacheó una sesión vieja
        // con un nombre que ya no está en el config, descartamos silenciosamente.
        $available = self::getAvailable();
        return array_key_exists($override, $available) ? $override : null;
    }

    /**
     * Setea el override en sesión. Devuelve true si se aceptó, false si:
     *  - feature no está enabled.
     *  - el nombre no está en la whitelist.
     *  - el valor está vacío (equivale a "limpiar override" y usar el .env).
     */
    public static function setActive(string $dbName): bool
    {
        if (!self::isEnabled()) {
            return false;
        }
        if ($dbName === '') {
            unset($_SESSION[self::SESSION_KEY]);
            return true;
        }
        $available = self::getAvailable();
        if (!array_key_exists($dbName, $available)) {
            return false;
        }
        $_SESSION[self::SESSION_KEY] = $dbName;
        return true;
    }
}
