<?php

declare(strict_types=1);

namespace App\Modules\RxnLive;

use App\Core\Database;

class RxnLiveService
{
    private array $datasets = [
        'ventas_historico' => [
            'name' => 'Ventas Histórico',
            'view' => 'RXN_LIVE_VW_VENTAS',
            'description' => 'Evolución transaccional e importes facturados por fecha.',
            'chart_label' => 'Total Ventas ($)',
            'chart_group_col' => 'fecha',
            'chart_val_col' => 'total',
            'chart_type' => 'bar',
            'pivot_metadata' => [
                'fecha' => ['label' => 'Fecha', 'type' => 'date', 'groupable' => true, 'aggregatable' => false],
                'estado_sincronizacion' => ['label' => 'Estado Sync', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'cliente_nombre' => ['label' => 'Cliente', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'total' => ['label' => 'Total ($)', 'type' => 'numeric', 'groupable' => false, 'aggregatable' => true],
                'cantidad' => ['label' => 'Tickets', 'type' => 'numeric', 'groupable' => false, 'aggregatable' => true]
            ]
        ],
        'ventas_estados' => [
            'name' => 'Integración Tango',
            'view' => 'RXN_LIVE_VW_VENTAS',
            'description' => 'Métricas de pedidos según su estado de sincronización hacia Tango.',
            'chart_label' => 'Pedidos por Estado',
            'chart_group_col' => 'estado_sincronizacion',
            'chart_val_col' => 'cantidad',
            'chart_type' => 'doughnut',
            'pivot_metadata' => [
                'estado_sincronizacion' => ['label' => 'Estado', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'fecha' => ['label' => 'Fecha', 'type' => 'date', 'groupable' => true, 'aggregatable' => false],
                'cantidad' => ['label' => 'Tickets', 'type' => 'numeric', 'groupable' => false, 'aggregatable' => true],
                'total' => ['label' => 'Monto Bruto ($)', 'type' => 'numeric', 'groupable' => false, 'aggregatable' => true]
            ]
        ],
        'clientes' => [
            'name' => 'Análisis de Clientes',
            'view' => 'RXN_LIVE_VW_CLIENTES',
            'description' => 'Registro y análisis de clientes de la cartera CRM.',
            'chart_label' => 'Clientes por Estado',
            'chart_group_col' => 'estado',
            'chart_val_col' => 'cantidad',
            'chart_type' => 'doughnut',
            'pivot_metadata' => [
                'estado' => ['label' => 'Estado', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'fecha_registro' => ['label' => 'Fecha Registro', 'type' => 'date', 'groupable' => true, 'aggregatable' => false],
                'cantidad' => ['label' => 'Cant. Clientes', 'type' => 'numeric', 'groupable' => false, 'aggregatable' => true]
            ]
        ],
        'pedidos_servicio' => [
            'name' => 'Pedidos de Servicio (Tiempos)',
            'view' => 'RXN_LIVE_VW_PEDIDOS_SERVICIO',
            'description' => 'Métricas CRM sobre horas imputadas, técnicos, tipos de servicio y facturación.',
            'chart_label' => 'Total Horas PDS',
            'chart_group_col' => 'usuario',
            'chart_val_col' => 'totalpds',
            'chart_type' => 'bar',
            'pivot_metadata' => [
                'fecha' => ['label' => 'Fecha', 'type' => 'date', 'groupable' => true, 'aggregatable' => false],
                'usuario' => ['label' => 'Usuario', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'id_tecnico' => ['label' => 'ID Técnico', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'razon_social' => ['label' => 'Cliente', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'codigo_articulo' => ['label' => 'Cód. Artículo', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'articulo_factura' => ['label' => 'Artículo', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'cod_tango' => ['label' => 'Cód. Tango', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'nro_pedido_tango' => ['label' => 'Nro. Pedido (Tango)', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'clasificacion' => ['label' => 'Clasificación', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'solicitante' => ['label' => 'Solicitante', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'tango_estado_label' => ['label' => 'Estado Tango', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'tango_estado_sync_at' => ['label' => 'Última Sync Tango', 'type' => 'datetime', 'groupable' => true, 'aggregatable' => false],
                'totalpds' => ['label' => 'Tiempo (Hs)', 'type' => 'numeric', 'groupable' => false, 'aggregatable' => true],
                'cantidad' => ['label' => 'Cant. PDS', 'type' => 'numeric', 'groupable' => false, 'aggregatable' => true]
            ]
        ]
    ];

    public function __construct()
    {
        $this->ensureViewsExist();
    }

    private function ensureViewsExist(): void
    {
        // La recreación de vistas se maneja exclusivamente via migraciones versionadas.
        // Ver: database/migrations/2026_04_05_fix_rxn_live_vw_clientes.php
        // Este método se conserva para extensión futura si se requiere validación crítica.
    }

    public function getAvailableDatasets(): array
    {
        return $this->datasets;
    }

    public function isValidDataset(string $key): bool
    {
        return isset($this->datasets[$key]);
    }

    public function getDatasetInfo(string $key): array
    {
        return $this->datasets[$key];
    }

    private function buildQuery(string $viewName, array $filters): array
    {
        $where = [];
        $params = [];
        
        $advancedFilters = $filters['f'] ?? []; // rxn-advanced-filters.js sends 'f'
        unset($filters['f']);

        foreach ($filters as $key => $value) {
            if (is_array($value) || $value === '' || $value === null) continue;
            // Clean key
            $cleanKey = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$key);
            if (!$cleanKey) continue;
            
            $where[] = "$cleanKey LIKE :$cleanKey";
            $params[":$cleanKey"] = "%" . ltrim((string)$value, '%') . "%"; // avoid double %%
        }
        
        if (!empty($advancedFilters) && is_array($advancedFilters)) {
            $columnMap = [];
            foreach ($advancedFilters as $k => $v) {
                $cleanCol = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$k);
                if ($cleanCol !== '') {
                    $columnMap[$cleanCol] = "`$cleanCol`";
                }
            }

            [$advSql, $advParams] = \App\Core\AdvancedQueryFilter::build($advancedFilters, $columnMap);
            if ($advSql !== '') {
                $where[] = "($advSql)";
                $params = array_merge($params, $advParams);
            }
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        return [$whereClause, $params];
    }

    public function getDatasetData(string $key, array $filters, int $page, int $limit): array
    {
        $info = $this->datasets[$key];
        $view = $info['view'];
        $offset = ($page - 1) * $limit;
        
        $sortCol = $filters['sort_col'] ?? null;
        $sortAsc = isset($filters['sort_asc']) ? (bool)$filters['sort_asc'] : true;
        unset($filters['sort_col'], $filters['sort_asc']);

        [$whereClause, $params] = $this->buildQuery($view, $filters);
        
        $orderBy = '';
        if ($sortCol) {
            $cleanSortCol = preg_replace('/[^a-zA-Z0-9_]/', '', $sortCol);
            if ($cleanSortCol) {
                $dir = $sortAsc ? 'ASC' : 'DESC';
                $orderBy = "ORDER BY `$cleanSortCol` $dir";
            }
        }

        $sql = "SELECT * FROM $view $whereClause $orderBy LIMIT $limit OFFSET $offset";
        
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $res = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return is_array($res) ? $res : [];
        } catch (\Exception $e) {
            error_log("RxnLive Error [getDatasetData]: " . $e->getMessage());
            return [];
        }
    }

    public function getDatasetCount(string $key, array $filters): int
    {
        $info = $this->datasets[$key];
        $view = $info['view'];
        
        [$whereClause, $params] = $this->buildQuery($view, $filters);
        
        $sql = "SELECT COUNT(*) as total FROM $view $whereClause";
        
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $res = $stmt->fetch();
            return (int)($res['total'] ?? 0);
        } catch (\Exception $e) {
            error_log("RxnLive Error [getDatasetCount]: " . $e->getMessage());
            return 0;
        }
    }



    public function getSystemDefaultViews(string $datasetKey): array {
        if ($datasetKey === 'ventas_historico') {
            return [
                [
                    'id' => 'default_vh_1',
                    'nombre' => '⭐ Vista Operativa (por Estado)',
                    'config' => [
                        'flatSortCol' => null,
                        'flatSortAsc' => true,
                        'flatFilters' => [],
                        'flatDiscreteFilters' => [],
                        'tab_activo' => '#pivot-tab',
                        'chartConfig' => [
                            'groupCol' => 'estado_sincronizacion',
                            'valCol' => 'total',
                            'type' => 'doughnut',
                            'op' => 'SUM'
                        ],
                        'pivotState' => [
                            'rows' => [
                                ['field' => 'estado_sincronizacion', 'dateFmt' => ''],
                                ['field' => 'cliente_nombre', 'dateFmt' => '']
                            ],
                            'cols' => [],
                            'vals' => [
                                ['field' => 'total', 'op' => 'SUM'],
                                ['field' => 'cantidad', 'op' => 'COUNT']
                            ]
                        ],
                        'pivotOptions' => [
                            'sortDesc' => true,
                            'rowTot' => true,
                            'colTot' => true
                        ]
                    ]
                ]
            ];
        }
        return [];
    }

    /**
     * Devuelve las vistas de un dataset para el usuario actual, con scope de lectura por empresa:
     * todos los usuarios de la misma empresa ven las mismas vistas. El ownership para editar/borrar
     * sigue siendo por `usuario_id` (guard aplicado en saveUserView/deleteUserView).
     *
     * Cada vista incluye `usuario_id` y `usuario_nombre` para que el frontend pueda distinguir
     * entre vistas propias y ajenas (solo el dueño ve los botones de guardar/eliminar).
     */
    public function getUserViews(int $empresaId, string $datasetKey): array {
        try {
            $db = \App\Core\Database::getConnection();
            $stmt = $db->prepare("
                SELECT v.id, v.nombre, v.config, v.usuario_id,
                       COALESCE(u.nombre, '') AS usuario_nombre
                  FROM rxn_live_vistas v
             LEFT JOIN usuarios u ON u.id = v.usuario_id
                 WHERE v.empresa_id = ? AND v.dataset = ?
              ORDER BY v.nombre ASC
            ");
            $stmt->execute([$empresaId, $datasetKey]);
            $res = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $userViews = [];
            foreach ($res as $v) {
                $v['config'] = json_decode($v['config'], true);
                $v['usuario_id'] = (int)$v['usuario_id'];
                $userViews[] = $v;
            }

            $systemViews = $this->getSystemDefaultViews($datasetKey);
            return array_merge($systemViews, $userViews);
        } catch (\Exception $e) {
            // Si la tabla aun no existe (migración no corrida)
            return $this->getSystemDefaultViews($datasetKey);
        }
    }

    /**
     * Elimina una vista del usuario actual.
     * Guard de ownership: solo borra si `usuario_id` matchea al user que pidió el delete.
     * Para delete cross-user (admin), usar `deleteVistaAdmin()`.
     */
    public function deleteUserView(int $userId, int $viewId): bool {
        try {
            $db = \App\Core\Database::getConnection();
            $stmt = $db->prepare("DELETE FROM rxn_live_vistas WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$viewId, $userId]);
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * [ADMIN] Lista todas las vistas de todos los usuarios, con datos del dueño.
     */
    public function getAllVistasAdmin(): array {
        try {
            $db = \App\Core\Database::getConnection();
            $sql = "SELECT v.id, v.usuario_id, v.dataset, v.nombre, v.config, v.created_at,
                           u.nombre AS usuario_nombre, u.email AS usuario_email, u.empresa_id
                      FROM rxn_live_vistas v
                      LEFT JOIN usuarios u ON u.id = v.usuario_id
                  ORDER BY v.dataset ASC, v.created_at DESC";
            $res = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($res as &$row) {
                // Dejamos el config como string crudo — el admin lo muestra formateado en el front.
                // Si se querés el array, usá json_decode donde corresponda.
                $row['config_preview'] = json_decode($row['config'], true);
            }
            return $res;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * [ADMIN] Obtiene una vista por ID (sin filtrar por usuario).
     */
    public function getVistaByIdAdmin(int $viewId): ?array {
        try {
            $db = \App\Core\Database::getConnection();
            $stmt = $db->prepare("SELECT v.id, v.usuario_id, v.dataset, v.nombre, v.config, v.created_at,
                                         u.nombre AS usuario_nombre, u.email AS usuario_email
                                    FROM rxn_live_vistas v
                               LEFT JOIN usuarios u ON u.id = v.usuario_id
                                   WHERE v.id = ? LIMIT 1");
            $stmt->execute([$viewId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * [ADMIN] Elimina una vista por ID (sin filtrar por usuario).
     * Pensado para destrabar configs rotos que tumban la UI.
     */
    public function deleteVistaAdmin(int $viewId): bool {
        try {
            $db = \App\Core\Database::getConnection();
            $stmt = $db->prepare("DELETE FROM rxn_live_vistas WHERE id = ?");
            $stmt->execute([$viewId]);
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * [ADMIN] Importa una vista desde un array. El array debe tener las claves
     * `dataset`, `nombre`, `config`. Devuelve el ID insertado, o lanza Exception.
     * Si `usuarioId` es null, usa el usuario actual como dueño.
     */
    public function importVistaAdmin(array $vista, int $usuarioId): int {
        if (empty($vista['dataset']) || !is_string($vista['dataset'])) {
            throw new \InvalidArgumentException("Vista inválida: falta 'dataset'.");
        }
        if (empty($vista['nombre']) || !is_string($vista['nombre'])) {
            throw new \InvalidArgumentException("Vista inválida: falta 'nombre'.");
        }
        if (!isset($vista['config'])) {
            throw new \InvalidArgumentException("Vista inválida: falta 'config'.");
        }

        // Normalizamos config: aceptamos tanto un array como un string JSON.
        if (is_array($vista['config'])) {
            $configJson = json_encode($vista['config']);
        } elseif (is_string($vista['config'])) {
            // Validamos que sea JSON parseable
            $decoded = json_decode($vista['config'], true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException("Vista inválida: 'config' no es JSON válido.");
            }
            $configJson = $vista['config'];
        } else {
            throw new \InvalidArgumentException("Vista inválida: 'config' debe ser array o string JSON.");
        }

        if (!$this->isValidDataset($vista['dataset'])) {
            throw new \InvalidArgumentException("Dataset desconocido: '{$vista['dataset']}'.");
        }

        $db = \App\Core\Database::getConnection();
        // Asegurar que la tabla existe — mismo patrón que saveUserView()
        $db->exec("
            CREATE TABLE IF NOT EXISTS rxn_live_vistas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                dataset VARCHAR(100) NOT NULL,
                nombre VARCHAR(150) NOT NULL,
                config JSON NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_usuario_dataset (usuario_id, dataset)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $stmt = $db->prepare("INSERT INTO rxn_live_vistas (usuario_id, dataset, nombre, config) VALUES (?, ?, ?, ?)");
        $stmt->execute([$usuarioId, $vista['dataset'], $vista['nombre'], $configJson]);
        return (int)$db->lastInsertId();
    }

    /**
     * Guarda una vista. El UPDATE aplica guard de ownership (usuario_id = dueño original);
     * si un usuario ajeno intenta sobrescribir una vista de otro, el UPDATE no afecta filas.
     * La migración 2026_04_20_02 ya garantiza que exista la columna empresa_id.
     */
    public function saveUserView(int $empresaId, int $userId, string $datasetKey, string $nombre, array $config, ?int $viewId = null): int {
        $db = \App\Core\Database::getConnection();

        // Auto-crear la tabla para facilitar el entorno de testing/produ local sin OTA.
        // La migración 2026_04_20_02 agrega empresa_id en instalaciones existentes.
        $db->exec("
            CREATE TABLE IF NOT EXISTS rxn_live_vistas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                empresa_id INT NULL DEFAULT NULL,
                dataset VARCHAR(100) NOT NULL,
                nombre VARCHAR(150) NOT NULL,
                config JSON NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_usuario_dataset (usuario_id, dataset),
                KEY idx_empresa_dataset (empresa_id, dataset)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if ($viewId) {
            $stmt = $db->prepare("UPDATE rxn_live_vistas SET config = ?, nombre = ? WHERE id = ? AND usuario_id = ?");
            $stmt->execute([
                json_encode($config),
                $nombre,
                $viewId,
                $userId
            ]);
            return $viewId;
        } else {
            $stmt = $db->prepare("INSERT INTO rxn_live_vistas (usuario_id, empresa_id, dataset, nombre, config) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $empresaId,
                $datasetKey,
                $nombre,
                json_encode($config)
            ]);
            return (int)$db->lastInsertId();
        }
    }
}
