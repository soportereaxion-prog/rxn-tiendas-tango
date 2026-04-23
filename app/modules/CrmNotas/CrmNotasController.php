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
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $status = $_GET['status'] ?? 'activos';
        $onlyDeleted = $status === 'papelera';

        $advancedFilters = $this->handleCrudFilters('crm_notas');

        // Filtro opcional por tratativa (viene por query param desde detalle de tratativa).
        // Se valida que pertenezca a la empresa antes de aplicar el filtro.
        $tratativaIdFilter = null;
        $tratativaFiltroInfo = null;
        if (!empty($_GET['tratativa_id'])) {
            $tratativaIdCandidate = (int) $_GET['tratativa_id'];
            if ($tratativaIdCandidate > 0) {
                $tratativaRepo = new \App\Modules\CrmTratativas\TratativaRepository();
                $tratativaRow = $tratativaRepo->findById($tratativaIdCandidate, $empresaId);
                if ($tratativaRow !== null) {
                    $tratativaIdFilter = $tratativaIdCandidate;
                    $tratativaFiltroInfo = [
                        'id' => $tratativaIdCandidate,
                        'numero' => (int) ($tratativaRow['numero'] ?? 0),
                        'titulo' => (string) ($tratativaRow['titulo'] ?? ''),
                    ];
                }
            }
        }

        $totalItems = $this->repository->countAll($empresaId, $search, $onlyDeleted, $advancedFilters, $tratativaIdFilter);
        $items = $this->repository->findAllWithClientName($empresaId, $perPage, $offset, $search, $sortColumn, $sortDir, $onlyDeleted, $advancedFilters, $tratativaIdFilter);

        // Resolver nota activa para el panel derecho:
        //   1) ?n={id} en la URL (deep link / recarga conservando selección)
        //   2) primera nota del listado si existe
        //   3) null → el panel muestra placeholder
        $activeNota = null;
        $activeNotaId = null;
        if (!empty($_GET['n'])) {
            $candidate = (int) $_GET['n'];
            if ($candidate > 0) {
                $activeNota = $this->repository->findByIdAndEmpresa($candidate, $empresaId, true);
                if ($activeNota !== null) {
                    $activeNotaId = $activeNota->id;
                }
            }
        }
        if ($activeNota === null && !empty($items)) {
            $firstId = (int) $items[0]['id'];
            $activeNota = $this->repository->findByIdAndEmpresa($firstId, $empresaId, $onlyDeleted);
            if ($activeNota !== null) {
                $activeNotaId = $activeNota->id;
            }
        }

        View::render('app/modules/CrmNotas/views/index.php', array_merge($ui, [
            'notas' => $items,
            'search' => $search,
            'page' => $page,
            'totalPages' => max(1, (int) ceil($totalItems / $perPage)),
            'totalItems' => $totalItems,
            'status' => $status,
            'sort' => $sortColumn,
            'dir' => $sortDir,
            'tratativaFiltroInfo' => $tratativaFiltroInfo,
            'activeNota' => $activeNota,
            'activeNotaId' => $activeNotaId,
            'empresaId' => $empresaId,
            'hasExplicitNotaParam' => !empty($_GET['n']),
        ]));
    }

    public function create(): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        $prefill = $this->resolvePrefillFromQuery($empresaId);

        View::render('app/modules/CrmNotas/views/form.php', array_merge($ui, [
            'isEdit' => false,
            'nota' => null,
            'prefill' => $prefill,
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
            $nota->tratativa_id = $this->resolveTratativaIdFromInput($_POST['tratativa_id'] ?? null, $empresaId);
            $nota->titulo = trim($_POST['titulo'] ?? '');
            $nota->contenido = trim($_POST['contenido'] ?? '');
            $nota->tags = !empty($_POST['tags']) ? trim($_POST['tags']) : null;
            $nota->activo = isset($_POST['activo']) ? 1 : 0;

            if ($nota->titulo === '' || $nota->contenido === '') {
                throw new \InvalidArgumentException("El título y contenido son obligatorios.");
            }

            $this->repository->save($nota);

            // Si la nota quedó vinculada a una tratativa, volvemos al detalle de la tratativa
            // (mismo patrón que PDS/Presupuestos creados desde una tratativa).
            // Si no, volvemos al split view con la nota recién creada seleccionada (?n=ID).
            $redirectPath = $nota->tratativa_id !== null
                ? '/mi-empresa/crm/tratativas/' . $nota->tratativa_id
                : $ui['indexPath'] . '?n=' . (int) $nota->id;

            header('Location: ' . $this->withSuccess($redirectPath, 'Nota creada exitosamente.'));
            exit;
        } catch (\Exception $e) {
            View::render('app/modules/CrmNotas/views/form.php', array_merge($ui, [
                'error' => $e->getMessage(),
                'isEdit' => false,
                'old' => $_POST,
                'prefill' => $this->resolvePrefillFromQuery($empresaId),
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
            $nota->tratativa_id = $this->resolveTratativaIdFromInput($_POST['tratativa_id'] ?? null, $empresaId);
            $nota->titulo = trim($_POST['titulo'] ?? '');
            $nota->contenido = trim($_POST['contenido'] ?? '');
            $nota->tags = !empty($_POST['tags']) ? trim($_POST['tags']) : null;
            $nota->activo = isset($_POST['activo']) ? 1 : 0;

            if ($nota->titulo === '' || $nota->contenido === '') {
                throw new \InvalidArgumentException("El título y contenido son obligatorios.");
            }

            $this->repository->save($nota);
            // Volvemos al split view parado en la nota recién editada (?n=ID).
            header('Location: ' . $this->withSuccess($ui['indexPath'] . '?n=' . (int) $nota->id, 'Nota actualizada.'));
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

    /**
     * Endpoint AJAX: devuelve el HTML parcial del detalle de una nota para el panel derecho
     * del split view. NO envuelve en admin_layout — se inyecta con innerHTML en el cliente.
     */
    public function panel(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        $nota = $this->repository->findByIdAndEmpresa((int) $id, $empresaId, true);

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');

        if (!$nota) {
            http_response_code(404);
            echo '<div class="alert alert-warning m-3">Nota no encontrada o fuera de esta empresa.</div>';
            exit;
        }

        $indexPath = $ui['indexPath'];
        include BASE_PATH . '/app/modules/CrmNotas/views/partials/detail_panel.php';
        exit;
    }

    /**
     * Endpoint AJAX: devuelve el HTML parcial con los items de la lista (columna izquierda)
     * aplicando los mismos filtros que index(). Se usa para búsqueda en vivo y paginación.
     */
    public function listPartial(): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        $search = $_GET['search'] ?? '';
        $sortColumn = $_GET['sort'] ?? 'created_at';
        $sortDir = strtoupper($_GET['dir'] ?? 'DESC');

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $status = $_GET['status'] ?? 'activos';
        $onlyDeleted = $status === 'papelera';

        $advancedFilters = $this->handleCrudFilters('crm_notas');

        $tratativaIdFilter = null;
        if (!empty($_GET['tratativa_id'])) {
            $candidate = (int) $_GET['tratativa_id'];
            if ($candidate > 0) {
                $tratativaRepo = new \App\Modules\CrmTratativas\TratativaRepository();
                if ($tratativaRepo->findById($candidate, $empresaId) !== null) {
                    $tratativaIdFilter = $candidate;
                }
            }
        }

        $totalItems = $this->repository->countAll($empresaId, $search, $onlyDeleted, $advancedFilters, $tratativaIdFilter);
        $items = $this->repository->findAllWithClientName($empresaId, $perPage, $offset, $search, $sortColumn, $sortDir, $onlyDeleted, $advancedFilters, $tratativaIdFilter);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');

        $indexPath = $ui['indexPath'];
        $isPapelera = $onlyDeleted;
        include BASE_PATH . '/app/modules/CrmNotas/views/partials/list_items.php';
        exit;
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
        $notaCopy->tratativa_id = $original->tratativa_id;
        $notaCopy->titulo = $original->titulo . ' (Copia)';
        $notaCopy->contenido = $original->contenido;
        $notaCopy->tags = $original->tags;
        $notaCopy->activo = $original->activo;

        $this->repository->save($notaCopy);

        // Parar el split en la copia recién creada.
        header('Location: ' . $this->withSuccess($ui['indexPath'] . '?n=' . (int) $notaCopy->id, 'Nota copiada exitosamente.'));
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

    /**
     * Endpoint de autocomplete de tratativas para el selector del form de notas.
     * Busca por numero, titulo o cliente (campos usados por TratativaRepository::findSuggestions).
     * Devuelve el contrato canónico: { id, label, caption }.
     */
    public function tratativaSuggestions(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $empresaId = $this->getEmpresaIdOrDie();
        $term = trim((string) ($_GET['search'] ?? $_GET['q'] ?? ''));

        if (mb_strlen($term) < 2) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        $tratativaRepo = new \App\Modules\CrmTratativas\TratativaRepository();
        // Buscamos en todos los campos y devolvemos hasta 10 resultados (similar a clientSuggestions).
        $rows = $tratativaRepo->findSuggestions($empresaId, $term, 'all', '', 10);

        $data = array_map(static function (array $row): array {
            $numero = (int) ($row['numero'] ?? 0);
            $titulo = trim((string) ($row['titulo'] ?? ''));
            $cliente = trim((string) ($row['cliente_nombre'] ?? ''));
            $estado = trim((string) ($row['estado'] ?? 'nueva'));

            return [
                'id' => (int) ($row['id'] ?? 0),
                'numero' => $numero,
                'titulo' => $titulo,
                'label' => 'Tratativa #' . $numero . ($titulo !== '' ? ' — ' . $titulo : ''),
                'caption' => trim(($cliente !== '' ? $cliente : 'Sin cliente') . ' · ' . strtoupper($estado)),
            ];
        }, $rows);

        echo json_encode(['success' => true, 'data' => $data], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    /**
     * Valida que una tratativa_id (cruda del POST) exista y pertenezca a la empresa.
     * Devuelve el ID validado o null si no aplica / no pasa la validación.
     */
    private function resolveTratativaIdFromInput($rawInput, int $empresaId): ?int
    {
        if ($rawInput === null || $rawInput === '' || (int) $rawInput <= 0) {
            return null;
        }

        $candidate = (int) $rawInput;
        $tratativaRepo = new \App\Modules\CrmTratativas\TratativaRepository();
        if (!$tratativaRepo->existsActiveForEmpresa($candidate, $empresaId)) {
            return null;
        }

        return $candidate;
    }

    /**
     * Si la URL trae ?tratativa_id=X (y opcionalmente ?cliente_id=Y), precarga los datos
     * para mostrarlos en el form de creación de nota. Si la tratativa tiene cliente
     * asociado, el cliente de la nota se hereda automáticamente (criterio acordado).
     *
     * Devuelve un array con:
     *   - tratativa_id / tratativa_numero / tratativa_titulo (o null si no se encontró)
     *   - cliente_id / cliente_nombre (o null si no se encontró)
     */
    private function resolvePrefillFromQuery(int $empresaId): array
    {
        $prefill = [
            'tratativa_id' => null,
            'tratativa_numero' => null,
            'tratativa_titulo' => null,
            'cliente_id' => null,
            'cliente_nombre' => null,
        ];

        if (!empty($_GET['tratativa_id'])) {
            $candidate = (int) $_GET['tratativa_id'];
            if ($candidate > 0) {
                $tratativaRepo = new \App\Modules\CrmTratativas\TratativaRepository();
                $tratativa = $tratativaRepo->findById($candidate, $empresaId);
                if ($tratativa !== null) {
                    $prefill['tratativa_id'] = $candidate;
                    $prefill['tratativa_numero'] = (int) ($tratativa['numero'] ?? 0);
                    $prefill['tratativa_titulo'] = (string) ($tratativa['titulo'] ?? '');

                    // Heredar cliente de la tratativa si tiene uno.
                    $clienteIdFromTratativa = (int) ($tratativa['cliente_id'] ?? 0);
                    if ($clienteIdFromTratativa > 0) {
                        $prefill['cliente_id'] = $clienteIdFromTratativa;
                        $prefill['cliente_nombre'] = (string) ($tratativa['cliente_nombre'] ?? '');
                    }
                }
            }
        }

        // Si se pasa cliente_id por URL, tiene prioridad sobre el heredado de la tratativa.
        if (!empty($_GET['cliente_id'])) {
            $candidate = (int) $_GET['cliente_id'];
            if ($candidate > 0) {
                $clienteRepo = new \App\Modules\CrmClientes\CrmClienteRepository();
                $cliente = $clienteRepo->findById($candidate, $empresaId);
                if ($cliente !== null) {
                    $prefill['cliente_id'] = $candidate;
                    $razon = trim((string) ($cliente['razon_social'] ?? ''));
                    $prefill['cliente_nombre'] = $razon !== '' ? $razon : trim(((string) ($cliente['nombre'] ?? '')) . ' ' . ((string) ($cliente['apellido'] ?? '')));
                }
            }
        }

        return $prefill;
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
