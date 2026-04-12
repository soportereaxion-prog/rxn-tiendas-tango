<?php
declare(strict_types=1);

namespace App\Modules\Usuarios;

use App\Core\View;
use App\Core\Database;
use App\Shared\Services\OperationalAreaService;
use PDO;

class UsuarioPerfilController
{
    public function index(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $area = OperationalAreaService::resolveFromRequest();

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        View::render('app/modules/Usuarios/views/mi_perfil.php', [
            'usuario' => $usuario,
            'area' => $area,
            'dashboardPath' => OperationalAreaService::dashboardPath($area),
            'helpPath' => OperationalAreaService::helpPath($area),
            'formPath' => OperationalAreaService::profilePath($area),
        ]);
    }

    public function guardar(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $area = OperationalAreaService::resolveFromRequest();

        $tema = $_POST['preferencia_tema'] ?? 'light';
        $fuente = $_POST['preferencia_fuente'] ?? 'md';
        $colorCalendario = $_POST['color_calendario'] ?? '#007bff';

        // Validar color hex
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colorCalendario)) {
            $colorCalendario = '#007bff';
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE usuarios SET preferencia_tema = ?, preferencia_fuente = ?, color_calendario = ? WHERE id = ?");
        $stmt->execute([$tema, $fuente, $colorCalendario, $_SESSION['user_id']]);

        // Actualizar caché de sesión en vivo
        $_SESSION['pref_theme'] = $tema;
        $_SESSION['pref_font'] = $fuente;
        $_SESSION['color_calendario'] = $colorCalendario;

        $redirect = OperationalAreaService::profilePath($area);
        $separator = str_contains($redirect, '?') ? '&' : '?';
        header('Location: ' . $redirect . $separator . 'success=Preferencias+actualizadas');
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
            $area = strtolower(trim((string) ($input['area'] ?? 'tiendas')));
            $area = $area === 'crm' ? 'crm' : 'tiendas';

            $currentOrder = $_SESSION['dashboard_order'] ?? null;
            $decodedOrder = json_decode((string) $currentOrder, true);

            if (is_array($decodedOrder) && array_is_list($decodedOrder)) {
                $decodedOrder = ['tiendas' => $decodedOrder];
            }

            if (!is_array($decodedOrder)) {
                $decodedOrder = [];
            }

            $decodedOrder[$area] = array_values($input['order']);
            $jsonOrder = json_encode($decodedOrder);
            
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("UPDATE usuarios SET dashboard_order = ? WHERE id = ?");
            $stmt->execute([$jsonOrder, $_SESSION['user_id']]);
            
            $_SESSION['dashboard_order'] = $jsonOrder;
            
            echo json_encode(['success' => true]);
        }
        exit;
    }

    public function toggleTheme(): void
    {
        if (empty($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'No autorizado']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $newTheme = $input['theme'] ?? 'light';
        if (!in_array($newTheme, ['light', 'dark'])) {
            $newTheme = 'light';
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("UPDATE usuarios SET preferencia_tema = ? WHERE id = ?");
            $stmt->execute([$newTheme, $_SESSION['user_id']]);
            $_SESSION['pref_theme'] = $newTheme;
            echo json_encode(['success' => true, 'theme' => $newTheme]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error de BD']);
        }
        exit;
    }
}
