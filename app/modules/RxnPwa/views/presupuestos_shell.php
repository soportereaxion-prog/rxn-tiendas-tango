<?php
/** @var int $empresaId */
/** @var string $pageTitle */
$pageTitle = $pageTitle ?? 'RXN PWA';
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
</head>
<body>

    <header class="rxnpwa-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <?php require BASE_PATH . '/app/modules/RxnPwa/views/_brand_icon.php'; ?>
            <div>
                <div class="fw-bold">RXN PWA</div>
                <div class="small text-muted">Presupuestos</div>
            </div>
        </div>
        <div class="d-flex gap-1">
            <a href="/rxnpwa" class="btn btn-sm btn-outline-light" title="Menú PWA">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
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

        <!-- Header del shell: 2 cajas en grid — info de catálogo (izq) + acción sync (der).
             En mobile (sm-) se apilan; en pantallas más anchas quedan lado a lado. -->
        <div class="row g-2 mb-3 rxnpwa-header-grid">
            <div class="col-7">
                <div id="rxnpwa-status" class="rxnpwa-card rxnpwa-card-compact h-100" aria-live="polite">
                    <div class="small text-muted">⏳ Comprobando catálogo…</div>
                </div>
            </div>
            <div class="col-5">
                <div class="rxnpwa-card rxnpwa-card-compact h-100 d-flex align-items-center justify-content-center">
                    <button type="button" class="btn btn-sm btn-primary w-100" data-rxnpwa-sync>
                        <i class="bi bi-arrow-repeat"></i> Sincronizar
                    </button>
                </div>
            </div>
        </div>

        <div class="rxnpwa-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h2 class="h6 mb-0">
                    <i class="bi bi-receipt"></i> Mis borradores
                </h2>
                <a href="/rxnpwa/presupuestos/nuevo" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg"></i> Nuevo
                </a>
            </div>
            <div id="rxnpwa-drafts-list">
                <div class="rxnpwa-placeholder small">Cargando...</div>
            </div>
        </div>

        <div class="rxnpwa-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h2 class="h6 mb-0">
                    <i class="bi bi-cloud-upload"></i> Cola de envío
                </h2>
                <span id="rxnpwa-queue-net-badge" class="badge bg-secondary"><i class="bi bi-wifi"></i> —</span>
            </div>
            <div id="rxnpwa-queue-summary">
                <div class="rxnpwa-placeholder small">Sin elementos en cola.</div>
            </div>
        </div>

        <div class="text-center small text-muted mt-4">
            Empresa #<?= (int) $empresaId ?> · RXN Suite
        </div>

    </main>

    <!-- Helper global de fullscreen + persistencia (release 1.42.0). -->
    <script src="/js/rxn-fullscreen.js?v=<?= time() ?>"></script>
    <!-- Geo gate ANTES que todos los demás — bloquea la PWA si no hay GPS. -->
    <script src="/js/pwa/rxnpwa-geo-gate.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-catalog-store.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-drafts-store.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-sync-queue.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-register.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-shell-drafts.js?v=<?= time() ?>"></script>
</body>
</html>
