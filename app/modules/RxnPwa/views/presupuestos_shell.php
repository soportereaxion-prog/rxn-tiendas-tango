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
</head>
<body>

    <header class="rxnpwa-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <img src="/icons/rxnpwa-192.png" alt="" width="32" height="32" style="border-radius: 6px;">
            <div>
                <div class="fw-bold">RXN PWA</div>
                <div class="small text-muted">Presupuestos en campo</div>
            </div>
        </div>
        <a href="/mi-empresa/crm/dashboard" class="btn btn-sm btn-outline-light" title="Volver al backoffice">
            <i class="bi bi-box-arrow-up-right"></i>
        </a>
    </header>

    <main class="rxnpwa-shell">

        <div id="rxnpwa-status" aria-live="polite">
            <div class="alert alert-info mb-3">⏳ Comprobando estado del catálogo offline…</div>
        </div>

        <div id="rxnpwa-actions" class="rxnpwa-card">
            <h2 class="h6 mb-2">
                <i class="bi bi-cloud-arrow-down"></i> Preparar app para uso offline
            </h2>
            <p class="small text-muted mb-3">
                Descarga el catálogo completo (clientes, artículos, precios, listas, condiciones, transportes,
                vendedores, depósitos y clasificaciones) en este dispositivo. Lo necesitás antes de salir a campo.
            </p>
            <button type="button" class="btn btn-primary w-100" data-rxnpwa-sync>
                <i class="bi bi-arrow-repeat"></i> Sincronizar catálogo ahora
            </button>
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
            <h2 class="h6 mb-2">
                <i class="bi bi-cloud-upload"></i> Cola de envío
            </h2>
            <div class="rxnpwa-placeholder">
                <i class="bi bi-cone-striped fs-2 d-block mb-2"></i>
                <strong>Próximamente — Fase 3</strong>
                <div class="small mt-1">
                    Reconciliación con server al volver online.
                </div>
            </div>
        </div>

        <div class="text-center small text-muted mt-4">
            Empresa #<?= (int) $empresaId ?> · RXN Suite
        </div>

    </main>

    <script src="/js/pwa/rxnpwa-catalog-store.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-drafts-store.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-register.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-shell-drafts.js?v=<?= time() ?>"></script>
</body>
</html>
