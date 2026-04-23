<?php
use App\Core\Helpers\UIHelper;
use App\Core\View;
use App\Modules\Auth\AuthService;
$ui = isset($environmentLabel) ? compact('environmentLabel', 'dashboardPath') : [];
$activeUser = AuthService::getCurrentUser();
$miInterno = $activeUser && $activeUser->anura_interno !== null ? (string)$activeUser->anura_interno : '';
?>
<?php
$pageTitle = 'RXN Suite';
$sort = $sort ?? 'fecha';
$dir = $dir ?? 'DESC';
$buildQuery = function (array $overrides = []) use ($search, $sort, $dir, $page, $status) {
    $params = [
        'search' => $search,
        'limit' => 25,
        'sort' => $sort,
        'dir' => $dir,
        'page' => $page,
        'status' => $status ?? 'activos',
    ];

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
            continue;
        }
        $params[$key] = $value;
    }

    return http_build_query($params);
};
ob_start();
?>


    <main class="container-fluid flex-grow-1 px-4 mb-5 crm-llamadas-shell">
        <div class="rxn-module-header mb-4 pb-3 border-bottom border-secondary border-opacity-25">
            <div>
                <h1 class="h3 fw-bold mb-1"><i class="bi bi-telephone-fill"></i> Llamadas Central Telefónica</h1>
                
            </div>
            <div class="d-flex gap-2">
                <a href="<?= htmlspecialchars($dashboardPath ?? '/') ?>" class="btn btn-outline-secondary btn-sm" title="Volver al CRM"><i class="bi bi-arrow-left"></i> Volver</a>
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
                <form action="<?= htmlspecialchars($indexPath) ?>" method="GET" class="row g-3 align-items-end" data-search-form>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status ?? 'activos') ?>">
                    <div class="col-md-6">
                        <label class="form-label text-muted small mb-1">Buscar Llamada</label>
                        <input type="text" name="search" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($search ?? '') ?>" placeholder='🔎 Presioná F3 o "/" para buscar' data-search-input autocomplete="off">
                    </div>
                    <div class="col-md-2" style="display:none;">
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
                <a class="nav-link <?= !$isPapelera ? 'active fw-bold bg-dark text-light border-secondary border-bottom-0' : 'text-muted border-0 hover-text-light' ?>" href="<?= htmlspecialchars($indexPath) ?>?<?= htmlspecialchars($buildQuery(['status' => 'activos', 'page' => 1])) ?>">
                    Activas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger <?= $isPapelera ? 'active fw-bold bg-dark border-secondary border-bottom-0' : 'border-0 hover-text-danger' ?>" href="<?= htmlspecialchars($indexPath) ?>?<?= htmlspecialchars($buildQuery(['status' => 'papelera', 'page' => 1])) ?>">
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
                        <?php
                        $sortLink = function (string $fieldName, string $label) use ($buildQuery, $sort, $dir) {
                            $newDir = ($sort === $fieldName && $dir === 'ASC') ? 'DESC' : 'ASC';
                            $icon = ($sort === $fieldName) ? ($dir === 'ASC' ? '▲' : '▼') : '';
                            $href = '?' . $buildQuery(['sort' => $fieldName, 'dir' => $newDir]);
                            return '<a href="' . $href . '" class="text-white text-decoration-none"><span>' . $label . '</span><span class="ms-1">' . $icon . '</span></a>';
                        };
                        ?>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="checkAll" class="form-check-input" onclick="document.querySelectorAll('.check-item').forEach(e => e.checked = this.checked);"></th>
                            <th style="width: 150px;" class="rxn-filter-col rxn-hide-mobile" data-filter-field="fecha"><?= $sortLink('fecha', 'Fecha y Hora') ?></th>
                            <th class="rxn-filter-col" data-filter-field="numero_origen"><?= $sortLink('numero_origen', 'Origen') ?></th>
                            <th class="rxn-filter-col rxn-hide-mobile" data-filter-field="destino"><?= $sortLink('destino', 'Destino') ?></th>
                            <th class="rxn-filter-col rxn-hide-mobile" data-filter-field="usuario_nombre"><?= $sortLink('usuario_nombre', 'Interno (Usuario)') ?></th>
                            <th class="rxn-filter-col" data-filter-field="duracion"><?= $sortLink('duracion', 'Duración') ?></th>
                            <th class="rxn-filter-col" data-filter-field="grabacion_estado">Grabación</th>
                            <th style="width: 80px;" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($llamadas)): ?>
                            <tr><td colspan="8" class="text-center p-4 text-muted">No existen llamadas registradas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($llamadas as $item): ?>
                                <tr class="rxn-hover-bg" data-row-link="">
                                    <td data-row-link-ignore><input type="checkbox" name="ids[]" value="<?= (int) $item['id'] ?>" class="form-check-input check-item" form="hiddenFormBulk"></td>
                                    <td class="small fw-bold rxn-hide-mobile">
                                        <?= date('d/m/Y H:i', strtotime($item['fecha'])) ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($item['numero_origen'] ?? $item['origen'] ?? '-') ?></div>
                                        <?php if (!empty($item['cliente_nombre'])): ?>
                                            <span class="badge bg-success text-white mt-1"><i class="bi bi-building"></i> <?= htmlspecialchars($item['cliente_nombre']) ?></span>
                                        <?php else: ?>
                                            <?php if (!$isPapelera): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary mt-1 py-0 px-2 small" onclick="openVincularModal(<?= (int)$item['id'] ?>, '<?= htmlspecialchars($item['numero_origen'] ?? $item['origen'] ?? '') ?>')" data-row-link-ignore><i class="bi bi-person-plus"></i> Vincular</button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="rxn-hide-mobile"><?= htmlspecialchars($item['destino'] ?? '-') ?></td>
                                    <td class="rxn-hide-mobile">
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
                                    <td data-row-link-ignore>
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
                                        <div class="btn-group" data-row-link-ignore>
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
                                                    $urlCrear = "/mi-empresa/crm/pedidos-servicio/crear?diagnostico=" . urlencode($diagnosticoStr) . "&inicio=" . urlencode($isoInicio) . "&fin=" . urlencode($isoFin);
                                                    if (!empty($item['cliente_id'])) {
                                                        $urlCrear .= "&cliente_id=" . urlencode((string)$item['cliente_id']);
                                                    }
                                                    $internoLiteral = (string)($item['interno'] ?? '');
                                                    $internoExtraccion = substr((string)($item['atendio'] ?? ''), 0, 3);
                                                ?>
                                                    <button type="button" onclick="validarInternoPds('<?= htmlspecialchars($internoLiteral) ?>', '<?= htmlspecialchars($internoExtraccion) ?>', '<?= htmlspecialchars($miInterno) ?>', '<?= $urlCrear ?>')" class="btn btn-sm btn-outline-primary <?= !empty($item['cliente_id']) ? 'border-end-0' : '' ?>" title="Generar PDS"><i class="bi bi-journal-plus"></i></button>
                                                <?php endif; ?>
                                                <?php if (!empty($item['cliente_id'])): ?>
                                                    <form action="<?= htmlspecialchars($indexPath) ?>/<?= $item['id'] ?>/desvincular" method="POST" style="display:inline;" class="rxn-confirm-form" data-msg="¿Desea eliminar la relación cliente teléfono asociado?">
                                                        <input type="hidden" name="numero_origen" value="<?= htmlspecialchars($item['numero_origen'] ?? $item['origen'] ?? '') ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary <?= (($item['evento_link'] ?? '') === 'HANGUP') ? 'border-start-0' : '' ?> border-end-0" title="Desvincular Cliente"><i class="bi bi-person-dash"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                                <form action="<?= htmlspecialchars($indexPath) ?>/<?= $item['id'] ?>/eliminar" method="POST" style="display:inline;" class="rxn-confirm-form" data-msg="¿Enviar llamada a la papelera?">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger <?= (($item['evento_link'] ?? '') === 'HANGUP' || !empty($item['cliente_id'])) ? 'border-start-0' : '' ?>" title="Eliminar (Papelera)"><i class="bi bi-trash"></i></button>
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
                        <a href="?<?= htmlspecialchars($buildQuery(['page' => $page - 1])) ?>" class="btn btn-sm btn-outline-secondary">Anterior</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= htmlspecialchars($buildQuery(['page' => $page + 1])) ?>" class="btn btn-sm btn-outline-secondary">Siguiente</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </main>


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

    <!-- Modal Vincular Cliente Tango -->
    <div class="modal fade" id="vincularClienteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-secondary text-light shadow-lg">
                <div class="modal-header border-secondary border-opacity-25 py-3">
                    <h5 class="modal-title fs-5"><i class="bi bi-person-plus me-2"></i>Vincular Cliente Tango</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="text-muted small">Asociá este número de origen a un cliente. El sistema preasignará el cliente en futuras llamadas desde este teléfono.</p>
                    <form id="vincularForm" onsubmit="submitVincular(event)">
                        <input type="hidden" id="v_llamada_id" name="llamada_id">
                        <input type="hidden" id="v_numero_origen" name="numero_origen">
                        
                        <div class="mb-3">
                            <label class="form-label text-light small fw-bold">Buscar Cliente (Razón Social o Cód.)</label>
                            <input type="text" class="form-control bg-dark border-secondary text-light" id="vincular_cliente_search" placeholder="Escribí para buscar..." autocomplete="off">
                            <input type="hidden" id="vincular_cliente_id" name="cliente_id" required>
                            <div id="vincular_suggestions" class="dropdown-menu w-100 bg-dark border-secondary" style="max-height:200px;overflow-y:auto;"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-secondary border-opacity-25 pt-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="vincularForm" class="btn btn-primary" id="btn-vincular-submit" disabled>Vincular Cliente</button>
                </div>
            </div>
        </div>
    </div>
    
<?php
$content = ob_get_clean();
ob_start();
?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // ... rxnConfirmModal event handlers removed since they are grouped in universal JS ...
    });

    function validarInternoPds(internoLlamada1, internoLlamada2, miInterno, urlPds) {
        if (!miInterno) {
            showErrorModal('No tienes un interno de Anura configurado en tu perfil. No puedes generar PDS desde las llamadas de la central.');
            return;
        }
        if (internoLlamada1 !== miInterno && internoLlamada2 !== miInterno) {
            showErrorModal(`No puedes generar un Pedido de Servicio desde esta llamada. El interno no coincide con tu interno asignado (${miInterno}).`);
            return;
        }
        window.location.href = urlPds;
    }

    function showErrorModal(msg) {
        (window.rxnAlert || alert)(msg, 'danger', 'Acceso Denegado');
    }

    // --- Lógica Autocomplete Vincular Clientes ---
    const searchInput = document.getElementById('vincular_cliente_search');
    const suggestionsDiv = document.getElementById('vincular_suggestions');
    const hiddenInput = document.getElementById('vincular_cliente_id');
    const btnSubmit = document.getElementById('btn-vincular-submit');
    let searchTimeout;
    let currentFocus = -1;

    if(searchInput) {
        function loadSuggestions(q = '') {
            suggestionsDiv.innerHTML = '';
            currentFocus = -1;

            fetch(`/mi-empresa/crm/pedidos-servicio/clientes/sugerencias?q=${encodeURIComponent(q)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        suggestionsDiv.innerHTML = data.data.map(item => `
                            <a href="#" class="dropdown-item text-light suggestion-item px-3 py-2 border-bottom border-light border-opacity-10" data-id="${item.id}" data-label="${item.label.replace(/"/g, '&quot;')}">
                                <div class="fw-bold">${item.label}</div>
                                <div class="small text-muted">${item.caption}</div>
                            </a>
                        `).join('');
                        suggestionsDiv.classList.add('show');
                    } else {
                        suggestionsDiv.innerHTML = '<div class="dropdown-item text-muted disabled py-2">No se encontraron clientes</div>';
                        suggestionsDiv.classList.add('show');
                    }
                })
                .catch(err => console.error(err));
        }

        searchInput.addEventListener('input', (e) => {
            const q = e.target.value.trim();
            hiddenInput.value = '';
            btnSubmit.disabled = true;
            suggestionsDiv.classList.remove('show');

            clearTimeout(searchTimeout);

            searchTimeout = setTimeout(() => {
                loadSuggestions(q);
            }, 300);
        });

        searchInput.addEventListener('click', () => {
            if (!suggestionsDiv.classList.contains('show')) {
                loadSuggestions(searchInput.value.trim());
            }
        });

        searchInput.addEventListener('focus', () => {
            if (!suggestionsDiv.classList.contains('show')) {
                loadSuggestions(searchInput.value.trim());
            }
        });

        searchInput.addEventListener('keydown', function(e) {
            let items = suggestionsDiv.querySelectorAll('.suggestion-item');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                currentFocus++;
                addActive(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                currentFocus--;
                addActive(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (currentFocus > -1) {
                    if (items) items[currentFocus].click();
                } else if (items.length === 1) {
                    items[0].click();
                } else if (!btnSubmit.disabled) {
                    btnSubmit.click();
                }
            } else if (e.key === 'Escape') {
                const modalEl = document.getElementById('vincularClienteModal');
                const modalIns = bootstrap.Modal.getInstance(modalEl);
                if (modalIns) modalIns.hide();
            }
        });

        function addActive(items) {
            if (!items || items.length === 0) return false;
            removeActive(items);
            if (currentFocus >= items.length) currentFocus = 0;
            if (currentFocus < 0) currentFocus = (items.length - 1);
            items[currentFocus].classList.add('active', 'bg-primary');
            items[currentFocus].scrollIntoView({ block: 'nearest' });
        }

        function removeActive(items) {
            for (let i = 0; i < items.length; i++) {
                items[i].classList.remove('active', 'bg-primary');
            }
        }

        suggestionsDiv.addEventListener('click', (e) => {
            const row = e.target.closest('.suggestion-item');
            if (row) {
                e.preventDefault();
                hiddenInput.value = row.dataset.id;
                searchInput.value = row.dataset.label;
                suggestionsDiv.classList.remove('show');
                btnSubmit.disabled = false;
                searchInput.focus();
            }
        });

        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                suggestionsDiv.classList.remove('show');
            }
        });
    }

    function openVincularModal(id, numOrigen) {
        document.getElementById('v_llamada_id').value = id;
        document.getElementById('v_numero_origen').value = numOrigen;
        hiddenInput.value = '';
        searchInput.value = '';
        btnSubmit.disabled = true;
        suggestionsDiv.classList.remove('show');
        
        const myModal = new bootstrap.Modal(document.getElementById('vincularClienteModal'));
        myModal.show();
    }

    function submitVincular(e) {
        e.preventDefault();
        btnSubmit.disabled = true;
        const clId = hiddenInput.value;
        const llId = document.getElementById('v_llamada_id').value;
        const numOrg = document.getElementById('v_numero_origen').value;

        fetch('<?= htmlspecialchars($indexPath) ?>/vincular-cliente-api', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ llamada_id: llId, cliente_id: clId, numero_origen: numOrg })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                (window.rxnAlert || alert)(data.message || 'Error al vincular cliente', 'danger', 'No se pudo vincular');
                btnSubmit.disabled = false;
                return;
            }
            
            (window.rxnAlert || alert)('Cliente vinculado correctamente', 'success', 'Operación exitosa');
            window.location.reload();
        }).catch(err => {
            console.error(err);
            (window.rxnAlert || alert)('Error de conexión', 'danger', 'Error de red');
            btnSubmit.disabled = false;
        });
    }

    const vincularModalEl = document.getElementById('vincularClienteModal');
    if (vincularModalEl && searchInput) {
        vincularModalEl.addEventListener('shown.bs.modal', function () {
            searchInput.focus();
        });
    }
    </script>
    <script src="/js/rxn-advanced-filters.js"></script>
    <script src="/js/rxn-crud-search.js"></script>
    <script src="/js/rxn-row-links.js"></script>
    <script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
