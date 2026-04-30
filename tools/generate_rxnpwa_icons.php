<?php

declare(strict_types=1);

/**
 * Genera íconos placeholder de la PWA RXN — fondo oscuro #0f172a + "RXN" en blanco.
 * Reemplazables después por los íconos finales (mismas rutas).
 *
 * Uso:
 *   /d/RXNAPP/3.3/bin/php/php8.3.14/php.exe tools/generate_rxnpwa_icons.php
 */

$baseDir = dirname(__DIR__) . '/public/icons';
if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
    fwrite(STDERR, "No pude crear $baseDir\n");
    exit(1);
}

if (!extension_loaded('gd')) {
    fwrite(STDERR, "Falta extensión GD en PHP.\n");
    exit(1);
}

$generate = static function (int $size, string $path): void {
    $img = imagecreatetruecolor($size, $size);
    $bg = imagecolorallocate($img, 0x0f, 0x17, 0x2a);
    $fg = imagecolorallocate($img, 0xff, 0xff, 0xff);
    imagefill($img, 0, 0, $bg);

    $text = 'RXN';
    $font = 5; // built-in font (15px aprox)
    $textW = imagefontwidth($font) * strlen($text);
    $textH = imagefontheight($font);

    // Texto chico de la built-in font; lo escalamos creando un canvas chico y agrandándolo.
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

    imagepng($img, $path, 6);
    imagedestroy($img);

    echo "  [OK] $path (" . filesize($path) . " bytes)\n";
};

echo "Generando íconos placeholder PWA...\n";
$generate(192, $baseDir . '/rxnpwa-192.png');
$generate(512, $baseDir . '/rxnpwa-512.png');
echo "Listo. Reemplazables manualmente cuando haya arte final.\n";
