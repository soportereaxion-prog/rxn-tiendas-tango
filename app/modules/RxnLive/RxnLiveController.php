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

        if ($safeMode) {
            // Descartar cualquier filtro GET en safe mode — cargamos dataset limpio.
            $filters = [];
        } else {
            $filters = $_GET;
            unset(
                $filters['dataset'], $filters['page'], $filters['view_id'],
                $filters['reset_view'], $filters['reset_filters'],
                $filters['b_query'], $filters['query'], $filters['estado'], $filters['razon_social']
            );
        }

        $limit = 50;

        $data = $this->service->getDatasetData($datasetKey, $filters, $page, $limit);
        $totalRegistros = $this->service->getDatasetCount($datasetKey, $filters);

        $userId = $_SESSION['usuario_id'] ?? 0;
        // En safe mode no cargamos vistas del user — evita que el JS intente rehidratar
        // alguna configuración rota si el navegador tiene view_id cacheado en algún lado.
        $myViews = $safeMode ? [] : $this->service->getUserViews($userId, $datasetKey);

        View::render('app/modules/RxnLive/views/dataset.php', [
            'datasetKey' => $datasetKey,
            'myViews' => $myViews,
            'datasetInfo' => $this->service->getDatasetInfo($datasetKey),
            'filters' => $filters,
            'datasetRows' => $data,
            'totalRegistros' => $totalRegistros,
            'page' => $page,
            'limit' => $limit,
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
        $theme = $filters['theme'] ?? 'dark';
        
        $discreteFiltersJson = $filters['discrete_filters'] ?? '{}';
        $discreteFilters = json_decode($discreteFiltersJson, true);
        if (!is_array($discreteFilters)) $discreteFilters = [];
        
        unset($filters['dataset'], $filters['format'], $filters['hidden_cols'], $filters['ordered_cols'], $filters['view_id'], $filters['theme'], $filters['discrete_filters']);

        $data = $this->service->getDatasetData($datasetKey, $filters, 1, 10000); // Múltiplos lógicos preventivos
        
        // Aplicar filtros discretos en memoria (evita complejidad en ORM/SQL)
        if (!empty($data) && !empty($discreteFilters)) {
            $data = array_filter($data, function($row) use ($discreteFilters) {
                foreach ($discreteFilters as $col => $allowedValues) {
                    if (!empty($allowedValues)) {
                        $val = $row[$col] ?? null;
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

        if ($format === 'xlsx') {
            if (!class_exists('\\OpenSpout\\Writer\\XLSX\\Writer')) {
                die("La exportación requiere que OpenSpout esté instalado.");
            }
            if (ob_get_length()) {
                ob_end_clean();
            }

            $filename = "export_" . htmlspecialchars($datasetKey, ENT_QUOTES) . "_" . date('Ymd_His') . ".xlsx";
            $writer = new \OpenSpout\Writer\XLSX\Writer();

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->openToFile('php://output');

            // === Estilos theme-aware ===
            // El front inyecta `theme` en el form antes de submit (detectado por data-bs-theme
            // o clases bg-dark/navbar-dark). El default es 'dark' — matchea el UI actual de RXN Live.
            [$headerStyle, $rowStyle] = $this->buildXlsxThemeStyles($theme);

            if (!empty($data)) {
                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValuesWithStyle(array_keys($data[0]), $headerStyle));
                foreach ($data as $row) {
                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValuesWithStyle(array_values($row), $rowStyle));
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
        }
        
        fclose($output);
        exit;
    }

    /**
     * Construye los dos estilos (header + body) para el export XLSX según el tema.
     *
     * Tema 'dark' (default): matchea el UI oscuro de RXN Live — útil si se abre directo.
     * Tema 'light': claro + contrastado — útil para imprimir o compartir por mail.
     *
     * @return array{0: \OpenSpout\Common\Entity\Style\Style, 1: \OpenSpout\Common\Entity\Style\Style}
     */
    private function buildXlsxThemeStyles(string $theme): array
    {
        $isDark = ($theme === 'dark');

        if ($isDark) {
            // Paleta oscura (alineada con bg-dark + text-white de Bootstrap)
            $headerBg   = '343A40'; // gris carbón
            $headerFg   = 'FFFFFF';
            $rowBg      = '2C3034';
            $rowFg      = 'F8F9FA';
            $borderCol  = '495057';
        } else {
            // Paleta clara (header azulado tipo primary-subtle, cuerpo blanco)
            $headerBg   = 'E7F1FF';
            $headerFg   = '0D6EFD';
            $rowBg      = 'FFFFFF';
            $rowFg      = '212529';
            $borderCol  = 'DEE2E6';
        }

        $border = new \OpenSpout\Common\Entity\Style\Border(
            new \OpenSpout\Common\Entity\Style\BorderPart(
                \OpenSpout\Common\Entity\Style\BorderName::TOP,
                $borderCol,
                \OpenSpout\Common\Entity\Style\BorderWidth::THIN,
                \OpenSpout\Common\Entity\Style\BorderStyle::SOLID
            ),
            new \OpenSpout\Common\Entity\Style\BorderPart(
                \OpenSpout\Common\Entity\Style\BorderName::BOTTOM,
                $borderCol,
                \OpenSpout\Common\Entity\Style\BorderWidth::THIN,
                \OpenSpout\Common\Entity\Style\BorderStyle::SOLID
            ),
            new \OpenSpout\Common\Entity\Style\BorderPart(
                \OpenSpout\Common\Entity\Style\BorderName::LEFT,
                $borderCol,
                \OpenSpout\Common\Entity\Style\BorderWidth::THIN,
                \OpenSpout\Common\Entity\Style\BorderStyle::SOLID
            ),
            new \OpenSpout\Common\Entity\Style\BorderPart(
                \OpenSpout\Common\Entity\Style\BorderName::RIGHT,
                $borderCol,
                \OpenSpout\Common\Entity\Style\BorderWidth::THIN,
                \OpenSpout\Common\Entity\Style\BorderStyle::SOLID
            )
        );

        $headerStyle = (new \OpenSpout\Common\Entity\Style\Style())
            ->withFontBold(true)
            ->withFontColor($headerFg)
            ->withBackgroundColor($headerBg)
            ->withBorder($border);

        $rowStyle = (new \OpenSpout\Common\Entity\Style\Style())
            ->withFontColor($rowFg)
            ->withBackgroundColor($rowBg)
            ->withBorder($border);

        return [$headerStyle, $rowStyle];
    }

    public function eliminarVista(): void
    {
        header('Content-Type: application/json');
        $viewId = isset($_POST['view_id']) && is_numeric($_POST['view_id']) ? (int)$_POST['view_id'] : 0;
        if ($viewId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de vista inválido.']);
            exit;
        }

        $userId = (int)($_SESSION['usuario_id'] ?? 0);
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

        $userId = (int)($_SESSION['usuario_id'] ?? 0);
        $configArr = json_decode($configJson, true) ?? [];
        
        try {
            $insertedId = $this->service->saveUserView($userId, $datasetKey, $nombre, $configArr, $viewId);
            echo json_encode(['success' => true, 'view_id' => $insertedId]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
