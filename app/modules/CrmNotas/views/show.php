<?php
use App\Core\Helpers\UIHelper;
use App\Core\View;
$ui = isset($environmentLabel) ? compact('environmentLabel', 'dashboardPath') : [];
?>
<?php
$pageTitle = 'RXN Tiendas IA';
ob_start();
?>


    <main class="container-fluid flex-grow-1 px-4 mb-5" style="max-width: 900px; margin: 0 auto;">
        
        <div class="rxn-module-header mb-4 pb-3 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-end">
            <div>
                <span class="badge <?= $nota->activo ? 'bg-success' : 'bg-danger' ?> mb-2"><?= $nota->activo ? 'Activa' : 'Inactiva' ?></span>
                <h1 class="h3 fw-bold mb-1"><i class="bi bi-file-text"></i> <?= htmlspecialchars($nota->titulo) ?></h1>
                <p class="text-muted mb-0 small">
                    <i class="bi bi-calendar3"></i> Creada el <?= date('d/m/Y H:i', strtotime($nota->created_at)) ?> 
                    <?php if ($nota->updated_at && $nota->updated_at !== $nota->created_at): ?>
                        (Actualizada: <?= date('d/m/Y H:i', strtotime($nota->updated_at)) ?>)
                    <?php endif; ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= htmlspecialchars($indexPath) ?>/<?= $nota->id ?>/editar" class="btn btn-outline-info"><i class="bi bi-pencil"></i> Editar</a>
                <a href="<?= htmlspecialchars($indexPath) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
            </div>
        </div>

        <?php 
        $moduleNotesKey = 'crm_notas';
        $moduleNotesLabel = 'CRM - Notas';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php'; 
        ?>

        <div class="card bg-dark text-light border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row align-items-center mb-4">
                    <div class="col-md-6 border-end border-secondary border-opacity-25">
                        <h6 class="text-muted text-uppercase small fw-bold mb-2">Cliente Vinculado</h6>
                        <?php if ($nota->cliente_id): ?>
                            <div class="d-flex align-items-center">
                                <div class="bg-secondary bg-opacity-25 text-info rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="bi bi-person-badge fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold"><?= htmlspecialchars((string) $nota->cliente_nombre) ?></h6>
                                    <small class="text-muted">ID Sistema: <?= htmlspecialchars((string) $nota->cliente_id) ?> | Cód: <?= htmlspecialchars((string) $nota->cliente_codigo) ?></small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-muted"><i class="bi bi-dash-circle"></i> Sin cliente vinculado</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 ps-4">
                        <h6 class="text-muted text-uppercase small fw-bold mb-2">Etiquetas</h6>
                        <?php if (!empty($nota->tags)): ?>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach (explode(',', $nota->tags) as $tag): ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars(trim($tag)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="text-muted small">Ninguna</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-black bg-opacity-25 rounded p-4 border border-secondary border-opacity-10 position-relative">
                    <button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2" onclick="navigator.clipboard.writeText(document.getElementById('notaContenido').innerText); this.innerHTML='<i class=\'bi bi-check2\'></i> Copiado'">
                        <i class="bi bi-clipboard"></i> Copiar
                    </button>
                    <div id="notaContenido" class="text-light" style="white-space: pre-wrap; font-size: 1.05rem; line-height: 1.6;"><?= htmlspecialchars($nota->contenido) ?></div>
                </div>
            </div>
        </div>
        
    </main>
    
<?php
$content = ob_get_clean();
ob_start();
?>
<script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
