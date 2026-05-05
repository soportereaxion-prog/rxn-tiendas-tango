<?php

declare(strict_types=1);

namespace App\Modules\RxnLive;

use App\Core\View;

class RxnLiveController
{
    private RxnLiveService $service;

    public function __construct()
    {
        $this->service = new RxnLiveService();
    }

    public function index(): void
    {
        if (isset($_GET['from'])) {
            if ($_GET['from'] === 'crm') {
                $_SESSION['rxn_live_back_url'] = '/mi-empresa/crm/dashboard';
                $_SESSION['rxn_live_back_label'] = 'Volver a CRM';
            } elseif ($_GET['from'] === 'tiendas') {
                $_SESSION['rxn_live_back_url'] = '/mi-empresa/dashboard';
                $_SESSION['rxn_live_back_label'] = 'Volver a Tiendas';
            } elseif ($_GET['from'] === 'admin') {
                $_SESSION['rxn_live_back_url'] = '/admin/dashboard';
                $_SESSION['rxn_live_back_label'] = 'Volver a Panel Admin';
            }
        }

        $datasets = $this->service->getAvailableDatasets();
        View::render('app/modules/RxnLive/views/index.php', [
            'datasets' => $datasets
        ]);
    }

    public function dataset(): void
    {
        $datasetKey = $_GET['dataset'] ?? ($_POST['dataset'] ?? '');
        if (!$datasetKey || !$this->service->isValidDataset($datasetKey)) {
            header('Location: /rxn_live');
            exit;
        }

        // SAFE MODE: escape hatch ante vistas/filtros corruptos que tumban la UI.
        // Uso: /rxn_live/dataset?dataset=X&safe_mode=1
        // - Salta redirect por last_url (para no caer de nuevo en la URL rota cacheada).
        // - Descarta view_id y todos los filtros GET.
        // - No guarda last_url (no contamina la sesión).
        // - El front detecta window.rxnSafeMode y skippea hidratación de sessionStorage.
        $safeMode = !empty($_GET['safe_mode']);

        if (isset($_GET['reset_view'])) {
            unset($_SESSION['rxn_live_last_url'][$datasetKey]);
            $redirectUrl = "/rxn_live/dataset?dataset=" . urlencode($datasetKey);
            if (isset($_GET['view_id'])) {
                $redirectUrl .= "&view_id=" . urlencode($_GET['view_id']);
            }
            header("Location: " . $redirectUrl);
            exit;
        }

        if (!$safeMode) {
            $lastUrl = $_SESSION['rxn_live_last_url'][$datasetKey] ?? '';
            if (count($_GET) === 1 && isset($_GET['dataset']) && !empty($lastUrl) && $lastUrl !== $_SERVER['REQUEST_URI']) {
                header("Location: " . $lastUrl);
                exit;
            }
            $_SESSION['rxn_live_last_url'][$datasetKey] = $_SERVER['REQUEST_URI'];
        } else {
            // En safe mode limpiamos el last_url del dataset así la próxima vez
            // sin safe_mode no vuelve a caer en la URL rota.
            unset($_SESSION['rxn_live_last_url'][$datasetKey]);
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) $page = 1;

        // per_page: tamaño de página seleccionado por el usuario. Whitelist estricta.
        // 'all' carga todo (limit técnico = 1.000.000 para evitar OOM en datasets monstruosos).
        $allowedPerPage = ['50', '100', '250', '500', 'all'];
        $perPageRaw = (string)($_GET['per_page'] ?? '50');
        if (!in_array($perPageRaw, $allowedPerPage, true)) {
            $perPageRaw = '50';
        }
        $perPage = $perPageRaw;
        $limit = ($perPage === 'all') ? 1000000 : (int)$perPage;
        // Cuando "Todos" está activo no hay paginación: forzamos page=1 para que la paginación
        // del SQL no pida un offset que devolvería 0 filas.
        if ($perPage === 'all') $page = 1;

        if ($safeMode) {
            // Descartar cualquier filtro GET en safe mode — cargamos dataset limpio.
            $filters = [];
        } else {
            $filters = $_GET;
            unset(
                $filters['dataset'], $filters['page'], $filters['per_page'], $filters['view_id'],
                $filters['reset_view'], $filters['reset_filters'],
                $filters['b_query'], $filters['query'], $filters['estado'], $filters['razon_social']
            );
        }

        $data = $this->service->getDatasetData($datasetKey, $filters, $page, $limit);
        $totalRegistros = $this->service->getDatasetCount($datasetKey, $filters);

        // Clave de sesión: AuthService guarda 'user_id' (no 'usuario_id'). Este módulo estaba leyendo
        // la clave incorrecta desde hace varias releases, lo que resultaba en usuario_id=0 en todas
        // las vistas guardadas (bug silencioso hasta la release 1.16.2 — el scope empresa lo hizo visible).
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $empresaId = (int)($_SESSION['empresa_id'] ?? 0);
        // En safe mode no cargamos vistas — evita que el JS intente rehidratar
        // alguna configuración rota si el navegador tiene view_id cacheado en algún lado.
        // Las vistas se comparten a nivel empresa: todos los usuarios ven las mismas,
        // pero solo el dueño puede editar o eliminar las suyas.
        $myViews = $safeMode ? [] : $this->service->getUserViews($empresaId, $datasetKey);

        View::render('app/modules/RxnLive/views/dataset.php', [
            'datasetKey' => $datasetKey,
            'myViews' => $myViews,
            'currentUserId' => $userId,
            'datasetInfo' => $this->service->getDatasetInfo($datasetKey),
            'filters' => $filters,
            'datasetRows' => $data,
            'totalRegistros' => $totalRegistros,
            'page' => $page,
            'limit' => $limit,
            'perPage' => $perPage,
            'safeMode' => $safeMode,
        ]);
    }

    public function exportar(): void
    {
        $datasetKey = $_POST['dataset'] ?? '';
        if (!$datasetKey || !$this->service->isValidDataset($datasetKey)) {
            header('Location: /rxn_live');
            exit;
        }

        $format = $_POST['format'] ?? 'csv';

        $hiddenColsJson = $_POST['hidden_cols'] ?? '[]';
        $hiddenCols = json_decode($hiddenColsJson, true);
        if (!is_array($hiddenCols)) $hiddenCols = [];

        $orderedColsJson = $_POST['ordered_cols'] ?? '[]';
        $orderedCols = json_decode($orderedColsJson, true);
        if (!is_array($orderedCols)) $orderedCols = [];

        $filters = $_POST;

        $discreteFiltersJson = $filters['discrete_filters'] ?? '{}';
        $discreteFilters = json_decode($discreteFiltersJson, true);
        if (!is_array($discreteFilters)) $discreteFilters = [];

        // Filtros "contiene" por columna (los que el user tipea en el input "Filtrar..." del header).
        // Antes el export ignoraba esto y salía el dataset entero. Ahora se aplican en memoria
        // igual que en el JS, replicando el formato visual de fechas para que matcheen con lo que
        // el usuario ve en pantalla.
        $flatFiltersJson = $filters['flat_filters'] ?? '{}';
        $flatFilters = json_decode($flatFiltersJson, true);
        if (!is_array($flatFilters)) $flatFilters = [];

        // Formato de fecha visual del front. Necesario para que los flat_filters matcheen con
        // lo que el user ve en pantalla (si tipeó "05/03" en una fecha mostrada como DD/MM/YYYY).
        $globalDateFormat = (string)($filters['global_date_format'] ?? 'Y-m-d');

        // Widths custom por columna en píxeles — los setea el user con el resize handle del front.
        // Se convierten a Excel width units (≈ px/7 para Calibri 11 default) antes de aplicarlos al XLSX.
        $colWidthsJson = $filters['col_widths'] ?? '{}';
        $colWidths = json_decode($colWidthsJson, true);
        if (!is_array($colWidths)) $colWidths = [];

        unset(
            $filters['dataset'], $filters['format'], $filters['hidden_cols'], $filters['ordered_cols'],
            $filters['view_id'], $filters['discrete_filters'], $filters['flat_filters'],
            $filters['global_date_format'], $filters['col_widths']
        );

        $data = $this->service->getDatasetData($datasetKey, $filters, 1, 10000); // Múltiplos lógicos preventivos

        // Metadata de columnas (tipo date/datetime) para aplicar filtros visuales de fecha.
        $datasetInfo = $this->service->getDatasetInfo($datasetKey);
        $pivotMeta = $datasetInfo['pivot_metadata'] ?? [];

        $formatVisual = function ($raw, string $col) use ($pivotMeta, $globalDateFormat) {
            if ($raw === null || $raw === '') return $raw;
            $type = $pivotMeta[$col]['type'] ?? null;
            $isDate = in_array($type, ['date', 'datetime', 'timestamp'], true);
            if (!$isDate || $globalDateFormat === 'Y-m-d') return $raw;
            try {
                $dt = new \DateTime((string)$raw);
                // Mapeo mínimo JS → PHP: el front usa tokens estilo PHP date(), así que va directo.
                return $dt->format($globalDateFormat);
            } catch (\Throwable $e) {
                return $raw;
            }
        };

        // Aplicar filtros discretos en memoria (evita complejidad en ORM/SQL)
        if (!empty($data) && !empty($discreteFilters)) {
            $data = array_filter($data, function($row) use ($discreteFilters, $formatVisual) {
                foreach ($discreteFilters as $col => $allowedValues) {
                    if (!empty($allowedValues)) {
                        $val = $row[$col] ?? null;
                        // Replicar formateo visual de fechas para matchear con lo elegido en el dropdown.
                        $val = $formatVisual($val, $col);
                        if ($val === null || $val === '') $val = '(Vacío)';
                        $valStr = (string)$val;
                        if (!in_array($valStr, $allowedValues, true)) {
                            return false;
                        }
                    }
                }
                return true;
            });
            $data = array_values($data);
        }

        // Aplicar flat filters (texto "contiene" por columna) en memoria.
        // Soporta wildcards % estilo LIKE: "abc%" = empieza con abc, "%abc" = termina con abc, "a%c" = matchea a...c.
        if (!empty($data) && !empty($flatFilters)) {
            $data = array_filter($data, function($row) use ($flatFilters, $formatVisual) {
                foreach ($flatFilters as $col => $term) {
                    $term = (string)$term;
                    if ($term === '') continue;
                    $raw = $row[$col] ?? '';
                    $val = (string)$formatVisual($raw, $col);
                    $termLc = mb_strtolower($term);
                    $valLc = mb_strtolower($val);
                    if (strpos($termLc, '%') !== false) {
                        // preg_quote no escapa el %, así que str_replace después es seguro.
                        $pattern = '/^' . str_replace('%', '.*', preg_quote($termLc, '/')) . '$/u';
                        if (!preg_match($pattern, $valLc)) return false;
                    } else {
                        if (mb_strpos($valLc, $termLc) === false) return false;
                    }
                }
                return true;
            });
            $data = array_values($data);
        }
        
        if (!empty($data)) {
            $baseCols = array_keys($data[0]);

            if (!empty($orderedCols)) {
                $validOrder = array_intersect($orderedCols, $baseCols);
                $missing = array_diff($baseCols, $validOrder);
                $finalOrder = array_merge($validOrder, $missing);
                
                $data = array_map(function($row) use ($finalOrder) {
                    $newRow = [];
                    foreach ($finalOrder as $key) {
                        $newRow[$key] = $row[$key];
                    }
                    return $newRow;
                }, $data);
            }

            // Excluir columnas ocultas enviadas desde el frontend
            if (!empty($hiddenCols)) {
                $data = array_map(function($row) use ($hiddenCols) {
                    foreach ($hiddenCols as $col) {
                        unset($row[$col]);
                    }
                    return $row;
                }, $data);
            }
        }

        // Fila de totales (suma de columnas numéricas visibles) — replica lo que el <tfoot> muestra en pantalla.
        // Charly pidió exportar el valor ya expresado, no fórmula Excel. Se calcula DESPUÉS de ordered_cols
        // + hidden_cols para que las columnas ocultas no aparezcan y el orden matchee el de la tabla.
        $totalsRow = null;
        if (!empty($data)) {
            $visibleCols = array_keys($data[0]);
            $numericCols = [];
            foreach ($visibleCols as $col) {
                if (($pivotMeta[$col]['type'] ?? null) === 'numeric') {
                    $numericCols[] = $col;
                }
            }

            if (!empty($numericCols)) {
                // Inicializar la fila con strings vacíos para todas las columnas visibles.
                $totalsRow = array_fill_keys($visibleCols, '');
                // Sumar cada columna numérica.
                foreach ($numericCols as $col) {
                    $sum = 0.0;
                    foreach ($data as $row) {
                        $v = $row[$col] ?? null;
                        if ($v === null || $v === '') continue;
                        if (is_numeric($v)) $sum += (float)$v;
                    }
                    $totalsRow[$col] = $sum;
                }
                // Poner la etiqueta "TOTAL" en la primera columna no numérica visible (si existe).
                foreach ($visibleCols as $col) {
                    if (!in_array($col, $numericCols, true)) {
                        $totalsRow[$col] = 'TOTAL';
                        break;
                    }
                }
            }
        }

        // Formato es-AR para columnas de fecha en el export (regla de UI del proyecto):
        // date → d/m/Y, datetime/timestamp → d/m/Y H:i:S. Aplica a CSV y XLSX por igual,
        // antes del split. Cualquier valor que no parsee como DateTime se deja crudo.
        if (!empty($data)) {
            $dateCols = [];
            foreach (array_keys($data[0]) as $col) {
                $type = $pivotMeta[$col]['type'] ?? null;
                if ($type === 'date') {
                    $dateCols[$col] = 'd/m/Y';
                } elseif ($type === 'datetime' || $type === 'timestamp') {
                    $dateCols[$col] = 'd/m/Y H:i:s';
                }
            }
            if (!empty($dateCols)) {
                foreach ($data as &$row) {
                    foreach ($dateCols as $col => $fmt) {
                        $raw = $row[$col] ?? null;
                        if ($raw === null || $raw === '') continue;
                        try {
                            $row[$col] = (new \DateTime((string)$raw))->format($fmt);
                        } catch (\Throwable $e) {
                            // dejar crudo si no parsea
                        }
                    }
                }
                unset($row);
            }
        }

        if ($format === 'xlsx') {
            if (!class_exists('\\OpenSpout\\Writer\\XLSX\\Writer')) {
                die("La exportación requiere que OpenSpout esté instalado.");
            }
            // Vaciar TODOS los niveles de output buffer antes de escribir el binario.
            // Open Server / framework pueden tener varios apilados; un solo ob_end_clean
            // dejaba basura adelante del ZIP y Excel rechazaba el archivo como corrupto.
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $filename = "export_" . htmlspecialchars($datasetKey, ENT_QUOTES) . "_" . date('Ymd_His') . ".xlsx";

            // Options con column widths — se convierten px → Excel units (≈ px/7 para Calibri 11 default).
            // Aplicados solo a las columnas visibles, respetando el orden final del $data.
            $options = new \OpenSpout\Writer\XLSX\Options();
            if (!empty($data) && !empty($colWidths)) {
                $visibleCols = array_keys($data[0]);
                foreach ($visibleCols as $idx => $col) {
                    if (isset($colWidths[$col]) && is_numeric($colWidths[$col])) {
                        $px = (float)$colWidths[$col];
                        if ($px < 40) $px = 40;
                        if ($px > 800) $px = 800;
                        $excelWidth = max(5.0, round($px / 7.0, 2));
                        // setColumnWidth(width, ...columns) — columnas 1-indexed.
                        $options->setColumnWidth($excelWidth, $idx + 1);
                    }
                }
            }

            $writer = new \OpenSpout\Writer\XLSX\Writer($options);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->openToFile('php://output');

            // Estilos fijos tipo "Tabla azul" de Excel (independiente del tema de la UI).
            // Charly pidió explícitamente que el export siempre salga con la paleta Excel clásica.
            [$headerStyle, $rowStyle, $footerStyle] = $this->buildXlsxStyles();

            if (!empty($data)) {
                // OpenSpout v4: Row::fromValues($values, $style) — el segundo arg es el Style.
                // El método fromValuesWithStyle (singular) era v3 y no existe más.
                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(array_keys($data[0]), $headerStyle));
                foreach ($data as $row) {
                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(array_values($row), $rowStyle));
                }
                // Fila de totales al final con estilo destacado (bold + fondo azul claro #D9E1F2).
                if ($totalsRow !== null) {
                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(array_values($totalsRow), $footerStyle));
                }
            }

            $writer->close();
            exit;
        }

        // Comportamiento CSV por defecto
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="export_' . htmlspecialchars($datasetKey, ENT_QUOTES) . '_' . date('Ymd_His') . '.csv"');

        $output = fopen('php://output', 'w');
        // BOM para Excel
        fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            // Fila de totales al final — mismo valor que el footer visual de la tabla.
            if ($totalsRow !== null) {
                fputcsv($output, array_values($totalsRow));
            }
        }

        fclose($output);
        exit;
    }

    /**
     * Construye los tres estilos (header + body + footer totals) para el export XLSX.
     *
     * Paleta fija tipo "Tabla azul medio" de Excel — NO depende del tema de la UI.
     * Decisión de Charly (2026-04-15): el export siempre debe verse como Excel clásico
     * para que sea consistente al abrirlo/compartirlo, sin importar el tema del navegador.
     *
     * - Header: fondo azul medio (#4472C4) + texto blanco, negrita.
     * - Body: fondo blanco + texto negro.
     * - Footer (fila totales): fondo azul claro (#D9E1F2) + texto negro, negrita. Solo se usa
     *   si el export incluye fila de totales (hay al menos una columna numérica visible).
     * - Bordes: azul claro (#8EA9DB) para matchear el look de tabla Excel.
     *
     * @return array{0: \OpenSpout\Common\Entity\Style\Style, 1: \OpenSpout\Common\Entity\Style\Style, 2: \OpenSpout\Common\Entity\Style\Style}
     */
    private function buildXlsxStyles(): array
    {
        $headerBg   = '4472C4';
        $headerFg   = 'FFFFFF';
        $rowBg      = 'FFFFFF';
        $rowFg      = '000000';
        $footerBg   = 'D9E1F2';
        $footerFg   = '000000';
        $borderCol  = '8EA9DB';

        // OpenSpout v4: las constantes de border (TOP/BOTTOM/LEFT/RIGHT, WIDTH_THIN, STYLE_SOLID)
        // viven en \OpenSpout\Common\Entity\Style\Border. Las clases sueltas BorderName/
        // BorderWidth/BorderStyle son de v3 — si vuelven a aparecer, el export rompe con
        // "Class not found" (incidente 1.46.2).
        $border = new \OpenSpout\Common\Entity\Style\Border(
            new \OpenSpout\Common\Entity\Style\BorderPart(
                \OpenSpout\Common\Entity\Style\Border::TOP,
                $borderCol,
                \OpenSpout\Common\Entity\Style\Border::WIDTH_THIN,
                \OpenSpout\Common\Entity\Style\Border::STYLE_SOLID
            ),
            new \OpenSpout\Common\Entity\Style\BorderPart(
                \OpenSpout\Common\Entity\Style\Border::BOTTOM,
                $borderCol,
                \OpenSpout\Common\Entity\Style\Border::WIDTH_THIN,
                \OpenSpout\Common\Entity\Style\Border::STYLE_SOLID
            ),
            new \OpenSpout\Common\Entity\Style\BorderPart(
                \OpenSpout\Common\Entity\Style\Border::LEFT,
                $borderCol,
                \OpenSpout\Common\Entity\Style\Border::WIDTH_THIN,
                \OpenSpout\Common\Entity\Style\Border::STYLE_SOLID
            ),
            new \OpenSpout\Common\Entity\Style\BorderPart(
                \OpenSpout\Common\Entity\Style\Border::RIGHT,
                $borderCol,
                \OpenSpout\Common\Entity\Style\Border::WIDTH_THIN,
                \OpenSpout\Common\Entity\Style\Border::STYLE_SOLID
            )
        );

        // OpenSpout v4 usa setters set*() (no with*()). El v3 era inmutable estilo with*; v4 los renombró.
        $headerStyle = (new \OpenSpout\Common\Entity\Style\Style())
            ->setFontBold()
            ->setFontColor($headerFg)
            ->setBackgroundColor($headerBg)
            ->setBorder($border);

        $rowStyle = (new \OpenSpout\Common\Entity\Style\Style())
            ->setFontColor($rowFg)
            ->setBackgroundColor($rowBg)
            ->setBorder($border);

        $footerStyle = (new \OpenSpout\Common\Entity\Style\Style())
            ->setFontBold()
            ->setFontColor($footerFg)
            ->setBackgroundColor($footerBg)
            ->setBorder($border);

        return [$headerStyle, $rowStyle, $footerStyle];
    }

    public function eliminarVista(): void
    {
        header('Content-Type: application/json');
        $viewId = isset($_POST['view_id']) && is_numeric($_POST['view_id']) ? (int)$_POST['view_id'] : 0;
        if ($viewId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de vista inválido.']);
            exit;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Sesión inválida.']);
            exit;
        }

        // deleteUserView ya aplica guard de ownership (usuario_id = ?).
        // Si la vista no es del user (o no existe), devuelve false.
        $ok = $this->service->deleteUserView($userId, $viewId);
        echo json_encode([
            'success' => $ok,
            'message' => $ok ? 'Vista eliminada.' : 'No se pudo eliminar (no existe o no te pertenece).',
        ]);
        exit;
    }

    public function guardarVista(): void
    {
        header('Content-Type: application/json');
        $datasetKey = $_POST['dataset'] ?? null;
        $nombre = $_POST['nombre'] ?? '';
        $configJson = $_POST['config'] ?? '';
        $viewId = isset($_POST['view_id']) && is_numeric($_POST['view_id']) ? (int)$_POST['view_id'] : null;

        if (!$datasetKey || !$nombre || !$configJson) {
            echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos.']);
            exit;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $empresaId = (int)($_SESSION['empresa_id'] ?? 0);
        if ($userId <= 0 || $empresaId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Sesión inválida. Volvé a iniciar sesión.']);
            exit;
        }
        $configArr = json_decode($configJson, true) ?? [];

        try {
            $insertedId = $this->service->saveUserView($empresaId, $userId, $datasetKey, $nombre, $configArr, $viewId);
            echo json_encode(['success' => true, 'view_id' => $insertedId]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
