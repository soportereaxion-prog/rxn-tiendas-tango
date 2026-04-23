<?php
use App\Core\View;

$pageTitle = ($formMode === 'edit' ? 'Editar' : 'Nueva') . ' Tratativa - rxn_suite';
$tratativa = $tratativa ?? [];
$errors = $errors ?? [];
$formAction = $formAction ?? '/mi-empresa/crm/tratativas';
$formMode = $formMode ?? 'create';

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . htmlspecialchars((string) $errors[$field]) . '</div>' : '';
};

$estadoOpciones = [
    'nueva' => 'Nueva',
    'en_curso' => 'En curso',
    'ganada' => 'Ganada',
    'perdida' => 'Perdida',
    'pausada' => 'Pausada',
];

ob_start();
?>

<main class="container-fluid flex-grow-1 px-4 mb-5 crm-tratativas-shell">
    <div class="rxn-module-header mb-4 pb-3 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 fw-bold mb-1">
                <i class="bi bi-briefcase-fill"></i>
                <?= $formMode === 'edit' ? 'Editar Tratativa #' . (int) $tratativa['numero'] : 'Nueva Tratativa (Preview #' . (int) $tratativa['numero'] . ')' ?>
            </h1>
            <p class="text-muted small mb-0">Agrupá un cliente, un estado comercial y vinculá tus PDS y Presupuestos bajo un mismo caso.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-outline-secondary btn-sm" title="Volver al listado de Tratativas" data-rxn-back><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
    </div>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle"></i> Revisá los errores en el formulario antes de guardar.</div>
    <?php endif; ?>

    <form action="<?= htmlspecialchars($formAction) ?>" method="POST" id="tratativa-form" autocomplete="off">
        <div class="card bg-dark text-light border-0 shadow-sm mb-4">
            <div class="card-header border-bottom border-secondary border-opacity-25">
                <h5 class="mb-0 fw-bold"><i class="bi bi-info-circle"></i> Datos generales</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label small text-muted">Título *</label>
                        <input type="text" name="titulo" class="form-control bg-dark text-light border-secondary <?= isset($errors['titulo']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars((string) $tratativa['titulo']) ?>" maxlength="200" required>
                        <?= $fieldError('titulo') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Estado *</label>
                        <select name="estado" class="form-select bg-dark text-light border-secondary <?= isset($errors['estado']) ? 'is-invalid' : '' ?>" required>
                            <?php foreach ($estadoOpciones as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>" <?= $tratativa['estado'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $fieldError('estado') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-muted">Descripción</label>
                        <textarea name="descripcion" class="form-control bg-dark text-light border-secondary" rows="3" placeholder="Contexto del caso: cómo surgió, qué necesita el cliente, quién lo originó..."><?= htmlspecialchars((string) $tratativa['descripcion']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-dark text-light border-0 shadow-sm mb-4">
            <div class="card-header border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-person-vcard"></i> Cliente</h5>
                <span class="small text-muted">Presioná <kbd>Enter</kbd> o <kbd>F3</kbd> en el campo para abrir el buscador</span>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-10 position-relative" data-picker-url="<?= htmlspecialchars($basePath) ?>/clientes/sugerencias">
                        <label class="form-label small text-muted">Cliente (opcional)</label>
                        <input
                            type="text"
                            id="cliente_search"
                            data-picker-input
                            class="form-control bg-dark text-light border-secondary <?= isset($errors['cliente_id']) ? 'is-invalid' : '' ?>"
                            value="<?= htmlspecialchars((string) $tratativa['cliente_nombre']) ?>"
                            placeholder="Buscar cliente por razón social, código Tango, documento o email (Enter / F3 / doble click)"
                            autocomplete="off"
                        >
                        <input type="hidden" name="cliente_id" data-picker-hidden value="<?= htmlspecialchars((string) $tratativa['cliente_id']) ?>">
                        <input type="hidden" name="cliente_nombre" id="cliente_nombre_hidden" value="<?= htmlspecialchars((string) $tratativa['cliente_nombre']) ?>">
                        <?= $fieldError('cliente_id') ?>
                    </div>
                    <div class="col-md-2">
                        <button type="button" id="clear-cliente" class="btn btn-outline-secondary w-100"><i class="bi bi-x-circle"></i> Limpiar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-dark text-light border-0 shadow-sm mb-4">
            <div class="card-header border-bottom border-secondary border-opacity-25">
                <h5 class="mb-0 fw-bold"><i class="bi bi-bar-chart"></i> Valor y pronóstico</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Probabilidad (%)</label>
                        <input type="number" name="probabilidad" class="form-control bg-dark text-light border-secondary <?= isset($errors['probabilidad']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars((string) $tratativa['probabilidad']) ?>" min="0" max="100" step="5">
                        <?= $fieldError('probabilidad') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Valor estimado</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark text-light border-secondary">$</span>
                            <input type="number" name="valor_estimado" class="form-control bg-dark text-light border-secondary <?= isset($errors['valor_estimado']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars((string) $tratativa['valor_estimado']) ?>" min="0" step="0.01">
                        </div>
                        <?= $fieldError('valor_estimado') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Responsable</label>
                        <input type="text" class="form-control bg-dark text-muted border-secondary" value="<?= htmlspecialchars((string) $tratativa['usuario_nombre']) ?>" readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-dark text-light border-0 shadow-sm mb-4">
            <div class="card-header border-bottom border-secondary border-opacity-25">
                <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-event"></i> Fechas</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Apertura</label>
                        <input type="date" name="fecha_apertura" class="form-control bg-dark text-light border-secondary <?= isset($errors['fecha_apertura']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars((string) $tratativa['fecha_apertura']) ?>">
                        <?= $fieldError('fecha_apertura') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Cierre estimado</label>
                        <input type="date" name="fecha_cierre_estimado" class="form-control bg-dark text-light border-secondary <?= isset($errors['fecha_cierre_estimado']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars((string) $tratativa['fecha_cierre_estimado']) ?>">
                        <?= $fieldError('fecha_cierre_estimado') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Cierre real</label>
                        <input type="date" name="fecha_cierre_real" class="form-control bg-dark text-light border-secondary <?= isset($errors['fecha_cierre_real']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars((string) $tratativa['fecha_cierre_real']) ?>">
                        <?= $fieldError('fecha_cierre_real') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-muted">Motivo de cierre (obligatorio si estado es Ganada / Perdida)</label>
                        <textarea name="motivo_cierre" class="form-control bg-dark text-light border-secondary <?= isset($errors['motivo_cierre']) ? 'is-invalid' : '' ?>" rows="2"><?= htmlspecialchars((string) $tratativa['motivo_cierre']) ?></textarea>
                        <?= $fieldError('motivo_cierre') ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i> Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> <?= $formMode === 'edit' ? 'Guardar cambios' : 'Crear tratativa' ?></button>
        </div>
    </form>
</main>

<?php
$content = ob_get_clean();
ob_start();
?>
<script>
    // El spotlight global (rxn-spotlight.js cargado en admin_layout.php) maneja
    // la búsqueda de cliente vía el wrapper [data-picker-url] + input [data-picker-input]
    // + hidden [data-picker-hidden]. Acá solo enganchamos el botón "Limpiar" y
    // mantenemos sincronizado el hidden legacy cliente_nombre_hidden con el visible.
    (function () {
        const searchInput = document.getElementById('cliente_search');
        const hiddenName = document.getElementById('cliente_nombre_hidden');
        const btnClear = document.getElementById('clear-cliente');
        const hiddenIdEl = document.querySelector('[data-picker-hidden][name="cliente_id"]');

        if (!searchInput) { return; }

        // Cuando el spotlight selecciona un item, dispara "picker-selected" en el input.
        // Aprovechamos para mantener el snapshot legacy de cliente_nombre sincronizado.
        searchInput.addEventListener('picker-selected', (ev) => {
            const data = ev.detail || {};
            if (hiddenName) {
                hiddenName.value = data.label || data.value || searchInput.value || '';
            }
        });

        // Si el operador escribe a mano (sin elegir del modal), desvinculamos el id
        // para que el backend no persista un vínculo con un cliente que no existe.
        searchInput.addEventListener('input', () => {
            if (hiddenIdEl) { hiddenIdEl.value = ''; }
            if (hiddenName) { hiddenName.value = ''; }
        });

        btnClear && btnClear.addEventListener('click', () => {
            searchInput.value = '';
            if (hiddenIdEl) { hiddenIdEl.value = ''; }
            if (hiddenName) { hiddenName.value = ''; }
            searchInput.focus();
        });
    })();
</script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
