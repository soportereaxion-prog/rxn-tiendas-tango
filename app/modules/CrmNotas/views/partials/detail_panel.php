<?php
/**
 * Partial: panel derecho del split view de Notas CRM.
 * Devuelve el detalle de una nota SIN admin_layout — se inyecta via innerHTML.
 *
 * Variables esperadas:
 *   $nota (CrmNota)   — entidad completa con joins a cliente y tratativa
 *   $indexPath (string) — '/mi-empresa/crm/notas'
 */

/** @var \App\Modules\CrmNotas\CrmNota $nota */
/** @var string $indexPath */
?>
<div class="notas-detail" data-nota-id="<?= (int) $nota->id ?>">
    <div class="rxn-module-header mb-3 pb-3 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div class="min-w-0 flex-grow-1">
            <span class="badge <?= $nota->activo ? 'bg-success' : 'bg-danger' ?> mb-2"><?= $nota->activo ? 'Activa' : 'Inactiva' ?></span>
            <h2 class="h4 fw-bold mb-1 text-break"><i class="bi bi-file-text"></i> <?= htmlspecialchars($nota->titulo) ?></h2>
            <p class="text-muted mb-0 small">
                <i class="bi bi-calendar3"></i> Creada el <?= date('d/m/Y H:i', strtotime($nota->created_at)) ?>
                <?php if ($nota->updated_at && $nota->updated_at !== $nota->created_at): ?>
                    · Actualizada <?= date('d/m/Y H:i', strtotime($nota->updated_at)) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= htmlspecialchars($indexPath) ?>/<?= (int) $nota->id ?>/editar" class="btn btn-outline-info btn-sm" data-nota-edit>
                <i class="bi bi-pencil"></i> Editar
            </a>
            <form action="<?= htmlspecialchars($indexPath) ?>/<?= (int) $nota->id ?>/copiar" method="POST" class="rxn-confirm-form m-0" data-msg="¿Copiar nota (duplicar registro)?">
                <button type="submit" class="btn btn-outline-success btn-sm" title="Copiar">
                    <i class="bi bi-files"></i> Copiar
                </button>
            </form>
            <?php if ($nota->deleted_at ?? null): ?>
                <form action="<?= htmlspecialchars($indexPath) ?>/<?= (int) $nota->id ?>/restore" method="POST" class="rxn-confirm-form m-0" data-msg="¿Restaurar esta nota?">
                    <button type="submit" class="btn btn-outline-success btn-sm" title="Restaurar">
                        <i class="bi bi-arrow-counterclockwise"></i> Restaurar
                    </button>
                </form>
            <?php else: ?>
                <form action="<?= htmlspecialchars($indexPath) ?>/<?= (int) $nota->id ?>/eliminar" method="POST" class="rxn-confirm-form m-0" data-msg="¿Enviar nota a la papelera?">
                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Enviar a papelera">
                        <i class="bi bi-trash"></i> Papelera
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card bg-dark text-light border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row align-items-start g-3 mb-3">
                <div class="col-md-4 border-end border-secondary border-opacity-25">
                    <h6 class="text-muted text-uppercase small fw-bold mb-2">Cliente</h6>
                    <?php if ($nota->cliente_id): ?>
                        <div class="d-flex align-items-center">
                            <div class="bg-secondary bg-opacity-25 text-info rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 36px; height: 36px;">
                                <i class="bi bi-person-badge"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="fw-bold text-truncate"><?= htmlspecialchars((string) $nota->cliente_nombre) ?></div>
                                <small class="text-muted">Cód: <?= htmlspecialchars((string) ($nota->cliente_codigo ?? '')) ?></small>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-muted small"><i class="bi bi-dash-circle"></i> Sin cliente</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 border-end border-secondary border-opacity-25">
                    <h6 class="text-muted text-uppercase small fw-bold mb-2">Tratativa</h6>
                    <?php if (!empty($nota->tratativa_id)): ?>
                        <a href="/mi-empresa/crm/tratativas/<?= (int) $nota->tratativa_id ?>" class="text-decoration-none d-flex align-items-center text-light">
                            <div class="bg-primary bg-opacity-25 text-primary rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 36px; height: 36px;">
                                <i class="bi bi-briefcase-fill"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="fw-bold">#<?= (int) ($nota->tratativa_numero ?? 0) ?></div>
                                <small class="text-muted text-truncate d-block"><?= htmlspecialchars((string) ($nota->tratativa_titulo ?? '')) ?></small>
                            </div>
                        </a>
                    <?php else: ?>
                        <div class="text-muted small"><i class="bi bi-dash-circle"></i> Sin tratativa</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <h6 class="text-muted text-uppercase small fw-bold mb-2">Etiquetas</h6>
                    <?php if (!empty($nota->tags)): ?>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach (explode(',', $nota->tags) as $tag): $tag = trim($tag); if ($tag === '') continue; ?>
                                <span class="badge bg-secondary"><?= htmlspecialchars($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <span class="text-muted small">Ninguna</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-black bg-opacity-25 rounded p-3 border border-secondary border-opacity-10 position-relative">
                <button type="button" class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2" data-nota-copy-content>
                    <i class="bi bi-clipboard"></i> Copiar
                </button>
                <div class="text-light" data-nota-content style="white-space: pre-wrap; font-size: 1rem; line-height: 1.55;"><?= htmlspecialchars($nota->contenido) ?></div>
            </div>
        </div>
    </div>

    <?php
        $ownerType = 'crm_nota';
        $ownerId   = (int) $nota->id;
        $panelTitle = 'Archivos adjuntos';
        include BASE_PATH . '/app/shared/views/partials/attachments-panel.php';
    ?>
</div>
