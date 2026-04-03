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
        
        <div class="rxn-module-header mb-4 pb-3 border-bottom border-secondary border-opacity-25">
            <div>
                <h1 class="h3 fw-bold mb-1"><i class="bi bi-file-earmark-excel"></i> Importar Masivamente</h1>
                
            </div>
            <div>
                <a href="<?= htmlspecialchars($indexPath) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card bg-dark text-light border-0 shadow pt-3 px-2 pb-4">
            <div class="card-body">
                
                <div class="alert alert-info bg-dark text-info border-info border-opacity-25 d-flex align-items-center mb-4 justify-content-between">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-info-circle fs-4 me-3"></i>
                        <div>
                            <strong>Estructura esperada del Excel:</strong> 
                            El archivo debe contener las siguientes columnas (el orden no importa):<br>
                            <code>titulo</code> | <code>contenido</code> | <code>tags</code> | <code>codigo_tango</code>
                        </div>
                    </div>
                    <a href="<?= htmlspecialchars($indexPath) ?>/importar/plantilla" class="btn btn-sm btn-outline-info fw-bold" target="_blank">
                        <i class="bi bi-download"></i> Descargar Matriz Excel
                    </a>
                </div>

                <form action="<?= htmlspecialchars($indexPath) ?>/importar" method="POST" enctype="multipart/form-data" class="needs-validation">
                    
                    <h5 class="fw-bold border-bottom border-secondary pb-2 mb-3 text-white"><i class="bi bi-cloud-upload"></i> Subir Archivo</h5>
                    
                    <div class="mb-4">
                        <label class="form-label text-muted small">Archivo (.xlsx)</label>
                        <input type="file" name="archivo" class="form-control bg-dark text-light border-secondary" accept=".xlsx" required>
                        <div class="form-text text-secondary"><i class="bi bi-file-earmark-spreadsheet"></i> El proceso validará automáticamente el código Tango contra los clientes del CRM.</div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input bg-dark border-secondary" type="checkbox" role="switch" name="crear_huerfanas" id="switchHuerfanas" checked>
                            <label class="form-check-label text-light ms-2" for="switchHuerfanas">Crear notas huérfanas si el <code>codigo_tango</code> no existe en DB local</label>
                        </div>
                        <div class="form-text text-secondary small ms-5">Si se desactiva, las notas sin cliente válido serán ignoradas.</div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top border-secondary border-opacity-25">
                        <a href="<?= htmlspecialchars($indexPath) ?>" class="btn btn-outline-secondary px-4"><i class="bi bi-x-circle"></i> Cancelar</a>
                        <button type="submit" class="btn btn-success px-5 fw-bold text-white shadow-sm">
                            <i class="bi bi-play-circle"></i> Iniciar Importación
                        </button>
                    </div>
                </form>
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
