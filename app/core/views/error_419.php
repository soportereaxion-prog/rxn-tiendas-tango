<?php
/**
 * @var string $next  URL segura a la que volver después de re-loguear (puede ser '').
 */
$next = isset($next) && is_string($next) ? $next : '';
$loginUrl = '/login?expired=1';
if ($next !== '') {
    $loginUrl .= '&next=' . urlencode($next);
}
?>
<!DOCTYPE html>
<html lang="es" <?= class_exists('\\App\\Core\\Helpers\\UIHelper') ? \App\Core\Helpers\UIHelper::getHtmlAttributes() : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesión expirada · Suite RXN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="rxn-page-shell rxn-auth-screen">
    <div class="container rxn-responsive-container rxn-form-shell-sm rxn-auth-shell">
        <div class="text-center mb-4">
            <p class="rxn-auth-eyebrow mb-3">Suite RXN</p>
            <h2 class="fw-bold rxn-auth-title">Sesión expirada</h2>
            <p class="text-muted mb-0 rxn-auth-subtitle">
                El formulario expiró por inactividad o el token de seguridad ya no es válido.
                No es nada grave — volvé a iniciar sesión y seguimos.
            </p>
        </div>

        <div class="card rxn-form-card rxn-auth-card p-4 p-lg-5">
            <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary w-100 fw-bold mb-2">
                Iniciar sesión nuevamente
            </a>
            <button type="button" class="btn btn-outline-secondary w-100" onclick="history.back()">
                Volver atrás
            </button>

            <div class="text-center mt-4 pt-3 border-top rxn-auth-footer">
                <a href="https://reaxion.com.ar/" target="_blank" rel="noopener noreferrer" class="text-decoration-none small d-block mb-2">reaxion.com.ar</a>
                <a href="mailto:soporte@reaxion.com.ar" class="text-decoration-none text-muted small">soporte@reaxion.com.ar</a>
            </div>
        </div>
    </div>
</body>
</html>
