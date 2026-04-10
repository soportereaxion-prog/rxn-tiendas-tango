<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
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
