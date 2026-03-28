<?php

declare(strict_types=1);

namespace App\Shared\Services;

use DateTimeImmutable;
use Throwable;

final class VersionService
{
    private static ?array $config = null;

    public static function all(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $file = BASE_PATH . '/app/config/version.php';

        if (!is_file($file)) {
            self::$config = self::fallbackConfig();
            return self::$config;
        }

        $config = require $file;

        if (!is_array($config)) {
            self::$config = self::fallbackConfig();
            return self::$config;
        }

        self::$config = [
            'current_version' => (string) ($config['current_version'] ?? ''),
            'current_build' => (string) ($config['current_build'] ?? ''),
            'history' => isset($config['history']) && is_array($config['history']) ? $config['history'] : [],
        ];

        return self::$config;
    }

    public static function current(): array
    {
        $config = self::all();
        $version = $config['current_version'];
        $build = $config['current_build'];

        foreach (self::history() as $entry) {
            if (($entry['version'] ?? '') !== $version) {
                continue;
            }

            if ($build === '' || ($entry['build'] ?? '') === $build) {
                return $entry;
            }
        }

        $history = self::history(1);

        return $history[0] ?? self::fallbackEntry();
    }

    public static function history(int $limit = 0): array
    {
        $history = array_values(array_filter(self::all()['history'], static fn ($entry): bool => is_array($entry)));

        if ($limit > 0) {
            return array_slice($history, 0, $limit);
        }

        return $history;
    }

    public static function currentHighlights(int $limit = 3): array
    {
        $items = self::current()['items'] ?? [];

        if (!is_array($items)) {
            return [];
        }

        $items = array_values(array_filter($items, static fn ($item): bool => is_string($item) && $item !== ''));

        if ($limit > 0) {
            return array_slice($items, 0, $limit);
        }

        return $items;
    }

    public static function currentLabel(): string
    {
        $version = (string) (self::current()['version'] ?? '0.0.0');

        return 'v' . $version;
    }

    public static function currentBuildLabel(): string
    {
        $build = (string) (self::current()['build'] ?? '');

        return $build !== '' ? 'build ' . $build : '';
    }

    public static function formattedDate(?string $date): string
    {
        if ($date === null || trim($date) === '') {
            return '';
        }

        try {
            return (new DateTimeImmutable($date))->format('d/m/Y');
        } catch (Throwable) {
            return $date;
        }
    }

    private static function fallbackConfig(): array
    {
        return [
            'current_version' => '0.0.0',
            'current_build' => 'local',
            'history' => [self::fallbackEntry()],
        ];
    }

    private static function fallbackEntry(): array
    {
        return [
            'version' => '0.0.0',
            'build' => 'local',
            'released_at' => '',
            'title' => 'Version interna no configurada',
            'summary' => 'No hay una release declarada en el archivo de version.',
            'items' => [
                'Defini la release activa en app/config/version.php.',
            ],
        ];
    }
}
