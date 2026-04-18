<?php

declare(strict_types=1);

namespace App\Core;

/**
 * RateLimiter — throttle de intentos por key dentro de una ventana temporal.
 *
 * Persistencia en FileCache (sin migración de DB). Suficiente para deploys
 * de un solo frontend (Plesk actual). Si en el futuro hay múltiples workers,
 * migrar a tabla DB o Redis manteniendo esta API.
 *
 * Uso típico en un endpoint de auth:
 *
 *   $key = 'login:' . $email . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
 *   if (!RateLimiter::allow($key, 5, 900)) {
 *       // 5 intentos máx cada 15 min — responder genérico
 *   }
 *   RateLimiter::hit($key, 900);
 *   // ... validar credenciales
 *   // en éxito:
 *   RateLimiter::reset($key);
 */
class RateLimiter
{
    private const CACHE_PREFIX = 'rl_';

    /**
     * ¿Permite un nuevo intento? (no registra — sólo chequea).
     * Devuelve true si hay cuota; false si ya superó $maxAttempts dentro de la ventana.
     */
    public static function allow(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $data = self::read($key);
        if ($data === null) {
            return true;
        }

        // Si la ventana ya expiró, permitir (la entrada vieja se limpia al próximo hit).
        if ((int) ($data['expires_at'] ?? 0) <= time()) {
            return true;
        }

        return ((int) ($data['count'] ?? 0)) < $maxAttempts;
    }

    /**
     * Registra un intento. Si la ventana expiró (o no existe), arranca una nueva.
     */
    public static function hit(string $key, int $windowSeconds): void
    {
        $data = self::read($key);
        $now = time();

        if ($data === null || ((int) ($data['expires_at'] ?? 0)) <= $now) {
            $data = [
                'count' => 1,
                'first_at' => $now,
                'expires_at' => $now + $windowSeconds,
            ];
        } else {
            $data['count'] = ((int) ($data['count'] ?? 0)) + 1;
        }

        FileCache::set(self::cacheKey($key), $data, $windowSeconds);
    }

    /**
     * Chequea + registra + decide en una sola operación.
     * Retorna true si el intento es permitido (y queda registrado).
     * Retorna false si ya está throttled (no registra — evita extender la ventana infinitamente).
     */
    public static function attempt(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        if (!self::allow($key, $maxAttempts, $windowSeconds)) {
            return false;
        }
        self::hit($key, $windowSeconds);
        return true;
    }

    /**
     * Limpia la entrada (usar tras un intento exitoso — login ok, reset ok, etc.).
     */
    public static function reset(string $key): void
    {
        FileCache::delete(self::cacheKey($key));
    }

    /**
     * Segundos restantes hasta que se libera el throttle, o 0 si no hay bloqueo.
     */
    public static function retryAfter(string $key): int
    {
        $data = self::read($key);
        if ($data === null) {
            return 0;
        }
        $remaining = ((int) ($data['expires_at'] ?? 0)) - time();
        return max(0, $remaining);
    }

    private static function read(string $key): ?array
    {
        $value = FileCache::get(self::cacheKey($key));
        return is_array($value) ? $value : null;
    }

    private static function cacheKey(string $key): string
    {
        // FileCache sanitiza a [a-zA-Z0-9_-]. Usamos hash para colapsar chars inválidos y evitar colisiones.
        return self::CACHE_PREFIX . substr(hash('sha256', $key), 0, 32);
    }

    /**
     * Helper para obtener un identificador de cliente razonable.
     * Combina email (si se provee) + IP remota. NO es confiable al 100% (IP puede ser compartida/spoofeable),
     * pero alcanza para throttle defensivo.
     */
    public static function clientKey(string $scope, ?string $email = null): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $emailPart = $email !== null && $email !== '' ? ':' . strtolower(trim($email)) : '';
        return $scope . $emailPart . ':' . $ip;
    }
}
