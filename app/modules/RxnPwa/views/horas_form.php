<?php
/** @var int $empresaId */
/** @var string $tmpUuid */
/** @var string $pageTitle */
$pageTitle = $pageTitle ?? 'Cargar turno';
$tmpUuid = $tmpUuid ?? '';
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <meta name="rxn-empresa-id" content="<?= (int) $empresaId ?>">
    <meta name="csrf-token" content="<?= htmlspecialchars(\App\Core\CsrfHelper::generateToken()) ?>">
    <title><?= htmlspecialchars($pageTitle) ?> — RXN PWA</title>

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
            <a href="/rxnpwa/horas" class="btn btn-sm btn-outline-light" title="Volver"><i class="bi bi-arrow-left"></i></a>
            <?php require BASE_PATH . '/app/modules/RxnPwa/views/_brand_icon.php'; ?>
            <div>
                <div class="fw-bold" id="rxnpwa-horas-form-title">Cargar turno</div>
                <div class="small text-muted" id="rxnpwa-horas-form-subtitle">Borrador local</div>
            </div>
        </div>
        <div class="d-flex gap-1">
            <button type="button" class="btn btn-sm btn-outline-light"
                    data-rxn-fullscreen-toggle title="Pantalla completa" aria-pressed="false">
                <i class="bi bi-fullscreen"></i>
            </button>
            <button type="button" class="btn btn-sm btn-success" id="rxnpwa-horas-save" title="Guardar borrador">
                <i class="bi bi-save"></i>
            </button>
        </div>
    </header>

    <main class="rxnpwa-shell" data-tmp-uuid="<?= htmlspecialchars($tmpUuid) ?>" data-empresa-id="<?= (int) $empresaId ?>">

        <div id="rxnpwa-horas-form-status" aria-live="polite"></div>

        <!-- Fechas -->
        <section class="rxnpwa-card">
            <h2 class="h6 mb-3"><i class="bi bi-clock"></i> Inicio y fin</h2>
            <div class="row g-2">
                <div class="col-12">
                    <label class="form-label small" for="rxnpwa-horas-inicio">Inicio</label>
                    <input type="datetime-local" step="1" class="form-control" id="rxnpwa-horas-inicio">
                </div>
                <div class="col-12">
                    <label class="form-label small" for="rxnpwa-horas-fin">Fin</label>
                    <input type="datetime-local" step="1" class="form-control" id="rxnpwa-horas-fin">
                </div>
            </div>
            <div class="small text-muted mt-2" id="rxnpwa-horas-duracion">Duración: —</div>
        </section>

        <!-- Concepto -->
        <section class="rxnpwa-card">
            <h2 class="h6 mb-3"><i class="bi bi-card-text"></i> Concepto</h2>
            <textarea class="form-control" id="rxnpwa-horas-concepto" rows="4" maxlength="2000"
                placeholder="Ej: Visita técnica - Cliente X. Detalles del servicio, contexto, observaciones..."></textarea>
        </section>

        <!-- Tratativa -->
        <section class="rxnpwa-card">
            <h2 class="h6 mb-3"><i class="bi bi-link-45deg"></i> Vincular a tratativa (opcional)</h2>
            <select class="form-select" id="rxnpwa-horas-tratativa">
                <option value="">— ninguna —</option>
            </select>
            <div class="small text-muted mt-1">Mostramos las tratativas activas del catálogo offline.</div>
        </section>

        <!-- Descuento -->
        <section class="rxnpwa-card">
            <h2 class="h6 mb-3"><i class="bi bi-dash-circle"></i> Descuento (opcional)</h2>
            <div class="row g-2">
                <div class="col-12">
                    <label class="form-label small" for="rxnpwa-horas-descuento">Tiempo a descontar (HH:MM:SS)</label>
                    <input type="text" class="form-control" id="rxnpwa-horas-descuento" value="00:00:00" placeholder="00:00:00">
                </div>
                <div class="col-12">
                    <label class="form-label small" for="rxnpwa-horas-motivo">Motivo</label>
                    <textarea class="form-control" id="rxnpwa-horas-motivo" rows="2"
                        placeholder="Ej: pausa larga, almuerzo, traslado no facturable..."></textarea>
                </div>
            </div>
            <div class="small text-muted mt-2" id="rxnpwa-horas-net-time">Tiempo neto: —</div>
        </section>

        <!-- Adjuntos -->
        <section class="rxnpwa-card">
            <h2 class="h6 mb-3"><i class="bi bi-paperclip"></i> Adjuntos</h2>
            <div id="rxnpwa-horas-att-list" class="d-flex flex-column gap-2 mb-3"></div>
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-outline-light" id="rxnpwa-horas-att-photo">
                    <i class="bi bi-camera"></i> Sacar foto (certificado, planilla, etc.)
                </button>
                <button type="button" class="btn btn-outline-light" id="rxnpwa-horas-att-file">
                    <i class="bi bi-file-earmark-arrow-up"></i> Adjuntar archivo
                </button>
            </div>
            <input type="file" id="rxnpwa-horas-att-photo-input" accept="image/*" capture="environment" hidden>
            <input type="file" id="rxnpwa-horas-att-file-input"
                accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                multiple hidden>
            <div class="small text-muted mt-2">
                <span id="rxnpwa-horas-att-count">0</span> adjunto(s) ·
                Útil para certificados médicos, planillas, fotos del trabajo.
            </div>
        </section>

        <div class="d-grid gap-2 mb-4">
            <button type="button" class="btn btn-success btn-lg" id="rxnpwa-horas-save-bottom">
                <i class="bi bi-save"></i> Guardar borrador
            </button>
            <button type="button" class="btn btn-outline-danger btn-sm" id="rxnpwa-horas-delete">
                <i class="bi bi-trash"></i> Descartar borrador
            </button>
        </div>

        <!-- Sincronizar -->
        <div class="rxnpwa-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h2 class="h6 mb-0"><i class="bi bi-cloud-upload"></i> Sincronizar al servidor</h2>
                <span id="rxnpwa-horas-form-net-badge" class="badge bg-secondary small"><i class="bi bi-wifi"></i> —</span>
            </div>
            <div class="small text-muted mb-3" id="rxnpwa-horas-form-sync-state">
                Borrador local — sin sincronizar.
            </div>
            <div class="d-grid">
                <button type="button" class="btn btn-primary" id="rxnpwa-horas-sync">
                    <i class="bi bi-cloud-upload"></i> Sincronizar al servidor
                </button>
            </div>
            <div id="rxnpwa-horas-sync-message" class="small mt-2"></div>
        </div>

        <div class="text-center small text-muted mt-4 mb-4">
            Empresa #<?= (int) $empresaId ?> · RXN Suite
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/js/rxn-fullscreen.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-geo-gate.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-catalog-store.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-horas-drafts-store.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-image-compressor.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-horas-sync-queue.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-horas-form.js?v=<?= time() ?>"></script>
</body>
</html>
