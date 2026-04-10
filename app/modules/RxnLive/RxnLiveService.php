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
                'razon_social' => ['label' => 'Cliente', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'codigo_articulo' => ['label' => 'Cód. Artículo', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'articulo_factura' => ['label' => 'Artículo', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'nro_pedido_tango' => ['label' => 'Nro. Pedido (Tango)', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'clasificacion' => ['label' => 'Clasificación', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
                'solicitante' => ['label' => 'Solicitante', 'type' => 'string', 'groupable' => true, 'aggregatable' => false],
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

    public function getUserViews(int $userId, string $datasetKey): array {
        try {
            $db = \App\Core\Database::getConnection();
            $stmt = $db->prepare("SELECT id, nombre, config FROM rxn_live_vistas WHERE usuario_id = ? AND dataset = ? ORDER BY nombre ASC");
            $stmt->execute([$userId, $datasetKey]);
            $res = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $userViews = [];
            foreach ($res as $v) {                
                $v['config'] = json_decode($v['config'], true);
                $userViews[] = $v;
            }
            
            $systemViews = $this->getSystemDefaultViews($datasetKey);
            return array_merge($systemViews, $userViews);
        } catch (\Exception $e) {
            // Si la tabla aun no existe (migración no corrida)
            return $this->getSystemDefaultViews($datasetKey);
        }
    }

    public function saveUserView(int $userId, string $datasetKey, string $nombre, array $config, ?int $viewId = null): int {
        $db = \App\Core\Database::getConnection();
        
        // Auto-crear la tabla para facilitar el entorno de testing/produ local sin OTA
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
            $stmt = $db->prepare("INSERT INTO rxn_live_vistas (usuario_id, dataset, nombre, config) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $userId, 
                $datasetKey, 
                $nombre, 
                json_encode($config)
            ]);
            return (int)$db->lastInsertId();
        }
    }
}
