<?php

declare(strict_types=1);

if (!function_exists('h')) {
    /**
     * Escapa caracteres HTML para prevenir inyecciones XSS.
     * Convierte comillas dobles y simples a entidades.
     */
    function h(?string $value): string {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
