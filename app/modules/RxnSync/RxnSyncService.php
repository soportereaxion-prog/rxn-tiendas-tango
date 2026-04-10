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
}
