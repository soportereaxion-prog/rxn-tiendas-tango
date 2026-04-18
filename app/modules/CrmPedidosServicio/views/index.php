<?php
$pageTitle = 'RXN Suite';
ob_start();
?>
<?php
    $buildQuery = function (array $overrides = []) use ($search, $field, $estado, $limit, $sort, $dir, $page) {
        $params = [
            'search' => $search,
            'field' => $field,
            'estado' => $estado,
            'limit' => $limit,
            'sort' => $sort,
            'dir' => $dir,
            'page' => $page,
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
    ?>
    <div class="container-fluid mt-2 rxn-responsive-container">
        <div class="rxn-module-header mb-4">
            <div>
                <h2>CRM - Pedidos de Servicio</h2>
                
            </div>
            <div class="rxn-module-actions">

                <a href="<?= htmlspecialchars((string) $helpPath) ?>" class="btn btn-outline-info" target="_blank" rel="noopener noreferrer"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="/mi-empresa/crm/pedidos-servicio/crear" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nuevo pedido</a>
                <a href="<?= htmlspecialchars((string) $dashboardPath) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver al CRM</a>
            </div>
        </div>

        <?php require BASE_PATH . '/app/shared/views/components/module_notes_panel.php'; ?>

        <?php $flash = \App\Core\Flash::get(); ?>
        <?php if ($flash): ?>
            <div class="rxn-flash-banner rxn-flash-banner-<?= htmlspecialchars((string) $flash['type']) ?> shadow-sm mb-4" role="alert">
                <div class="rxn-flash-icon"><i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill' : ($flash['type'] === 'warning' ? 'bi-exclamation-triangle-fill' : ($flash['type'] === 'danger' ? 'bi-x-circle-fill' : 'bi-info-circle-fill')) ?>"></i></div>
                <div class="flex-grow-1">
                    <div class="fw-bold mb-1"><?= ucfirst((string) $flash['type']) ?></div>
                    <div><?= htmlspecialchars((string) $flash['message']) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card rxn-crud-card">
            <div class="card-body p-4">
                <div class="rxn-toolbar-split mb-4">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge bg-dark text-light fs-6 py-2 px-3"><i class="bi bi-tools"></i> Total: <?= (int) $totalItems ?></span>
                        <span class="badge text-bg-light border py-2 px-3">Calculo neto = finalizado - inicio - descuento</span>
                    </div>
                    <div class="small text-muted">El cliente se resuelve desde la base hoy disponible y el pedido guarda snapshot local de cliente/articulo.</div>
                </div>

                <ul class="nav nav-tabs mb-4 border-secondary border-opacity-25" style="border-bottom-width: 2px;">
                    <li class="nav-item">
                        <a class="nav-link <?= $estado !== 'papelera' ? 'active fw-bold border-bottom-0' : 'text-muted border-0' ?>" href="?estado=">Activos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $estado === 'papelera' ? 'active fw-bold border-bottom-0 text-danger' : 'text-danger border-0' ?>" href="?estado=papelera"><i class="bi bi-trash"></i> Papelera</a>
                    </li>
                </ul>

                <div class="rxn-toolbar-split mb-3">
                    <div class="small text-muted">Buscador con sugerencias en vivo; el listado solo se filtra al confirmar.</div>
                    <form action="/mi-empresa/crm/pedidos-servicio" method="GET" class="rxn-filter-form justify-content-end flex-md-nowrap ms-md-auto" style="width: 980px; max-width: 100%;" data-search-form>
                        <input type="hidden" name="search" value="<?= htmlspecialchars((string) $search) ?>" data-search-hidden>
                        <select name="estado" class="form-select form-select-sm border-secondary rxn-filter-compact" style="width: 145px;" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <option value="abierto" <?= $estado === 'abierto' ? 'selected' : '' ?>>Abiertos</option>
                            <option value="finalizado" <?= $estado === 'finalizado' ? 'selected' : '' ?>>Finalizados</option>
                        </select>
                        <select name="limit" class="form-select form-select-sm border-secondary rxn-filter-compact" style="width: 80px;" onchange="this.form.submit()">
                            <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                        </select>
                        <select name="field" class="form-select form-select-sm border-secondary rxn-filter-compact rxn-search-field-wrap" style="width: 165px;" data-search-field>
                            <option value="all" <?= $field === 'all' ? 'selected' : '' ?>>Todos los campos</option>
                            <option value="numero" <?= $field === 'numero' ? 'selected' : '' ?>>Numero</option>
                            <option value="cliente" <?= $field === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                            <option value="solicito" <?= $field === 'solicito' ? 'selected' : '' ?>>Solicito</option>
                            <option value="articulo" <?= $field === 'articulo' ? 'selected' : '' ?>>Articulo</option>
                            <option value="clasificacion" <?= $field === 'clasificacion' ? 'selected' : '' ?>>Clasificacion</option>
                            <option value="usuario" <?= $field === 'usuario' ? 'selected' : '' ?>>Usuario</option>
                            <option value="estado" <?= $field === 'estado' ? 'selected' : '' ?>>Estado</option>
                        </select>
                        <div class="rxn-search-input-wrap rxn-filter-grow" style="width: 270px;">
                            <input type="text" class="form-control form-control-sm border-secondary" placeholder='🔎 Presioná F3 o "/" para buscar' value="<?= htmlspecialchars((string) $search) ?>" data-search-input data-suggestions-url="/mi-empresa/crm/pedidos-servicio/sugerencias" autocomplete="off">
                            <div class="rxn-search-suggestions d-none" data-search-suggestions></div>
                        </div>
                        <button type="submit" class="btn btn-secondary btn-sm text-white">Buscar</button>
                        <?php if ($search !== '' || $estado !== ''): ?>
                            <a href="/mi-empresa/crm/pedidos-servicio" class="btn btn-light btn-sm border">Limpiar</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="form-text rxn-search-help text-md-end mb-3">El formulario calcula duracion bruta y neta usando segundos reales del rango horario.</div>

                <?php if ($estado !== 'papelera'): ?>
                <div class="mb-3">
                    <button type="submit" form="hiddenFormBulk" formaction="/mi-empresa/crm/pedidos-servicio/eliminar-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="¿Enviar los pedidos seleccionados a la papelera?"><i class="bi bi-trash"></i> Eliminar Seleccionados</button>
                </div>
                <?php else: ?>
                <div class="mb-3 d-flex gap-2">
                    <button type="submit" form="hiddenFormBulk" formaction="/mi-empresa/crm/pedidos-servicio/restore-masivo" class="btn btn-outline-success btn-sm rxn-confirm-form" data-msg="¿Restaurar los pedidos seleccionados?"><i class="bi bi-arrow-counterclockwise"></i> Restaurar Seleccionados</button>
                    <button type="submit" form="hiddenFormBulk" formaction="/mi-empresa/crm/pedidos-servicio/force-delete-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="⚠️ ATENCIÓN: Acción irreversible. ¿Destruir definitivamente los pedidos seleccionados?"><i class="bi bi-x-circle"></i> Destruir Seleccionados</button>
                </div>
                <?php endif; ?>

                <form method="POST" id="hiddenFormBulk"></form>
                <div class="table-responsive rxn-table-responsive">
                    <table class="table table-hover align-middle table-sm rxn-crud-table" style="font-size: 0.92rem;">
                        <thead class="table-light">
                            <?php
                            $sortLink = function (string $fieldName, string $label) use ($buildQuery, $sort, $dir) {
                                $newDir = ($sort === $fieldName && $dir === 'ASC') ? 'DESC' : 'ASC';
                                $icon = ($sort === $fieldName) ? ($dir === 'ASC' ? '▲' : '▼') : '';
                                $href = '?' . $buildQuery(['sort' => $fieldName, 'dir' => $newDir]);
                                return '<a href="' . $href . '" class="rxn-sort-link"><span>' . $label . '</span><span class="rxn-sort-indicator">' . $icon . '</span></a>';
                            };
                            ?>
                            <tr>
                                <th style="width: 40px;" class="text-center">
                                    <input type="checkbox" class="form-check-input" id="selectAllCheckbox" onchange="document.querySelectorAll('.row-checkbox').forEach(e => e.checked = this.checked);">
                                </th>
                                <th class="rxn-filter-col" data-filter-field="numero"><?= $sortLink('numero', 'Numero') ?></th>
                                <th class="rxn-filter-col rxn-hide-mobile" data-filter-field="fecha_inicio"><?= $sortLink('fecha_inicio', 'Inicio') ?></th>
                                <th class="rxn-filter-col rxn-hide-mobile" data-filter-field="fecha_finalizado"><?= $sortLink('fecha_finalizado', 'Finalizado') ?></th>
                                <th class="rxn-filter-col" data-filter-field="cliente_nombre"><?= $sortLink('cliente_nombre', 'Cliente') ?></th>
                                <th class="rxn-filter-col rxn-hide-mobile" data-filter-field="solicito">Solicito</th>
                                <th class="rxn-filter-col" data-filter-field="articulo_nombre"><?= $sortLink('articulo_nombre', 'Articulo') ?></th>
                                <th class="rxn-filter-col rxn-hide-mobile" data-filter-field="clasificacion_codigo"><?= $sortLink('clasificacion_codigo', 'Clasificacion') ?></th>
                                <th class="rxn-filter-col rxn-hide-mobile" data-filter-field="usuario_nombre">Usuario</th>
                                <th class="rxn-hide-mobile"><?= $sortLink('duracion_neta_segundos', 'Tiempo neto') ?></th>
                                <th class="rxn-hide-mobile text-center" style="width: 70px;" title="Envíos por correo">Correo</th>
                                <th class="rxn-filter-col" data-filter-field="estado_codigo">Estado</th>
                                <th class="rxn-row-chevron-col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($pedidos === []): ?>
                                <tr>
                                    <td colspan="12" class="rxn-empty-state text-muted">
                                        <div class="mb-2 fs-3"><i class="bi bi-tools"></i></div>
                                        Todavia no hay pedidos de servicio cargados o no existen coincidencias con el filtro actual.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pedidos as $pedido): ?>
                                    <tr data-row-link="/mi-empresa/crm/pedidos-servicio/<?= (int) $pedido['id'] ?>/editar" data-copy-url="/mi-empresa/crm/pedidos-servicio/<?= (int) $pedido['id'] ?>/copiar" class="rxn-row-link">
                                        <td class="text-center" data-row-link-ignore>
                                            <input type="checkbox" class="form-check-input row-checkbox" name="ids[]" value="<?= (int) $pedido['id'] ?>" form="hiddenFormBulk">
                                        </td>
                                        <td class="fw-bold text-dark">
                                            #<?= (int) $pedido['numero'] ?>
                                            <?php if (!empty($pedido['nro_pedido']) || !empty($pedido['tango_nro_pedido'])): ?>
                                                <?php
                                                $_tangoEstado = isset($pedido['tango_estado']) && $pedido['tango_estado'] !== null
                                                    ? (int) $pedido['tango_estado']
                                                    : null;
                                                $_estadoMeta  = \App\Modules\CrmPedidosServicio\TangoPedidoEstado::meta($_tangoEstado);
                                                $_syncAt = !empty($pedido['tango_estado_sync_at'])
                                                    ? date('d/m/Y H:i', strtotime((string) $pedido['tango_estado_sync_at']))
                                                    : 'Nunca';
                                                // Priorizamos el NRO_PEDIDO resuelto desde Tango (formato legible X00652-...)
                                                // por sobre el nro_pedido legacy (que históricamente guardaba el ID crudo).
                                                $_nroPedidoMostrar = !empty($pedido['tango_nro_pedido'])
                                                    ? (string) $pedido['tango_nro_pedido']
                                                    : (string) $pedido['nro_pedido'];
                                                $_titleParts = [
                                                    'Pedido Tango: ' . $_nroPedidoMostrar,
                                                    'Estado: ' . $_estadoMeta['label'],
                                                    'Última sync: ' . $_syncAt,
                                                ];
                                                ?>
                                                <br><span class="badge bg-<?= htmlspecialchars($_estadoMeta['color']) ?> fw-normal mt-1"
                                                          title="<?= htmlspecialchars(implode(' · ', $_titleParts)) ?>"
                                                          style="font-size: 0.75rem;">
                                                    <i class="bi <?= htmlspecialchars($_estadoMeta['icon']) ?>"></i>
                                                    <?= htmlspecialchars($_nroPedidoMostrar) ?>
                                                    <?php if ($_tangoEstado !== null): ?>
                                                        · <?= htmlspecialchars($_estadoMeta['label']) ?>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-nowrap rxn-hide-mobile"><small><?= htmlspecialchars((string) $pedido['fecha_inicio']) ?></small></td>
                                        <td class="text-nowrap rxn-hide-mobile"><small><?= htmlspecialchars((string) ($pedido['fecha_finalizado'] ?? '--')) ?></small></td>
                                        <td class="text-wrap" style="max-width: 210px;"><?= htmlspecialchars((string) $pedido['cliente_nombre']) ?></td>
                                        <td class="rxn-hide-mobile"><?= htmlspecialchars((string) $pedido['solicito']) ?></td>
                                        <td class="text-wrap" style="max-width: 220px;"><?= htmlspecialchars((string) $pedido['articulo_nombre']) ?></td>
                                        <td class="rxn-hide-mobile">
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars((string) ($pedido['clasificacion_codigo'] ?: 'Sin clasificar')) ?></span>
                                        </td>
                                        <td class="rxn-hide-mobile">
                                            <small class="text-muted"><i class="bi bi-person"></i> <?= htmlspecialchars((string) ($pedido['usuario_nombre'] ?? '--')) ?></small>
                                        </td>
                                        <td class="rxn-hide-mobile">
                                            <div class="fw-semibold text-success"><?= htmlspecialchars((string) sprintf('%02d:%02d:%02d', intdiv((int) ($pedido['duracion_neta_segundos'] ?? 0), 3600), intdiv(((int) ($pedido['duracion_neta_segundos'] ?? 0) % 3600), 60), ((int) ($pedido['duracion_neta_segundos'] ?? 0) % 60))) ?></div>
                                            <small class="text-muted">Bruto: <?= htmlspecialchars((string) sprintf('%02d:%02d:%02d', intdiv((int) ($pedido['duracion_bruta_segundos'] ?? 0), 3600), intdiv(((int) ($pedido['duracion_bruta_segundos'] ?? 0) % 3600), 60), ((int) ($pedido['duracion_bruta_segundos'] ?? 0) % 60))) ?></small>
                                        </td>
                                        <td class="rxn-hide-mobile text-center" data-row-link-ignore>
                                            <?php
                                            $count = (int) ($pedido['correos_enviados_count'] ?? 0);
                                            $ultimoEnvio = $pedido['correos_ultimo_envio_at'] ?? null;
                                            $ultimoError = $pedido['correos_ultimo_error'] ?? null;
                                            $ultimoErrorAt = $pedido['correos_ultimo_error_at'] ?? null;
                                            include BASE_PATH . '/app/shared/views/components/correo_envio_badge.php';
                                            ?>
                                        </td>
                                        <td>
                                            <?php if (empty($pedido['fecha_finalizado'])): ?>
                                                <span class="badge bg-warning text-dark">Abierto</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Finalizado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="rxn-row-chevron-col text-end text-nowrap">
                                            <?php if ($estado === 'papelera'): ?>
                                                <form method="POST" action="/mi-empresa/crm/pedidos-servicio/<?= (int) $pedido['id'] ?>/restore" class="d-inline" data-row-link-ignore>
                                                    <button type="submit" class="btn btn-sm btn-outline-success py-0 px-2 fw-medium rxn-confirm-form" data-msg="¿Restaurar este pedido?" title="Restaurar"><i class="bi bi-arrow-counterclockwise"></i></button>
                                                </form>
                                                <form method="POST" action="/mi-empresa/crm/pedidos-servicio/<?= (int) $pedido['id'] ?>/force-delete" class="d-inline" data-row-link-ignore>
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 fw-medium rxn-confirm-form" data-msg="¿Destruir definitivamente este pedido?" title="Destruir"><i class="bi bi-x-circle"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="/mi-empresa/crm/pedidos-servicio/<?= (int) $pedido['id'] ?>/copiar" class="d-inline" data-row-link-ignore>
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-2 fw-medium" title="Copiar pedido (Usa pedido como plantilla)"><i class="bi bi-copy"></i></button>
                                                </form>
                                                <?php if (empty($pedido['nro_pedido'])): ?>
                                                <form method="POST" action="/mi-empresa/crm/pedidos-servicio/<?= (int) $pedido['id'] ?>/eliminar" class="d-inline" data-row-link-ignore>
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 fw-medium rxn-confirm-form" data-msg="¿Enviar este pedido a la papelera?" title="Eliminar"><i class="bi bi-trash"></i></button>
                                                </form>
                                                <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 fw-medium" onclick="(window.rxnAlert || alert)('El PDS fue enviado a Tango. No se puede eliminar.', 'danger', 'Operación bloqueada'); event.stopPropagation();" title="No se puede eliminar"><i class="bi bi-trash"></i></button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <a href="/mi-empresa/crm/pedidos-servicio/<?= (int) $pedido['id'] ?>/editar" class="btn btn-sm btn-outline-primary py-0 px-2 fw-medium rxn-row-link-action rxn-row-chevron" title="Abrir pedido" aria-label="Abrir pedido" data-row-link-ignore>›</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="mt-4 rxn-pagination-wrap">
                        <ul class="pagination justify-content-center pagination-sm">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= htmlspecialchars($buildQuery(['page' => max(1, $page - 1)])) ?>">Anterior</a>
                            </li>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= htmlspecialchars($buildQuery(['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= htmlspecialchars($buildQuery(['page' => min($totalPages, $page + 1)])) ?>">Siguiente</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    
<?php
$content = ob_get_clean();
ob_start();
?>
<script src="/js/rxn-advanced-filters.js"></script>
    <script src="/js/rxn-crud-search.js"></script>
    <script src="/js/rxn-confirm-modal.js"></script>
    <script src="/js/rxn-row-links.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
