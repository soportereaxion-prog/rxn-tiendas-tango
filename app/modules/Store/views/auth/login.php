<?php ob_start(); ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-5">
                    <h2 class="fw-bolder mb-4 text-center">Ingreso a tu Cuenta</h2>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger rounded-3"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form action="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>/login<?= !empty($_GET['next']) ? '?next='.urlencode($_GET['next']) : '' ?>" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-medium text-secondary">Correo Electrónico</label>
                            <input type="email" name="email" class="form-control form-control-lg bg-light border-0" required autofocus>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-medium text-secondary">Contraseña</label>
                            <input type="password" name="password" class="form-control form-control-lg bg-light border-0" required>
                        </div>
                        <button type="submit" class="btn btn-dark w-100 py-3 rounded-3 fw-bold mb-3">Iniciar Sesión</button>
                        <div class="text-center">
                            <span class="text-muted">¿No tienes cuenta?</span>
                            <a href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>/registro" class="text-dark fw-bold text-decoration-none">Regístrate aquí</a>
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
