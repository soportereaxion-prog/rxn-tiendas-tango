<?php
use App\Core\View;

$pageTitle = ($formMode === 'edit' ? 'Editar' : 'Nuevo') . ' Evento - Agenda CRM';
$evento = $evento ?? [];
$errors = $errors ?? [];
$formMode = $formMode ?? 'create';
$formAction = $formAction ?? '/mi-empresa/crm/agenda';

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . htmlspecialchars((string) $errors[$field]) . '</div>' : '';
};

ob_start();
?>

<main class="container-fluid flex-grow-1 px-4 mb-5" style="max-width: 900px;">
    <div class="rxn-module-header mb-4 pb-3 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 fw-bold mb-1">
                <i class="bi bi-calendar-plus"></i>
                <?= $formMode === 'edit' ? 'Editar Evento' : 'Nuevo Evento Manual' ?>
            </h1>
            <p class="text-muted small mb-0">Los eventos manuales se crean directamente desde acá. Los de PDS, Presupuestos y Tratativas se proyectan automáticamente.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver al calendario</a>
        </div>
    </div>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle"></i> Revisá los errores en el formulario antes de guardar.</div>
    <?php endif; ?>

    <form action="<?= htmlspecialchars($formAction) ?>" method="POST" autocomplete="off">
        <div class="card bg-dark text-light border-0 shadow-sm mb-4">
            <div class="card-header border-bottom border-secondary border-opacity-25">
                <h5 class="mb-0 fw-bold"><i class="bi bi-info-circle"></i> Datos del evento</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-9">
                        <label class="form-label small text-muted">Título *</label>
                        <input type="text" name="titulo" class="form-control bg-dark text-light border-secondary <?= isset($errors['titulo']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars((string) $evento['titulo']) ?>" maxlength="200" required>
                        <?= $fieldError('titulo') ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Color</label>
                        <input type="color" name="color" class="form-control form-control-color bg-dark border-secondary" value="<?= htmlspecialchars((string) $evento['color']) ?>" style="width:100%; height:38px;">
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-muted">Descripción</label>
                        <textarea name="descripcion" class="form-control bg-dark text-light border-secondary" rows="3"><?= htmlspecialchars((string) $evento['descripcion']) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-muted">Ubicación</label>
                        <input type="text" name="ubicacion" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars((string) $evento['ubicacion']) ?>" maxlength="255" placeholder="Ej: oficina del cliente, Meet, teléfono">
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-dark text-light border-0 shadow-sm mb-4">
            <div class="card-header border-bottom border-secondary border-opacity-25">
                <h5 class="mb-0 fw-bold"><i class="bi bi-clock"></i> Ventana temporal</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label small text-muted">Inicio *</label>
                        <input type="datetime-local" name="inicio" class="form-control bg-dark text-light border-secondary <?= isset($errors['inicio']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars((string) $evento['inicio']) ?>" required>
                        <?= $fieldError('inicio') ?>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small text-muted">Fin *</label>
                        <input type="datetime-local" name="fin" class="form-control bg-dark text-light border-secondary <?= isset($errors['fin']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars((string) $evento['fin']) ?>" required>
                        <?= $fieldError('fin') ?>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="all_day" id="all_day" value="1" <?= !empty($evento['all_day']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="all_day">Todo el día</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Estado</label>
                        <select name="estado" class="form-select bg-dark text-light border-secondary">
                            <option value="programado" <?= ($evento['estado'] ?? 'programado') === 'programado' ? 'selected' : '' ?>>Programado</option>
                            <option value="en_curso" <?= ($evento['estado'] ?? '') === 'en_curso' ? 'selected' : '' ?>>En curso</option>
                            <option value="completado" <?= ($evento['estado'] ?? '') === 'completado' ? 'selected' : '' ?>>Completado</option>
                            <option value="cancelado" <?= ($evento['estado'] ?? '') === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between gap-2">
            <?php if ($formMode === 'edit' && !empty($evento['id'])): ?>
                <form action="<?= htmlspecialchars($basePath) ?>/<?= (int) $evento['id'] ?>/eliminar" method="POST" class="rxn-confirm-form" data-msg="¿Eliminar este evento? También se borra del Google Calendar sincronizado.">
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> Eliminar</button>
                </form>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
            <div class="d-flex gap-2">
                <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i> Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> <?= $formMode === 'edit' ? 'Guardar cambios' : 'Crear evento' ?></button>
            </div>
        </div>
    </form>
</main>

<?php
$content = ob_get_clean();
ob_start();
?>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
