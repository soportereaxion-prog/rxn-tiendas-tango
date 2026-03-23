<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    private const BASE_PATH = '/rxnTiendasIA/public';

    public function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function getUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = strtok($uri, '?');
        $uri = substr($uri, strlen(self::BASE_PATH));
        return '/' . trim((string) $uri, '/');
    }
}
