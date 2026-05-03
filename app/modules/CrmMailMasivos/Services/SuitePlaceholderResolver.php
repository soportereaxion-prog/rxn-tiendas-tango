<?php

declare(strict_types=1);

namespace App\Modules\CrmMailMasivos\Services;

/**
 * SuitePlaceholderResolver
 *
 * Reemplaza placeholders globales de la suite (no atados a un destinatario
 * particular) por valores absolutos al momento de armar el body_snapshot
 * de un envío o de previsualizar el mail final.
 *
 * Tokens soportados:
 *   {{Suite.base_url}} → scheme + host actual (ej. https://e-reaxion.com.ar)
 *   {{Suite.logo_url}} → URL absoluta del logo principal de Reaxion
 *
 * Multi-tenant: los valores se construyen a partir de $_SERVER en cada
 * request, así cada empresa apunta a su propio dominio sin compartir
 * configuración. Cuando no hay request (ej. CLI), se usa $_ENV['APP_URL']
 * como fallback y, si tampoco está, queda http://localhost.
 */
class SuitePlaceholderResolver
{
    public const LOGO_PATH = '/img/email/LogoRXN-SinFondo.png';

    public static function resolve(string $html): string
    {
        if ($html === '' || strpos($html, '{{Suite.') === false) {
            return $html;
        }
        $base = self::baseUrl();
        $map = [
            '{{Suite.base_url}}' => $base,
            '{{Suite.logo_url}}' => $base . self::LOGO_PATH,
        ];
        return strtr($html, $map);
    }

    public static function baseUrl(): string
    {
        $appUrl = (string) ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: '');
        if ($appUrl !== '') {
            return rtrim($appUrl, '/');
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return 'http://localhost';
        }
        $scheme = (string) ($_SERVER['REQUEST_SCHEME'] ?? '');
        if ($scheme === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        }
        return $scheme . '://' . $host;
    }
}
