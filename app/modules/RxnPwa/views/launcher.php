<?php
/**
 * RXN PWA — Launcher / sub-menú con todas las PWAs disponibles offline.
 *
 * Patrón: cada vez que sumamos una nueva PWA mobile (Presupuestos, Horas, y
 * las que vengan), agregamos una card acá. El operador entra a /rxnpwa desde
 * el banner del backoffice y elige cuál abrir.
 *
 * Pre-cacheado por el SW para que también funcione offline después del primer
 * load — el operador en campo sin red puede entrar igual y elegir entre las
 * PWAs ya descargadas.
 *
 * @var int $empresaId
 * @var string $pageTitle
 */
$pageTitle = $pageTitle ?? 'RXN PWA';

// Catálogo de PWAs disponibles. Para sumar una nueva basta con agregar una
// entrada acá. Convención: la clave coincide con el slug de la URL.
$pwaApps = [
    [
        'key' => 'presupuestos',
        'title' => 'Presupuestos',
        'desc' => 'Cotizá en campo offline con cliente, adjuntos y cámara. Sincroniza al volver online y emite a Tango.',
        'icon' => 'bi-receipt',
        'color' => 'primary',
        'link' => '/rxnpwa/presupuestos',
    ],
    [
        'key' => 'horas',
        'title' => 'Horas',
        'desc' => 'Turnero mobile para registrar horas en campo. Iniciar/cerrar turno con un toque, descuentos y certificados.',
        'icon' => 'bi-stopwatch',
        'color' => 'success',
        'link' => '/rxnpwa/horas',
    ],
];
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <meta name="rxn-empresa-id" content="<?= (int) $empresaId ?>">
    <meta name="csrf-token" content="<?= htmlspecialchars(\App\Core\CsrfHelper::generateToken()) ?>">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/rxnpwa-192.png">
    <link rel="apple-touch-icon" href="/icons/rxnpwa-192.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/rxnpwa.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/css/rxn-fullscreen.css?v=<?= time() ?>">
    <style>
        .rxnpwa-launcher-card {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 1rem;
            background: var(--rxnpwa-card-bg);
            border: 1px solid var(--rxnpwa-card-border);
            border-radius: 0.75rem;
            text-decoration: none;
            color: var(--rxnpwa-text);
            transition: transform 0.12s ease, border-color 0.12s ease;
        }
        .rxnpwa-launcher-card:hover,
        .rxnpwa-launcher-card:active {
            color: var(--rxnpwa-text);
            border-color: #475569;
            transform: translateY(-1px);
        }
        .rxnpwa-launcher-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border-radius: 14px;
            font-size: 1.6rem;
            color: #fff;
            flex-shrink: 0;
        }
        .rxnpwa-launcher-icon.bg-primary { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .rxnpwa-launcher-icon.bg-success { background: linear-gradient(135deg, #10b981, #047857); }
        .rxnpwa-launcher-icon.bg-warning { background: linear-gradient(135deg, #f59e0b, #b45309); }
        .rxnpwa-launcher-icon.bg-info    { background: linear-gradient(135deg, #06b6d4, #0e7490); }
    </style>
</head>
<body>

    <header class="rxnpwa-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <?php require BASE_PATH . '/app/modules/RxnPwa/views/_brand_icon.php'; ?>
            <div>
                <div class="fw-bold">RXN PWA</div>
                <div class="small text-muted">Mobile Suite</div>
            </div>
        </div>
        <div class="d-flex gap-1">
            <button type="button" class="btn btn-sm btn-outline-light"
                    data-rxn-fullscreen-toggle
                    title="Pantalla completa"
                    aria-pressed="false">
                <i class="bi bi-fullscreen"></i>
            </button>
            <a href="/mi-empresa/crm/dashboard" class="btn btn-sm btn-outline-light" title="Volver al backoffice">
                <i class="bi bi-box-arrow-up-right"></i>
            </a>
        </div>
    </header>

    <main class="rxnpwa-shell">

        <div class="text-center mb-3">
            <h1 class="h5 fw-bold mb-1">Apps disponibles</h1>
            <p class="small text-muted mb-0">Tocá una para abrirla. Funcionan también sin red después del primer uso.</p>
        </div>

        <div class="d-flex flex-column gap-2 mb-4">
            <?php foreach ($pwaApps as $app): ?>
                <a href="<?= htmlspecialchars($app['link']) ?>" class="rxnpwa-launcher-card">
                    <span class="rxnpwa-launcher-icon bg-<?= htmlspecialchars($app['color']) ?>">
                        <i class="bi <?= htmlspecialchars($app['icon']) ?>"></i>
                    </span>
                    <div class="flex-grow-1">
                        <div class="fw-bold mb-1"><?= htmlspecialchars($app['title']) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars($app['desc']) ?></div>
                    </div>
                    <i class="bi bi-chevron-right text-muted flex-shrink-0"></i>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="rxnpwa-card rxnpwa-card-compact">
            <div class="small text-muted">
                <i class="bi bi-info-circle"></i> Más PWAs próximamente. Cada módulo que se sume aparece automáticamente acá.
            </div>
        </div>

        <div class="text-center small text-muted mt-4">
            Empresa #<?= (int) $empresaId ?> · RXN Suite
        </div>

    </main>

    <!-- Helper global de fullscreen + persistencia. -->
    <script src="/js/rxn-fullscreen.js?v=<?= time() ?>"></script>
    <!-- Geo gate: el operador necesita GPS habilitado para entrar a cualquier PWA. -->
    <script src="/js/pwa/rxnpwa-geo-gate.js?v=<?= time() ?>"></script>
    <!-- Registro del SW para que el launcher mismo quede cacheado offline. -->
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => { /* silent */ });
        }
    </script>
</body>
</html>
