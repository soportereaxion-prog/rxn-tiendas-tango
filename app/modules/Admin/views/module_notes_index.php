<?php
$pageTitle = 'RXN Suite';
ob_start();
?>
<div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell" style="max-width: 1120px;">
        <div class="rxn-module-header mb-4">
            <div>
                <h2 class="fw-bold mb-1">Bitacora de Modulos</h2>
                
            </div>
            <div class="rxn-module-actions">
                
                <span class="badge text-bg-dark px-3 py-2"><?= (int) ($totalNotes ?? 0) ?> notas</span>
                <a href="/admin/dashboard" class="btn btn-outline-secondary btn-sm" title="Volver al Backoffice"><i class="bi bi-arrow-left"></i> Volver</a>
            </div>
        </div>

        <?php if (is_array($flash ?? null) && !empty($flash['message'])): ?>
            <div class="alert alert-<?= htmlspecialchars((string) ($flash['type'] ?? 'info')) ?> mb-4">
                <?= htmlspecialchars((string) $flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($modules)): ?>
            <div class="card rxn-form-card border-0 shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="h5 fw-bold mb-2">Todavia no hay anotaciones</h3>
                    
                </div>
            </div>
        <?php else: ?>
            <div class="d-grid gap-4">
                <?php foreach ($modules as $module): ?>
                    <div class="card rxn-form-card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                                <div>
                                    <h3 class="h5 fw-bold mb-1"><?= htmlspecialchars((string) ($module['label'] ?? 'Modulo')) ?></h3>
                                    <p class="text-muted small mb-0">
                                        Ultima actualizacion: <?= htmlspecialchars(\App\Shared\Services\ModuleNoteService::formatTimestamp((string) ($module['updated_at'] ?? ''))) ?>
                                    </p>
                                </div>
                                <span class="badge rounded-pill text-bg-secondary px-3 py-2"><?= (int) ($module['count'] ?? 0) ?> registro<?= ((int) ($module['count'] ?? 0) === 1) ? '' : 's' ?></span>
                            </div>

                            <div class="d-grid gap-3">
                                <?php foreach (($module['notes'] ?? []) as $note): ?>
                                    <?php $attachments = is_array($note['attachments'] ?? null) ? $note['attachments'] : []; ?>
                                    <div class="border rounded-3 p-3 bg-light bg-opacity-50">
                                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2">
                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                <span class="badge <?= htmlspecialchars(\App\Shared\Services\ModuleNoteService::typeBadgeClass((string) ($note['type'] ?? 'idea'))) ?>">
                                                    <?= htmlspecialchars(\App\Shared\Services\ModuleNoteService::typeLabel((string) ($note['type'] ?? 'idea'))) ?>
                                                </span>
                                                <span class="small text-muted"><?= htmlspecialchars(\App\Shared\Services\ModuleNoteService::formatTimestamp((string) ($note['created_at'] ?? ''))) ?></span>
                                            </div>
                                            <span class="small text-muted">por <?= htmlspecialchars((string) ($note['author_name'] ?? 'Administrador')) ?></span>
                                        </div>
                                        <?php if ((string) ($note['content'] ?? '') !== ''): ?>
                                            <div class="small mb-3" style="white-space: pre-wrap;"><?= htmlspecialchars((string) ($note['content'] ?? '')) ?></div>
                                        <?php endif; ?>

                                        <?php if ($attachments !== []): ?>
                                            <div class="row g-3">
                                                <?php foreach ($attachments as $attachment): ?>
                                                    <?php $attachmentPath = (string) ($attachment['path'] ?? ''); ?>
                                                    <?php if ($attachmentPath === '') { continue; } ?>
                                                    <div class="col-12 col-md-6 col-lg-4">
                                                        <div class="border rounded p-2 h-100 ">
                                                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                                                <span class="badge text-bg-dark"><?= htmlspecialchars((string) ($attachment['label'] ?? '#imagen')) ?></span>
                                                                <?php if (!empty($attachment['name'])): ?>
                                                                    <span class="small text-muted text-truncate"><?= htmlspecialchars((string) $attachment['name']) ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <a href="<?= htmlspecialchars($attachmentPath) ?>" target="_blank" rel="noopener noreferrer" class="d-inline-block text-decoration-none">
                                                                <img src="<?= htmlspecialchars($attachmentPath) ?>" alt="Captura adjunta" class="img-fluid rounded border" style="max-height: 220px; width: 100%; object-fit: cover;">
                                                            </a>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
<?php
$content = ob_get_clean();
ob_start();
?>
<script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
