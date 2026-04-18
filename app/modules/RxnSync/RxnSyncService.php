<?php

declare(strict_types=1);

namespace App\Modules\RxnSync;

use App\Core\AdvancedQueryFilter;
use App\Core\Database;
use App\Modules\Tango\TangoService;
use RuntimeException;
use PDO;

class RxnSyncService
{
    private PDO $db;
    
    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Sincroniza (Pull) todos los articulos locales contra Tango usando Match Suave.
     * Solo inserta o actualiza el Pivot. No escribe a Tango.
     */
    public function auditarArticulos(int $empresaId): array
    {
        // Obtener articulos locales
        $stmt = $this->db->prepare("SELECT id, codigo_externo, nombre FROM crm_articulos WHERE empresa_id = ?");
        $stmt->execute([$empresaId]);
        $locales = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Llamar a Tango y obtener articulos completos (o paginados)
        $tangoService = TangoService::forCrm();
        $apiClient = $tangoService->getApiClient();
        
        // Optimizacion: Usaremos el endpoint crudo o fetchArticulos segun viabilidad.
        // Por ahora, asumimos que recuperamos los topes de Tango
        $artsTango = [];
        $res = $apiClient->getRawClient()->get('Get', ['process' => 87, 'pageSize' => 500]);
        if (isset($res['data']['resultData']['list'])) {
            $artsTango = $res['data']['resultData']['list'];
        }

        // Crear mapa Tango
        $mapTango = [];
        foreach ($artsTango as $t) {
            $mapTango[$t['COD_STA11']] = $t;
        }

        $auditLog = ['vinculados' => 0, 'pendientes' => 0, 'errores' => 0, 'detalles' => []];

        foreach ($locales as $local) {
            $codExterno = $local['codigo_externo'];
            $tangoRef = $mapTango[$codExterno] ?? null;

            if ($tangoRef && isset($tangoRef['ID_STA11'])) {
                // Match Suave exitoso por SKU / Codigo Externo
                $this->upsertPivot($empresaId, 'articulo', (int)$local['id'], (int)$tangoRef['ID_STA11'], 'vinculado', null, 'pull', 'ok', $tangoRef);
                $auditLog['vinculados']++;
            } else {
                $this->upsertPivot($empresaId, 'articulo', (int)$local['id'], null, 'pendiente', 'No existe el codigo ' . $codExterno . ' en Tango', 'pull', 'error', null);
                $auditLog['pendientes']++;
            }
        }

        return $auditLog;
    }

    public function getPivotStatus(int $empresaId, string $entidad, array $advancedFilters = []): array
    {
        // Retorna un join con la tabla local para pintar la UI
        $table = $entidad === 'cliente' ? 'crm_clientes' : 'crm_articulos';
        $ident = $entidad === 'cliente' ? 'razon_social as nombre, codigo_tango as codigo' : 'nombre, codigo_externo as codigo';

        $columnMap = $entidad === 'cliente'
            ? ['nombre' => 'l.razon_social', 'codigo' => 'l.codigo_tango', 'estado' => 'p.estado']
            : ['nombre' => 'l.nombre', 'codigo' => 'l.codigo_externo', 'estado' => 'p.estado'];

        [$advSql, $advParams] = AdvancedQueryFilter::build($advancedFilters, $columnMap);

        $sql = "SELECT p.*, l.$ident
                FROM rxn_sync_status p
                JOIN $table l ON l.id = p.local_id
                WHERE p.empresa_id = :empresa_id AND p.entidad = :entidad";

        if ($advSql !== '') {
            $sql .= " AND $advSql";
        }

        $params = array_merge(['empresa_id' => $empresaId, 'entidad' => $entidad], $advParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function upsertPivot(int $empresaId, string $entidad, int $localId, ?int $tangoId, string $estado, ?string $mensaje = null, string $direccion = 'link', string $resultado = 'pending', ?array $payload = null): void
    {
        $fechaToUpdate = '';
        if ($direccion === 'push') {
            $fechaToUpdate = "fecha_ultimo_push = NOW(),";
        } elseif ($direccion === 'pull') {
            $fechaToUpdate = "fecha_ultimo_pull = NOW(),";
        }

        $sql = "INSERT INTO rxn_sync_status (empresa_id, entidad, local_id, tango_id, estado, mensaje_error, direccion_ultima_sync, resultado_ultima_sync, fecha_ultima_sync)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    tango_id = VALUES(tango_id),
                    estado = VALUES(estado),
                    mensaje_error = VALUES(mensaje_error),
                    direccion_ultima_sync = VALUES(direccion_ultima_sync),
                    resultado_ultima_sync = VALUES(resultado_ultima_sync),
                    $fechaToUpdate
                    fecha_ultima_sync = NOW()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $empresaId,
            $entidad,
            $localId,
            $tangoId,
            $estado,
            $mensaje,
            $direccion,
            $resultado
        ]);

        // Insert into log
        $logSql = "INSERT INTO rxn_sync_log (empresa_id, entidad, local_id, tango_id, direccion, resultado, mensaje, payload_resumen)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtLog = $this->db->prepare($logSql);
        $stmtLog->execute([
            $empresaId,
            $entidad,
            $localId,
            $tangoId,
            $direccion,
            $resultado,
            $mensaje,
            $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null
        ]);
    }

    public function pushToTango(int $empresaId, int $pivotId, string $entidad): array
    {
        // 1. Fetch pivot
        $stmt = $this->db->prepare("SELECT * FROM rxn_sync_status WHERE id = ? AND empresa_id = ? AND entidad = ?");
        $stmt->execute([$pivotId, $empresaId, $entidad]);
        $pivot = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pivot) {
            throw new RuntimeException("Registro de auditoría no encontrado.");
        }

        return $this->pushToTangoByLocalId($empresaId, (int)$pivot['local_id'], $entidad);
    }

    /**
     * Push a Tango usando directamente el ID local del artículo/cliente.
     * No requiere que rxn_sync_status esté pre-poblada.
     * Hace Match Suave por codigo_externo para encontrar el ID en Tango,
     * luego hydrate + PUT, y upserta el pivot con el resultado.
     */
    public function pushToTangoByLocalId(int $empresaId, int $localId, string $entidad, int $pageSize = 500): array
    {
        $tangoService = TangoService::forCrm();
        $apiClient = $tangoService->getApiClient();

        // 1. Fetch local record
        $table  = $entidad === 'cliente' ? 'crm_clientes' : 'crm_articulos';
        $stmtLocal = $this->db->prepare("SELECT * FROM {$table} WHERE id = ? AND empresa_id = ?");
        $stmtLocal->execute([$localId, $empresaId]);
        $localData = $stmtLocal->fetch(PDO::FETCH_ASSOC);

        if (!$localData) {
            throw new RuntimeException("No se encontró el registro local (ID: {$localId}).");
        }

        // 2. Resolve Tango ID — primero por pivot existente, luego por Match Suave
        $tangoId = null;

        $stmtPivot = $this->db->prepare(
            "SELECT tango_id FROM rxn_sync_status WHERE empresa_id = ? AND entidad = ? AND local_id = ? AND tango_id IS NOT NULL LIMIT 1"
        );
        $stmtPivot->execute([$empresaId, $entidad, $localId]);
        $pivotRow = $stmtPivot->fetch(PDO::FETCH_ASSOC);

        if ($pivotRow && !empty($pivotRow['tango_id'])) {
            $tangoId = (int)$pivotRow['tango_id'];
        } else {
            // Match Suave: buscar en Tango por SKU / codigo_tango
            $tangoId = $this->resolveTangoIdBySku($apiClient, $entidad, $localData, $pageSize);
        }

        if (!$tangoId) {
            $sku = $entidad === 'cliente' ? ($localData['codigo_tango'] ?? '') : ($localData['codigo_externo'] ?? '');
            $this->upsertPivot($empresaId, $entidad, $localId, null, 'pendiente',
                "No se encontró match en Tango para el código: {$sku}", 'push', 'error', null);
            throw new RuntimeException("No se encontró correspondencia en Tango para este registro. Verifique que el código externo coincida.");
        }

        // 3. Fetch GetById (Shadow Copy para no pisar campos read-only)
        if ($entidad === 'cliente') {
            $tangoData = $apiClient->getClienteById($tangoId);
        } else {
            $tangoData = $apiClient->getArticuloById($tangoId);
        }

        if (!$tangoData) {
            throw new RuntimeException("No se encontró la entidad original en Tango (ID: {$tangoId}).");
        }

        // 4. Hydrate Whitelist (Strict Partial DTO)
        $updatePayload = [];

        if ($entidad === 'cliente') {
            $updatePayload['ID_GVA14']              = (int)$tangoId;
            $updatePayload['COD_GVA14']             = $tangoData['COD_GVA14'] ?? '';
            $updatePayload['ID_TIPO_DOCUMENTO_GV']  = $tangoData['ID_TIPO_DOCUMENTO_GV'] ?? 26;
            $updatePayload['ID_GVA05']              = $tangoData['ID_GVA05'] ?? 1;
            $updatePayload['ID_GVA18']              = $tangoData['ID_GVA18'] ?? 1;
            $updatePayload['ID_CATEGORIA_IVA']      = $tangoData['ID_CATEGORIA_IVA'] ?? 1;
            $updatePayload['RAZON_SOCI']            = mb_substr(trim((string)($localData['razon_social'] ?? '')), 0, 60);
            $updatePayload['CUIT']                  = mb_substr(trim((string)($localData['documento'] ?? '')), 0, 20);
            $updatePayload['DOMICILIO']             = mb_substr(trim((string)($localData['direccion'] ?? ($tangoData['DOMICILIO'] ?? ''))), 0, 60);
            $updatePayload['LOCALIDAD']             = mb_substr(trim((string)($localData['localidad'] ?? ($tangoData['LOCALIDAD'] ?? ''))), 0, 20);
            $updatePayload['C_POSTAL']              = mb_substr(trim((string)($localData['codigo_postal'] ?? ($tangoData['C_POSTAL'] ?? ''))), 0, 8);
            $updatePayload['E_MAIL']                = mb_substr(trim((string)($localData['email'] ?? '')), 0, 255);
            $updatePayload['TELEFONO_1']            = mb_substr(trim((string)($localData['telefono'] ?? '')), 0, 20);
            foreach (['TELEFONO_2', 'TELEFONO_MOVIL', 'WEB', 'NOM_COM', 'DIR_COM'] as $field) {
                if (isset($tangoData[$field])) $updatePayload[$field] = mb_substr(trim((string)$tangoData[$field]), 0, 50);
            }
        } else {
            // Payload mínimo para artículos: solo campos de texto seguros.
            // PERFIL_ARTICULO, ID_STA22, COD_BARRA y OBSERVACIONES pueden ser
            // rechazados por Tango según el perfil configurado (read-only o límite de chars).
            $updatePayload['ID_STA11']   = (int)$tangoId;
            $updatePayload['COD_STA11']  = $tangoData['COD_STA11'] ?? '';
            $updatePayload['DESCRIPCIO'] = mb_substr(trim((string)($localData['nombre'] ?? '')), 0, 60);
        }

        // 5. Save back to Tango
        $processAction = $entidad === 'cliente' ? 2117 : 87;
        $pushErrorLogged = false;

        try {
            $response = $apiClient->updateEntity($processAction, $updatePayload);
            if (!$this->isSuccessfulConnectResponse($response)) {
                $message = $this->extractConnectError($response);
                $payloadLog = [
                    'payload_enviado' => $updatePayload,
                    'response_tango' => $response['data'] ?? $response,
                    'snapshot_tango' => $tangoData,
                ];
                $pushErrorLogged = true;
                $this->upsertPivot($empresaId, $entidad, $localId, $tangoId, 'error', mb_substr($message, 0, 255), 'push', 'error', $payloadLog);
                throw new RuntimeException('Tango rechazó el push: ' . $message);
            }

            $payloadLog = [
                'payload_enviado' => $updatePayload,
                'response_tango' => $response['data'] ?? $response,
                'snapshot_tango' => $tangoData,
            ];
            $this->upsertPivot($empresaId, $entidad, $localId, $tangoId, 'vinculado', null, 'push', 'ok', $payloadLog);
            return [
                'tango_id'       => $tangoId,
                'payload_enviado' => $updatePayload,
                'snapshot_tango' => $tangoData,
                'response_tango' => $response['data'] ?? $response,
            ];
        } catch (\Exception $e) {
            if (!$pushErrorLogged) {
                $this->upsertPivot($empresaId, $entidad, $localId, $tangoId, 'error', mb_substr($e->getMessage(), 0, 255), 'push', 'error', [
                    'payload_enviado' => $updatePayload,
                    'snapshot_tango' => $tangoData,
                ]);
            }

            $message = $e->getMessage();
            if (str_starts_with($message, 'Tango rechazó el push:') || str_starts_with($message, 'Fallo al empujar a Tango:')) {
                throw new RuntimeException($message);
            }

            throw new RuntimeException('Fallo al empujar a Tango: ' . $message);
        }
    }

    /**
     * Busca el ID numérico de Tango para una entidad local usando su código/SKU.
     * Recorre varias páginas para evitar falsos pendientes en catálogos grandes.
     */
    private function resolveTangoIdBySku(\App\Modules\Tango\TangoApiClient $apiClient, string $entidad, array $localData, int $pageSize = 500): ?int
    {
        try {
            $isArticulo = $entidad === 'articulo';
            $needle = $isArticulo
                ? trim((string) ($localData['codigo_externo'] ?? ''))
                : trim((string) ($localData['codigo_tango'] ?? ''));
            if ($needle === '') {
                return null;
            }

            $process = $isArticulo ? 87 : 2117;
            $codeKey = $isArticulo ? 'COD_STA11' : 'COD_GVA14';
            $idKey = $isArticulo ? 'ID_STA11' : 'ID_GVA14';
            $pageIndex = 0;
            $seenFirstIds = [];

            while (true) {
                $res = $apiClient->getRawClient()->get('Get', [
                    'process'   => $process,
                    'pageSize'  => $pageSize,
                    'pageIndex' => $pageIndex,
                    'view'      => ''
                ]);

                $list = $res['data']['resultData']['list'] ?? $res['resultData']['list'] ?? [];
                if ($list === []) {
                    break;
                }

                $firstId = isset($list[0][$idKey]) ? (string) $list[0][$idKey] : '';
                if ($firstId !== '') {
                    if (isset($seenFirstIds[$firstId])) {
                        break;
                    }
                    $seenFirstIds[$firstId] = true;
                }

                foreach ($list as $item) {
                    if (isset($item[$codeKey]) && trim((string) $item[$codeKey]) === $needle) {
                        return isset($item[$idKey]) ? (int) $item[$idKey] : null;
                    }
                }

                if (count($list) < $pageSize || $pageIndex >= 10) {
                    break;
                }

                $pageIndex++;
            }
        } catch (\Exception $e) {
            // Si falla el lookup, retornamos null para que el caller maneje el error
        }

        return null;
    }

    /**
     * Auditoría Pull de Clientes: hace Match Suave por codigo_tango y populea rxn_sync_status.
     */
    public function auditarClientes(int $empresaId): array
    {
        $stmt = $this->db->prepare("SELECT id, codigo_tango, razon_social FROM crm_clientes WHERE empresa_id = ?");
        $stmt->execute([$empresaId]);
        $locales = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tangoService = TangoService::forCrm();
        $apiClient    = $tangoService->getApiClient();

        $clientesTango = [];
        $res = $apiClient->getRawClient()->get('Get', ['process' => 2117, 'pageSize' => 500]);
        $list = $res['data']['resultData']['list'] ?? $res['resultData']['list'] ?? [];
        foreach ($list as $t) {
            if (!empty($t['COD_GVA14'])) {
                $clientesTango[trim((string)$t['COD_GVA14'])] = $t;
            }
        }

        $log = ['vinculados' => 0, 'pendientes' => 0, 'errores' => 0];

        foreach ($locales as $local) {
            $codigo   = trim((string)($local['codigo_tango'] ?? ''));
            $tangoRef = $clientesTango[$codigo] ?? null;

            if ($tangoRef && isset($tangoRef['ID_GVA14'])) {
                $this->upsertPivot($empresaId, 'cliente', (int)$local['id'], (int)$tangoRef['ID_GVA14'], 'vinculado', null, 'pull', 'ok', $tangoRef);
                $log['vinculados']++;
            } else {
                $this->upsertPivot($empresaId, 'cliente', (int)$local['id'], null, 'pendiente', "No existe el código {$codigo} en Tango", 'pull', 'error', null);
                $log['pendientes']++;
            }
        }

        return $log;
    }

    /**
     * Pull desde Tango hacia el registro local.
     * Trae los datos del Tango ID resuelto y actualiza la tabla local.
     */
    public function pullFromTangoByLocalId(int $empresaId, int $localId, string $entidad): array
    {
        $tangoService = TangoService::forCrm();
        $apiClient    = $tangoService->getApiClient();

        // 1. Resolver Tango ID via pivot existente o match suave por código/SKU
        $stmtPivot = $this->db->prepare(
            "SELECT tango_id FROM rxn_sync_status WHERE empresa_id = ? AND entidad = ? AND local_id = ? AND tango_id IS NOT NULL LIMIT 1"
        );
        $stmtPivot->execute([$empresaId, $entidad, $localId]);
        $pivotRow = $stmtPivot->fetch(PDO::FETCH_ASSOC);

        $tangoId = ($pivotRow && !empty($pivotRow['tango_id'])) ? (int) $pivotRow['tango_id'] : null;

        if ($tangoId === null) {
            $table = $entidad === 'cliente' ? 'crm_clientes' : 'crm_articulos';
            $stmtLocal = $this->db->prepare("SELECT * FROM {$table} WHERE id = ? AND empresa_id = ? LIMIT 1");
            $stmtLocal->execute([$localId, $empresaId]);
            $localData = $stmtLocal->fetch(PDO::FETCH_ASSOC);

            if (!$localData) {
                throw new RuntimeException("No se encontró el registro local para hacer Pull.");
            }

            $tangoId = $this->resolveTangoIdBySku($apiClient, $entidad, $localData);
            if ($tangoId === null) {
                $sku = $entidad === 'cliente' ? ($localData['codigo_tango'] ?? '') : ($localData['codigo_externo'] ?? '');
                $this->upsertPivot($empresaId, $entidad, $localId, null, 'pendiente', "No existe el codigo {$sku} en Tango", 'pull', 'error', null);
                throw new RuntimeException("No se encontró correspondencia en Tango para este registro. Verifique que el código externo coincida.");
            }
        }

        $tangoData = $entidad === 'cliente'
            ? $apiClient->getClienteById($tangoId)
            : $apiClient->getArticuloById($tangoId);

        if (!$tangoData) {
            throw new RuntimeException("No se encontró el registro en Tango (ID: {$tangoId}).");
        }

        // 2. Actualizar local
        $localActualizado = $this->extractLocalFieldsFromTango($entidad, $tangoData);

        if ($entidad === 'articulo') {
            $sql  = "UPDATE crm_articulos SET nombre = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ?";
            $args = [
                (string) ($localActualizado['nombre'] ?? ''),
                $localId,
                $empresaId,
            ];
        } else {
            $sql  = "UPDATE crm_clientes
                     SET razon_social = ?, documento = ?, email = ?, telefono = ?, direccion = ?, activo = ?, fecha_ultima_sync = NOW(), updated_at = NOW()
                     WHERE id = ? AND empresa_id = ?";
            $args = [
                $localActualizado['razon_social'] ?? null,
                $localActualizado['documento'] ?? null,
                $localActualizado['email'] ?? null,
                $localActualizado['telefono'] ?? null,
                $localActualizado['direccion'] ?? null,
                (int) ($localActualizado['activo'] ?? 1),
                $localId,
                $empresaId,
            ];
        }

        $this->db->prepare($sql)->execute($args);
        $this->upsertPivot($empresaId, $entidad, $localId, $tangoId, 'vinculado', null, 'pull', 'ok', [
            'snapshot_tango' => $tangoData,
            'local_actualizado' => $localActualizado,
        ]);

        return [
            'tango_id'       => $tangoId,
            'snapshot_tango' => $tangoData,
            'local_actualizado' => $localActualizado,
        ];
    }

    private function extractLocalFieldsFromTango(string $entidad, array $tangoData): array
    {
        if ($entidad === 'articulo') {
            return [
                'nombre' => mb_substr(trim((string)($tangoData['DESCRIPCIO'] ?? '')), 0, 255),
            ];
        }

        return [
            'razon_social' => $this->nullableTrim($this->firstNonEmpty($tangoData, ['RAZON_SOCI', 'RAZON_SOCIAL', 'NOMBRE', 'NOMBRE_CLIENTE']), 200),
            'documento' => $this->nullableTrim($this->firstNonEmpty($tangoData, ['CUIT', 'N_COMP', 'NRO_DOC', 'DOCUMENTO']), 50),
            'email' => $this->nullableTrim($this->firstNonEmpty($tangoData, ['E_MAIL', 'EMAIL', 'MAIL']), 150),
            'telefono' => $this->nullableTrim($this->firstNonEmpty($tangoData, ['TELEFONO_1', 'TELEFONO', 'TEL']), 80),
            'direccion' => $this->nullableTrim($this->firstNonEmpty($tangoData, ['DOMICILIO', 'DIRECCION']), 255),
            'activo' => $this->resolveClienteActivo($tangoData),
        ];
    }

    private function isSuccessfulConnectResponse(array $response): bool
    {
        $status = (int) ($response['status'] ?? 500);
        if ($status < 200 || $status >= 300) {
            return false;
        }

        $data = $response['data'] ?? null;
        if (is_array($data) && array_key_exists('succeeded', $data)) {
            return (bool) $data['succeeded'];
        }

        return true;
    }

    private function extractConnectError(array $response): string
    {
        $data = $response['data'] ?? null;
        if (!is_array($data)) {
            return 'Respuesta inválida de Tango Connect.';
        }

        foreach (['message', 'Messages', 'errors', 'error'] as $key) {
            if (!array_key_exists($key, $data) || $data[$key] === null) {
                continue;
            }

            $value = $data[$key];
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_array($value)) {
                $json = json_encode($value, JSON_UNESCAPED_UNICODE);
                if (is_string($json) && $json !== '') {
                    return $json;
                }
            }
        }

        return 'Tango Connect devolvió una respuesta no exitosa.';
    }

    private function firstNonEmpty(array $item, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $item) && $item[$key] !== null && trim((string) $item[$key]) !== '') {
                return $item[$key];
            }
        }

        return null;
    }

    private function resolveClienteActivo(array $item): int
    {
        if (array_key_exists('ACTIVO', $item)) {
            return $this->isTruthy($item['ACTIVO']) ? 1 : 0;
        }

        if (array_key_exists('HABILITADO', $item)) {
            return $this->isTruthy($item['HABILITADO']) ? 1 : 0;
        }

        if (array_key_exists('INHABILITADO', $item)) {
            return $this->isTruthy($item['INHABILITADO']) ? 0 : 1;
        }

        return 1;
    }

    private function isTruthy(mixed $value): bool
    {
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 't', 's', 'si', 'y', 'yes'], true);
    }

    private function nullableTrim(mixed $value, int $maxLen): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLen);
    }

    /**
     * Lista los PDS enviados a Tango con éxito para pintar el tab Pedidos de RxnSync.
     * Se incluyen también los que no tienen tango_id_gva21 resuelto todavía (aparecen
     * con badge "Sin sync" y el botón de sync los resuelve on-the-fly desde el JSON).
     */
    public function getPedidosSyncList(int $empresaId, array $advancedFilters = []): array
    {
        $columnMap = [
            'numero'              => 'ps.numero',
            'cliente'             => 'ps.cliente_nombre',
            'tango_nro_pedido'    => 'ps.tango_nro_pedido',
            'tango_estado'        => 'ps.tango_estado',
            'tango_estado_sync_at'=> 'ps.tango_estado_sync_at',
        ];

        [$advSql, $advParams] = \App\Core\AdvancedQueryFilter::build($advancedFilters, $columnMap);

        $sql = "SELECT ps.id, ps.numero, ps.cliente_nombre, ps.tango_id_gva21,
                       ps.tango_nro_pedido, ps.tango_estado, ps.tango_estado_sync_at,
                       ps.fecha_inicio, ps.fecha_finalizado, ps.tango_sync_status,
                       ps.nro_pedido
                FROM crm_pedidos_servicio ps
                WHERE ps.empresa_id = :empresa_id
                  AND ps.deleted_at IS NULL
                  AND ps.tango_sync_status = 'success'";

        if ($advSql !== '') {
            $sql .= " AND {$advSql}";
        }

        $sql .= " ORDER BY ps.numero DESC";

        $params = array_merge(['empresa_id' => $empresaId], $advParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Extrae el ID_GVA21 desde el JSON tango_sync_response ya guardado. Tango Connect
     * devuelve el ID del pedido creado en `data.savedId` (int escalar).
     * Fallback por si algún wrapper distinto lo expone en otra clave.
     */
    private function extractTangoIdFromResponseJson(?string $responseJson): ?int
    {
        if ($responseJson === null || trim($responseJson) === '') {
            return null;
        }

        $data = json_decode($responseJson, true);
        if (!is_array($data)) {
            return null;
        }

        $candidates = [
            $data['data']['savedId']        ?? null,
            $data['data']['value']['ID_GVA21'] ?? null,
            $data['value']['ID_GVA21']      ?? null,
            $data['ID_GVA21']               ?? null,
            $data['savedId']                ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        return null;
    }

    /**
     * Resuelve y persiste tango_id_gva21 desde el JSON ya guardado (sin pegarle a Tango).
     * Retorna el ID_GVA21 resuelto o null si no se pudo extraer.
     */
    private function ensureTangoIdGva21(int $empresaId, array $pedidoRow): ?int
    {
        if (!empty($pedidoRow['tango_id_gva21'])) {
            return (int) $pedidoRow['tango_id_gva21'];
        }

        $stmt = $this->db->prepare(
            'SELECT tango_sync_response FROM crm_pedidos_servicio
             WHERE id = ? AND empresa_id = ? LIMIT 1'
        );
        $stmt->execute([(int) $pedidoRow['id'], $empresaId]);
        $json = $stmt->fetchColumn();

        $resolved = $this->extractTangoIdFromResponseJson($json !== false ? (string) $json : null);
        if ($resolved === null) {
            return null;
        }

        $upd = $this->db->prepare(
            'UPDATE crm_pedidos_servicio
             SET tango_id_gva21 = :gva21
             WHERE id = :id AND empresa_id = :empresa_id AND tango_id_gva21 IS NULL'
        );
        $upd->execute([
            ':gva21'      => $resolved,
            ':id'         => (int) $pedidoRow['id'],
            ':empresa_id' => $empresaId,
        ]);

        return $resolved;
    }

    /**
     * Pull masivo de estados de pedidos desde Tango Connect (process=19845).
     *
     * Estrategia: en lugar de hacer N requests GetById (una por PDS), paginamos el
     * listado Get?process=19845 (hasta 500 por página) y armamos un map por ID_GVA21.
     * Mucho más eficiente y consistente con el patrón de auditarArticulos/auditarClientes.
     */
    public function syncPedidosEstados(int $empresaId): array
    {
        // Arrancamos con TODOS los PDS con tango_sync_status='success' (incluye los que
        // todavía no tienen tango_id_gva21 resuelto). Para esos intentamos resolverlo
        // desde el JSON antes del pull — así cubrimos históricos sin tener que correr
        // backfill manual.
        $stmt = $this->db->prepare(
            "SELECT id, numero, tango_id_gva21, tango_sync_response
             FROM crm_pedidos_servicio
             WHERE empresa_id = ? AND deleted_at IS NULL AND tango_sync_status = 'success'"
        );
        $stmt->execute([$empresaId]);
        $locales = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($locales === []) {
            return ['total' => 0, 'actualizados' => 0, 'sin_match' => 0, 'errores' => 0, 'resueltos_id' => 0, 'detalles' => []];
        }

        // Auto-resolve de tango_id_gva21 faltantes desde el JSON guardado (sin Tango).
        $resueltosId = 0;
        foreach ($locales as $idx => $row) {
            if (empty($row['tango_id_gva21'])) {
                $resolved = $this->ensureTangoIdGva21($empresaId, $row);
                if ($resolved !== null) {
                    $locales[$idx]['tango_id_gva21'] = $resolved;
                    $resueltosId++;
                }
            }
        }

        // Filtramos los que NO pudimos resolver — van al contador "sin_match" al final.
        $sinIdResuelto = array_values(array_filter($locales, static fn ($r) => empty($r['tango_id_gva21'])));
        $locales       = array_values(array_filter($locales, static fn ($r) => !empty($r['tango_id_gva21'])));

        $mapLocal = [];
        foreach ($locales as $row) {
            $mapLocal[(int) $row['tango_id_gva21']] = (int) $row['id'];
        }

        $tangoService = \App\Modules\Tango\TangoService::forCrm();
        $apiClient    = $tangoService->getApiClient();
        $rawClient    = $apiClient->getRawClient();

        // Estrategia: en lugar de paginar el listado entero (que puede tener decenas de
        // miles de pedidos y obligar a recorrer muchas páginas), consultamos directamente
        // por los ID_GVA21 que nos interesan usando GetByFilter?process=19845 con un
        // filtroSql `WHERE ID_GVA21 IN (...)`. Batches de 100 IDs para evitar URLs gigantes.
        $idsToQuery = array_map(static fn ($r) => (int) $r['tango_id_gva21'], $locales);
        $batches    = array_chunk($idsToQuery, 100);
        $mapTango   = [];

        foreach ($batches as $batch) {
            if ($batch === []) {
                continue;
            }
            $filtroSql = 'WHERE ID_GVA21 IN (' . implode(',', $batch) . ')';
            $endpoint  = 'GetByFilter?process=19845&view=&filtroSql=' . rawurlencode($filtroSql);

            try {
                $res = $rawClient->get($endpoint);
            } catch (\Throwable $e) {
                throw new RuntimeException('Falló GetByFilter contra Tango: ' . $e->getMessage());
            }

            // Ojo shape: GetByFilter devuelve `data.list`, NO `data.resultData.list` (que usa Get).
            $list = $res['data']['list']
                ?? $res['data']['resultData']['list']
                ?? $res['resultData']['list']
                ?? [];
            foreach ($list as $item) {
                if (!isset($item['ID_GVA21'])) {
                    continue;
                }
                $gva21 = (int) $item['ID_GVA21'];
                $mapTango[$gva21] = [
                    'estado'     => isset($item['ESTADO']) ? (int) $item['ESTADO'] : null,
                    'nro_pedido' => isset($item['NRO_PEDIDO']) ? trim((string) $item['NRO_PEDIDO']) : null,
                ];
            }
        }

        $actualizados = 0;
        $sinMatch = 0;
        $errores = 0;
        $detalles = [];

        $update = $this->db->prepare(
            'UPDATE crm_pedidos_servicio
             SET tango_estado = :estado,
                 tango_nro_pedido = COALESCE(:nro_pedido, tango_nro_pedido),
                 tango_estado_sync_at = NOW()
             WHERE id = :id AND empresa_id = :empresa_id'
        );

        foreach ($locales as $local) {
            $gva21 = (int) $local['tango_id_gva21'];
            $snap  = $mapTango[$gva21] ?? null;

            if ($snap === null || $snap['estado'] === null) {
                $sinMatch++;
                $detalles[] = "PDS #{$local['numero']} (ID_GVA21 {$gva21}): no encontrado en Tango.";
                continue;
            }

            try {
                $update->execute([
                    ':estado'       => $snap['estado'],
                    ':nro_pedido'   => $snap['nro_pedido'],
                    ':id'           => (int) $local['id'],
                    ':empresa_id'   => $empresaId,
                ]);
                $actualizados++;
            } catch (\Throwable $e) {
                $errores++;
                $detalles[] = "PDS #{$local['numero']}: error al persistir estado — " . $e->getMessage();
            }
        }

        foreach ($sinIdResuelto as $local) {
            $detalles[] = "PDS #{$local['numero']}: no se pudo extraer ID_GVA21 del response Tango guardado.";
        }

        return [
            'total'        => count($locales) + count($sinIdResuelto),
            'actualizados' => $actualizados,
            'sin_match'    => $sinMatch + count($sinIdResuelto),
            'errores'      => $errores,
            'resueltos_id' => $resueltosId,
            'detalles'     => $detalles,
        ];
    }

    /**
     * Pull individual de estado de un PDS — consulta GetById?process=19845&id=ID_GVA21.
     * Útil como botón "refrescar" por fila. Si el PDS todavía no tiene tango_id_gva21
     * resuelto, intenta extraerlo del JSON antes del pull.
     */
    public function syncPedidoEstadoByLocalId(int $empresaId, int $pedidoServicioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, numero, tango_id_gva21, tango_sync_status
             FROM crm_pedidos_servicio
             WHERE id = ? AND empresa_id = ? AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$pedidoServicioId, $empresaId]);
        $local = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($local === false) {
            throw new RuntimeException('El PDS no existe o no pertenece a la empresa activa.');
        }

        if (empty($local['tango_id_gva21'])) {
            if (($local['tango_sync_status'] ?? '') !== 'success') {
                throw new RuntimeException('Este PDS todavía no fue enviado a Tango con éxito.');
            }

            $resolved = $this->ensureTangoIdGva21($empresaId, $local);
            if ($resolved === null) {
                throw new RuntimeException('No se pudo extraer el ID Tango del response guardado. Reenvialo a Tango desde el form del PDS.');
            }

            $local['tango_id_gva21'] = $resolved;
        }

        $tangoService = \App\Modules\Tango\TangoService::forCrm();
        $apiClient    = $tangoService->getApiClient();
        $rawClient    = $apiClient->getRawClient();

        $res = $rawClient->get('GetById', [
            'process' => 19845,
            'id'      => (int) $local['tango_id_gva21'],
        ]);

        $value = $res['data']['value'] ?? $res['value'] ?? null;
        if (!is_array($value)) {
            throw new RuntimeException('Tango no devolvió el pedido con ID_GVA21 ' . (int) $local['tango_id_gva21'] . '.');
        }

        $estado = isset($value['ESTADO']) ? (int) $value['ESTADO'] : null;
        $nroPedido = isset($value['NRO_PEDIDO']) ? trim((string) $value['NRO_PEDIDO']) : null;

        if ($estado === null) {
            throw new RuntimeException('El response de Tango no trajo el campo ESTADO.');
        }

        $update = $this->db->prepare(
            'UPDATE crm_pedidos_servicio
             SET tango_estado = :estado,
                 tango_nro_pedido = COALESCE(:nro_pedido, tango_nro_pedido),
                 tango_estado_sync_at = NOW()
             WHERE id = :id AND empresa_id = :empresa_id'
        );
        $update->execute([
            ':estado'     => $estado,
            ':nro_pedido' => $nroPedido,
            ':id'         => (int) $local['id'],
            ':empresa_id' => $empresaId,
        ]);

        return [
            'id'         => (int) $local['id'],
            'numero'     => (int) $local['numero'],
            'tango_id_gva21' => (int) $local['tango_id_gva21'],
            'estado'     => $estado,
            'nro_pedido' => $nroPedido,
            'snapshot_tango' => $value,
        ];
    }
}
