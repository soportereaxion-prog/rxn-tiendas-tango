<?php
declare(strict_types=1);

namespace App\Modules\PrintForms;

class PrintFormRenderer
{
    public function buildDocument(array $pageConfig, array $objects, array $context, ?string $backgroundUrl = null): array
    {
        $page = $this->normalizePageConfig($pageConfig);
        $background = $pageConfig['background'] ?? [];
        $defaults = $pageConfig['defaults'] ?? [];

        usort($objects, static function (array $a, array $b): int {
            return (int) ($a['z_index'] ?? 0) <=> (int) ($b['z_index'] ?? 0);
        });

        $renderedObjects = [];
        foreach ($objects as $object) {
            $renderedObjects[] = match (strtolower(trim((string) ($object['type'] ?? '')))) {
                'text', 'text_multiline' => $this->renderTextLike($object, $context, $page, $defaults, false),
                'variable' => $this->renderTextLike($object, $context, $page, $defaults, true),
                'image' => $this->renderImage($object, $context, $page, $defaults),
                'line' => $this->renderLine($object, $page),
                'rect' => $this->renderRect($object, $page),
                'table_repeater' => $this->renderTableRepeater($object, $context, $page, $defaults),
                default => null,
            };
        }

        // Leer configuración de color/transparencia de página desde page_config['page']
        $rawPage = $pageConfig['page'] ?? [];
        $bgColor = (string) ($rawPage['background_color'] ?? ($pageConfig['background_color'] ?? '#ffffff'));
        $isTransparent = !empty($rawPage['transparent_bg']) || !empty($pageConfig['transparent_bg']);

        return [
            'page' => [
                'size' => $page['size'],
                'orientation' => $page['orientation'],
                'width_mm' => $page['width_mm'],
                'height_mm' => $page['height_mm'],
                'background_url' => $backgroundUrl,
                'background_opacity' => isset($background['opacity']) ? (float) $background['opacity'] : 1,
                'background_color' => $bgColor !== '' ? $bgColor : '#ffffff',
                'transparent_bg' => $isTransparent,
            ],
            'objects' => array_values(array_filter($renderedObjects)),
        ];
    }

    private function renderTextLike(array $object, array $context, array $page, array $defaults, bool $isVariable): array
    {
        $style = $object['style'] ?? [];
        $content = $isVariable
            ? $this->resolveValue((string) ($object['source'] ?? ''), $context)
            : $this->interpolateText((string) ($object['content'] ?? ''), $context);

        if ($content === '' && $isVariable && str_starts_with((string) $object['source'], 'sample.')) {
            $content = '{{' . (string) $object['source'] . '}}';
        }

        return [
            'type' => 'text',
            'position_style' => $this->buildPositionStyle($object, $page),
            'inner_style' => implode('; ', [
                'display:block',
                'font-family:' . ($style['font_family'] ?? $defaults['font_family'] ?? 'Arial, Helvetica, sans-serif'),
                'font-size:' . (float) ($style['font_size_pt'] ?? $defaults['font_size_pt'] ?? 10) . 'pt',
                'font-weight:' . (int) ($style['font_weight'] ?? 400),
                'color:' . ($style['color'] ?? $defaults['color'] ?? '#111111'),
                'text-align:' . ($style['align'] ?? 'left'),
                'white-space:pre-wrap',
                'padding: 0.5mm',
                'overflow:hidden',
            ]),
            'content' => $content,
        ];
    }

    private function renderLine(array $object, array $page): array
    {
        $style = $object['style'] ?? [];
        $isVertical = (float) ($object['h_mm'] ?? 0) > (float) ($object['w_mm'] ?? 0);
        $stroke = (string) ($style['stroke'] ?? '#1f2937');
        $strokeWidth = max(0.1, (float) ($style['stroke_width_mm'] ?? 0.3));

        return [
            'type' => 'line',
            'position_style' => $this->buildPositionStyle($object, $page),
            'inner_style' => $isVertical
                ? 'width:0; height:100%; border-left:' . $strokeWidth . 'mm solid ' . $stroke
                : 'width:100%; height:0; border-top:' . $strokeWidth . 'mm solid ' . $stroke,
        ];
    }

    private function renderRect(array $object, array $page): array
    {
        $style = $object['style'] ?? [];

        return [
            'type' => 'rect',
            'position_style' => $this->buildPositionStyle($object, $page),
            'inner_style' => implode('; ', [
                'width:100%',
                'height:100%',
                'box-sizing:border-box',
                'border:' . max(0.1, (float) ($style['stroke_width_mm'] ?? 0.3)) . 'mm solid ' . ($style['stroke'] ?? '#94a3b8'),
                'background:' . (($style['fill'] ?? 'transparent') !== '' ? ($style['fill'] ?? 'transparent') : 'transparent'),
            ]),
        ];
    }

    private function renderImage(array $object, array $context, array $page, array $defaults): array
    {
        $style = $object['style'] ?? [];
        $src = $this->resolveValue((string) ($object['source'] ?? ''), $context);
        
        // Si no se puede resolver como variable, o si literal ya viene algo parseado e injertado, la imagen usaría el path
        // Aunque generalmente acá la variable tiene la ruta.
        if (empty($src)) {
             $src = (string) ($object['content'] ?? '');
             if (!empty($src)) {
                 $src = $this->interpolateText($src, $context);
             }
        }

        return [
            'type' => 'image',
            'position_style' => $this->buildPositionStyle($object, $page),
            'inner_style' => implode('; ', [
                'width:100%',
                'height:100%',
                'object-fit:' . ($style['object_fit'] ?? 'contain'),
            ]),
            'content' => $src,
        ];
    }

    private function renderTableRepeater(array $object, array $context, array $page, array $defaults): array
    {
        $style = $object['style'] ?? [];
        $columns = is_array($object['columns'] ?? null) ? $object['columns'] : [];
        $rows = $this->resolveRepeaterRows((string) ($object['source'] ?? ''), $context);
        $rowHeight = max(4.5, (float) ($object['row_height_mm'] ?? 8));
        $showHeader = !array_key_exists('show_header', $object) || (bool) $object['show_header'];
        $availableHeight = max(0.0, (float) ($object['h_mm'] ?? 0) - ($showHeader ? $rowHeight : 0.0));
        $maxRows = $rowHeight > 0 ? (int) floor($availableHeight / $rowHeight) : count($rows);
        if ($maxRows > 0 && count($rows) > $maxRows) {
            $rows = array_slice($rows, 0, $maxRows);
        }

        $totalWidth = 0.0;
        foreach ($columns as $column) {
            $totalWidth += max(0.0, (float) ($column['width_mm'] ?? 0));
        }
        if ($totalWidth <= 0) {
            $totalWidth = (float) max(1, count($columns));
        }

        return [
            'type' => 'table_repeater',
            'position_style' => $this->buildPositionStyle($object, $page) . '; overflow:hidden',
            'table_style' => implode('; ', [
                'width:100%',
                'height:100%',
                'border-collapse:collapse',
                'table-layout:fixed',
                'font-family:' . ($style['font_family'] ?? $defaults['font_family'] ?? 'Arial, Helvetica, sans-serif'),
                'font-size:' . (float) ($style['font_size_pt'] ?? 9) . 'pt',
                'color:' . ($style['color'] ?? $defaults['color'] ?? '#111111'),
            ]),
            'header_row_style' => 'height:' . $rowHeight . 'mm',
            'body_row_style' => 'height:' . $rowHeight . 'mm',
            'cell_style' => implode('; ', [
                'border:0.2mm solid ' . ($style['border_color'] ?? '#94a3b8'),
                'padding:0.8mm 1mm',
                'overflow:hidden',
                'text-overflow:ellipsis',
                'white-space:nowrap',
            ]),
            'header_cell_style' => implode('; ', [
                'border:0.2mm solid ' . ($style['border_color'] ?? '#94a3b8'),
                'padding:0.8mm 1mm',
                'background:' . ($style['header_background'] ?? '#e5e7eb'),
                'color:' . ($style['header_color'] ?? '#111111'),
                'font-weight:700',
                'overflow:hidden',
                'text-overflow:ellipsis',
                'white-space:nowrap',
            ]),
            'show_header' => $showHeader,
            'columns' => array_map(static function (array $column) use ($totalWidth): array {
                $width = max(0.0, (float) ($column['width_mm'] ?? 0));
                return [
                    'key' => (string) ($column['key'] ?? ''),
                    'label' => (string) ($column['label'] ?? ''),
                    'align' => (string) ($column['align'] ?? 'left'),
                    'width_percent' => $totalWidth > 0 ? ($width / $totalWidth) * 100 : 0,
                ];
            }, $columns),
            'rows' => $rows,
        ];
    }

    private function resolveRepeaterRows(string $source, array $context): array
    {
        $value = $this->resolveRawValue($source, $context);
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static function (mixed $row): ?array {
            if (!is_array($row)) {
                return null;
            }

            $normalized = [];
            foreach ($row as $key => $cellValue) {
                $normalized[(string) $key] = is_scalar($cellValue) || $cellValue === null ? (string) $cellValue : '';
            }

            return $normalized;
        }, $value)));
    }

    private function resolveValue(string $path, array $context): string
    {
        $value = $this->resolveRawValue($path, $context);

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return '';
    }

    private function interpolateText(string $text, array $context): string
    {
        // Interpola {{ variable.path }} o { variable.path } o incluso { Empresa / Nombre } 
        return preg_replace_callback('/\{{1,2}\s*([^}]+?)\s*\}{1,2}/', function (array $matches) use ($context) {
            // Normalizar el path: convertir a minusculas, reemplazar barras/espacios por puntos y eliminar dobles puntos
            $path = strtolower(trim($matches[1]));
            $path = preg_replace('/[\/\s\\\\]+/', '.', $path);
            $path = preg_replace('/\.+/', '.', $path);

            $value = $this->resolveRawValue($path, $context);
            if (is_scalar($value) || $value === null) {
                return (string) $value;
            }
            return $matches[0];
        }, $text) ?? $text;
    }

    private function resolveRawValue(string $path, array $context): mixed
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (str_ends_with($path, '[]')) {
            $path = substr($path, 0, -2);
        }

        $segments = explode('.', $path);
        $carry = $context;

        foreach ($segments as $segment) {
            if (!is_array($carry) || !array_key_exists($segment, $carry)) {
                return null;
            }
            $carry = $carry[$segment];
        }

        return $carry;
    }

    private function buildPositionStyle(array $object, array $page): string
    {
        return implode('; ', [
            'position:absolute',
            'left:' . max(0.0, (float) ($object['x_mm'] ?? 0)) . 'mm',
            'top:' . max(0.0, (float) ($object['y_mm'] ?? 0)) . 'mm',
            'width:' . max(0.0, (float) ($object['w_mm'] ?? 0)) . 'mm',
            'height:' . max(0.0, (float) ($object['h_mm'] ?? 0)) . 'mm',
            'z-index:' . (int) ($object['z_index'] ?? 1),
            'box-sizing:border-box',
        ]);
    }

    private function justifyFromAlign(string $align): string
    {
        return match ($align) {
            'center' => 'center',
            'right' => 'flex-end',
            default => 'flex-start',
        };
    }

    private function normalizePageConfig(array $pageConfig): array
    {
        $page = $pageConfig['page'] ?? [];
        $orientation = (string) ($page['orientation'] ?? 'portrait');
        $baseWidth = (float) ($page['width_mm'] ?? 210);
        $baseHeight = (float) ($page['height_mm'] ?? 297);

        if ($orientation === 'landscape') {
            return [
                'size' => (string) ($page['size'] ?? 'A4'),
                'orientation' => 'landscape',
                'width_mm' => $baseHeight,
                'height_mm' => $baseWidth,
            ];
        }

        return [
            'size' => (string) ($page['size'] ?? 'A4'),
            'orientation' => 'portrait',
            'width_mm' => $baseWidth,
            'height_mm' => $baseHeight,
        ];
    }
}
