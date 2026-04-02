<?php
use App\Core\Helpers\UIHelper;
use App\Core\View;
use App\Modules\Auth\AuthService;
$ui = isset($environmentLabel) ? compact('environmentLabel', 'dashboardPath') : [];
$activeUser = AuthService::getCurrentUser();
$miInterno = $activeUser && $activeUser->anura_interno !== null ? (string)$activeUser->anura_interno : '';
?>
<?php
$pageTitle = 'RXN Tiendas IA';
ob_start();
?>
<?php View::render('app/shared/views/components/backoffice_user_banner.php', $ui); ?>

    <main class="container-fluid flex-grow-1 px-4 mb-5" style="max-width: 1400px;">
        <div class="rxn-module-header mb-4 pb-3 border-bottom border-secondary border-opacity-25">
            <div>
                <h1 class="h3 fw-bold mb-1"><i class="bi bi-telephone-fill"></i> Llamadas Central Telefónica</h1>
                <p class="text-muted mb-0">Historial de llamadas registradas desde la integración con Anura.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= htmlspecialchars($dashboardPath ?? '/rxnTiendasIA/public/') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
            </div>
        </div>

        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <?= htmlspecialchars($_GET['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 bg-dark text-light mb-4 rxn-card-hover">
            <div class="card-body">
                <form action="<?= htmlspecialchars($indexPath) ?>" method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status ?? 'activos') ?>">
                    <div class="col-md-6">
                        <label class="form-label text-muted small mb-1">Buscar Llamada</label>
                        <input type="text" name="search" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($search ?? '') ?>" placeholder='🔎 Presioná F3 o "/" para buscar' data-search-input autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted small mb-1">Ordenar por</label>
                        <select name="sort" class="form-select bg-dark text-light border-secondary">
                            <option value="fecha_desc" <?= (empty($_GET['sort']) || $_GET['sort'] === 'fecha_desc') ? 'selected' : '' ?>>Más Recientes</option>
                            <option value="fecha_asc" <?= ($_GET['sort'] ?? '') === 'fecha_asc' ? 'selected' : '' ?>>Más Antiguas</option>
                            <option value="duracion_desc" <?= ($_GET['sort'] ?? '') === 'duracion_desc' ? 'selected' : '' ?>>Mayor Duración</option>
                            <option value="usuario_nombre_asc" <?= ($_GET['sort'] ?? '') === 'usuario_nombre_asc' ? 'selected' : '' ?>>Atendió A-Z</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Aplicar</button>
                    </div>
                </form>
            </div>
        </div>

        <?php
        $status = $status ?? 'activos';
        $isPapelera = $status === 'papelera';
        ?>

        <ul class="nav nav-tabs mb-4 border-secondary border-opacity-25" style="border-bottom-width: 2px;">
            <li class="nav-item">
                <a class="nav-link <?= !$isPapelera ? 'active fw-bold bg-dark text-light border-secondary border-bottom-0' : 'text-muted border-0 hover-text-light' ?>" href="<?= htmlspecialchars($indexPath) ?>?status=activos&search=<?= urlencode($search ?? '') ?>">
                    Activas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger <?= $isPapelera ? 'active fw-bold bg-dark border-secondary border-bottom-0' : 'border-0 hover-text-danger' ?>" href="<?= htmlspecialchars($indexPath) ?>?status=papelera&search=<?= urlencode($search ?? '') ?>">
                    <i class="bi bi-trash"></i> Papelera
                </a>
            </li>
        </ul>

        <div class="card shadow-sm border-0 bg-dark text-light">
            <?php if (!$isPapelera): ?>
            <div class="px-4 pt-3 pb-2 border-bottom border-secondary border-opacity-25 pb-0">
                <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($indexPath) ?>/eliminar-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="¿Enviar las llamadas seleccionadas a la papelera?"><i class="bi bi-trash"></i> Eliminar Seleccionadas</button>
            </div>
            <?php else: ?>
            <div class="px-4 pt-3 pb-2 border-bottom border-secondary border-opacity-25 d-flex gap-2">
                <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($indexPath) ?>/restore-masivo" class="btn btn-outline-success btn-sm rxn-confirm-form" data-msg="¿Restaurar las llamadas seleccionadas?"><i class="bi bi-arrow-counterclockwise"></i> Restaurar Seleccionadas</button>
                <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($indexPath) ?>/force-delete-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="⚠️ ATENCIÓN: Acción irreversible. ¿Destruir definitivamente las llamadas seleccionadas?"><i class="bi bi-x-circle"></i> Destruir Seleccionadas</button>
            </div>
            <?php endif; ?>

            <form method="POST" id="hiddenFormBulk"></form>
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="checkAll" class="form-check-input" onclick="document.querySelectorAll('.check-item').forEach(e => e.checked = this.checked);"></th>
                            <th style="width: 150px;">Fecha y Hora</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th>Interno (Usuario)</th>
                            <th>Duración</th>
                            <th>Grabación</th>
                            <th style="width: 80px;" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($llamadas)): ?>
                            <tr><td colspan="8" class="text-center p-4 text-muted">No existen llamadas registradas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($llamadas as $item): ?>
                                <tr class="rxn-hover-bg">
                                    <td><input type="checkbox" name="ids[]" value="<?= (int) $item['id'] ?>" class="form-check-input check-item" form="hiddenFormBulk"></td>
                                    <td class="small fw-bold">
                                        <?= date('d/m/Y H:i', strtotime($item['fecha'])) ?>
                                    </td>
                                    <td><?= htmlspecialchars($item['numero_origen'] ?? $item['origen'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($item['destino'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge bg-secondary me-1"><?= htmlspecialchars($item['interno'] ?? '-') ?></span>
                                        <span class="small fw-bold text-info"><?= htmlspecialchars($item['usuario_nombre'] ?? $item['atendio'] ?? 'Desconocido') ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['duracion'])): 
                                            $sgs = (int)$item['duracion'];
                                            $h = floor($sgs / 3600);
                                            $m = floor(($sgs % 3600) / 60);
                                            $s = $sgs % 60;
                                            $dFmt = sprintf('%02d:%02d:%02d', $h, $m, $s);
                                        ?>
                                            <span class="badge bg-dark border border-secondary"><i class="bi bi-clock"></i> <?= $dFmt ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['mp3'])): ?>
                                            <audio controls style="height: 30px; max-width: 250px;">
                                                <source src="<?= htmlspecialchars($item['mp3']) ?>" type="audio/mpeg">
                                                No soportado.
                                            </audio>
                                            <a href="<?= htmlspecialchars($item['mp3']) ?>" target="_blank" class="btn btn-sm text-info py-0 ms-1" title="Abrir link original"><i class="bi bi-box-arrow-up-right"></i></a>
                                        <?php elseif (!empty($item['evento_link'])): ?>
                                            <span class="badge <?= $item['evento_link'] === 'HANGUP' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                                <i class="bi bi-telephone"></i> <?= htmlspecialchars($item['evento_link']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">Sin audio</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <?php if (!$isPapelera): ?>
                                                <?php if (($item['evento_link'] ?? '') === 'HANGUP'): 
                                                    $dSegs = (int)($item['duracion'] ?? 0);
                                                    $tsInicio = strtotime($item['fecha'] ?? '');
                                                    $tsFin = $tsInicio + $dSegs;
                                                    $horaIni = date('H:i:s', $tsInicio);
                                                    $horaFin = date('H:i:s', $tsFin);
                                                    $h = floor($dSegs / 3600);
                                                    $m = floor(($dSegs % 3600) / 60);
                                                    $s = $dSegs % 60;
                                                    $duracionFormat = sprintf('%02d:%02d:%02d', $h, $m, $s);
                                                    $origenNum = $item['numero_origen'] ?? $item['origen'] ?? '';
                                                    $diagnosticoStr = "Llamada, Sonando: {$origenNum} | Comienzo de la llamada: {$horaIni} | Finalizada: {$horaFin} | Total: {$duracionFormat}";
                                                    
                                                    $isoInicio = date('Y-m-d\TH:i:s', $tsInicio);
                                                    $isoFin = date('Y-m-d\TH:i:s', $tsFin);
                                                    $urlCrear = "/rxnTiendasIA/public/mi-empresa/crm/pedidos-servicio/crear?diagnostico=" . urlencode($diagnosticoStr) . "&inicio=" . urlencode($isoInicio) . "&fin=" . urlencode($isoFin);
                                                    $internoExtraccion = substr((string)($item['atendio'] ?? ''), 0, 3);
                                                ?>
                                                    <button type="button" onclick="validarInternoPds('<?= htmlspecialchars($internoExtraccion) ?>', '<?= htmlspecialchars($miInterno) ?>', '<?= $urlCrear ?>')" class="btn btn-sm btn-outline-primary" title="Generar PDS"><i class="bi bi-journal-plus"></i></button>
                                                <?php endif; ?>
                                                <form action="<?= htmlspecialchars($indexPath) ?>/<?= $item['id'] ?>/eliminar" method="POST" style="display:inline;" class="rxn-confirm-form" data-msg="¿Enviar llamada a la papelera?">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger <?= (($item['evento_link'] ?? '') === 'HANGUP') ? 'border-start-0' : '' ?>" title="Eliminar (Papelera)"><i class="bi bi-trash"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <form action="<?= htmlspecialchars($indexPath) ?>/<?= $item['id'] ?>/restore" method="POST" style="display:inline;" class="rxn-confirm-form" data-msg="¿Confirma restaurar esta llamada?">
                                                    <button type="submit" class="btn btn-sm btn-outline-success border-end-0" title="Restaurar Llamada"><i class="bi bi-arrow-counterclockwise"></i></button>
                                                </form>
                                                <form action="<?= htmlspecialchars($indexPath) ?>/<?= $item['id'] ?>/force-delete" method="POST" style="display:inline;" class="rxn-confirm-form" data-msg="⚠️ ATENCIÓN: Esta acción es irreversible. ¿Eliminar llamada definitivamente?">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" title="Destruir"><i class="bi bi-x-circle"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <span class="text-sm text-muted">Mostrando página <?= $page ?> de <?= $totalPages ?> (Total: <?= $totalItems ?>)</span>
                <div class="d-flex gap-1">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sortRaw ?? '') ?>&status=<?= urlencode($status ?? 'activos') ?>" class="btn btn-sm btn-outline-secondary">Anterior</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sortRaw ?? '') ?>&status=<?= urlencode($status ?? 'activos') ?>" class="btn btn-sm btn-outline-secondary">Siguiente</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- Modal de Confirmación Universal rxnTiendasIA -->
    <div class="modal fade" id="rxnConfirmModal" tabindex="-1" aria-labelledby="rxnConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-secondary text-light shadow-lg">
                <div class="modal-header border-secondary border-opacity-25 pb-2">
                    <h5 class="modal-title fs-5" id="rxnConfirmModalLabel"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Confirmación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4 fs-5 text-center" id="rxnConfirmMsg">
                    ¿Estás seguro/a?
                </div>
                <div class="modal-footer border-0 pt-0 justify-content-center gap-2">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary px-4 fw-bold" id="rxnConfirmBtn">Aceptar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Error Universal -->
    <div class="modal fade" id="rxnErrorModal" tabindex="-1" aria-labelledby="rxnErrorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-secondary text-light shadow-lg">
                <div class="modal-header border-secondary border-opacity-25 pb-2">
                    <h5 class="modal-title fs-5 text-danger" id="rxnErrorModalLabel"><i class="bi bi-x-circle me-2"></i>Acceso Denegado</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4 fs-5 text-center" id="rxnErrorMsg">
                </div>
                <div class="modal-footer border-0 pt-0 justify-content-center">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>

    
<?php
$content = ob_get_clean();
ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const confirmForms = document.querySelectorAll('.rxn-confirm-form');
        const confirmModalEl = document.getElementById('rxnConfirmModal');
        if (!confirmModalEl) return;
        
        const confirmModal = new bootstrap.Modal(confirmModalEl);
        const confirmMsg = document.getElementById('rxnConfirmMsg');
        const confirmBtn = document.getElementById('rxnConfirmBtn');
        let pendingForm = null;

        confirmForms.forEach(form => {
            if (form.closest('.btn-group') !== null) {
                // If it's a small button, use form submission on form element itself avoiding nested forms edge cases if any
            }
            form.addEventListener('submit', (e) => {
                const isFormBulk = form.id === 'hiddenFormBulk';
                
                // Workaround for hiddenFormBulk button submissions where button lies outside
                // actually we check if the submitter is marked or not.
                e.preventDefault();
                pendingForm = form;
                
                // If it's a hidden form bulk, we need to read from the clicked button
                const submitter = e.submitter;
                let msg = '';
                let action = '';
                
                if (submitter) {
                    msg = submitter.getAttribute('data-msg') || '¿Estás seguro/a?';
                    action = submitter.getAttribute('formaction') || form.getAttribute('action');
                    form.action = action; // set the proper action if changed
                } else {
                    msg = form.getAttribute('data-msg') || '¿Estás seguro/a?';
                    action = form.getAttribute('action');
                }
                
                if (action && action.includes('eliminar') || action.includes('force')) {
                    confirmBtn.className = 'btn btn-danger px-4 fw-bold';
                } else {
                    confirmBtn.className = 'btn btn-primary px-4 fw-bold';
                }
                
                confirmMsg.textContent = msg;
                confirmModal.show();
            });
        });

        confirmModalEl.addEventListener('shown.bs.modal', () => {
            confirmBtn.focus();
        });

        confirmBtn.addEventListener('click', () => {
            if (pendingForm) {
                pendingForm.submit();
            }
        });
    });

    function validarInternoPds(internoLlamada, miInterno, urlPds) {
        if (!miInterno) {
            showErrorModal('No tienes un interno de Anura configurado en tu perfil. No puedes generar PDS desde las llamadas de la central.');
            return;
        }
        if (internoLlamada !== miInterno) {
            showErrorModal(`No puedes generar un Pedido de Servicio desde esta llamada. El interno (${internoLlamada}) no coincide con tu interno asignado (${miInterno}).`);
            return;
        }
        window.location.href = urlPds;
    }

    function showErrorModal(msg) {
        const modalEl = document.getElementById('rxnErrorModal');
        if (modalEl) {
            document.getElementById('rxnErrorMsg').textContent = msg;
            const myModal = new bootstrap.Modal(modalEl);
            myModal.show();
        } else {
            alert(msg);
        }
    }
    </script>
    <script src="/rxnTiendasIA/public/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
