<?php
/** @var array $registros */
?>
<!-- Toolbar de búsqueda y filtros client-side -->
<div class="d-flex gap-2 mb-3 align-items-center flex-wrap">
    <input type="text" id="rxnsync-search-art" class="form-control form-control-sm border-info"
           placeholder="🔎 Buscar (F3 o /)..." style="max-width:260px;"
           data-search-input autocomplete="off">
    <select id="rxnsync-filter-estado-art" class="form-select form-select-sm" style="width:170px;">
        <option value="">🔽 Todos los estados</option>
        <option value="vinculado">✅ Vinculados</option>
        <option value="pendiente">⏳ Pendientes</option>
        <option value="conflicto">❌ Conflicto</option>
    </select>
    <small class="text-muted ms-auto" id="rxnsync-art-count"></small>
</div>

<div class="table-responsive text-start">
    <table class="table table-hover table-sm align-middle view-table" id="rxnsync-table-art">
        <thead class="table-light">
            <tr>
                <th style="width:40px;" class="text-center">
                    <input type="checkbox" id="rxnsync-select-all-art" class="form-check-input" title="Seleccionar todos">
                </th>
                <th class="rxnsync-sortable" data-col="estado">Estado <span class="rxnsync-sort-icon"></span></th>
                <th class="rxnsync-sortable" data-col="codigo">Código <span class="rxnsync-sort-icon"></span></th>
                <th>ID Tango</th>
                <th class="rxnsync-sortable" data-col="nombre">Nombre <span class="rxnsync-sort-icon"></span></th>
                <th class="rxnsync-sortable" data-col="fecha">Última Sync <span class="rxnsync-sort-icon"></span></th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody id="rxnsync-tbody-art">
            <?php if (empty($registros)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        No hay información de auditoría para Artículos. Ejecute una Auditoría desde el tab.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($registros as $row): ?>
                <tr data-estado="<?= htmlspecialchars($row['estado']) ?>"
                    data-nombre="<?= htmlspecialchars(strtolower((string)$row['nombre'])) ?>"
                    data-codigo="<?= htmlspecialchars(strtolower((string)$row['codigo'])) ?>"
                    data-fecha="<?= htmlspecialchars((string)($row['fecha_ultima_sync'] ?? '')) ?>">
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input rxnsync-row-check"
                               data-id="<?= (int)$row['local_id'] ?>"
                               data-pivot-id="<?= (int)$row['id'] ?>">
                    </td>
                    <td>
                        <?php if ($row['estado'] === 'vinculado'): ?>
                            <span class="badge bg-success rounded-pill px-2"><i class="fas fa-check-circle me-1"></i>Vinculado</span>
                        <?php elseif ($row['estado'] === 'pendiente'): ?>
                            <span class="badge bg-warning text-dark rounded-pill px-2"><i class="fas fa-clock me-1"></i>Pendiente</span>
                        <?php else: ?>
                            <span class="badge bg-danger rounded-pill px-2"><i class="fas fa-exclamation-triangle me-1"></i>Conflicto</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap font-monospace text-secondary small"><?= htmlspecialchars((string)$row['codigo']) ?></td>
                    <td class="font-monospace text-muted small"><?= $row['tango_id'] ? '#' . $row['tango_id'] : '<span class="text-muted">–</span>' ?></td>
                    <td class="fw-medium"><?= htmlspecialchars((string)$row['nombre']) ?></td>
                    <td>
                        <div class="d-flex flex-column">
                            <span class="text-muted small">
                                <?= $row['fecha_ultima_sync'] ? date('d/m/Y H:i', strtotime($row['fecha_ultima_sync'])) : 'Nunca' ?>
                            </span>
                            <?php if (!empty($row['direccion_ultima_sync']) && $row['direccion_ultima_sync'] !== 'none'): ?>
                                <small>
                                    <span class="badge bg-<?= $row['resultado_ultima_sync'] === 'ok' ? 'success' : 'danger' ?> opacity-75">
                                        <?= strtoupper($row['direccion_ultima_sync']) ?> <?= strtoupper($row['resultado_ultima_sync']) ?>
                                    </span>
                                </small>
                            <?php endif; ?>
                            <?php if ($row['mensaje_error']): ?>
                                <small class="text-danger mt-1 text-truncate" style="max-width:220px;" title="<?= htmlspecialchars($row['mensaje_error']) ?>">
                                    <i class="fas fa-info-circle"></i> <?= htmlspecialchars($row['mensaje_error']) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-warning btn-push-tango"
                                    data-id="<?= (int)$row['local_id'] ?>"
                                    data-entidad="articulo"
                                    data-nombre="<?= htmlspecialchars((string)$row['nombre']) ?>"
                                    title="Push → Tango"
                                    <?= $row['estado'] === 'pendiente' ? 'disabled' : '' ?>>
                                <i class="bi bi-cloud-upload"></i>
                            </button>
                            <button class="btn btn-outline-info btn-pull-tango"
                                    data-id="<?= (int)$row['local_id'] ?>"
                                    data-entidad="articulo"
                                    data-nombre="<?= htmlspecialchars((string)$row['nombre']) ?>"
                                    title="Pull ← Tango"
                                    <?= $row['estado'] !== 'vinculado' ? 'disabled' : '' ?>>
                                <i class="bi bi-cloud-download"></i>
                            </button>
                            <button class="btn btn-outline-secondary btn-payload-info"
                                    data-id="<?= (int)$row['local_id'] ?>"
                                    data-entidad="articulo"
                                    title="Ver último payload Tango">
                                <i class="bi bi-info-circle"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
