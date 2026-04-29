<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Controller;

/**
 * Endpoint de status de sesión para que el front sepa cuánto le queda
 * antes de expirar (idle / absoluto). Permite mostrar aviso preventivo
 * sin que el usuario descubra la expiración tipeando un form.
 *
 * IMPORTANTE: este endpoint NO renueva por sí mismo más allá del refresh
 * natural que hace App.php cuando ve user_id en sesión. Es decir, llamarlo
 * mantiene viva la sesión igual que cualquier otro hit autenticado — lo
 * cual está bien, porque el botón "Extender ahora" se apoya justamente en
 * esa renovación natural.
 */
class SessionController extends Controller
{
    public function heartbeat(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'authenticated' => false]);
            return;
        }

        $idleTimeout = 21600;     // 6 horas — debe matchear App.php
        $absoluteTimeout = 43200; // 12 horas — debe matchear App.php

        $now = time();
        $createdAt = (int) ($_SESSION['backoffice_created_at'] ?? $now);
        // App.php ya actualizó last_activity en este request (somos un hit
        // autenticado), así que el remaining_idle es básicamente el máximo.
        $lastActivity = (int) ($_SESSION['backoffice_last_activity'] ?? $now);

        $remainingIdle = max(0, $idleTimeout - ($now - $lastActivity));
        $remainingAbsolute = max(0, $absoluteTimeout - ($now - $createdAt));

        echo json_encode([
            'ok' => true,
            'authenticated' => true,
            'remaining_idle' => $remainingIdle,
            'remaining_absolute' => $remainingAbsolute,
            'now' => $now,
        ]);
    }
}
