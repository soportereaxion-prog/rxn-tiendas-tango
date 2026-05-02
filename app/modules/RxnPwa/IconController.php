<?php

declare(strict_types=1);

namespace App\Modules\RxnPwa;

/**
 * Fallback runtime para los iconos de la PWA.
 *
 * Comportamiento:
 *  - Cuando el archivo físico existe en `public/icons/rxnpwa-{size}.png`, Apache
 *    lo sirve directo (gracias al RewriteCond !-f del .htaccess) y este controller
 *    NO se invoca.
 *  - Cuando el archivo NO existe (deploy incompleto, permisos del directorio,
 *    etc.), Apache cae al front controller y este handler genera el PNG con GD,
 *    lo persiste a disco para futuras requests, y lo sirve.
 *
 * Esto garantiza que `/icons/rxnpwa-192.png` y `/icons/rxnpwa-512.png` SIEMPRE
 * respondan 200 con un image/png válido. Crítico para que Chrome marque la PWA
 * como installable: si el icono del manifest da 404, no aparece "Instalar app".
 *
 * Sin auth (los iconos son públicos por naturaleza). Sin rate limit por la misma
 * razón — son requests one-shot que se cachean en disco al primer hit.
 */
class IconController
{
    /** @var int[] Sizes válidos del manifest. */
    private const ALLOWED_SIZES = [192, 512];

    public function serveRxnpwaIcon(string $size): void
    {
        $sizeInt = (int) $size;
        if (!in_array($sizeInt, self::ALLOWED_SIZES, true)) {
            http_response_code(404);
            echo 'Icon size not supported.';
            return;
        }

        // OJO: NO usar /public/icons/ porque Apache default tiene un alias /icons/
        // que apunta a los iconitos de mod_autoindex. En Plesk ese alias sobreescribe
        // al document root del vhost y los archivos nunca se sirven. Por eso vivimos
        // en /public/img/pwa/.
        $iconsDir = BASE_PATH . '/public/img/pwa';
        $targetFile = $iconsDir . '/rxnpwa-' . $sizeInt . '.png';

        // Caso normal: el archivo existe. Apache debería haberlo servido directo,
        // pero por las dudas (algún proxy raro), lo servimos nosotros.
        if (is_file($targetFile)) {
            $this->emit($targetFile);
            return;
        }

        // Caso fallback: generar on-the-fly con GD y persistir.
        if (!extension_loaded('gd')) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'GD extension missing — cannot generate fallback icon.';
            return;
        }

        if (!is_dir($iconsDir)) {
            @mkdir($iconsDir, 0775, true);
        }

        $bytes = $this->generatePng($sizeInt);
        if ($bytes === null) {
            http_response_code(500);
            echo 'Could not generate icon.';
            return;
        }

        // Persistir best-effort. Si falla (permisos), igual servimos los bytes
        // generados — la próxima request volverá a generar.
        if (is_dir($iconsDir) && is_writable($iconsDir)) {
            @file_put_contents($targetFile, $bytes);
        }

        header('Content-Type: image/png');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: public, max-age=86400');
        echo $bytes;
    }

    private function emit(string $path): void
    {
        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=2592000'); // 30 días
        readfile($path);
    }

    /**
     * Genera un PNG cuadrado con fondo `#0f172a` (theme_color del manifest) y
     * texto "RXN" centrado en blanco. Réplica de tools/generate_rxnpwa_icons.php.
     *
     * @return string|null Bytes del PNG, o null si falló.
     */
    private function generatePng(int $size): ?string
    {
        $img = imagecreatetruecolor($size, $size);
        if ($img === false) {
            return null;
        }

        $bg = imagecolorallocate($img, 0x0f, 0x17, 0x2a);
        $fg = imagecolorallocate($img, 0xff, 0xff, 0xff);
        imagefill($img, 0, 0, $bg);

        $text = 'RXN';
        $font = 5;
        $textW = imagefontwidth($font) * strlen($text);
        $textH = imagefontheight($font);

        $tmpW = $textW * 2;
        $tmpH = $textH * 2;
        $tmp = imagecreatetruecolor($tmpW, $tmpH);
        $tmpBg = imagecolorallocate($tmp, 0x0f, 0x17, 0x2a);
        $tmpFg = imagecolorallocate($tmp, 0xff, 0xff, 0xff);
        imagefill($tmp, 0, 0, $tmpBg);
        imagestring($tmp, $font, (int) (($tmpW - $textW) / 2), (int) (($tmpH - $textH) / 2), $text, $tmpFg);

        $targetW = (int) ($size * 0.55);
        $targetH = (int) ($targetW * ($tmpH / $tmpW));
        $dstX = (int) (($size - $targetW) / 2);
        $dstY = (int) (($size - $targetH) / 2);

        imagecopyresampled($img, $tmp, $dstX, $dstY, 0, 0, $targetW, $targetH, $tmpW, $tmpH);
        imagedestroy($tmp);

        ob_start();
        imagepng($img, null, 6);
        $bytes = ob_get_clean();
        imagedestroy($img);

        return $bytes ?: null;
    }
}
