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

        /* Textos de descripción / hints sobre fondo dark. text-muted de Bootstrap
           queda casi invisible. Subimos al 70% blanco para legibilidad. */
        .rxnpwa-launcher-card .small.text-muted,
        .rxnpwa-launcher-text {
            color: rgba(255, 255, 255, 0.72) !important;
        }
        .rxnpwa-launcher-footer {
            color: rgba(255, 255, 255, 0.55) !important;
        }
        /* Subtítulo "Tocá una para abrirla..." debajo del título principal. */
        main.rxnpwa-shell > .text-center .text-muted {
            color: rgba(255, 255, 255, 0.7) !important;
        }
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

        <!-- Instalar como app. Botón siempre visible:
              - Si el browser disparó beforeinstallprompt → llama al diálogo nativo.
              - Si no (HTTP local, iOS Safari, browser sin support) → muestra
                instrucciones manuales paso a paso. -->
        <div id="rxnpwa-install-card" class="rxnpwa-card rxnpwa-card-compact mb-3">
            <div class="d-flex align-items-center gap-2">
                <div class="flex-grow-1">
                    <div class="fw-bold small mb-1"><i class="bi bi-download"></i> Instalá la app en el celular</div>
                    <div class="small rxnpwa-launcher-text">Sin barra del navegador y abre directo desde el escritorio.</div>
                </div>
                <button type="button" class="btn btn-primary btn-sm flex-shrink-0" id="rxnpwa-install-btn">
                    Instalar
                </button>
            </div>
        </div>

        <!-- Modal con instrucciones manuales (para cuando beforeinstallprompt no disparó). -->
        <div class="modal fade" id="rxnpwa-install-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
                <div class="modal-content bg-dark text-light">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-download"></i> Instalar como app</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="rxnpwa-install-reason" class="alert alert-warning small mb-3"></div>

                        <!-- Android Chrome / Edge -->
                        <div id="rxnpwa-install-android" class="d-none">
                            <h6 class="fw-bold"><i class="bi bi-android2"></i> Android (Chrome / Edge)</h6>
                            <ol class="mb-0">
                                <li>Tocá los 3 puntos <i class="bi bi-three-dots-vertical"></i> arriba a la derecha del navegador.</li>
                                <li>Buscá <strong>"Instalar app"</strong> o <strong>"Agregar a pantalla de inicio"</strong>.</li>
                                <li>Tocá <strong>Instalar</strong>. La app queda en el escritorio del celu.</li>
                            </ol>
                        </div>

                        <!-- iOS Safari -->
                        <div id="rxnpwa-install-ios" class="d-none mt-3">
                            <h6 class="fw-bold"><i class="bi bi-apple"></i> iPhone / iPad (Safari)</h6>
                            <ol class="mb-0">
                                <li>Tocá <i class="bi bi-box-arrow-up"></i> <strong>Compartir</strong> en la barra inferior.</li>
                                <li>Bajá y tocá <strong>"Agregar a pantalla de inicio"</strong>.</li>
                                <li>Confirmá con <strong>Agregar</strong>. La app queda en el escritorio.</li>
                            </ol>
                        </div>

                        <!-- Genérico para cualquier otro -->
                        <div id="rxnpwa-install-generic" class="d-none mt-3">
                            <h6 class="fw-bold"><i class="bi bi-globe"></i> Otros navegadores</h6>
                            <p class="mb-0">Buscá en el menú del navegador la opción <strong>"Instalar app"</strong> o <strong>"Agregar a pantalla de inicio"</strong>. Está en el menú de los 3 puntos / hamburguesa.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="rxnpwa-card rxnpwa-card-compact">
            <div class="small rxnpwa-launcher-text">
                <i class="bi bi-info-circle"></i> Más PWAs próximamente. Cada módulo que se sume aparece automáticamente acá.
            </div>
        </div>

        <div class="text-center small rxnpwa-launcher-footer mt-4">
            Empresa #<?= (int) $empresaId ?> · RXN Suite
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Botón "Instalar" híbrido:
        //  - Si beforeinstallprompt llegó → llama al prompt nativo de Chrome/Edge.
        //  - Si NO llegó (HTTP local, iOS Safari, browser sin soporte) → abre modal
        //    con instrucciones manuales adaptadas al UA detectado.
        //
        // Por qué no llega siempre: el evento solo dispara en HTTPS o localhost +
        // manifest válido + SW activo + criterios de engagement. En LAN HTTP plano
        // (ej: 192.168.x.x) Chrome NO lo dispara, sin importar el manifest.
        (function () {
            'use strict';
            let deferredPrompt = null;
            const installCard = document.getElementById('rxnpwa-install-card');
            const installBtn = document.getElementById('rxnpwa-install-btn');

            // Si ya está corriendo en standalone, ocultar todo el bloque.
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true;
            if (isStandalone) {
                if (installCard) installCard.classList.add('d-none');
                return;
            }

            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
            });

            if (installBtn) {
                installBtn.addEventListener('click', async () => {
                    // Camino 1: el browser nos entregó el prompt nativo.
                    if (deferredPrompt) {
                        try {
                            deferredPrompt.prompt();
                            const { outcome } = await deferredPrompt.userChoice;
                            deferredPrompt = null;
                            if (outcome === 'accepted' && installCard) {
                                installCard.classList.add('d-none');
                            }
                            return;
                        } catch (e) {
                            // fallback al modal manual
                        }
                    }
                    // Camino 2: modal con instrucciones manuales según UA.
                    showManualInstallModal();
                });
            }

            function showManualInstallModal() {
                const ua = navigator.userAgent || '';
                const isIOS = /iPhone|iPad|iPod/.test(ua) && !window.MSStream;
                const isAndroid = /Android/.test(ua);
                const isSafariMobile = isIOS && /^((?!CriOS|FxiOS|EdgiOS).)*Safari/.test(ua);
                const isHttpInsecure = location.protocol === 'http:' && location.hostname !== 'localhost' && !location.hostname.startsWith('127.');

                const reason = document.getElementById('rxnpwa-install-reason');
                const androidBlock = document.getElementById('rxnpwa-install-android');
                const iosBlock = document.getElementById('rxnpwa-install-ios');
                const genericBlock = document.getElementById('rxnpwa-install-generic');

                // Mensaje contextual.
                if (isHttpInsecure) {
                    reason.innerHTML = '<i class="bi bi-shield-exclamation"></i> <strong>Servidor sin HTTPS</strong> — el navegador no ofrece el botón automático de instalación en HTTP plano (LAN local). Igual podés instalarla manualmente:';
                } else {
                    reason.innerHTML = '<i class="bi bi-info-circle"></i> Tu navegador no ofreció el diálogo automático. Probá instalarla manualmente:';
                }

                // Mostrar bloques relevantes.
                androidBlock.classList.toggle('d-none', !isAndroid);
                iosBlock.classList.toggle('d-none', !isSafariMobile);
                genericBlock.classList.toggle('d-none', isAndroid || isSafariMobile);

                const modal = new bootstrap.Modal(document.getElementById('rxnpwa-install-modal'));
                modal.show();
            }

            // Si ya quedó instalada después (otro tab), ocultar el card.
            window.addEventListener('appinstalled', () => {
                if (installCard) installCard.classList.add('d-none');
                deferredPrompt = null;
            });
        })();
    </script>

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
