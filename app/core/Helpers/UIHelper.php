<?php
declare(strict_types=1);

namespace App\Core\Helpers;

use App\Core\Database;
use PDO;

class UIHelper
{
    /**
     * Computa los atributos dinámicos del tag <HTML> en el Admin (B2B).
     * Lee SIEMPRE de DB — no usar `$_SESSION` como caché: el snapshot de sesión
     * entre pestañas genera race condition (pestaña A cambia tema → pestaña B
     * al cerrar su request sobrescribe la sesión con valores stale y el cambio
     * se pierde). La DB es la única fuente de verdad. Costo: 1 query por render.
     */
    public static function getHtmlAttributes(): string
    {
        $theme = 'light';
        $font = 'md';

        if (!empty($_SESSION['user_id'])) {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare("SELECT preferencia_tema, preferencia_fuente FROM usuarios WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $theme = $row['preferencia_tema'] ?: 'light';
                    $font = $row['preferencia_fuente'] ?: 'md';
                    // Mantenemos la sesión sincronizada para componentes que todavía
                    // leen de `$_SESSION['pref_theme']` (user_action_menu.php, etc.).
                    $_SESSION['pref_theme'] = $theme;
                    $_SESSION['pref_font'] = $font;
                }
            } catch (\Exception $e) {}
        }

        return sprintf('data-theme="%s" data-font="%s"', htmlspecialchars($theme), htmlspecialchars($font));
    }

    /**
     * Construye un bloque <style> reactivo con variables CSS consumidas desde 
     * la UI pública (Store Front B2C), extraídas del branding configurado por el Operador.
     */
    public static function getTenantStyles(int $empresaId): string
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT color_primary, color_secondary FROM empresas WHERE id = ?");
            $stmt->execute([$empresaId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                // Caemos a un fallback negro/gris si no está cargado, simulando Bootstrap Dark default
                $primary = $row['color_primary'] ?: '#212529'; 
                $secondary = $row['color_secondary'] ?: '#495057';

                return "
                <style>
                    :root {
                        --color-primary: {$primary};
                        --color-secondary: {$secondary};
                        
                        /* Hacks Bootstrap Cascade */
                        --bs-primary: {$primary};
                        --bs-dark: {$secondary};
                        --bs-primary-rgb: " . self::hexToRgb($primary) . ";
                    }
                </style>
                ";
            }
        } catch (\Exception $e) {}

        return "";
    }

    private static function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex,0,1).substr($hex,0,1));
            $g = hexdec(substr($hex,1,1).substr($hex,1,1));
            $b = hexdec(substr($hex,2,1).substr($hex,2,1));
        } else {
            $r = hexdec(substr($hex,0,2));
            $g = hexdec(substr($hex,2,2));
            $b = hexdec(substr($hex,4,2));
        }
        return "$r, $g, $b";
    }
}
