<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string) ($title ?? 'Preview de impresion')) ?> - rxn_suite</title>
    <style>
        :root {
            color-scheme: light;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #eef2f7;
            color: #111827;
        }

        .print-preview-toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.9rem 1rem;
            background: rgba(17, 24, 39, 0.95);
            color: #f8fafc;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.18);
            backdrop-filter: blur(8px);
        }

        .print-preview-toolbar__title {
            display: grid;
            gap: 0.2rem;
        }

        .print-preview-toolbar__title small {
            color: #cbd5e1;
        }

        .print-preview-toolbar__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .print-preview-toolbar__actions a,
        .print-preview-toolbar__actions button {
            appearance: none;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
            padding: 0.55rem 0.9rem;
            font: inherit;
            text-decoration: none;
            cursor: pointer;
        }

        .print-preview-toolbar__actions .is-primary {
            background: #2563eb;
            border-color: #2563eb;
        }

        .print-preview-shell {
            padding: 1.5rem 0.75rem 2.5rem;
        }

        .print-page {
            position: relative;
            width: <?= number_format((float) ($page['width_mm'] ?? 210), 2, '.', '') ?>mm;
            min-height: <?= number_format((float) ($page['height_mm'] ?? 297), 2, '.', '') ?>mm;
            margin: 0 auto;
            background-color: #ffffff !important;
            color: #000000 !important;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
            overflow: hidden;
        }

        .print-page__background {
            position: absolute;
            inset: 0;
            z-index: 0;
            background-position: center;
            background-repeat: no-repeat;
            background-size: 100% 100%;
        }

        .print-object {
            position: absolute;
            box-sizing: border-box;
        }

        .print-object__table {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        @page {
            size: <?= htmlspecialchars((string) (($page['size'] ?? 'A4') . ' ' . (($page['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait'))) ?>;
            margin: 0;
        }

        @media print {
            body {
                background: #fff;
            }

            .print-preview-toolbar {
                display: none !important;
            }

            .print-preview-shell {
                padding: 0;
            }

            .print-page {
                width: <?= number_format((float) ($page['width_mm'] ?? 210), 2, '.', '') ?>mm;
                min-height: <?= number_format((float) ($page['height_mm'] ?? 297), 2, '.', '') ?>mm;
                margin: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <?php if (empty($hideToolbar)): ?>
    <div class="print-preview-toolbar">
        <div class="print-preview-toolbar__title">
            <strong><?= htmlspecialchars((string) ($title ?? 'Preview de impresion')) ?></strong>
            <small><?= htmlspecialchars((string) ($subtitle ?? 'Formulario renderizado con la definicion activa del canvas.')) ?></small>
        </div>
        <div class="print-preview-toolbar__actions">
            <?php if (!empty($backPath)): ?><a href="<?= htmlspecialchars((string) $backPath) ?>">Volver</a><?php endif; ?>
            <?php if (!empty($printPath)): ?><a href="<?= htmlspecialchars((string) $printPath) ?>">Modo imprimir</a><?php endif; ?>
            <button type="button" class="is-primary" onclick="window.print()">Imprimir</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="print-preview-shell" <?php if (!empty($hideToolbar)): ?>style="padding:0;"<?php endif; ?>>
        <?php
        $resolveUrl = static function(string $url): string {
            $url = trim($url);
            if ($url === '') return '';
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                return $url;
            }
            if (str_starts_with($url, '/')) {
                $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                return $scheme . '://' . $host . $url;
            }
            return $url;
        };
        $bgUrl = $resolveUrl((string) ($page['background_url'] ?? ''));
        $isTransparent = !empty($page['transparent_bg']);
        $bgColor = $isTransparent ? 'transparent' : htmlspecialchars((string) ($page['background_color'] ?? '#ffffff'));
        ?>
        <div class="print-page" style="background-color: <?= $bgColor ?>;">
            <?php if (!empty($bgUrl)): ?>
                <div class="print-page__background" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; background-position: center; background-repeat: no-repeat; background-size: 100% 100%; background-image:url('<?= htmlspecialchars($bgUrl) ?>'); opacity:<?= htmlspecialchars((string) ($page['background_opacity'] ?? 1)) ?>;"></div>
            <?php endif; ?>

            <?php foreach (($renderedObjects ?? []) as $object): ?>
                <div class="print-object" style="<?= htmlspecialchars((string) ($object['position_style'] ?? '')) ?>">
                    <?php if (($object['type'] ?? '') === 'text'): ?>
                        <div style="<?= htmlspecialchars((string) ($object['inner_style'] ?? '')) ?>"><?= nl2br(\App\Shared\Helpers\HtmlSanitizer::allowSafeInlineHtml((string) ($object['content'] ?? ''))) ?></div>
                    <?php elseif (($object['type'] ?? '') === 'image'): ?>
                        <img src="<?= htmlspecialchars($resolveUrl((string) ($object['content'] ?? ''))) ?>" style="<?= htmlspecialchars((string) ($object['inner_style'] ?? '')) ?>" alt="Canvas Image">
                    <?php elseif (($object['type'] ?? '') === 'line' || ($object['type'] ?? '') === 'rect'): ?>
                        <div style="<?= htmlspecialchars((string) ($object['inner_style'] ?? '')) ?>"></div>
                    <?php elseif (($object['type'] ?? '') === 'table_repeater'): ?>
                        <table class="print-object__table" style="<?= htmlspecialchars((string) ($object['table_style'] ?? '')) ?>">
                            <?php if (!empty($object['show_header'])): ?>
                                <thead>
                                    <tr style="<?= htmlspecialchars((string) ($object['header_row_style'] ?? '')) ?>">
                                        <?php foreach (($object['columns'] ?? []) as $column): ?>
                                            <th style="<?= htmlspecialchars((string) ($object['header_cell_style'] ?? '')) ?>; width:<?= number_format((float) ($column['width_percent'] ?? 0), 4, '.', '') ?>%; text-align:<?= htmlspecialchars((string) ($column['align'] ?? 'left')) ?>;">
                                                <?= htmlspecialchars((string) ($column['label'] ?? '')) ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                            <?php endif; ?>
                            <tbody>
                                <?php foreach (($object['rows'] ?? []) as $row): ?>
                                    <tr style="<?= htmlspecialchars((string) ($object['body_row_style'] ?? '')) ?>">
                                        <?php foreach (($object['columns'] ?? []) as $column): ?>
                                            <?php $cellKey = (string) ($column['key'] ?? ''); ?>
                                            <td style="<?= htmlspecialchars((string) ($object['cell_style'] ?? '')) ?>; text-align:<?= htmlspecialchars((string) ($column['align'] ?? 'left')) ?>;">
                                                <?= htmlspecialchars((string) ($row[$cellKey] ?? '')) ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($autoPrint)): ?>
        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    <?php endif; ?>
    <script src="/js/rxn-shortcuts.js"></script>
</body>
</html>

