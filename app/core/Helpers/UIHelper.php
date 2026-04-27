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
        [$theme, $font, $zoom] = self::loadUserUiPrefs();
        // El "zoom" se aplica como font-size: X% en el <html>. Bootstrap usa
        // rem extensivamente (botones, padding, spacing, breakpoints), así
        // que cambiar el font-size root reflowea todo el layout PROPORCIONAL
        // sin tocar el viewport — los containers siguen ocupando 100%, pero
        // el contenido se hace más chico/grande y entra/sale más en la
        // pantalla. Es exactamente el comportamiento del Ctrl+/Ctrl- nativo
        // de Chrome.
        //
        // Por qué NO usar `zoom` ni `transform: scale`:
        //   - `zoom` escala todo incluido el viewport efectivo → los cards
        //     se achican pero no llenan el ancho.
        //   - `transform: scale` deja el espacio reservado original → genera
        //     bandas vacías y rompe fixed positioning.
        $style = $zoom !== 100 ? sprintf(' style="font-size: %d%%;"', $zoom) : '';
        return sprintf(
            'data-theme="%s" data-font="%s" data-zoom="%d"%s',
            htmlspecialchars($theme),
            htmlspecialchars($font),
            $zoom,
            $style
        );
    }

    /**
     * No-op. El zoom se inyecta en el <html> via getHtmlAttributes() como
     * font-size. Se mantiene el método para no romper el call site en
     * admin_layout.php.
     */
    public static function getBodyZoomStyle(): string
    {
        return '';
    }

    /**
     * Lectura única de las prefs visuales del usuario en sesión. Lee SIEMPRE de
     * DB — `$_SESSION` se usa solo como sync para componentes legacy.
     *
     * @return array{0:string,1:string,2:int} [tema, fuente, zoom]
     */
    private static function loadUserUiPrefs(): array
    {
        $theme = 'light';
        $font = 'md';
        $zoom = 100;

        if (!empty($_SESSION['user_id'])) {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare("SELECT preferencia_tema, preferencia_fuente, preferencia_zoom FROM usuarios WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $theme = $row['preferencia_tema'] ?: 'light';
                    $font  = $row['preferencia_fuente'] ?: 'md';
                    $zoom  = self::clampZoom((int) ($row['preferencia_zoom'] ?? 100));
                    $_SESSION['pref_theme'] = $theme;
                    $_SESSION['pref_font']  = $font;
                    $_SESSION['pref_zoom']  = $zoom;
                }
            } catch (\Exception $e) {}
        }

        return [$theme, $font, $zoom];
    }

    /**
     * Clampea el zoom a la grilla de valores permitidos. Cualquier valor fuera
     * de rango cae a 100 — defensivo ante manipulación del POST o data legacy.
     *
     * @return int
     */
    public static function clampZoom(int $zoom): int
    {
        $allowed = [75, 80, 90, 100, 110, 125, 150];
        return in_array($zoom, $allowed, true) ? $zoom : 100;
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
