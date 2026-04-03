<?php
$pageTitle = 'Formularios de Impresion - rxn_suite';
ob_start();
?>
    <div class="container mt-5 rxn-responsive-container">
        <div class="rxn-module-header mb-4">
            <div>
                <h2>CRM - Formularios de Impresion</h2>
                <p class="text-muted mb-0">Canvas versionables para disenar salidas impresas de la plataforma sin depender de motores externos tipo Crystal.</p>
            </div>
            <div class="rxn-module-actions">
                
                <a href="<?= htmlspecialchars((string) $helpPath) ?>" class="btn btn-outline-info" target="_blank" rel="noopener noreferrer"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="<?= htmlspecialchars((string) $dashboardPath) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver al CRM</a>
            </div>
        </div>

        <?php require BASE_PATH . '/app/shared/views/components/module_notes_panel.php'; ?>

        <?php $flash = \App\Core\Flash::get(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars((string) $flash['type']) ?> shadow-sm mb-4" role="alert">
                <?= htmlspecialchars((string) $flash['message']) ?>
            </div>
        <?php endif; ?>

        <div class="alert alert-secondary border-0 rounded-4 shadow-sm mb-4">
            Este modulo estandariza la definicion de formularios de impresion de la plataforma. El primer consumidor operativo es <strong>Presupuesto CRM</strong>, pero la mecanica queda preparada para futuros documentos.
        </div>

        <div class="row g-4">
            <?php foreach ($documents as $document): ?>
                <?php $activeVersion = $document['active_version'] ?? null; ?>
                <div class="col-lg-6">
                    <div class="card rxn-form-card h-100 shadow-sm">
                        <div class="card-body p-4 d-flex flex-column gap-3">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="small text-uppercase text-muted fw-bold mb-2">Documento</div>
                                    <h4 class="mb-2"><?= htmlspecialchars((string) ($document['label'] ?? 'Formulario')) ?></h4>
                                    <p class="text-muted mb-0"><?= htmlspecialchars((string) ($document['description'] ?? '')) ?></p>
                                </div>
                                <span class="badge rounded-pill text-bg-dark"><?= $activeVersion ? 'Version activa' : 'Sin definir' ?></span>
                            </div>

                            <div class="row g-3 small">
                                <div class="col-sm-4">
                                    <div class="text-muted text-uppercase fw-bold mb-1">Document key</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($document['document_key'] ?? '')) ?></div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="text-muted text-uppercase fw-bold mb-1">Version</div>
                                    <div class="fw-semibold"><?= $activeVersion ? '#' . (int) ($activeVersion['version'] ?? 0) : '--' ?></div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="text-muted text-uppercase fw-bold mb-1">Ultima actualizacion</div>
                                    <div class="fw-semibold"><?= $activeVersion ? htmlspecialchars((string) ($activeVersion['created_at'] ?? '--')) : '--' ?></div>
                                </div>
                            </div>

                            <div class="mt-auto pt-2 d-flex flex-wrap gap-2">
                                <?php
                                $docKey = $document['document_key'] ?? '';
                                $isPds = strpos($docKey, 'pds') !== false;
                                $goUrl = $isPds ? '/mi-empresa/crm/pedidos-servicio' : '/mi-empresa/crm/presupuestos';
                                $goLabel = $isPds ? 'Ir a Pedidos de Servicio' : 'Ir a Presupuestos';
                                ?>
                                <a href="<?= htmlspecialchars((string) $basePath) ?>/<?= rawurlencode((string) $docKey) ?>" class="btn btn-primary"><i class="bi bi-vector-pen"></i> Editar canvas</a>
                                <a href="<?= htmlspecialchars((string) $goUrl) ?>" class="btn btn-outline-secondary"><?= htmlspecialchars((string) $goLabel) ?></a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
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

