<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Sistema simple de cache en disco usando JSON serializado 
 * para aislar las tiendas de consultas masivas concurrentes a MySQL.
 */
class FileCache
{
    private static function getCacheDir(): string
    {
        $dir = BASE_PATH . '/app/storage/cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private static function getFilePath(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '', $key);
        return self::getCacheDir() . '/' . $safeKey . '.json';
    }

    public static function get(string $key): mixed
    {
        $file = self::getFilePath($key);
        if (is_file($file)) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            if (isset($data['expires']) && time() > $data['expires']) {
                unlink($file);
                return null;
            }
            return $data['value'] ?? null;
        }
        return null;
    }

    public static function set(string $key, mixed $value, int $ttlSeconds = 3600): void
    {
        $file = self::getFilePath($key);
        $data = [
            'expires' => time() + $ttlSeconds,
            'value' => $value
        ];
        file_put_contents($file, json_encode($data));
    }

    public static function delete(string $key): void
    {
        $file = self::getFilePath($key);
        if (is_file($file)) {
            unlink($file);
        }
    }

    public static function clearAll(): void
    {
        $files = glob(self::getCacheDir() . '/*.json');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public static function clearPrefix(string $prefix): void
    {
        $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix);
        $files = glob(self::getCacheDir() . '/' . $safePrefix . '*.json');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
