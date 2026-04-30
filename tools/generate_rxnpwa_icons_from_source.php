<?php

declare(strict_types=1);

/**
 * Genera íconos finales de la PWA RXN desde el arte fuente.
 *
 * Fuente esperada: public/icons/rxnpwa-source.png
 *   (cuadrado, idealmente 1024×1024, fondo transparente o sólido oscuro).
 *
 * Output:
 *   public/icons/rxnpwa-192.png — 192×192 con safe-area de 12% (para máscaras circulares).
 *   public/icons/rxnpwa-512.png — 512×512 con safe-area de 12%.
 *
 * Uso:
 *   /d/RXNAPP/3.3/bin/php/php8.3.14/php.exe tools/generate_rxnpwa_icons_from_source.php
 */

$baseDir = dirname(__DIR__) . '/public/icons';
$sourcePath = $baseDir . '/rxnpwa-source.png';

if (!extension_loaded('gd')) {
    fwrite(STDERR, "Falta extensión GD en PHP.\n");
    exit(1);
}

if (!is_file($sourcePath)) {
    fwrite(STDERR, "No encontré la fuente en: $sourcePath\n");
    fwrite(STDERR, "Dejá el PNG ahí (cuadrado, idealmente 1024×1024) y reintentá.\n");
    fwrite(STDERR, "Mientras tanto se sigue usando el placeholder de tools/generate_rxnpwa_icons.php.\n");
    exit(1);
}

$source = @imagecreatefrompng($sourcePath);
if (!$source) {
    fwrite(STDERR, "No pude abrir el PNG fuente: $sourcePath\n");
    exit(1);
}

$srcW = imagesx($source);
$srcH = imagesy($source);

$generate = static function (int $size) use ($source, $srcW, $srcH, $baseDir): void {
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    // Fondo negro #0f172a (consistente con el theme PWA + manifest).
    $bg = imagecolorallocatealpha($img, 0x0f, 0x17, 0x2a, 0);
    imagefill($img, 0, 0, $bg);
    imagealphablending($img, true);

    // Safe-area: 12% por lado para máscaras circulares.
    $padding = (int) round($size * 0.12);
    $targetSize = $size - 2 * $padding;

    imagecopyresampled(
        $img,
        $source,
        $padding,
        $padding,
        0,
        0,
        $targetSize,
        $targetSize,
        $srcW,
        $srcH
    );

    $path = $baseDir . '/rxnpwa-' . $size . '.png';
    imagepng($img, $path, 6);
    imagedestroy($img);

    echo "  [OK] $path (" . filesize($path) . " bytes)\n";
};

echo "Generando íconos finales desde $sourcePath ({$srcW}×{$srcH})...\n";
$generate(192);
$generate(512);
imagedestroy($source);
echo "Listo. SW debe bumpearse para invalidar el cache previo.\n";
