<?php ob_start(); ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-5">
                    <h2 class="fw-bolder mb-4 text-center">Registro de Cliente</h2>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger rounded-3"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form action="/<?= htmlspecialchars($empresa_slug) ?>/registro" method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-medium text-secondary">Nombre <span class="text-danger">*</span></label>
                                <input type="text" name="nombre" class="form-control form-control-lg bg-light border-0" required autofocus>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium text-secondary">Apellido</label>
                                <input type="text" name="apellido" class="form-control form-control-lg bg-light border-0">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-medium text-secondary">Correo Electrónico <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control form-control-lg bg-light border-0" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-medium text-secondary">Teléfono</label>
                                <input type="text" name="telefono" class="form-control form-control-lg bg-light border-0">
                            </div>
                            <div class="col-12 mb-4">
                                <label class="form-label fw-medium text-secondary">Contraseña <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control form-control-lg bg-light border-0" required minlength="6">
                                <div class="form-text">Si ya tenías compras previas asociadas a este mail, recuperaremos tu historial automáticamente.</div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-dark w-100 py-3 rounded-3 fw-bold mb-3">Crear Cuenta</button>
                        <div class="text-center">
                            <span class="text-muted">¿Ya tienes cuenta?</span>
                            <a href="/<?= htmlspecialchars($empresa_slug) ?>/login" class="text-dark fw-bold text-decoration-none">Inicia sesión</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php 
$content = ob_get_clean(); 
require __DIR__ . '/../layout.php'; 
?>
