<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * UploadValidator — validación centralizada de uploads.
 *
 * Previene:
 * - Archivos con extensión válida pero MIME real malicioso (ej: .jpg que es PHP).
 * - Archivos que no son imagen real (fallan getimagesize()).
 * - Archivos que exceden tamaño máximo.
 *
 * Siempre usar este validator en lugar de chequear sólo `pathinfo(..., PATHINFO_EXTENSION)`.
 */
class UploadValidator
{
    private const IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private const FAVICON_MIMES = [
        'image/x-icon'      => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
        'image/png'         => 'png',
        'image/jpeg'        => 'jpg',
    ];

    public const DEFAULT_MAX_BYTES = 5 * 1024 * 1024; // 5 MB

    /**
     * Valida un upload de imagen común (producto, categoría, logo, header/footer de impresión).
     *
     * @param array|null $file  Entrada de $_FILES['campo'] (puede ser null si no subieron nada).
     * @param int $maxBytes     Tope en bytes.
     * @return array|null       ['tmp_name', 'ext', 'mime', 'size'] o null si no hay archivo.
     * @throws RuntimeException si el archivo existe pero es inválido.
     */
    public static function image(?array $file, int $maxBytes = self::DEFAULT_MAX_BYTES): ?array
    {
        return self::validate($file, self::IMAGE_MIMES, $maxBytes, true);
    }

    /**
     * Valida un upload de favicon (acepta ico + png/jpg).
     */
    public static function favicon(?array $file, int $maxBytes = 1 * 1024 * 1024): ?array
    {
        return self::validate($file, self::FAVICON_MIMES, $maxBytes, false);
    }

    /**
     * Núcleo del validator.
     *
     * @param array<string,string> $allowedMimes  mime => extension canónica
     * @param bool $requireImageDimensions  si true, usa getimagesize() como tercer check
     * @return array{tmp_name:string,ext:string,mime:string,size:int}|null
     */
    private static function validate(?array $file, array $allowedMimes, int $maxBytes, bool $requireImageDimensions): ?array
    {
        if (!is_array($file)) {
            return null;
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo procesar el archivo subido (error ' . $error . ').');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Archivo temporal de upload inválido.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            throw new RuntimeException('El archivo subido está vacío.');
        }
        if ($size > $maxBytes) {
            $maxMb = number_format($maxBytes / (1024 * 1024), 1);
            throw new RuntimeException('El archivo supera el tamaño máximo permitido (' . $maxMb . ' MB).');
        }

        $mime = self::detectMime($tmpName);
        if ($mime === null || !isset($allowedMimes[$mime])) {
            throw new RuntimeException('Tipo de archivo no permitido. Formatos aceptados: ' . implode(', ', array_values($allowedMimes)) . '.');
        }

        if ($requireImageDimensions) {
            $info = @getimagesize($tmpName);
            if ($info === false || !isset($info[0], $info[1]) || $info[0] <= 0 || $info[1] <= 0) {
                throw new RuntimeException('El archivo no parece ser una imagen válida.');
            }
        }

        return [
            'tmp_name' => $tmpName,
            'ext'      => $allowedMimes[$mime],
            'mime'     => $mime,
            'size'     => $size,
        ];
    }

    private static function detectMime(string $tmpName): ?string
    {
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = @finfo_file($finfo, $tmpName);
                @finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($tmpName);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        return null;
    }

    /**
     * Prepara un directorio de upload con permisos seguros.
     * Reemplaza el patrón `mkdir($dir, 0777, true)` que aparecía antes en los uploaders.
     */
    public static function prepareDir(string $absolutePath): void
    {
        if (is_dir($absolutePath)) {
            return;
        }
        if (!@mkdir($absolutePath, 0755, true) && !is_dir($absolutePath)) {
            throw new RuntimeException('No se pudo preparar el directorio de uploads: ' . $absolutePath);
        }
    }

    /**
     * Genera un filename seguro: {prefix}_{empresaId}_{timestamp}_{random}.{ext}
     * Nunca usa $_FILES[...]['name'] del usuario (mitiga path traversal).
     */
    public static function generateFilename(string $prefix, int $empresaId, string $ext, ?string $suffix = null): string
    {
        $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix) ?: 'file';
        $ts = date('YmdHis');
        $rand = bin2hex(random_bytes(4));
        $suffixPart = $suffix !== null && $suffix !== '' ? '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $suffix) : '';

        return sprintf('%s_%d_%s%s_%s.%s', $safePrefix, $empresaId, $ts, $suffixPart, $rand, $ext);
    }
}
