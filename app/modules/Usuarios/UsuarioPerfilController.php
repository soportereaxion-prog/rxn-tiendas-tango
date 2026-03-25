<?php
declare(strict_types=1);

namespace App\Modules\Usuarios;

use App\Core\View;
use App\Core\Database;
use PDO;

class UsuarioPerfilController
{
    public function index(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /rxnTiendasIA/public/login');
            exit;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        View::render('app/modules/Usuarios/views/mi_perfil.php', ['usuario' => $usuario]);
    }

    public function guardar(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /rxnTiendasIA/public/login');
            exit;
        }

        $tema = $_POST['preferencia_tema'] ?? 'light';
        $fuente = $_POST['preferencia_fuente'] ?? 'md';
        
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE usuarios SET preferencia_tema = ?, preferencia_fuente = ? WHERE id = ?");
        $stmt->execute([$tema, $fuente, $_SESSION['user_id']]);

        // Actualizar caché de sesión en vivo
        $_SESSION['pref_theme'] = $tema;
        $_SESSION['pref_font'] = $fuente;

        header('Location: /rxnTiendasIA/public/mi-perfil?success=Preferencias+actualizadas');
        exit;
    }
}
