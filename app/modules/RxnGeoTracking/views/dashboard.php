<?php
/**
 * Dashboard admin de RxnGeoTracking.
 *
 * Recibe de RxnGeoTrackingController::index():
 *   - eventos (array)
 *   - usuariosConEventos (array)
 *   - filters (array)
 *   - page, limit, totalPages, totalItems
 *   - config (array)
 *   - googleMapsApiKey (string|null)
 *   - eventTypeLabels (array)
 */

$pageTitle = 'Geo Tracking - ' . ($_SESSION['empresa_nombre'] ?? 'rxn_suite');
$usePageHeader = true;
$pageHeaderTitle = 'RXN Geo Tracking';
$pageHeaderSubtitle = 'Auditoría de ubicación de inicios de sesión y creación de documentos.';
$pageHeaderIcon = 'bi-geo-alt-fill';
$pageHeaderBackUrl = $dashboardPath ?? '/mi-empresa/crm/dashboard';
$pageHeaderActions = '<a href="/mi-empresa/geo-tracking/config" class="btn btn-outline-secondary btn-sm">'
    . '<i class="bi bi-gear me-1"></i>Configuración</a>';

$events = $eventos ?? [];
$usuarios = $usuariosConEventos ?? [];
$filters = $filters ?? [];
$apiKey = $googleMapsApiKey ?? null;
$eventLabels = $eventTypeLabels ?? [];
$totalItems = $totalItems ?? 0;
$totalPages = $totalPages ?? 1;
$page = $page ?? 1;
$limit = $limit ?? 25;

$dateFrom = htmlspecialchars((string) ($filters['date_from'] ?? ''), ENT_QUOTES, 'UTF-8');
$dateTo = htmlspecialchars((string) ($filters['date_to'] ?? ''), ENT_QUOTES, 'UTF-8');
$userIdFilter = (int) ($filters['user_id'] ?? 0);
$eventTypeFilter = (string) ($filters['event_type'] ?? '');
$entidadTipoFilter = (string) ($filters['entidad_tipo'] ?? '');

// Query string de filtros para propagar en paginación + export
$filterParams = [];
foreach (['date_from', 'date_to', 'user_id', 'event_type', 'entidad_tipo'] as $key) {
    if (!empty($filters[$key])) {
        $filterParams[$key] = (string) $filters[$key];
    }
}
$filterQs = http_build_query($filterParams);

ob_start();
?>
<style>
    .rxn-geo-map { height: 480px; width: 100%; border-radius: 12px; background: var(--bs-tertiary-bg); }
    .rxn-geo-map-placeholder {
        display: flex; align-items: center; justify-content: center;
        color: var(--bs-secondary-color); text-align: center; padding: 2rem;
    }
    .rxn-geo-filters .form-label { font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem; }
    .rxn-geo-event-row small { color: var(--bs-secondary-color); }
    .rxn-geo-event-row.rxn-geo-row-clickable { cursor: pointer; }
    .rxn-geo-event-row.rxn-geo-row-clickable:hover { background: rgba(13, 110, 253, 0.08); }
    .rxn-geo-event-row.rxn-geo-row-clickable td:first-child { border-left: 3px solid transparent; transition: border-color 0.12s ease; }
    .rxn-geo-event-row.rxn-geo-row-clickable:hover td:first-child { border-left-color: #0d6efd; }
    .rxn-geo-event-row.rxn-geo-row-active { background: rgba(13, 110, 253, 0.14) !important; }
    .rxn-geo-event-row.rxn-geo-row-active td:first-child { border-left-color: #0d6efd !important; }
    .rxn-geo-accuracy-badge { font-size: 0.7rem; }
    .rxn-geo-accuracy-gps { background: #198754; color: #fff; }
    .rxn-geo-accuracy-wifi { background: #0d6efd; color: #fff; }
    .rxn-geo-accuracy-ip { background: #6c757d; color: #fff; }
    .rxn-geo-accuracy-denied { background: #ffc107; color: #000; }
    .rxn-geo-accuracy-error { background: #dc3545; color: #fff; }
</style>

<?php if (\App\Core\Flash::has('success')): ?>
    <div class="alert alert-success"><?= htmlspecialchars((string) \App\Core\Flash::get('success')) ?></div>
<?php endif; ?>
<?php if (\App\Core\Flash::has('danger')): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) \App\Core\Flash::get('danger')) ?></div>
<?php endif; ?>

<?php if (!($config['habilitado'] ?? true)): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div>
            <strong>Módulo deshabilitado.</strong>
            Los eventos nuevos no se están registrando. Podés reactivarlo desde
            <a href="/mi-empresa/geo-tracking/config">Configuración</a>.
        </div>
    </div>
<?php endif; ?>

<!-- Filtros -->
<form method="get" action="/mi-empresa/geo-tracking" class="card rxn-geo-filters mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" name="date_from" value="<?= $dateFrom ?>" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" name="date_to" value="<?= $dateTo ?>" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label">Usuario</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">— Todos —</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= (int) $u['user_id'] ?>" <?= $userIdFilter === (int) $u['user_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($u['user_nombre'] ?? 'Usuario #' . (int) $u['user_id'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipo evento</label>
                <select name="event_type" class="form-select form-select-sm">
                    <option value="">— Todos —</option>
                    <?php foreach ($eventLabels as $value => $label): ?>
                        <option value="<?= htmlspecialchars((string) $value) ?>" <?= $eventTypeFilter === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Entidad</label>
                <select name="entidad_tipo" class="form-select form-select-sm">
                    <option value="">— Todas —</option>
                    <option value="presupuesto" <?= $entidadTipoFilter === 'presupuesto' ? 'selected' : '' ?>>Presupuesto</option>
                    <option value="tratativa" <?= $entidadTipoFilter === 'tratativa' ? 'selected' : '' ?>>Tratativa</option>
                    <option value="pds" <?= $entidadTipoFilter === 'pds' ? 'selected' : '' ?>>PDS</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <a href="/mi-empresa/geo-tracking" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </div>
</form>

<!-- Mapa -->
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-map me-2"></i>Mapa de eventos</h6>
        <div class="d-flex gap-2 align-items-center">
            <span id="rxn-geo-map-status" class="small text-muted"></span>
            <a href="/mi-empresa/geo-tracking/export<?= $filterQs !== '' ? '?' . htmlspecialchars($filterQs) : '' ?>"
               class="btn btn-sm btn-outline-success">
                <i class="bi bi-download me-1"></i>Exportar CSV
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if ($apiKey === null): ?>
            <div class="rxn-geo-map rxn-geo-map-placeholder">
                <div>
                    <i class="bi bi-info-circle fs-1 d-block mb-2"></i>
                    <strong>Mapa no disponible.</strong>
                    <p class="mb-0 mt-2">
                        Configurá la variable de entorno <code>GOOGLE_MAPS_API_KEY</code> en el archivo <code>.env</code> del servidor
                        para activar el mapa. El listado y el export siguen funcionando sin mapa.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div id="rxn-geo-map" class="rxn-geo-map"></div>
        <?php endif; ?>
    </div>
</div>

<!-- Listado -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="bi bi-list-ul me-2"></i>Eventos
            <span class="badge bg-secondary ms-2"><?= (int) $totalItems ?></span>
        </h6>
        <div class="d-flex gap-2 align-items-center">
            <label class="small text-muted mb-0">Por página:</label>
            <form method="get" action="/mi-empresa/geo-tracking" class="d-inline">
                <?php foreach ($filterParams as $k => $v): ?>
                    <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                <?php endforeach; ?>
                <select name="limit" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <?php foreach ([25, 50, 100] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $limit === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="rxn-table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Evento</th>
                        <th>Entidad</th>
                        <th>Ubicación</th>
                        <th>IP</th>
                        <th>Precisión</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted p-4">
                                No hay eventos que matcheen los filtros aplicados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($events as $ev): ?>
                            <?php
                            $evType = (string) ($ev['event_type'] ?? '');
                            $evLabel = $eventLabels[$evType] ?? $evType;
                            $accuracySource = (string) ($ev['accuracy_source'] ?? 'ip');
                            $entidadTipo = (string) ($ev['entidad_tipo'] ?? '');
                            $entidadId = $ev['entidad_id'] !== null ? (int) $ev['entidad_id'] : null;
                            $ciudad = trim((string) ($ev['resolved_city'] ?? ''));
                            $pais = trim((string) ($ev['resolved_country'] ?? ''));
                            $ubicacion = $ciudad !== '' ? ($ciudad . ($pais !== '' ? ', ' . $pais : '')) : ($pais !== '' ? $pais : '—');
                            $evLat = isset($ev['lat']) && $ev['lat'] !== null ? (float) $ev['lat'] : null;
                            $evLng = isset($ev['lng']) && $ev['lng'] !== null ? (float) $ev['lng'] : null;
                            $hasGeoPoint = $evLat !== null && $evLng !== null;
                            $rowClasses = 'rxn-geo-event-row' . ($hasGeoPoint ? ' rxn-geo-row-clickable' : '');
                            $rowAttrs = '';
                            if ($hasGeoPoint) {
                                $rowAttrs = ' data-event-id="' . (int) ($ev['id'] ?? 0) . '"'
                                    . ' data-lat="' . htmlspecialchars((string) $evLat, ENT_QUOTES, 'UTF-8') . '"'
                                    . ' data-lng="' . htmlspecialchars((string) $evLng, ENT_QUOTES, 'UTF-8') . '"'
                                    . ' title="Click para centrar el mapa en este evento"';
                            }
                            ?>
                            <tr class="<?= $rowClasses ?>"<?= $rowAttrs ?>>
                                <td><small><?= htmlspecialchars((string) $ev['created_at']) ?></small></td>
                                <td>
                                    <strong><?= htmlspecialchars((string) ($ev['user_nombre'] ?? 'Usuario #' . (int) $ev['user_id'])) ?></strong>
                                    <br><small><?= htmlspecialchars((string) ($ev['user_email'] ?? '')) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary-emphasis"><?= htmlspecialchars((string) $evLabel) ?></span>
                                </td>
                                <td>
                                    <?php if ($entidadTipo !== '' && $entidadId !== null): ?>
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= htmlspecialchars($entidadTipo) ?> #<?= $entidadId ?></span>
                                    <?php else: ?>
                                        <small class="text-muted">—</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($ubicacion) ?></td>
                                <td><code class="small"><?= htmlspecialchars((string) ($ev['ip_address'] ?? '')) ?></code></td>
                                <td>
                                    <span class="badge rxn-geo-accuracy-<?= htmlspecialchars($accuracySource) ?> rxn-geo-accuracy-badge">
                                        <?= strtoupper(htmlspecialchars($accuracySource)) ?>
                                        <?php if (!empty($ev['accuracy_meters'])): ?>
                                            ±<?= (int) $ev['accuracy_meters'] ?>m
                                        <?php endif; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-muted">Página <?= (int) $page ?> de <?= (int) $totalPages ?> — <?= (int) $totalItems ?> resultados</small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $pagerQs = $filterParams;
                    $pagerQs['limit'] = (string) $limit;
                    $prev = max(1, $page - 1);
                    $next = min($totalPages, $page + 1);
                    $qsPrev = http_build_query(array_merge($pagerQs, ['page' => (string) $prev]));
                    $qsNext = http_build_query(array_merge($pagerQs, ['page' => (string) $next]));
                    ?>
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= $qsPrev ?>"><i class="bi bi-chevron-left"></i></a>
                    </li>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= $qsNext ?>"><i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();

ob_start();
?>
<?php if ($apiKey !== null): ?>
<script>
// Configuración para el JS del dashboard.
window.RxnGeoDashboardConfig = {
    mapPointsEndpoint: '/mi-empresa/geo-tracking/map-points',
    filters: <?= json_encode($filterParams, JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    eventLabels: <?= json_encode($eventLabels, JSON_INVALID_UTF8_SUBSTITUTE) ?>
};
</script>
<script src="/js/rxn-geo-tracking-dashboard.js?v=<?= time() ?>"></script>
<script async defer
    src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($apiKey, ENT_QUOTES, 'UTF-8') ?>&callback=rxnGeoInitMap&loading=async">
</script>
<?php endif; ?>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
