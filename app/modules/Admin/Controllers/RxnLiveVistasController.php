<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Modules\RxnLive\RxnLiveService;

/**
 * Administración cross-user de vistas guardadas de RXN Live.
 *
 * Propósito: permitir a los admins gestionar vistas de CUALQUIER usuario.
 * Caso de uso principal: destrabar datasets cuando una vista con config corrupto
 * tumba la UI (loop de render / pantalla titilando) y el dueño de la vista no
 * puede (o no está disponible) para borrarla.
 *
 * Funcionalidades:
 *   - Listado cross-user con datos del dueño.
 *   - Ver JSON crudo del config (audit / debugging de configs rotos).
 *   - Eliminar cualquier vista.
 *   - Exportar 1 o N vistas a JSON descargable (backup/versionado/transfer entre ambientes).
 *   - Importar vistas desde JSON subido.
 *
 * Todos los métodos protegidos por AuthService::requireRxnAdmin().
 */
class RxnLiveVistasController extends Controller
{
    private RxnLiveService $service;

    public function __construct()
    {
        $this->service = new RxnLiveService();
    }

    /**
     * Listado de todas las vistas (admin).
     */
    public function index(): void
    {
        AuthService::requireRxnAdmin();

        $vistas = $this->service->getAllVistasAdmin();
        $datasets = $this->service->getAvailableDatasets();

        // Filtro opcional por dataset
        $filterDataset = $_GET['dataset'] ?? '';
        if ($filterDataset) {
            $vistas = array_values(array_filter($vistas, fn($v) => $v['dataset'] === $filterDataset));
        }

        View::render('app/modules/Admin/views/rxn_live_vistas.php', [
            'vistas' => $vistas,
            'datasets' => $datasets,
            'filterDataset' => $filterDataset,
            'success' => $_GET['success'] ?? null,
            'error' => $_GET['error'] ?? null,
        ]);
    }

    /**
     * Endpoint JSON para ver el detalle de una vista puntual (modal de inspección).
     */
    public function ver(): void
    {
        AuthService::requireRxnAdmin();
        header('Content-Type: application/json');

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        $vista = $this->service->getVistaByIdAdmin($id);
        if (!$vista) {
            echo json_encode(['success' => false, 'message' => 'Vista no encontrada.']);
            exit;
        }

        // Parsear config como array para que el front lo muestre formateado
        $configArr = json_decode($vista['config'], true);
        echo json_encode([
            'success' => true,
            'vista' => [
                'id' => (int)$vista['id'],
                'usuario_id' => (int)$vista['usuario_id'],
                'usuario_nombre' => $vista['usuario_nombre'],
                'usuario_email' => $vista['usuario_email'],
                'dataset' => $vista['dataset'],
                'nombre' => $vista['nombre'],
                'created_at' => $vista['created_at'],
                'config' => $configArr,
            ],
        ]);
        exit;
    }

    /**
     * Eliminar cualquier vista (admin).
     */
    public function eliminar(): void
    {
        AuthService::requireRxnAdmin();
        header('Content-Type: application/json');

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        $ok = $this->service->deleteVistaAdmin($id);
        echo json_encode([
            'success' => $ok,
            'message' => $ok ? 'Vista eliminada.' : 'No se pudo eliminar (no existe o error de DB).',
        ]);
        exit;
    }

    /**
     * Exportar vistas a JSON descargable.
     *
     * Modos:
     *   - ?ids=3,7,12  → exporta solo esas vistas
     *   - ?dataset=X   → exporta todas las vistas de ese dataset
     *   - (sin params) → exporta TODAS las vistas
     *
     * El archivo resultante tiene estructura:
     *   {
     *     "version": 1,
     *     "exported_at": "2026-04-14T...",
     *     "source": "rxn_suite",
     *     "vistas": [
     *       { "id": 3, "usuario_id": 5, "usuario_email": "...",
     *         "dataset": "...", "nombre": "...", "config": {...}, "created_at": "..." },
     *       ...
     *     ]
     *   }
     */
    public function exportar(): void
    {
        AuthService::requireRxnAdmin();

        $todas = $this->service->getAllVistasAdmin();

        // Filtrar según parámetros
        $idsParam = $_GET['ids'] ?? '';
        $datasetParam = $_GET['dataset'] ?? '';

        if ($idsParam !== '') {
            $ids = array_filter(array_map('intval', explode(',', $idsParam)));
            $todas = array_values(array_filter($todas, fn($v) => in_array((int)$v['id'], $ids, true)));
        } elseif ($datasetParam !== '') {
            $todas = array_values(array_filter($todas, fn($v) => $v['dataset'] === $datasetParam));
        }

        // Normalizar estructura exportada (no queremos leak de empresa_id innecesariamente)
        $payload = [
            'version' => 1,
            'exported_at' => date('c'),
            'source' => 'rxn_suite',
            'count' => count($todas),
            'vistas' => array_map(function ($v) {
                return [
                    'id' => (int)$v['id'],
                    'usuario_id' => (int)$v['usuario_id'],
                    'usuario_email' => $v['usuario_email'] ?? null,
                    'dataset' => $v['dataset'],
                    'nombre' => $v['nombre'],
                    'config' => json_decode($v['config'], true),
                    'created_at' => $v['created_at'],
                ];
            }, $todas),
        ];

        if (ob_get_length()) {
            ob_end_clean();
        }

        $suffix = $datasetParam ? '_' . $datasetParam : ($idsParam ? '_seleccion' : '_todas');
        $filename = 'rxn_live_vistas' . $suffix . '_' . date('Ymd_His') . '.json';

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Importar vistas desde un archivo JSON subido.
     *
     * Espera el formato generado por `exportar()` o, alternativamente, una única vista
     * en la raíz (objeto con dataset/nombre/config).
     *
     * Parámetro POST `owner_id` (opcional):
     *   - Si viene, todas las vistas importadas se asignan a ese usuario.
     *   - Si no viene, se usa el usuario_id original del JSON (si existe)
     *     o el admin actual como fallback.
     */
    public function importar(): void
    {
        AuthService::requireRxnAdmin();

        // Clave de sesión correcta es `user_id` (AuthService::login la guarda así). Ver hotfix 1.16.3.
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        $ownerOverride = isset($_POST['owner_id']) && $_POST['owner_id'] !== ''
            ? (int)$_POST['owner_id']
            : null;

        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            header('Location: /admin/rxn_live/vistas?error=' . urlencode('Error al subir el archivo.'));
            exit;
        }

        $raw = file_get_contents($_FILES['archivo']['tmp_name']);
        if ($raw === false) {
            header('Location: /admin/rxn_live/vistas?error=' . urlencode('No se pudo leer el archivo.'));
            exit;
        }

        $parsed = json_decode($raw, true);
        if ($parsed === null && json_last_error() !== JSON_ERROR_NONE) {
            header('Location: /admin/rxn_live/vistas?error=' . urlencode('Archivo JSON inválido: ' . json_last_error_msg()));
            exit;
        }

        // Normalizamos: aceptamos tanto el wrapper (con `vistas` adentro) como una única vista plana.
        $vistas = [];
        if (isset($parsed['vistas']) && is_array($parsed['vistas'])) {
            $vistas = $parsed['vistas'];
        } elseif (isset($parsed['dataset']) && isset($parsed['nombre'])) {
            $vistas = [$parsed];
        } else {
            header('Location: /admin/rxn_live/vistas?error=' . urlencode('Formato desconocido (esperaba wrapper con "vistas" o vista plana).'));
            exit;
        }

        $okCount = 0;
        $errors = [];

        foreach ($vistas as $idx => $v) {
            try {
                $owner = $ownerOverride
                    ?? (isset($v['usuario_id']) ? (int)$v['usuario_id'] : $currentUserId);
                $this->service->importVistaAdmin([
                    'dataset' => $v['dataset'] ?? '',
                    'nombre' => $v['nombre'] ?? '',
                    'config' => $v['config'] ?? null,
                ], $owner);
                $okCount++;
            } catch (\Throwable $e) {
                $errors[] = "#{$idx} (" . ($v['nombre'] ?? 'sin nombre') . "): " . $e->getMessage();
            }
        }

        $msg = "Importadas: {$okCount}/" . count($vistas);
        if (!empty($errors)) {
            $msg .= " &mdash; Errores: " . implode(' | ', array_slice($errors, 0, 3));
            if (count($errors) > 3) $msg .= ' (y ' . (count($errors) - 3) . ' más)';
            header('Location: /admin/rxn_live/vistas?error=' . urlencode($msg));
        } else {
            header('Location: /admin/rxn_live/vistas?success=' . urlencode($msg));
        }
        exit;
    }
}
