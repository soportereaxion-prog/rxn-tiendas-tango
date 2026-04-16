<?php
use App\Core\View;

$pageTitle = 'Tratativa #' . (int) ($tratativa['numero'] ?? 0) . ' - rxn_suite';
$tratativa = $tratativa ?? [];
$pds = $pds ?? [];
$presupuestos = $presupuestos ?? [];
$notas = $notas ?? [];

$estadoBadges = [
    'nueva' => ['bg-secondary', 'Nueva'],
    'en_curso' => ['bg-primary', 'En curso'],
    'ganada' => ['bg-success', 'Ganada'],
    'perdida' => ['bg-danger', 'Perdida'],
    'pausada' => ['bg-warning text-dark', 'Pausada'],
];

$estadoKey = (string) ($tratativa['estado'] ?? 'nueva');
[$badgeClass, $estadoLabel] = $estadoBadges[$estadoKey] ?? ['bg-secondary', ucfirst($estadoKey)];

$tratativaId = (int) ($tratativa['id'] ?? 0);
$clienteId = (int) ($tratativa['cliente_id'] ?? 0);

$urlNuevoPds = '/mi-empresa/crm/pedidos-servicio/crear?tratativa_id=' . $tratativaId;
if ($clienteId > 0) {
    $urlNuevoPds .= '&cliente_id=' . $clienteId;
}

$urlNuevoPresupuesto = '/mi-empresa/crm/presupuestos/crear?tratativa_id=' . $tratativaId;
if ($clienteId > 0) {
    $urlNuevoPresupuesto .= '&cliente_id=' . $clienteId;
}

$urlNuevaNota = '/mi-empresa/crm/notas/crear?tratativa_id=' . $tratativaId;
if ($clienteId > 0) {
    $urlNuevaNota .= '&cliente_id=' . $clienteId;
}
$urlListadoNotasTratativa = '/mi-empresa/crm/notas?tratativa_id=' . $tratativaId;

$formatFecha = static function ($value): string {
    if ($value === null || trim((string) $value) === '' || (string) $value === '0000-00-00') {
        return '-';
    }
    try {
        return (new \DateTimeImmutable((string) $value))->format('d/m/Y');
    } catch (\Throwable) {
        return (string) $value;
    }
};

ob_start();
?>

<main class="container-fluid flex-grow-1 px-4 mb-5" style="max-width: 1400px;">
    <div class="rxn-module-header mb-4 pb-3 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 fw-bold mb-1">
                <i class="bi bi-briefcase-fill"></i>
                Tratativa #<?= (int) ($tratativa['numero'] ?? 0) ?>
                <span class="badge <?= $badgeClass ?> ms-2 fs-6"><?= htmlspecialchars($estadoLabel) ?></span>
            </h1>
            <p class="text-muted small mb-0"><?= htmlspecialchars((string) ($tratativa['titulo'] ?? '')) ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Listado</a>
            <a href="<?= htmlspecialchars($basePath) ?>/<?= $tratativaId ?>/editar" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
        </div>
    </div>

    <?php $flash = \App\Core\Flash::get(); ?>
    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars((string) $flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars((string) $flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card bg-dark text-light border-0 shadow-sm h-100">
                <div class="card-header border-bottom border-secondary border-opacity-25">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-info-circle"></i> Información general</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-muted small">Cliente</dt>
                        <dd class="col-sm-8">
                            <?php if (!empty($tratativa['cliente_nombre'])): ?>
                                <span class="badge bg-success"><i class="bi bi-building"></i> <?= htmlspecialchars((string) $tratativa['cliente_nombre']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">Sin cliente asignado</span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4 text-muted small">Descripción</dt>
                        <dd class="col-sm-8"><?= nl2br(htmlspecialchars((string) ($tratativa['descripcion'] ?? '-'))) ?></dd>

                        <dt class="col-sm-4 text-muted small">Responsable</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars((string) ($tratativa['usuario_nombre'] ?? '-')) ?></dd>

                        <dt class="col-sm-4 text-muted small">Motivo de cierre</dt>
                        <dd class="col-sm-8"><?= nl2br(htmlspecialchars((string) ($tratativa['motivo_cierre'] ?? '-'))) ?: '-' ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card bg-dark text-light border-0 shadow-sm h-100">
                <div class="card-header border-bottom border-secondary border-opacity-25">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-bar-chart"></i> Valor y fechas</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Probabilidad</div>
                        <div class="progress bg-secondary bg-opacity-25" style="height: 24px;">
                            <div class="progress-bar bg-primary" style="width: <?= (int) ($tratativa['probabilidad'] ?? 0) ?>%"><?= (int) ($tratativa['probabilidad'] ?? 0) ?>%</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Valor estimado</div>
                        <div class="fw-bold fs-4">$ <?= number_format((float) ($tratativa['valor_estimado'] ?? 0), 2, ',', '.') ?></div>
                    </div>
                    <hr class="border-secondary">
                    <div class="small">
                        <div class="d-flex justify-content-between mb-1"><span class="text-muted">Apertura:</span><span><?= $formatFecha($tratativa['fecha_apertura'] ?? null) ?></span></div>
                        <div class="d-flex justify-content-between mb-1"><span class="text-muted">Cierre estimado:</span><span><?= $formatFecha($tratativa['fecha_cierre_estimado'] ?? null) ?></span></div>
                        <div class="d-flex justify-content-between"><span class="text-muted">Cierre real:</span><span><?= $formatFecha($tratativa['fecha_cierre_real'] ?? null) ?></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card bg-dark text-light border-0 shadow-sm mb-4">
        <div class="card-header border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-tools"></i> Pedidos de Servicio asociados <span class="badge bg-secondary ms-2"><?= count($pds) ?></span></h5>
            <a href="<?= htmlspecialchars($urlNuevoPds) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> Nuevo PDS</a>
        </div>
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width: 80px;">#</th>
                        <th>Cliente</th>
                        <th>Artículo</th>
                        <th>Solicitó</th>
                        <th>Inicio</th>
                        <th>Estado</th>
                        <th>Tango</th>
                        <th style="width: 80px;" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pds)): ?>
                        <tr><td colspan="8" class="text-center p-4 text-muted">No hay pedidos de servicio vinculados a esta tratativa.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pds as $item): ?>
                            <?php
                            $pdsEstado = empty($item['fecha_finalizado']) ? 'abierto' : 'finalizado';
                            $pdsBadge = $pdsEstado === 'abierto' ? 'bg-warning text-dark' : 'bg-success';
                            ?>
                            <tr>
                                <td class="fw-bold">#<?= (int) ($item['numero'] ?? 0) ?></td>
                                <td><?= htmlspecialchars((string) ($item['cliente_nombre'] ?? '-')) ?></td>
                                <td class="small"><?= htmlspecialchars((string) ($item['articulo_nombre'] ?? '-')) ?></td>
                                <td class="small"><?= htmlspecialchars((string) ($item['solicito'] ?? '-')) ?></td>
                                <td class="small"><?= !empty($item['fecha_inicio']) ? date('d/m/Y H:i', strtotime((string) $item['fecha_inicio'])) : '-' ?></td>
                                <td><span class="badge <?= $pdsBadge ?>"><?= $pdsEstado ?></span></td>
                                <td>
                                    <?php if (!empty($item['nro_pedido'])): ?>
                                        <span class="badge bg-success">N° <?= htmlspecialchars((string) $item['nro_pedido']) ?></span>
                                    <?php elseif (($item['tango_sync_status'] ?? '') === 'error'): ?>
                                        <span class="badge bg-danger">error</span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="/mi-empresa/crm/pedidos-servicio/<?= (int) $item['id'] ?>/editar" class="btn btn-sm btn-outline-primary" title="Abrir PDS"><i class="bi bi-box-arrow-up-right"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card bg-dark text-light border-0 shadow-sm mb-4">
        <div class="card-header border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-spreadsheet"></i> Presupuestos asociados <span class="badge bg-secondary ms-2"><?= count($presupuestos) ?></span></h5>
            <a href="<?= htmlspecialchars($urlNuevoPresupuesto) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> Nuevo Presupuesto</a>
        </div>
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width: 80px;">#</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th class="text-end">Total</th>
                        <th>Tango</th>
                        <th style="width: 80px;" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($presupuestos)): ?>
                        <tr><td colspan="7" class="text-center p-4 text-muted">No hay presupuestos vinculados a esta tratativa.</td></tr>
                    <?php else: ?>
                        <?php foreach ($presupuestos as $item): ?>
                            <tr>
                                <td class="fw-bold">#<?= (int) ($item['numero'] ?? 0) ?></td>
                                <td><?= htmlspecialchars((string) ($item['cliente_nombre_snapshot'] ?? '-')) ?></td>
                                <td class="small"><?= !empty($item['fecha']) ? date('d/m/Y', strtotime((string) $item['fecha'])) : '-' ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars((string) ($item['estado'] ?? '-')) ?></span></td>
                                <td class="text-end">$ <?= number_format((float) ($item['total'] ?? 0), 2, ',', '.') ?></td>
                                <td>
                                    <?php if (!empty($item['nro_comprobante_tango'])): ?>
                                        <span class="badge bg-success"><?= htmlspecialchars((string) $item['nro_comprobante_tango']) ?></span>
                                    <?php elseif (($item['tango_sync_status'] ?? '') === 'error'): ?>
                                        <span class="badge bg-danger">error</span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="/mi-empresa/crm/presupuestos/<?= (int) $item['id'] ?>/editar" class="btn btn-sm btn-outline-primary" title="Abrir Presupuesto"><i class="bi bi-box-arrow-up-right"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card bg-dark text-light border-0 shadow-sm mb-4">
        <div class="card-header border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-journal-text"></i> Notas relacionadas <span class="badge bg-secondary ms-2"><?= count($notas) ?></span></h5>
            <div class="d-flex gap-2">
                <?php if (!empty($notas)): ?>
                    <a href="<?= htmlspecialchars($urlListadoNotasTratativa) ?>" class="btn btn-sm btn-outline-secondary" title="Ver todas las notas filtradas por esta tratativa"><i class="bi bi-list-ul"></i> Ver todas</a>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($urlNuevaNota) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> Nueva Nota</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Título</th>
                        <th>Contenido</th>
                        <th>Cliente</th>
                        <th>Tags</th>
                        <th style="width: 120px;">Fecha</th>
                        <th style="width: 80px;" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($notas)): ?>
                        <tr><td colspan="7" class="text-center p-4 text-muted">No hay notas vinculadas a esta tratativa.</td></tr>
                    <?php else: ?>
                        <?php foreach ($notas as $nota): ?>
                            <?php
                            $contenidoPlano = trim((string) ($nota['contenido'] ?? ''));
                            $contenidoResumen = mb_strlen($contenidoPlano) > 120 ? mb_substr($contenidoPlano, 0, 117) . '…' : $contenidoPlano;
                            ?>
                            <tr>
                                <td class="text-muted small">#<?= (int) ($nota['id'] ?? 0) ?></td>
                                <td>
                                    <a href="/mi-empresa/crm/notas/<?= (int) $nota['id'] ?>" class="text-info text-decoration-none fw-bold"><?= htmlspecialchars((string) ($nota['titulo'] ?? '')) ?></a>
                                    <?php if ((int) ($nota['activo'] ?? 1) === 0): ?>
                                        <span class="badge bg-danger ms-2">Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($contenidoResumen) ?: '-' ?></td>
                                <td class="small"><?= htmlspecialchars((string) ($nota['cliente_nombre'] ?? '-')) ?></td>
                                <td>
                                    <?php if (!empty($nota['tags'])): ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars((string) $nota['tags']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= !empty($nota['created_at']) ? date('d/m/Y', strtotime((string) $nota['created_at'])) : '-' ?></td>
                                <td class="text-end">
                                    <a href="/mi-empresa/crm/notas/<?= (int) $nota['id'] ?>/editar" class="btn btn-sm btn-outline-primary" title="Editar nota"><i class="bi bi-box-arrow-up-right"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<?php
$content = ob_get_clean();
ob_start();
?>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
