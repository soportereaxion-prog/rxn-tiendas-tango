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

<main class="container-fluid flex-grow-1 px-4 mb-5 crm-tratativas-shell">
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
            <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-outline-secondary" title="Volver al listado de Tratativas" data-rxn-back><i class="bi bi-arrow-left"></i> Listado</a>
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

    <?php /* ====== HORAS TRABAJADAS (módulo CrmHoras) ====== */ ?>
    <?php
    $horas = $horas ?? [];
    $horasTotalSeg = $horasTotalSeg ?? 0;
    $horasTotalH = intdiv($horasTotalSeg, 3600);
    $horasTotalM = intdiv($horasTotalSeg % 3600, 60);
    $horasTotalLabel = sprintf('%dh %02dm', $horasTotalH, $horasTotalM);
    ?>
    <div class="card bg-dark text-light border-0 shadow-sm mb-4">
        <div class="card-header border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-stopwatch text-info"></i> Horas trabajadas
                <span class="badge bg-secondary ms-2"><?= count($horas) ?></span>
                <?php if ($horasTotalSeg > 0): ?>
                    <span class="badge bg-info ms-1" title="Tiempo total invertido en esta tratativa"><?= htmlspecialchars($horasTotalLabel) ?></span>
                <?php endif; ?>
            </h5>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#vincularHoraModal" title="Asociar a esta tratativa un turno ya cargado">
                    <i class="bi bi-link-45deg"></i> Vincular existente
                </button>
                <a href="/mi-empresa/crm/horas" class="btn btn-sm btn-outline-primary" title="Ir al turnero — el turno que abras puede vincularse a esta tratativa al iniciarlo">
                    <i class="bi bi-arrow-up-right"></i> Ir al turnero
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Operador</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th style="width: 110px;">Duración</th>
                        <th>Concepto</th>
                        <th style="width: 100px;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($horas)): ?>
                        <tr><td colspan="7" class="text-center p-4 text-muted">No hay turnos vinculados a esta tratativa.</td></tr>
                    <?php else: foreach ($horas as $h): ?>
                        <?php
                        $hStart = $h['started_at'] ? (new DateTime((string) $h['started_at']))->format('d/m H:i') : '-';
                        $hEnd = $h['ended_at'] ? (new DateTime((string) $h['ended_at']))->format('d/m H:i') : '<em class="text-muted">abierto</em>';
                        $hDur = '-';
                        if ($h['ended_at'] && $h['started_at']) {
                            try {
                                $sec = (new \DateTimeImmutable((string) $h['ended_at']))->getTimestamp() - (new \DateTimeImmutable((string) $h['started_at']))->getTimestamp();
                                $hh = intdiv(max(0, $sec), 3600);
                                $mm = intdiv(max(0, $sec) % 3600, 60);
                                $hDur = sprintf('%dh %02dm', $hh, $mm);
                            } catch (\Throwable) {}
                        }
                        $estadoBadge = match ($h['estado'] ?? '') {
                            'abierto' => '<span class="badge bg-success">abierto</span>',
                            'anulado' => '<span class="badge bg-danger">anulado</span>',
                            default => '<span class="badge bg-info text-dark">cerrado</span>',
                        };
                        ?>
                        <tr>
                            <td class="text-muted small">#<?= (int) $h['id'] ?></td>
                            <td class="small"><?= htmlspecialchars((string) ($h['usuario_nombre'] ?? '-')) ?></td>
                            <td class="small"><?= $hStart ?></td>
                            <td class="small"><?= $hEnd ?></td>
                            <td class="small"><?= htmlspecialchars($hDur) ?></td>
                            <td class="small text-truncate" style="max-width: 280px;" title="<?= htmlspecialchars((string) ($h['concepto'] ?? '')) ?>">
                                <?= htmlspecialchars((string) ($h['concepto'] ?? '-')) ?>
                            </td>
                            <td><?= $estadoBadge ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php /* Modal: vincular hora existente */ ?>
    <div class="modal fade" id="vincularHoraModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="bi bi-link-45deg"></i> Vincular turno existente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Estos son tus turnos cerrados sin tratativa asignada. Elegí uno y vinculalo a esta tratativa.</p>
                    <div id="vincularHoraLoading" class="text-center text-muted py-3">
                        <i class="spinner-border spinner-border-sm"></i> Cargando…
                    </div>
                    <div id="vincularHoraEmpty" class="alert alert-secondary small" style="display:none;">
                        No tenés turnos sueltos para vincular.
                    </div>
                    <ul class="list-group list-group-flush" id="vincularHoraList" style="max-height: 400px; overflow-y: auto; display:none;"></ul>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const tratativaId = <?= (int) $tratativa['id'] ?>;
        const csrf = '<?= htmlspecialchars(\App\Core\CsrfHelper::generateToken()) ?>';
        const modal = document.getElementById('vincularHoraModal');
        const loading = document.getElementById('vincularHoraLoading');
        const empty = document.getElementById('vincularHoraEmpty');
        const list = document.getElementById('vincularHoraList');
        if (!modal) return;

        function fmtDate(iso) {
            try {
                const d = new Date(iso.replace(' ', 'T'));
                return d.toLocaleString('es-AR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
            } catch (_) { return iso; }
        }

        function loadSueltos() {
            loading.style.display = '';
            empty.style.display = 'none';
            list.style.display = 'none';
            list.innerHTML = '';
            fetch('/mi-empresa/crm/tratativas/' + tratativaId + '/horas-sueltas.json', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    loading.style.display = 'none';
                    if (!data.ok || !data.items || data.items.length === 0) {
                        empty.style.display = '';
                        return;
                    }
                    list.style.display = '';
                    data.items.forEach(h => {
                        const li = document.createElement('li');
                        li.className = 'list-group-item bg-dark text-light border-secondary d-flex justify-content-between align-items-center';
                        const concepto = h.concepto ? ' — ' + h.concepto : '';
                        li.innerHTML = `
                            <div>
                                <div class="fw-semibold">${fmtDate(h.started_at)} → ${fmtDate(h.ended_at)}</div>
                                <small class="text-muted">#${h.id}${concepto}</small>
                            </div>
                            <form method="POST" action="/mi-empresa/crm/tratativas/${tratativaId}/vincular-hora" class="m-0">
                                <input type="hidden" name="csrf_token" value="${csrf}">
                                <input type="hidden" name="hora_id" value="${h.id}">
                                <button type="submit" class="btn btn-sm btn-info"><i class="bi bi-link"></i> Vincular</button>
                            </form>
                        `;
                        list.appendChild(li);
                    });
                })
                .catch(() => {
                    loading.style.display = 'none';
                    empty.textContent = 'Error cargando turnos sueltos.';
                    empty.style.display = '';
                });
        }

        modal.addEventListener('shown.bs.modal', loadSueltos);
    })();
    </script>

</main>

<?php
$content = ob_get_clean();
ob_start();
?>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
