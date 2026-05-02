<?php
/** @var int $empresaId */
/** @var string $pageTitle */
$pageTitle = $pageTitle ?? 'RXN PWA — Horas';
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
    <link rel="icon" type="image/png" sizes="192x192" href="/img/pwa/rxnpwa-192.png">
    <link rel="apple-touch-icon" href="/img/pwa/rxnpwa-192.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/rxnpwa.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/css/rxn-fullscreen.css?v=<?= time() ?>">
</head>
<body>

    <header class="rxnpwa-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <?php require BASE_PATH . '/app/modules/RxnPwa/views/_brand_icon.php'; ?>
            <div>
                <div class="fw-bold">RXN PWA</div>
                <div class="small text-muted">Turnero</div>
            </div>
        </div>
        <div class="d-flex gap-1">
            <a href="/rxnpwa" class="btn btn-sm btn-outline-light" title="Menú PWA">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-light"
                    data-rxn-fullscreen-toggle title="Pantalla completa" aria-pressed="false">
                <i class="bi bi-fullscreen"></i>
            </button>
            <a href="/mi-empresa/crm/dashboard" class="btn btn-sm btn-outline-light" title="Volver al backoffice">
                <i class="bi bi-box-arrow-up-right"></i>
            </a>
        </div>
    </header>

    <main class="rxnpwa-shell">

        <!-- Total trabajado hoy (espejo del desktop) -->
        <div class="rxnpwa-card text-center mb-3">
            <div class="text-muted small mb-1">Hoy llevás trabajadas</div>
            <div class="fw-bold display-5" id="rxnpwa-horas-total">00:00:00</div>
        </div>

        <!-- Estado de la PWA -->
        <div id="rxnpwa-status" class="rxnpwa-card rxnpwa-card-compact mb-3" aria-live="polite">
            <div class="small text-muted">⏳ Comprobando catálogo…</div>
        </div>

        <!-- Botón principal contextual: Iniciar / Cerrar -->
        <div id="rxnpwa-horas-cron-card" class="rxnpwa-card mb-3">
            <div class="rxnpwa-placeholder small">Cargando estado del turno…</div>
        </div>

        <!-- Acciones secundarias -->
        <div class="d-flex gap-2 mb-3">
            <a href="/rxnpwa/horas/nuevo" class="btn btn-outline-light btn-sm flex-grow-1">
                <i class="bi bi-clock-history"></i> Cargar diferido
            </a>
            <button type="button" class="btn btn-outline-light btn-sm flex-grow-1" data-rxnpwa-sync>
                <i class="bi bi-arrow-repeat"></i> Sincronizar
            </button>
        </div>

        <!-- Mis turnos del día (drafts locales + cerrados) -->
        <div class="rxnpwa-card mb-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h2 class="h6 mb-0"><i class="bi bi-list-ul"></i> Mis turnos</h2>
                <span id="rxnpwa-horas-net-badge" class="badge bg-secondary"><i class="bi bi-wifi"></i> —</span>
            </div>
            <div id="rxnpwa-horas-drafts-list">
                <div class="rxnpwa-placeholder small">Cargando…</div>
            </div>
        </div>

        <!-- Cola de sincronización -->
        <div class="rxnpwa-card">
            <h2 class="h6 mb-2"><i class="bi bi-cloud-upload"></i> Cola de envío</h2>
            <div id="rxnpwa-horas-queue-summary">
                <div class="rxnpwa-placeholder small">Sin elementos en cola.</div>
            </div>
        </div>

        <div class="text-center small text-muted mt-4">
            Empresa #<?= (int) $empresaId ?> · RXN Suite
        </div>

    </main>

    <script src="/js/rxn-fullscreen.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-error-collector.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-geo-gate.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-catalog-store.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-horas-drafts-store.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-image-compressor.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-horas-sync-queue.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-register.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-horas-shell.js?v=<?= time() ?>"></script>
</body>
</html>
