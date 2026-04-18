<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    /**
     * Valida el token CSRF en requests POST. Si falla, aborta con 419.
     * Llamar al inicio de cualquier action que procese POST.
     *
     * Excepciones legítimas (no llamar):
     * - Webhooks externos (usan HMAC).
     * - APIs públicas con auth por token.
     */
    protected function verifyCsrfOrAbort(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        $token = $_POST['csrf_token'] ?? null;
        if (!CsrfHelper::validate(is_string($token) ? $token : null)) {
            http_response_code(419);
            header('Content-Type: text/html; charset=utf-8');
            // Respuesta mínima — no revelar info del entorno.
            echo '<h1>419 — Sesión expirada</h1><p>El formulario expiró o el token de seguridad es inválido. Recargá la página e intentá de nuevo.</p>';
            exit;
        }
    }

    /**
     * Resuelve los filtros avanzados de la sesión.
     * Si no hay filtros en la URL pero existen en la sesión para este módulo, redirige a la URL con los filtros.
     * Si el usuario usa ?reset_filters=1, limpia la sesión y redirige limpio.
     * Retorna los filtros parseados para usarse.
     */
    protected function handleCrudFilters(string $moduleIdentifier): array
    {
        // 1. Limpieza explicita
        if (isset($_GET['reset_filters'])) {
            unset($_SESSION['crud_filters'][$moduleIdentifier]);
            
            // Reconstruir URL sin f y sin reset_filters
            $query = $_GET;
            unset($query['reset_filters'], $query['f']);
            $url = strtok($_SERVER["REQUEST_URI"], '?');
            $qs = http_build_query($query);
            
            header('Location: ' . $url . ($qs ? '?' . $qs : ''));
            exit;
        }

        // 2. Si vienen filtros en la URL, guardarlos en session
        if (isset($_GET['f']) && is_array($_GET['f'])) {
            $_SESSION['crud_filters'][$moduleIdentifier] = $_GET['f'];
            return $_GET['f'];
        }

        // 3. Fallback: Si NO vienen filtros en la URL, pero EXISTEN en la session, redireccionar forzando los filtros
        if (empty($_GET['f']) && isset($_SESSION['crud_filters'][$moduleIdentifier])) {
            $filters = $_SESSION['crud_filters'][$moduleIdentifier];
            
            // Solo redireccionamos si hay filtros reales, no un array vacio
            if (!empty($filters)) {
                $query = $_GET;
                $query['f'] = $filters;
                $url = strtok($_SERVER["REQUEST_URI"], '?');
                $qs = http_build_query($query);
                
                header('Location: ' . $url . '?' . $qs);
                exit;
            }
        }

        return [];
    }
}
