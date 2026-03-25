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

    public function guardarOrdenDashboard(): void
    {
        if (empty($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'No autorizado']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['order']) && is_array($input['order'])) {
            $jsonOrder = json_encode($input['order']);
            
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("UPDATE usuarios SET dashboard_order = ? WHERE id = ?");
            $stmt->execute([$jsonOrder, $_SESSION['user_id']]);
            
            $_SESSION['dashboard_order'] = $jsonOrder;
            
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Bad request']);
        }
        exit;
    }
}
