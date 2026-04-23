<?php
/**
 * Partial: items de la columna izquierda del split view.
 * Se renderiza inicialmente con index.php y también se recarga via fetch
 * cuando cambia la búsqueda o la paginación.
 *
 * Variables esperadas:
 *   $items (array)      — filas de notas con joins
 *   $totalItems (int)
 *   $totalPages (int)
 *   $page (int)
 *   $indexPath (string)
 *   $isPapelera (bool)
 */

/** @var array $items */
/** @var int $totalItems */
/** @var int $totalPages */
/** @var int $page */
/** @var string $indexPath */
/** @var bool $isPapelera */
?>
<div class="notas-list-items" data-total="<?= (int) $totalItems ?>" data-page="<?= (int) $page ?>" data-total-pages="<?= (int) $totalPages ?>">
    <?php if (empty($items)): ?>
        <div class="text-center p-4 text-muted small">
            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
            No hay notas que coincidan con los filtros.
        </div>
    <?php else: ?>
        <ul class="list-unstyled notas-list-ul mb-0">
            <?php foreach ($items as $item): ?>
                <?php
                    $id = (int) $item['id'];
                    $fecha = !empty($item['created_at']) ? date('d/m/Y', strtotime($item['created_at'])) : '';
                    $tagsArr = [];
                    if (!empty($item['tags'])) {
                        foreach (explode(',', (string) $item['tags']) as $t) {
                            $t = trim($t);
                            if ($t !== '') $tagsArr[] = $t;
                        }
                    }
                ?>
                <li class="notas-list-item border-bottom border-secondary border-opacity-10" data-nota-id="<?= $id ?>">
                    <div class="d-flex align-items-start gap-2 p-2 notas-list-row" role="button" tabindex="0">
                        <div class="pt-1">
                            <input type="checkbox" name="ids[]" value="<?= $id ?>" class="form-check-input check-item" form="hiddenFormBulk" onclick="event.stopPropagation();">
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between align-items-baseline gap-2">
                                <div class="fw-semibold text-light text-truncate" title="<?= htmlspecialchars($item['titulo']) ?>">
                                    <?= htmlspecialchars($item['titulo']) ?>
                                    <?php if (!empty($item['activo']) && (int) $item['activo'] === 0): ?>
                                        <span class="badge bg-danger ms-1" style="font-size: 0.65rem;">Inactiva</span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted flex-shrink-0" style="font-size: 0.7rem;"><?= htmlspecialchars($fecha) ?></small>
                            </div>
                            <div class="small text-muted text-truncate">
                                <?php if (!empty($item['cliente_nombre'])): ?>
                                    <i class="bi bi-person-badge"></i> <?= htmlspecialchars($item['cliente_nombre']) ?>
                                <?php else: ?>
                                    <span class="fst-italic">sin cliente</span>
                                <?php endif; ?>
                                <?php if (!empty($item['tratativa_id'])): ?>
                                    · <i class="bi bi-briefcase"></i> #<?= (int) ($item['tratativa_numero'] ?? 0) ?>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($tagsArr)): ?>
                                <div class="mt-1 d-flex flex-wrap gap-1">
                                    <?php foreach (array_slice($tagsArr, 0, 3) as $tag): ?>
                                        <span class="badge bg-secondary bg-opacity-50" style="font-size: 0.65rem;"><?= htmlspecialchars($tag) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($tagsArr) > 3): ?>
                                        <span class="badge bg-secondary bg-opacity-25" style="font-size: 0.65rem;" title="<?= htmlspecialchars(implode(', ', array_slice($tagsArr, 3))) ?>">+<?= count($tagsArr) - 3 ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center p-2 border-top border-secondary border-opacity-25 bg-dark small">
            <span class="text-muted" style="font-size: 0.75rem;">Pág. <?= (int) $page ?> / <?= (int) $totalPages ?> · <?= (int) $totalItems ?> notas</span>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" data-notas-page="<?= max(1, $page - 1) ?>" <?= $page <= 1 ? 'disabled' : '' ?>>
                    <i class="bi bi-chevron-left"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary" data-notas-page="<?= min($totalPages, $page + 1) ?>" <?= $page >= $totalPages ? 'disabled' : '' ?>>
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>
