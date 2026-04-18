<?php

declare(strict_types=1);

namespace App\Modules\RxnSync;

use App\Core\Controller;
use App\Core\Context;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Modules\EmpresaConfig\EmpresaConfigRepository;
use RuntimeException;

class RxnSyncController extends Controller
{
    private RxnSyncService $service;

    public function __construct()
    {
        $this->service = new RxnSyncService();
    }

    /**
     * Muestra la Consola Centralizada de RXN - Sync
     */
    public function index(): void
    {
        $empresaId = Context::getEmpresaId();
        $area = $this->resolveArea();
        $config = EmpresaConfigRepository::forArea($area)->findByEmpresaId((int) $empresaId);
        $articulosStatus = $this->service->getPivotStatus((int) $empresaId, 'articulo');
        $articulosVinculados = count(array_filter($articulosStatus, static function (array $row): bool {
            return ($row['estado'] ?? '') === 'vinculado' && !empty($row['tango_id']);
        }));

        // Precondiciones operativas del circuito. En CRM leen del catálogo comercial
        // (poblado por Sync Catálogos). En Tiendas leen de los selectores planos del form.
        $catalogosReady = false;
        if ($area === 'crm') {
            $catalogRepo = new \App\Modules\CrmPresupuestos\CommercialCatalogRepository();
            $listasReady = $catalogRepo->countByType((int) $empresaId, 'lista_precio') > 0;
            $depositoReady = $catalogRepo->countByType((int) $empresaId, 'deposito') > 0;
            // El botón "Sync Catálogos" se habilita apenas hay credenciales Tango mínimas.
            $catalogosReady = !empty($config->tango_connect_token);
        } else {
            $listasReady = !empty($config->lista_precio_1) || !empty($config->lista_precio_2);
            $depositoReady = !empty($config->deposito_codigo);
        }

        View::render('app/modules/RxnSync/views/index.php', [
            'empresaId' => $empresaId,
            'area' => $area,
            'syncCircuit' => [
                'area' => $area,
                'articulos_total' => count($articulosStatus),
                'articulos_vinculados' => $articulosVinculados,
                'articulos_ready' => $articulosVinculados > 0,
                'listas_ready' => $listasReady,
                'deposito_ready' => $depositoReady,
                'catalogos_ready' => $catalogosReady,
                'precios_ready' => $articulosVinculados > 0 && $listasReady,
                'stock_ready' => $articulosVinculados > 0 && $depositoReady,
                'config_path' => $area === 'crm' ? '/mi-empresa/crm/configuracion' : '/mi-empresa/configuracion',
                'sync_articulos_path' => $area === 'crm' ? '/mi-empresa/crm/sync/articulos' : '/mi-empresa/sync/articulos',
                'sync_precios_path' => $area === 'crm' ? '/mi-empresa/crm/sync/precios?return=/mi-empresa/crm/rxn-sync' : '/mi-empresa/sync/precios?return=/mi-empresa/rxn-sync',
                'sync_stock_path' => $area === 'crm' ? '/mi-empresa/crm/sync/stock?return=/mi-empresa/crm/rxn-sync' : '/mi-empresa/sync/stock?return=/mi-empresa/rxn-sync',
                'sync_catalogos_path' => '/mi-empresa/crm/rxn-sync/sync-catalogos',
            ],
        ]);
    }

    public function listClientes(): void
    {
        $empresaId = Context::getEmpresaId();
        $advancedFilters = is_array($_GET['f'] ?? null) ? $_GET['f'] : [];
        $registros = $this->service->getPivotStatus($empresaId, 'cliente', $advancedFilters);

        View::render('app/modules/RxnSync/views/tabs/clientes.php', [
            'registros' => $registros
        ], true);
    }

    public function listArticulos(): void
    {
        $empresaId = Context::getEmpresaId();
        $advancedFilters = is_array($_GET['f'] ?? null) ? $_GET['f'] : [];
        $registros = $this->service->getPivotStatus($empresaId, 'articulo', $advancedFilters);

        View::render('app/modules/RxnSync/views/tabs/articulos.php', [
            'registros' => $registros
        ], true);
    }

    /**
     * AJAX: Dispara el Pull completo de artículos desde Tango.
     * Hace Match Suave por código externo y popula rxn_sync_status.
     */
    public function auditarArticulos(): void
    {
        $empresaId = Context::getEmpresaId();
        header('Content-Type: application/json');
        set_time_limit(120);

        try {
            $result = $this->service->auditarArticulos($empresaId);
            echo json_encode([
                'success' => true,
                'message' => "Auditoría completada. Vinculados: {$result['vinculados']} | Pendientes: {$result['pendientes']}",
                'stats'   => $result,
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }


    /**
     * AJAX: Push individual de un registro hacia Tango.
     * Recibe POST['id'] = local ID, POST['entidad'].
     */
    public function pushToTango(): void
    {
        $id        = (int)($_POST['id'] ?? 0);
        $entidad   = $_POST['entidad'] ?? '';
        $empresaId = Context::getEmpresaId();

        header('Content-Type: application/json');

        if (!$id || !in_array($entidad, ['cliente', 'articulo'])) {
            echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
            return;
        }

        try {
            $result = $this->service->pushToTangoByLocalId($empresaId, $id, $entidad);
            echo json_encode([
                'success' => true,
                'message' => 'Push a Tango completado.',
                'payload' => $result,
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Retorna el último snapshot Tango guardado para auditoría.
     * GET ?entidad=articulo|cliente&id={localId}
     */
    public function getPayload(): void
    {
        $id        = (int)($_GET['id'] ?? 0);
        $entidad   = $_GET['entidad'] ?? '';
        $empresaId = Context::getEmpresaId();

        header('Content-Type: application/json');

        if (!$id || !in_array($entidad, ['cliente', 'articulo'])) {
            echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
            return;
        }

        $db   = \App\Core\Database::getConnection();
        $stmt = $db->prepare(
            "SELECT tango_id, estado, direccion_ultima_sync, resultado_ultima_sync,
                    fecha_ultima_sync, mensaje_error
             FROM rxn_sync_status
             WHERE empresa_id = ? AND entidad = ? AND local_id = ?
             LIMIT 1"
        );
        $stmt->execute([$empresaId, $entidad, $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Sin historial de sincronización para este registro.']);
            return;
        }

        $snapshot = null;
        $stmtLog = $db->prepare(
            "SELECT payload_resumen
             FROM rxn_sync_log
             WHERE empresa_id = ? AND entidad = ? AND local_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT 1"
        );
        $stmtLog->execute([$empresaId, $entidad, $id]);
        $logRow = $stmtLog->fetch(\PDO::FETCH_ASSOC);
        if (!empty($logRow['payload_resumen'])) {
            $snapshot = json_decode((string) $logRow['payload_resumen'], true);
        }

        echo json_encode([
            'success'  => true,
            'meta'     => [
                'tango_id'    => $row['tango_id'],
                'estado'      => $row['estado'],
                'direccion'   => $row['direccion_ultima_sync'],
                'resultado'   => $row['resultado_ultima_sync'],
                'fecha'       => $row['fecha_ultima_sync'],
                'error'       => $row['mensaje_error'],
            ],
            'snapshot' => $snapshot,
        ]);
    }

    /**
     * AJAX: Pull individual de un registro desde Tango.
     * Recibe POST['id'] = local ID, POST['entidad'].
     */
    public function pullSingle(): void
    {
        $id      = (int)($_POST['id'] ?? 0);
        $entidad = $_POST['entidad'] ?? '';
        $empresaId = Context::getEmpresaId();

        header('Content-Type: application/json');

        if (!$id || !in_array($entidad, ['cliente', 'articulo'])) {
            echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
            return;
        }

        try {
            $result = $this->service->pullFromTangoByLocalId($empresaId, $id, $entidad);
            echo json_encode([
                'success' => true,
                'message' => 'Pull desde Tango completado. Datos locales actualizados.',
                'payload' => $result,
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }



    public function auditarClientes(): void
    {
        $empresaId = Context::getEmpresaId();
        header('Content-Type: application/json');
        set_time_limit(120);

        try {
            $result = $this->service->auditarClientes($empresaId);
            echo json_encode([
                'success' => true,
                'message' => "Auditoría completada. Vinculados: {$result['vinculados']} | Pendientes: {$result['pendientes']}",
                'stats'   => $result,
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * AJAX: Flujo completo para Articulos = Import (pull masivo desde Tango, upsert local)
     * + Audit (match suave por codigo). Devuelve stats consolidados.
     *
     * Esto reemplaza conceptualmente lo que antes hacia el boton "Sync Total" del
     * modulo Articulos antes de la migracion a RxnSync (ver docs/logs/2026-03-26_2107_sync_total_articulos.md).
     */
    public function syncPullArticulos(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');
        set_time_limit(240);

        $empresaId = (int) Context::getEmpresaId();

        try {
            $tangoSyncService = $this->resolveTangoSyncService();
            $importStats = $tangoSyncService->syncArticulos();
            $auditResult = $this->service->auditarArticulos($empresaId);

            $msg = sprintf(
                'Sincronización completada. Importados: %d recibidos → %d nuevos / %d actualizados / %d omitidos. Vinculación: %d vinculados / %d pendientes.',
                (int) ($importStats['recibidos'] ?? 0),
                (int) ($importStats['insertados'] ?? 0),
                (int) ($importStats['actualizados'] ?? 0),
                (int) ($importStats['omitidos'] ?? 0),
                (int) ($auditResult['vinculados'] ?? 0),
                (int) ($auditResult['pendientes'] ?? 0)
            );

            echo json_encode([
                'success' => true,
                'message' => $msg,
                'stats'   => [
                    'import' => $importStats,
                    'audit'  => $auditResult,
                ],
            ]);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error en sincronización: ' . $e->getMessage(),
                'error_class' => (new \ReflectionClass($e))->getShortName(),
                'error_file' => basename($e->getFile()) . ':' . $e->getLine(),
            ]);
        }
        exit;
    }

    /**
     * AJAX: Flujo completo para Clientes = Import + Audit. Solo aplica a area CRM.
     */
    public function syncPullClientes(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');
        set_time_limit(240);

        $empresaId = (int) Context::getEmpresaId();

        try {
            $tangoSyncService = $this->resolveTangoSyncService();
            $importStats = $tangoSyncService->syncClientes();
            $auditResult = $this->service->auditarClientes($empresaId);

            $msg = sprintf(
                'Sincronización completada. Importados: %d recibidos → %d nuevos / %d actualizados / %d omitidos. Vinculación: %d vinculados / %d pendientes.',
                (int) ($importStats['recibidos'] ?? 0),
                (int) ($importStats['insertados'] ?? 0),
                (int) ($importStats['actualizados'] ?? 0),
                (int) ($importStats['omitidos'] ?? 0),
                (int) ($auditResult['vinculados'] ?? 0),
                (int) ($auditResult['pendientes'] ?? 0)
            );

            echo json_encode([
                'success' => true,
                'message' => $msg,
                'stats'   => [
                    'import' => $importStats,
                    'audit'  => $auditResult,
                ],
            ]);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error en sincronización: ' . $e->getMessage(),
                'error_class' => (new \ReflectionClass($e))->getShortName(),
                'error_file' => basename($e->getFile()) . ':' . $e->getLine(),
            ]);
        }
        exit;
    }

    /**
     * AJAX: Sincroniza los catalogos comerciales del area CRM (condiciones de venta,
     * listas de precio, vendedores, transportes y depositos). Es prerequisito para
     * poder ejecutar Sync Precios y Sync Stock en CRM, porque esos dependen de que
     * haya listas y depositos en crm_catalogo_comercial_items.
     *
     * Antes vivia en PresupuestoController::syncCatalogs — se movio aca en release 1.12.5
     * porque semanticamente es responsabilidad de la consola de sincronizacion.
     */
    public function syncCatalogos(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');
        set_time_limit(240);

        try {
            $empresaId = (int) Context::getEmpresaId();
            $catalogSync = new \App\Modules\RxnSync\Services\CommercialCatalogSyncService();
            $stats = $catalogSync->sync($empresaId);

            $msg = sprintf(
                'Catálogos sincronizados. Condiciones: %d / Listas: %d / Vendedores: %d / Transportes: %d / Depósitos: %d / Clasificaciones PDS: %d (total recibidos).',
                (int) ($stats['condicion_venta']['received'] ?? 0),
                (int) ($stats['lista_precio']['received'] ?? 0),
                (int) ($stats['vendedor']['received'] ?? 0),
                (int) ($stats['transporte']['received'] ?? 0),
                (int) ($stats['deposito']['received'] ?? 0),
                (int) ($stats['clasificacion_pds']['received'] ?? 0)
            );

            echo json_encode([
                'success' => true,
                'message' => $msg,
                'stats'   => $stats,
            ]);
        } catch (\Throwable $e) {
            // \Throwable para atrapar Fatal errors (class not found, etc) y no
            // dejar que xdebug devuelva HTML con 200 status. Aplica regla del
            // proyecto: diagnóstico persistente > error silencioso.
            echo json_encode([
                'success' => false,
                'message' => 'Error en Sync Catálogos: ' . $e->getMessage(),
                'error_class' => (new \ReflectionClass($e))->getShortName(),
                'error_file' => basename($e->getFile()) . ':' . $e->getLine(),
            ]);
        }
        exit;
    }

    /**
     * Tab Pedidos: listado de PDS con su estado Tango cacheado.
     * Solo disponible en área CRM (los PDS son una entidad CRM).
     */
    public function listPedidos(): void
    {
        $empresaId = Context::getEmpresaId();
        $advancedFilters = is_array($_GET['f'] ?? null) ? $_GET['f'] : [];
        $registros = $this->service->getPedidosSyncList($empresaId, $advancedFilters);

        View::render('app/modules/RxnSync/views/tabs/pedidos.php', [
            'registros' => $registros,
        ], true);
    }

    /**
     * AJAX: Pull masivo de estados de PDS desde Tango. Recorre el listado paginado
     * de process=19845 y matchea por ID_GVA21 para update en bulk.
     */
    public function syncPedidosEstados(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');
        set_time_limit(240);

        $empresaId = (int) Context::getEmpresaId();

        try {
            $stats = $this->service->syncPedidosEstados($empresaId);
            $parts = [
                sprintf('Total: %d', (int) ($stats['total'] ?? 0)),
                sprintf('Actualizados: %d', (int) ($stats['actualizados'] ?? 0)),
            ];
            if (!empty($stats['resueltos_id'])) {
                $parts[] = sprintf('IDs Tango auto-resueltos: %d', (int) $stats['resueltos_id']);
            }
            if (!empty($stats['sin_match'])) {
                $parts[] = sprintf('Sin match: %d', (int) $stats['sin_match']);
            }
            if (!empty($stats['errores'])) {
                $parts[] = sprintf('Errores: %d', (int) $stats['errores']);
            }
            $msg = 'Sync de estados completado. ' . implode(' / ', $parts) . '.';

            echo json_encode([
                'success' => ($stats['errores'] ?? 0) === 0,
                'message' => $msg,
                'stats'   => $stats,
            ]);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error en sync de estados: ' . $e->getMessage(),
                'error_class' => (new \ReflectionClass($e))->getShortName(),
                'error_file' => basename($e->getFile()) . ':' . $e->getLine(),
            ]);
        }
        exit;
    }

    /**
     * AJAX: Pull individual de estado de un PDS. Recibe POST['id'] (local id del PDS).
     */
    public function syncPedidoEstado(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $empresaId = (int) Context::getEmpresaId();

        header('Content-Type: application/json');

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            return;
        }

        try {
            $result = $this->service->syncPedidoEstadoByLocalId($empresaId, $id);
            $estadoLabel = \App\Modules\CrmPedidosServicio\TangoPedidoEstado::label($result['estado']);
            echo json_encode([
                'success' => true,
                'message' => "Estado actualizado: {$estadoLabel}",
                'payload' => $result,
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function resolveTangoSyncService(): \App\Modules\Tango\Services\TangoSyncService
    {
        return $this->resolveArea() === 'crm'
            ? \App\Modules\Tango\Services\TangoSyncService::forCrm()
            : new \App\Modules\Tango\Services\TangoSyncService();
    }

    private function resolveArea(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        return str_contains($uri, '/mi-empresa/crm/') ? 'crm' : 'tiendas';
    }

    /**
     * AJAX: Push masivo de registros seleccionados hacia Tango.
     * Recibe POST['ids'] = JSON array de local IDs, POST['entidad'].
     */
    public function pushMasivo(): void
    {
        $empresaId = Context::getEmpresaId();
        header('Content-Type: application/json');
        set_time_limit(180);

        $entidad = $_POST['entidad'] ?? 'articulo';
        $ids     = json_decode($_POST['ids'] ?? '[]', true);

        if (!is_array($ids) || empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No se recibieron IDs.']);
            exit;
        }

        $ok = 0; $err = 0; $errors = [];
        foreach ($ids as $localId) {
            try {
                $this->service->pushToTangoByLocalId($empresaId, (int)$localId, $entidad);
                $ok++;
            } catch (\Exception $e) {
                $err++;
                $errors[] = "ID {$localId}: " . $e->getMessage();
            }
        }

        $msg = "Push completado. OK: {$ok} | Errores: {$err}";
        echo json_encode([
            'success' => $err === 0,
            'message' => $msg,
            'details' => $errors,
        ]);
        exit;
    }

    /**
     * AJAX: Pull masivo — trae datos de Tango y actualiza registros locales.
     * Recibe POST['ids'] = JSON array de local IDs, POST['entidad'].
     */
    public function pullMasivo(): void
    {
        $empresaId = Context::getEmpresaId();
        header('Content-Type: application/json');
        set_time_limit(180);

        $entidad = $_POST['entidad'] ?? 'articulo';
        $ids     = json_decode($_POST['ids'] ?? '[]', true);

        if (!is_array($ids) || empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No se recibieron IDs.']);
            exit;
        }

        $ok = 0; $err = 0; $errors = [];
        foreach ($ids as $localId) {
            try {
                $this->service->pullFromTangoByLocalId($empresaId, (int)$localId, $entidad);
                $ok++;
            } catch (\Exception $e) {
                $err++;
                $errors[] = "ID {$localId}: " . $e->getMessage();
            }
        }

        $msg = "Pull completado. Actualizados: {$ok} | Errores: {$err}";
        echo json_encode([
            'success' => $err === 0,
            'message' => $msg,
            'details' => $errors,
        ]);
        exit;
    }
}
