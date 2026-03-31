<?php
declare(strict_types=1);

namespace App\Modules\CrmPedidosServicio;

use App\Modules\EmpresaConfig\EmpresaConfigService;

class ClasificacionCatalogService
{
    /**
     * @return array<int, array{code:string,description:string,display:string,line:string}>
     */
    public function getEntries(): array
    {
        $config = EmpresaConfigService::forCrm()->getConfig();
        $raw = (string) ($config->clasificaciones_pds_raw ?? '');

        return $this->parseRaw($raw);
    }

    /**
     * @return array<int, array{code:string,description:string,display:string,line:string}>
     */
    public function search(string $term = '', int $limit = 8): array
    {
        $term = trim($term);
        $entries = $this->getEntries();

        if ($term === '') {
            return array_slice($entries, 0, $limit);
        }

        $needle = mb_strtolower($term);
        $filtered = array_values(array_filter($entries, static function (array $entry) use ($needle): bool {
            return str_contains(mb_strtolower($entry['code']), $needle)
                || str_contains(mb_strtolower($entry['description']), $needle)
                || str_contains(mb_strtolower($entry['line']), $needle);
        }));

        return array_slice($filtered, 0, $limit);
    }

    /**
     * @return array{code:string,description:string,display:string,line:string}|null
     */
    public function resolve(string $code = '', string $description = '', string $display = ''): ?array
    {
        $code = strtoupper(trim($code));
        $description = trim($description);
        $display = trim($display);

        foreach ($this->getEntries() as $entry) {
            if ($code !== '' && strtoupper($entry['code']) === $code) {
                return $entry;
            }

            if ($display !== '' && ($entry['display'] === $display || $entry['line'] === $display)) {
                return $entry;
            }

            if ($description !== '' && $entry['description'] === $description && ($code === '' || strtoupper($entry['code']) === $code)) {
                return $entry;
            }
        }

        if ($code !== '') {
            return [
                'code' => $code,
                'description' => $description,
                'display' => $description !== '' ? $code . ' - ' . $description : $code,
                'line' => $description !== '' ? $code . ' ' . $description : $code,
            ];
        }

        return null;
    }

    /**
     * @return array<int, array{code:string,description:string,display:string,line:string}>
     */
    private function parseRaw(string $raw): array
    {
        $entries = [];
        $seen = [];
        $lines = preg_split('/\R/u', $raw) ?: [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (!preg_match('/^(\S+)\s+(.+)$/u', $line, $matches)) {
                $code = strtoupper($line);
                $description = '';
            } else {
                $code = strtoupper(trim((string) ($matches[1] ?? '')));
                $description = trim((string) ($matches[2] ?? ''));
            }

            if ($code === '' || isset($seen[$code])) {
                continue;
            }

            $seen[$code] = true;
            $entries[] = [
                'code' => $code,
                'description' => $description,
                'display' => $description !== '' ? $code . ' - ' . $description : $code,
                'line' => $line,
            ];
        }

        return $entries;
    }
}
