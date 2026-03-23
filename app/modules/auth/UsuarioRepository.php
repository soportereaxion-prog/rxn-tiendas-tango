<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Database;

class UsuarioRepository
{
    public function findByEmail(string $email): ?Usuario
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetchObject(Usuario::class);
        return $user ?: null;
    }
}
