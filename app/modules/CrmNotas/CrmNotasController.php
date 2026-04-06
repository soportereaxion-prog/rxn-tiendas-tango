<?php

declare(strict_types=1);

namespace App\Modules\CrmNotas;

use App\Core\Controller;
use App\Core\View;
use App\Core\Context;
use App\Modules\Auth\AuthService;
use App\Shared\Services\OperationalAreaService;

class CrmNotasController extends Controller
{
    private CrmNotaRepository $repository;

    public function __construct()
    {
        $this->repository = new CrmNotaRepository();
    }

    private function getEmpresaIdOrDie(): int
    {
        $empresaId = Context::getEmpresaId();
        if ($empresaId === null || $empresaId <= 0) {
            $this->renderDenegado("No hay un contexto de empresa válido activo.", "/");
        }
        return (int) $empresaId;
    }

    public function index(): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        // Validar acceso por módulos compartidos (Si la empresa tiene modulo_crm_notas u modulo_tiendas_notas encendido)
        // Por ahora omitimos bloqueo estricto, o podríamos inyectar configuración general

        $search = $_GET['search'] ?? '';
        $sortColumn = $_GET['sort'] ?? 'created_at';
        $sortDir = strtoupper($_GET['dir'] ?? 'DESC');

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        $status = $_GET['status'] ?? 'activos';
        $onlyDeleted = $status === 'papelera';
        
        $advancedFilters = $this->handleCrudFilters('crm_notas');

        $totalItems = $this->repository->countAll($empresaId, $search, $onlyDeleted, $advancedFilters);
        $items = $this->repository->findAllWithClientName($empresaId, $perPage, $offset, $search, $sortColumn, $sortDir, $onlyDeleted, $advancedFilters);

        View::render('app/modules/CrmNotas/views/index.php', array_merge($ui, [
            'notas' => $items,
            'search' => $search,
            'page' => $page,
            'totalPages' => max(1, ceil($totalItems / $perPage)),
            'totalItems' => $totalItems,
            'status' => $status,
            'sort' => $sortColumn,
            'dir' => $sortDir
        ]));
    }

    public function create(): void
    {
        AuthService::requireLogin();
        $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        View::render('app/modules/CrmNotas/views/form.php', array_merge($ui, [
            'isEdit' => false,
            'nota' => null
        ]));
    }

    public function store(): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $ui['indexPath']);
            exit;
        }

        try {
            $nota = new CrmNota();
            $nota->empresa_id = $empresaId;
            $nota->cliente_id = !empty($_POST['cliente_id']) ? (int) $_POST['cliente_id'] : null;
            $nota->titulo = trim($_POST['titulo'] ?? '');
            $nota->contenido = trim($_POST['contenido'] ?? '');
            $nota->tags = !empty($_POST['tags']) ? trim($_POST['tags']) : null;
            $nota->activo = isset($_POST['activo']) ? 1 : 0;

            if ($nota->titulo === '' || $nota->contenido === '') {
                throw new \InvalidArgumentException("El título y contenido son obligatorios.");
            }

            $this->repository->save($nota);
            header('Location: ' . $this->withSuccess($ui['indexPath'], 'Nota creada exitosamente.'));
            exit;
        } catch (\Exception $e) {
            View::render('app/modules/CrmNotas/views/form.php', array_merge($ui, [
                'error' => $e->getMessage(),
                'isEdit' => false,
                'old' => $_POST
            ]));
        }
    }

    public function edit(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        $nota = $this->repository->findByIdAndEmpresa((int) $id, $empresaId);
        if (!$nota) {
            header('Location: ' . $ui['indexPath'] . '?error=' . urlencode('Nota no encontrada'));
            exit;
        }

        View::render('app/modules/CrmNotas/views/form.php', array_merge($ui, [
            'isEdit' => true,
            'nota' => $nota
        ]));
    }

    public function update(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $ui['indexPath']);
            exit;
        }

        try {
            $nota = $this->repository->findByIdAndEmpresa((int) $id, $empresaId);
            if (!$nota) {
                throw new \Exception('Nota no encontrada');
            }

            $nota->cliente_id = !empty($_POST['cliente_id']) ? (int) $_POST['cliente_id'] : null;
            $nota->titulo = trim($_POST['titulo'] ?? '');
            $nota->contenido = trim($_POST['contenido'] ?? '');
            $nota->tags = !empty($_POST['tags']) ? trim($_POST['tags']) : null;
            $nota->activo = isset($_POST['activo']) ? 1 : 0;

            if ($nota->titulo === '' || $nota->contenido === '') {
                throw new \InvalidArgumentException("El título y contenido son obligatorios.");
            }

            $this->repository->save($nota);
            header('Location: ' . $this->withSuccess($ui['indexPath'], 'Nota actualizada.'));
            exit;
        } catch (\Exception $e) {
            $nota = clone $this->repository->findByIdAndEmpresa((int) $id, $empresaId);
            // Patch old values for preview
            if ($nota) {
                $nota->titulo = $_POST['titulo'] ?? '';
                $nota->contenido = $_POST['contenido'] ?? '';
            }
            View::render('app/modules/CrmNotas/views/form.php', array_merge($ui, [
                'error' => $e->getMessage(),
                'isEdit' => true,
                'nota' => $nota
            ]));
        }
    }

    public function show(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        $nota = $this->repository->findByIdAndEmpresa((int) $id, $empresaId);
        if (!$nota) {
            header('Location: ' . $ui['indexPath'] . '?error=' . urlencode('Nota no encontrada'));
            exit;
        }

        View::render('app/modules/CrmNotas/views/show.php', array_merge($ui, [
            'nota' => $nota
        ]));
    }

    public function copy(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        $original = $this->repository->findByIdAndEmpresa((int) $id, $empresaId);
        if (!$original) {
            header('Location: ' . $ui['indexPath'] . '?error=' . urlencode('Nota no encontrada para copiar'));
            exit;
        }

        $notaCopy = new CrmNota();
        $notaCopy->empresa_id = $empresaId;
        $notaCopy->cliente_id = $original->cliente_id;
        $notaCopy->titulo = $original->titulo . ' (Copia)';
        $notaCopy->contenido = $original->contenido;
        $notaCopy->tags = $original->tags;
        $notaCopy->activo = $original->activo;

        $this->repository->save($notaCopy);

        header('Location: ' . $this->withSuccess($ui['indexPath'], 'Nota copiada exitosamente.'));
        exit;
    }

    public function tagsSuggestions(): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        
        $search = $_GET['q'] ?? '';
        
        $sql = "SELECT nombre FROM crm_notas_tags_diccionario WHERE empresa_id = :empresa_id AND nombre LIKE :search ORDER BY nombre ASC LIMIT 10";
        $db = \App\Core\Database::getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute([':empresa_id' => $empresaId, ':search' => "%{$search}%"]);
        
        $results = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $results]);
        exit;
    }

    public function clientSuggestions(): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();

        $search = $_GET['search'] ?? '';
        
        $repo = new \App\Modules\CrmClientes\CrmClienteRepository();
        $results = $repo->findSuggestions($empresaId, $search, 'all', 10);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $results]);
        exit;
    }

    public function showImportForm(): void
    {
        AuthService::requireLogin();
        $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        View::render('app/modules/CrmNotas/views/import.php', array_merge($ui, [
            'error' => null
        ]));
    }

    public function processImport(): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        if (empty($_FILES['archivo']['tmp_name'])) {
            View::render('app/modules/CrmNotas/views/import.php', array_merge($ui, [
                'error' => 'Por favor, selecciona un archivo válido.'
            ]));
            exit;
        }

        $tmpName = $_FILES['archivo']['tmp_name'];
        $originalName = $_FILES['archivo']['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'xlsx'])) {
            View::render('app/modules/CrmNotas/views/import.php', array_merge($ui, [
                'error' => 'El formato de archivo debe ser .xlsx o .csv'
            ]));
            exit;
        }

        try {
            if (!class_exists('\\OpenSpout\\Reader\\XLSX\\Reader')) {
                throw new \Exception('Se requiere ejecutar: composer require openspout/openspout para procesar Excels.');
            }

            if ($ext === 'xlsx') {
                $reader = new \OpenSpout\Reader\XLSX\Reader();
            } else {
                throw new \Exception('El formato de archivo debe ser .xlsx');
            }

            $reader->open($tmpName);

            $repoNotas = new CrmNotaRepository();
            $repoClientes = new \App\Modules\CrmClientes\CrmClienteRepository();
            
            $countSuccess = 0;
            $countSkipped = 0;
            $rowIndex = 0;
            $headerMap = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    /** @var \OpenSpout\Common\Entity\Row|\OpenSpout\Reader\XLSX\Row $row */
                    $rowData = $row->toArray();

                    if ($rowIndex === 0) {
                        // Leer cabeceras
                        foreach ($rowData as $colIdx => $colName) {
                            $headerMap[strtolower(trim($colName))] = $colIdx;
                        }
                        
                        // Validar cabeceras mínimas
                        if (!isset($headerMap['titulo'])) {
                            throw new \Exception("El archivo debe tener al menos la columna 'titulo'.");
                        }
                        $rowIndex++;
                        continue;
                    }

                    $titulo = isset($headerMap['titulo']) ? trim((string)$rowData[$headerMap['titulo']]) : '';
                    $contenido = isset($headerMap['contenido']) ? trim((string)$rowData[$headerMap['contenido']]) : '';
                    $tags = isset($headerMap['tags']) ? trim((string)$rowData[$headerMap['tags']]) : '';
                    
                    $codigoTango = isset($headerMap['codigo_tango']) ? trim((string)$rowData[$headerMap['codigo_tango']]) : '';
                    $clienteId = null;

                    if ($titulo === '') {
                        $countSkipped++;
                        continue;
                    }

                    if ($codigoTango !== '') {
                        $clienteOpt = $repoClientes->findByCodigoTango($codigoTango, $empresaId);
                        if ($clienteOpt) {
                            $clienteId = $clienteOpt['id'];
                        } else {
                            // No existe cliente, se asume huérfana o saltamos. Dejemos huerfana
                            $clienteId = null; 
                        }
                    }

                    $notaObj = new \App\Modules\CrmNotas\CrmNota();
                    $notaObj->empresa_id = $empresaId;
                    $notaObj->titulo = $titulo;
                    $notaObj->contenido = $contenido !== '' ? $contenido : null;
                    $notaObj->cliente_id = $clienteId;
                    $notaObj->tags = $tags !== '' ? $tags : null;
                    $notaObj->activo = 1;

                    $repoNotas->save($notaObj);
                    $countSuccess++;
                    $rowIndex++;
                }
                break; // Procesar solo la primera hoja
            }

            $reader->close();

            header('Location: ' . $this->withSuccess($ui['indexPath'], "Importación exitosa. Notas procesadas: {$countSuccess}. Saltadas/inválidas: {$countSkipped}."));
            exit;

        } catch (\Exception $e) {
            View::render('app/modules/CrmNotas/views/import.php', array_merge($ui, [
                'error' => 'Error durante la importación: ' . $e->getMessage()
            ]));
            exit;
        }
    }

    public function export(): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();

        if (!class_exists('\\OpenSpout\\Writer\\XLSX\\Writer')) {
            die("La exportación requiere que OpenSpout esté instalado.");
        }

        $search = $_GET['search'] ?? '';
        $sortColumn = $_GET['sort'] ?? 'created_at';
        $sortDir = strtoupper($_GET['dir'] ?? 'DESC');

        $status = $_GET['status'] ?? 'activos';
        $onlyDeleted = $status === 'papelera';

        $limit = 999999;
        $offset = 0;

        $items = $this->repository->findAllWithClientName($empresaId, $limit, $offset, $search, $sortColumn, $sortDir, $onlyDeleted);

        $filename = "notas_exportacion_" . date('Ymd_His') . ".xlsx";
        
        $writer = new \OpenSpout\Writer\XLSX\Writer();
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer->openToFile('php://output');
        
        $rowFromValues = function(array $values) {
            $cells = array_map(function($v) {
                return \OpenSpout\Common\Entity\Cell::fromValue($v);
            }, $values);
            return new \OpenSpout\Common\Entity\Row($cells);
        };

        // Header
        $writer->addRow($rowFromValues(['ID', 'Título', 'Contenido', 'Tags', 'Cliente Módulo', 'Código Tango', 'Fecha Creación', 'Estado']));

        foreach ($items as $item) {
            $writer->addRow($rowFromValues([
                $item['id'],
                $item['titulo'],
                $item['contenido'],
                $item['tags'],
                $item['cliente_nombre'],
                $item['cliente_codigo'],
                $item['created_at'],
                $item['activo'] == 1 ? 'Activo' : 'Inactivo'
            ]));
        }
        
        $writer->close();
        exit;
    }

    public function downloadTemplate(): void
    {
        AuthService::requireLogin();
        
        if (!class_exists('\\OpenSpout\\Writer\\XLSX\\Writer')) {
            die("La descarga del Excel requiere que OpenSpout esté instalado.");
        }

        $filename = "matriz_notas_importacion.xlsx";
        
        $writer = new \OpenSpout\Writer\XLSX\Writer();
        
        // No headers needed for Spout writer to php://output if we use openToFile("php://output")
        // But Spout v5 recommends doing this correctly or passing to a temp file and reading it.
        // Or simple:
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer->openToFile('php://output');
        
        $rowFromValues = function(array $values) {
            $cells = array_map(function($v) {
                return \OpenSpout\Common\Entity\Cell::fromValue($v);
            }, $values);
            return new \OpenSpout\Common\Entity\Row($cells);
        };

        $writer->addRow($rowFromValues(['titulo', 'contenido', 'tags', 'codigo_tango']));
        $writer->addRow($rowFromValues(['Llamado de consulta', 'El cliente consultó por precios...', 'PRE-VENTA, CONSULTA', 'VILL01']));
        $writer->addRow($rowFromValues(['Soporte técnico', 'Revisión de equipamiento...', 'SOPORTE, TECNICO', '']));
        
        $writer->close();
        exit;
    }

    public function eliminar(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        $this->repository->delete((int) $id, $empresaId);
        header('Location: ' . $this->withSuccess($ui['indexPath'], 'Nota enviada a la papelera.'));
        exit;
    }

    public function restore(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        $this->repository->restore((int) $id, $empresaId);
        header('Location: ' . $this->withSuccess($ui['indexPath'] . '?status=papelera', 'Nota restaurada exitosamente.'));
        exit;
    }

    public function forceDelete(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        $this->repository->forceDelete((int) $id, $empresaId);
        header('Location: ' . $this->withSuccess($ui['indexPath'] . '?status=papelera', 'Nota eliminada definitivamente.'));
        exit;
    }

    public function eliminarMasivo(): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $ui['indexPath']);
            exit;
        }

        $ids = $_POST['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            header('Location: ' . $ui['indexPath']);
            exit;
        }

        $count = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                try {
                    $this->repository->delete($id, $empresaId);
                    $count++;
                } catch (\Exception $e) {}
            }
        }
        header('Location: ' . $this->withSuccess($ui['indexPath'], "Se enviaron {$count} notas a la papelera."));
        exit;
    }

    public function restoreMasivo(): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $ui['indexPath'] . '?status=papelera');
            exit;
        }

        $ids = $_POST['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            header('Location: ' . $ui['indexPath'] . '?status=papelera');
            exit;
        }

        $count = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                try {
                    $this->repository->restore($id, $empresaId);
                    $count++;
                } catch (\Exception $e) {}
            }
        }
        header('Location: ' . $this->withSuccess($ui['indexPath'] . '?status=papelera', "Se restauraron {$count} notas exitosamente."));
        exit;
    }

    public function forceDeleteMasivo(): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $ui['indexPath'] . '?status=papelera');
            exit;
        }

        $ids = $_POST['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            header('Location: ' . $ui['indexPath'] . '?status=papelera');
            exit;
        }

        $count = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                try {
                    $this->repository->forceDelete($id, $empresaId);
                    $count++;
                } catch (\Exception $e) {}
            }
        }
        header('Location: ' . $this->withSuccess($ui['indexPath'] . '?status=papelera', "Se eliminaron {$count} notas definitivamente."));
        exit;
    }

    private function renderDenegado(string $motivo, string $backPath): void
    {
        http_response_code(403);
        echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
        echo "<h2>⚠️ Operación Interrumpida</h2>";
        echo "<p>" . htmlspecialchars($motivo) . "</p>";
        echo "<a href='" . htmlspecialchars($backPath, ENT_QUOTES, 'UTF-8') . "'>Volver</a>";
        echo "</div>";
        exit;
    }

    private function buildUiContext(): array
    {
        $area = OperationalAreaService::resolveFromRequest();

        return [
            'area' => $area,
            'basePath' => '/mi-empresa/crm/notas',
            'indexPath' => '/mi-empresa/crm/notas',
            'dashboardPath' => OperationalAreaService::dashboardPath($area),
            'environmentLabel' => OperationalAreaService::environmentLabel($area),
        ];
    }

    private function withSuccess(string $path, string $message): string
    {
        $separator = str_contains($path, '?') ? '&' : '?';
        return $path . $separator . 'success=' . urlencode($message);
    }


}
