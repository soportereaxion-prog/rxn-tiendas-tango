<?php

declare(strict_types=1);

namespace App\Shared\Services;

use DateTimeImmutable;
use Throwable;

final class ModuleNoteService
{
    private const STORAGE_PATH = '/app/storage/module_notes.json';
    private const MAX_NOTES_PER_MODULE = 50;
    private const MAX_ATTACHMENTS_PER_NOTE = 6;
    private const DEFAULT_TYPES = ['idea', 'ajuste', 'bug', 'dato'];

    public static function modules(): array
    {
        $modules = array_values(self::readStorage()['modules']);

        foreach ($modules as &$module) {
            $module['count'] = count($module['notes']);
        }
        unset($module);

        usort($modules, static function (array $left, array $right): int {
            return strcmp((string) ($right['updated_at'] ?? ''), (string) ($left['updated_at'] ?? ''));
        });

        return $modules;
    }

    public static function notesForModule(string $moduleKey, int $limit = 5): array
    {
        $key = self::normalizeModuleKey($moduleKey);
        $modules = self::readStorage()['modules'];
        $notes = $modules[$key]['notes'] ?? [];

        if ($limit > 0) {
            return array_slice($notes, 0, $limit);
        }

        return $notes;
    }

    public static function totalNotes(): int
    {
        $total = 0;

        foreach (self::modules() as $module) {
            $total += (int) ($module['count'] ?? 0);
        }

        return $total;
    }

    public static function add(
        string $moduleKey,
        string $moduleLabel,
        string $type,
        string $content,
        int $authorId,
        string $authorName,
        array $attachments = []
    ): void {
        $key = self::normalizeModuleKey($moduleKey);
        $label = self::normalizeModuleLabel($moduleLabel, $key);
        $normalizedType = self::normalizeType($type);
        $normalizedContent = trim(preg_replace('/\R/u', "\n", $content) ?? $content);
        $normalizedAttachments = self::normalizeIncomingAttachments($attachments);

        if ($key === '' || ($normalizedContent === '' && $normalizedAttachments === [])) {
            throw new \InvalidArgumentException('La nota del modulo necesita texto o al menos una captura.');
        }

        if (strlen($normalizedContent) > 3000) {
            throw new \InvalidArgumentException('La nota supera el maximo permitido de 3000 caracteres.');
        }

        if (count($normalizedAttachments) > self::MAX_ATTACHMENTS_PER_NOTE) {
            throw new \InvalidArgumentException('Cada nota admite hasta ' . self::MAX_ATTACHMENTS_PER_NOTE . ' capturas.');
        }

        $storage = self::readStorage();
        $module = $storage['modules'][$key] ?? [
            'key' => $key,
            'label' => $label,
            'updated_at' => '',
            'notes' => [],
        ];

        $timestamp = date('Y-m-d H:i:s');
        $module['key'] = $key;
        $module['label'] = $label;
        $module['updated_at'] = $timestamp;
        $module['notes'] = $module['notes'] ?? [];

        array_unshift($module['notes'], [
            'id' => self::generateId(),
            'type' => $normalizedType,
            'content' => $normalizedContent,
            'attachments' => $normalizedAttachments,
            'author_id' => $authorId,
            'author_name' => trim($authorName) !== '' ? trim($authorName) : 'Administrador',
            'created_at' => $timestamp,
        ]);

        $module['notes'] = array_slice($module['notes'], 0, self::MAX_NOTES_PER_MODULE);
        $storage['modules'][$key] = $module;

        self::writeStorage($storage);
    }

    public static function typeLabel(string $type): string
    {
        return match (self::normalizeType($type)) {
            'ajuste' => 'Ajuste',
            'bug' => 'Bug',
            'dato' => 'Dato',
            default => 'Idea',
        };
    }

    public static function typeBadgeClass(string $type): string
    {
        return match (self::normalizeType($type)) {
            'ajuste' => 'text-bg-success',
            'bug' => 'text-bg-danger',
            'dato' => 'text-bg-secondary',
            default => 'text-bg-primary',
        };
    }

    public static function formatTimestamp(?string $timestamp): string
    {
        if ($timestamp === null || trim($timestamp) === '') {
            return '';
        }

        try {
            return (new DateTimeImmutable($timestamp))->format('d/m/Y H:i');
        } catch (Throwable) {
            return $timestamp;
        }
    }

    private static function readStorage(): array
    {
        $path = self::storageFilePath();

        if (!is_file($path)) {
            return ['modules' => []];
        }

        $json = file_get_contents($path);
        if ($json === false || trim($json) === '') {
            return ['modules' => []];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return ['modules' => []];
        }

        $modules = [];
        foreach (($decoded['modules'] ?? []) as $key => $module) {
            if (!is_array($module)) {
                continue;
            }

            $normalizedKey = self::normalizeModuleKey(is_string($key) ? $key : (string) ($module['key'] ?? ''));
            if ($normalizedKey === '') {
                continue;
            }

            $notes = [];
            foreach (($module['notes'] ?? []) as $note) {
                if (!is_array($note)) {
                    continue;
                }

                $content = trim((string) ($note['content'] ?? ''));
                $attachments = self::normalizeStoredAttachments($note);

                if ($content === '' && $attachments === []) {
                    continue;
                }

                $notes[] = [
                    'id' => (string) ($note['id'] ?? self::generateId()),
                    'type' => self::normalizeType((string) ($note['type'] ?? 'idea')),
                    'content' => $content,
                    'attachments' => $attachments,
                    'author_id' => (int) ($note['author_id'] ?? 0),
                    'author_name' => (string) ($note['author_name'] ?? 'Administrador'),
                    'created_at' => (string) ($note['created_at'] ?? ''),
                ];
            }

            $modules[$normalizedKey] = [
                'key' => $normalizedKey,
                'label' => self::normalizeModuleLabel((string) ($module['label'] ?? ''), $normalizedKey),
                'updated_at' => (string) ($module['updated_at'] ?? ''),
                'notes' => $notes,
            ];
        }

        return ['modules' => $modules];
    }

    private static function writeStorage(array $storage): void
    {
        $path = self::storageFilePath();
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(
            $path,
            json_encode($storage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            LOCK_EX
        );
    }

    private static function storageFilePath(): string
    {
        return BASE_PATH . self::STORAGE_PATH;
    }

    private static function normalizeIncomingAttachments(array $attachments): array
    {
        $normalized = [];
        $fallbackIndex = 1;

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $path = self::normalizeAttachmentPath((string) ($attachment['path'] ?? ''));
            if ($path === null) {
                continue;
            }

            $label = self::normalizeAttachmentLabel((string) ($attachment['label'] ?? ''), $fallbackIndex);
            $labelIndex = self::extractAttachmentIndex($label);
            $fallbackIndex = $labelIndex !== null ? $labelIndex + 1 : $fallbackIndex + 1;

            $name = trim((string) ($attachment['name'] ?? ''));

            $normalized[] = [
                'label' => $label,
                'path' => $path,
                'name' => $name !== '' ? $name : basename($path),
            ];
        }

        return $normalized;
    }

    private static function normalizeStoredAttachments(array $note): array
    {
        $attachments = [];

        if (isset($note['attachments']) && is_array($note['attachments'])) {
            $attachments = $note['attachments'];
        } elseif (!empty($note['attachment_path'])) {
            $attachments = [[
                'label' => '#imagen1',
                'path' => (string) $note['attachment_path'],
                'name' => (string) ($note['attachment_name'] ?? ''),
            ]];
        }

        return self::normalizeIncomingAttachments($attachments);
    }

    private static function normalizeModuleKey(string $moduleKey): string
    {
        $normalized = strtolower(trim($moduleKey));
        $normalized = preg_replace('/[^a-z0-9_-]+/', '-', $normalized) ?? '';

        return trim($normalized, '-_');
    }

    private static function normalizeModuleLabel(string $moduleLabel, string $fallbackKey): string
    {
        $label = trim($moduleLabel);
        if ($label !== '') {
            return $label;
        }

        $fallback = str_replace(['-', '_'], ' ', $fallbackKey);
        return ucwords($fallback);
    }

    private static function normalizeType(string $type): string
    {
        $normalized = strtolower(trim($type));

        if (!in_array($normalized, self::DEFAULT_TYPES, true)) {
            return 'idea';
        }

        return $normalized;
    }

    private static function normalizeAttachmentPath(string $attachmentPath): ?string
    {
        $normalized = trim($attachmentPath);

        if ($normalized === '' || !str_starts_with($normalized, '/uploads/module-notes/')) {
            return null;
        }

        return $normalized;
    }

    private static function normalizeAttachmentLabel(string $label, int $fallbackIndex): string
    {
        $normalized = strtolower(trim($label));

        if (preg_match('/^#?imagen(\d+)$/', $normalized, $matches) === 1) {
            $index = max(1, (int) ($matches[1] ?? 0));
            return '#imagen' . $index;
        }

        return '#imagen' . max(1, $fallbackIndex);
    }

    private static function extractAttachmentIndex(string $label): ?int
    {
        if (preg_match('/^#imagen(\d+)$/', strtolower(trim($label)), $matches) !== 1) {
            return null;
        }

        return max(1, (int) ($matches[1] ?? 0));
    }

    private static function generateId(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (Throwable) {
            return uniqid('note_', true);
        }
    }
}
