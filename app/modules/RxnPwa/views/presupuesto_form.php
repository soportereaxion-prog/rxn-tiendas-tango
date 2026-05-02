<?php
/** @var int $empresaId */
/** @var string $tmpUuid (vacío = nuevo, lo asigna el JS) */
/** @var string $pageTitle */
$pageTitle = $pageTitle ?? 'Nuevo presupuesto';
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
            <a href="/rxnpwa/presupuestos" class="btn btn-sm btn-outline-light" title="Volver">
                <i class="bi bi-arrow-left"></i>
            </a>
            <?php require BASE_PATH . '/app/modules/RxnPwa/views/_brand_icon.php'; ?>
            <div>
                <div class="fw-bold" id="rxnpwa-form-title">Nuevo presupuesto</div>
                <div class="small text-muted" id="rxnpwa-form-subtitle">Borrador local</div>
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
            <button type="button" class="btn btn-sm btn-success" id="rxnpwa-form-save" title="Guardar borrador">
                <i class="bi bi-save"></i> Guardar
            </button>
        </div>
    </header>

    <main class="rxnpwa-shell" data-tmp-uuid="<?= htmlspecialchars($tmpUuid) ?>" data-empresa-id="<?= (int) $empresaId ?>">

        <div id="rxnpwa-form-status" aria-live="polite"></div>

        <!-- CABECERA -->
        <section class="rxnpwa-card">
            <h2 class="h6 mb-3"><i class="bi bi-person-vcard"></i> Cabecera</h2>

            <label class="form-label small" for="rxnpwa-cliente">Cliente</label>
            <div class="input-group mb-2">
                <input type="text" class="form-control" id="rxnpwa-cliente" placeholder="Buscar por nombre o documento..." autocomplete="off">
                <button class="btn btn-outline-light" type="button" id="rxnpwa-cliente-clear" title="Limpiar">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div id="rxnpwa-cliente-results" class="rxnpwa-suggestions"></div>
            <div id="rxnpwa-cliente-selected" class="small text-muted mb-3"></div>

            <div class="row g-2 mb-2">
                <div class="col-12 col-sm-6">
                    <label class="form-label small" for="rxnpwa-lista">Lista de precio <span class="text-danger">*</span></label>
                    <select class="form-select" id="rxnpwa-lista">
                        <option value="">— Seleccionar —</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label small" for="rxnpwa-deposito">Depósito <span class="text-danger">*</span></label>
                    <select class="form-select" id="rxnpwa-deposito" required>
                        <option value="">— Seleccionar —</option>
                    </select>
                </div>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-12 col-sm-6">
                    <label class="form-label small" for="rxnpwa-condicion">Condición de venta</label>
                    <select class="form-select" id="rxnpwa-condicion">
                        <option value="">— Seleccionar —</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label small" for="rxnpwa-vendedor">Vendedor</label>
                    <select class="form-select" id="rxnpwa-vendedor">
                        <option value="">— Seleccionar —</option>
                    </select>
                </div>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-12 col-sm-6">
                    <label class="form-label small" for="rxnpwa-transporte">Transporte</label>
                    <select class="form-select" id="rxnpwa-transporte">
                        <option value="">— Seleccionar —</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label small" for="rxnpwa-clasificacion">Clasificación <span class="text-danger">*</span></label>
                    <select class="form-select" id="rxnpwa-clasificacion" required>
                        <option value="">— Seleccionar —</option>
                    </select>
                </div>
            </div>

            <div class="small text-muted mb-1" id="rxnpwa-cliente-defaults-msg"></div>
        </section>

        <!-- RENGLONES -->
        <section class="rxnpwa-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h6 mb-0"><i class="bi bi-list-ol"></i> Renglones</h2>
                <button type="button" class="btn btn-sm btn-primary" id="rxnpwa-renglon-add">
                    <i class="bi bi-plus-lg"></i> Agregar
                </button>
            </div>
            <div id="rxnpwa-renglones-list">
                <div class="rxnpwa-placeholder small">Sin renglones todavía. Tocá <strong>Agregar</strong>.</div>
            </div>
            <div class="text-end mt-3 fs-5 fw-bold">
                Total: $ <span id="rxnpwa-total">0,00</span>
            </div>
        </section>

        <!-- COMENTARIOS / OBSERVACIONES -->
        <section class="rxnpwa-card">
            <h2 class="h6 mb-3"><i class="bi bi-chat-left-text"></i> Comentarios y observaciones</h2>

            <label class="form-label small" for="rxnpwa-comentarios">Comentarios</label>
            <textarea class="form-control mb-2" id="rxnpwa-comentarios" rows="3"
                placeholder="Info del producto, notas técnicas..."></textarea>

            <label class="form-label small" for="rxnpwa-observaciones">Observaciones</label>
            <textarea class="form-control" id="rxnpwa-observaciones" rows="3"
                placeholder="Texto libre del vendedor..."></textarea>

            <div class="small text-muted mt-2">
                <span id="rxnpwa-obs-counter">0 / 950 chars a Tango</span>
            </div>
        </section>

        <!-- ATTACHMENTS -->
        <section class="rxnpwa-card">
            <h2 class="h6 mb-3"><i class="bi bi-paperclip"></i> Adjuntos</h2>
            <div id="rxnpwa-attachments-warning" class="alert alert-warning small d-none">
                <i class="bi bi-exclamation-triangle"></i> Llegaste a <strong>5 adjuntos</strong>. Máximo 10 por presupuesto — más archivos, más demora al sincronizar.
            </div>
            <div id="rxnpwa-attachments-list" class="d-flex flex-column gap-2 mb-3"></div>

            <div class="d-grid gap-2">
                <button type="button" class="btn btn-outline-light" id="rxnpwa-att-photo">
                    <i class="bi bi-camera"></i> Sacar foto
                </button>
                <button type="button" class="btn btn-outline-light" id="rxnpwa-att-file">
                    <i class="bi bi-file-earmark-arrow-up"></i> Adjuntar archivo (PDF, Word, Excel, foto)
                </button>
            </div>

            <!-- Inputs ocultos -->
            <input type="file" id="rxnpwa-att-photo-input" accept="image/*" capture="environment" hidden>
            <input type="file" id="rxnpwa-att-file-input"
                accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                multiple hidden>

            <div class="small text-muted mt-2">
                <span id="rxnpwa-att-count">0</span> / 10 adjuntos · <span id="rxnpwa-att-total-size">0 KB</span>
            </div>
        </section>

        <div class="d-grid gap-2 mb-4">
            <button type="button" class="btn btn-success btn-lg" id="rxnpwa-form-save-bottom">
                <i class="bi bi-save"></i> Guardar borrador
            </button>
            <button type="button" class="btn btn-outline-danger btn-sm" id="rxnpwa-form-delete">
                <i class="bi bi-trash"></i> Descartar borrador
            </button>
        </div>

        <div class="rxnpwa-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h2 class="h6 mb-0"><i class="bi bi-cloud-upload"></i> Enviar al servidor</h2>
                <span id="rxnpwa-form-net-badge" class="badge bg-secondary small"><i class="bi bi-wifi"></i> —</span>
            </div>
            <div class="small text-muted mb-3" id="rxnpwa-form-sync-state">
                Borrador local — sin sincronizar.
            </div>
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-primary" id="rxnpwa-form-sync">
                    <i class="bi bi-cloud-upload"></i> Sincronizar al servidor
                </button>
                <button type="button" class="btn btn-success" id="rxnpwa-form-emit-tango" disabled
                    title="Enviá primero al servidor; después se habilita Tango.">
                    <i class="bi bi-send"></i> Enviar a Tango
                </button>
            </div>
            <div id="rxnpwa-form-sync-message" class="small mt-2"></div>
        </div>

        <div class="text-center small text-muted mt-4 mb-4">
            Empresa #<?= (int) $empresaId ?> · RXN Suite
        </div>

    </main>

    <!-- Modal: agregar renglón -->
    <div class="modal fade" id="rxnpwa-renglon-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header">
                    <h5 class="modal-title" id="rxnpwa-renglon-modal-title">Agregar renglón</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label small" for="rxnpwa-renglon-articulo">Artículo</label>
                    <input type="text" class="form-control mb-2" id="rxnpwa-renglon-articulo"
                        placeholder="Buscar por código o descripción..." autocomplete="off">
                    <div id="rxnpwa-renglon-articulo-results" class="rxnpwa-suggestions"></div>
                    <div id="rxnpwa-renglon-articulo-selected" class="small text-muted mb-3"></div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small" for="rxnpwa-renglon-cantidad">Cantidad</label>
                            <input type="number" class="form-control" id="rxnpwa-renglon-cantidad" value="1" min="0.01" step="0.01">
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="rxnpwa-renglon-descuento">Desc. %</label>
                            <input type="number" class="form-control" id="rxnpwa-renglon-descuento" value="0" min="0" max="100" step="0.01">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label small" for="rxnpwa-renglon-precio">Precio unitario</label>
                        <input type="number" class="form-control" id="rxnpwa-renglon-precio" value="0" min="0" step="0.01">
                        <div class="small text-muted mt-1" id="rxnpwa-renglon-precio-origin">—</div>
                    </div>

                    <div class="mt-3 small text-muted" id="rxnpwa-renglon-stock-info"></div>

                    <div class="mt-3 fs-5 text-end">
                        Subtotal: $ <span id="rxnpwa-renglon-subtotal">0,00</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="rxnpwa-renglon-confirm">
                        <span id="rxnpwa-renglon-confirm-label">Agregar al presupuesto</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Helper global de fullscreen + persistencia (release 1.42.0). -->
    <script src="/js/rxn-fullscreen.js?v=<?= time() ?>"></script>
    <!-- Geo gate ANTES que todos los demás — bloquea la PWA si no hay GPS. -->
    <script src="/js/pwa/rxnpwa-error-collector.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-geo-gate.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-catalog-store.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-drafts-store.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-image-compressor.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-sync-queue.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-form.js?v=<?= time() ?>"></script>
    <script src="/js/pwa/rxnpwa-form-sync.js?v=<?= time() ?>"></script>
</body>
</html>
