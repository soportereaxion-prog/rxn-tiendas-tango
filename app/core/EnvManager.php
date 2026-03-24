<?php

declare(strict_types=1);

namespace App\Core;

class EnvManager
{
    private string $envPath;

    public function __construct(string $envPath = null)
    {
        $this->envPath = $envPath ?? __DIR__ . '/../../.env';
    }

    /**
     * Retorna todas las variables parseadas desde .env de forma cruda como diccionario
     */
    public function getParsedVariables(): array
    {
        if (!file_exists($this->envPath)) {
            return [];
        }

        $lines = file($this->envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $vars = [];

        foreach ($lines as $line) {
            $line = trim($line);
            // Saltear comentarios
            if (str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                $parts = explode('=', $line, 2);
                $key = trim($parts[0]);
                $val = trim($parts[1]);
                $vars[$key] = $val;
            }
        }

        return $vars;
    }

    /**
     * Actualiza masivamente el archivo .env conservando comentarios y espaciados originales.
     * Insertará las keys faltantes al final del archivo.
     */
    public function updateVariables(array $updates): void
    {
        if (!file_exists($this->envPath)) {
            throw new \Exception("El archivo .env no existe en {$this->envPath}. Imposible actualizar configuraciones.");
        }

        $lines = file($this->envPath, FILE_IGNORE_NEW_LINES);
        $output = [];
        $keysUpdated = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                $output[] = $line;
                continue;
            }

            if (str_contains($line, '=')) {
                $parts = explode('=', $line, 2);
                $key = trim($parts[0]);

                if (array_key_exists($key, $updates)) {
                    // Update key in place
                    $output[] = $key . '=' . $updates[$key];
                    $keysUpdated[] = $key;
                } else {
                    $output[] = $line; // Keep untouched
                }
            } else {
                $output[] = $line;
            }
        }

        // Si se nos mandaron a actualizar Keys que NO existían previamente, las adjuntamos abajo
        $newKeys = array_diff(array_keys($updates), $keysUpdated);
        if (!empty($newKeys)) {
            $output[] = '';
            foreach ($newKeys as $key) {
                $output[] = $key . '=' . $updates[$key];
            }
        }

        $content = implode("\n", $output) . "\n";
        if (file_put_contents($this->envPath, $content) === false) {
             throw new \Exception("Permiso denegado al intentar escribir sobre .env");
        }
    }
}
