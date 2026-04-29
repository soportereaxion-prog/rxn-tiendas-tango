<?php
$pageTitle = $formMode === 'edit' ? 'Editar Presupuesto CRM - rxn_suite' : 'Nuevo Presupuesto CRM - rxn_suite';
$usePageHeader = false;

// Volver contextual: si el presupuesto vive bajo una tratativa, el Volver lleva a la
// tratativa. Si no, al listado. Mismo patrón que PDS v1.19.0.
$presupuestoBackHref = (string) $basePath;
$presupuestoBackTitle = 'Volver al listado de Presupuestos';
if (!empty($presupuesto['tratativa_id'])) {
    $presupuestoBackHref = '/mi-empresa/crm/tratativas/' . (int) $presupuesto['tratativa_id'];
    $presupuestoBackTitle = 'Volver a la Tratativa #' . (int) $presupuesto['tratativa_id'];
}

ob_start();
?>
    <style>
        .crm-budget-shell {
            max-width: 100%;
        }

        .crm-budget-form .card-body {
            padding: 0.75rem 0.85rem;
        }

        .crm-budget-form .form-label {
            margin-bottom: 0.24rem !important;
        }

        .crm-budget-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 0.35rem 0.45rem;
        }

        .crm-budget-col-12 { grid-column: span 12; }
        .crm-budget-col-8 { grid-column: span 8; }
        .crm-budget-col-6 { grid-column: span 6; }
        .crm-budget-col-4 { grid-column: span 4; }
        .crm-budget-col-3 { grid-column: span 3; }
        .crm-budget-col-2 { grid-column: span 2; }
        .crm-budget-col-1 { grid-column: span 1; }

        /* Inputs de leyenda: ancho visual ~40 caracteres aunque el maxlength real sea 60. */
        .crm-budget-leyenda-input {
            font-size: 0.85rem;
        }

        /* Textarea de descripción del renglón:
           - Por default: borde sutil estándar.
           - Cuando difiere del original del catálogo (.is-modified) → borde naranja
             para que el operador SEPA visualmente que esa descripción va a viajar
             a Tango como DESCRIPCION_ADICIONAL_ARTICULO. */
        .crm-budget-desc-textarea.is-modified {
            border-color: #fd7e14 !important;
            box-shadow: 0 0 0 0.15rem rgba(253, 126, 20, 0.18);
        }
        .crm-budget-desc-textarea.is-modified:focus {
            border-color: #fd7e14 !important;
            box-shadow: 0 0 0 0.25rem rgba(253, 126, 20, 0.28);
        }

        .crm-budget-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.55rem;
        }

        .crm-budget-chip {
            border: 1px solid rgba(13, 110, 253, 0.1);
            border-radius: 14px;
            padding: 0.4rem 0.6rem;
        }

        .crm-budget-chip-title {
            font-size: 0.7rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6c757d;
            margin-bottom: 0.25rem;
            font-weight: 700;
        }

        .crm-budget-chip-value {
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.15;
        }

        .crm-picker-wrap {
            position: relative;
        }

        .crm-picker-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .crm-picker-meta {
            min-height: 1.1rem;
        }

        .crm-picker-results {
            position: absolute;
            inset: calc(100% + 0.2rem) 0 auto 0;
            z-index: 30;
        }

        .crm-budget-items-card {
            border: 1px solid var(--bs-border-color, rgba(15, 23, 42, 0.08));
            border-radius: 16px;
        }

        .crm-budget-items-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: end;
            justify-content: space-between;
        }

        .crm-budget-items-toolbar .crm-budget-picker-col {
            min-width: 320px;
            flex: 1 1 420px;
        }

        .crm-budget-line-table td,
        .crm-budget-line-table th {
            vertical-align: middle;
        }

        .crm-budget-line-table input.form-control,
        .crm-budget-line-table textarea.form-control {
            min-width: 0;
        }

        .crm-budget-line-amount {
            min-width: 110px;
        }

        .crm-budget-line-desc {
            min-width: 240px;
        }

        .crm-budget-totals {
            display: grid;
            gap: 0.25rem;
            min-width: 280px;
        }

        .crm-budget-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 0.45rem 0.65rem;
            border-radius: 12px;
            background: rgba(15, 23, 42, 0.04);
        }

        .crm-budget-total-row.is-grand {
            background: rgba(25, 135, 84, 0.12);
            font-weight: 700;
        }

        .crm-budget-total-value {
            font-variant-numeric: tabular-nums;
            font-weight: 700;
        }

        .crm-budget-empty-lines {
            padding: 1.5rem 0.75rem;
            text-align: center;
            color: #6c757d;
        }

        .crm-budget-section-title {
            font-size: 0.88rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6c757d;
            margin-bottom: 0.6rem;
        }

        .crm-budget-client-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.22rem 0.55rem;
            border-radius: 999px;
            border: 1px solid rgba(13, 110, 253, 0.15);
            background: rgba(13, 110, 253, 0.06);
            color: #0d6efd;
            font-size: 0.76rem;
            font-weight: 600;
        }

        @media (max-width: 1199.98px) {
            .crm-budget-grid {
                grid-template-columns: repeat(6, minmax(0, 1fr));
            }

            .crm-budget-col-12,
            .crm-budget-col-8,
            .crm-budget-col-6,
            .crm-budget-col-4,
            .crm-budget-col-3,
            .crm-budget-col-2,
            .crm-budget-col-1 {
                grid-column: span 6;
            }
        }

        @media (max-width: 767.98px) {
            .crm-budget-summary,
            .crm-budget-grid {
                grid-template-columns: 1fr;
            }

            .crm-budget-col-12,
            .crm-budget-col-8,
            .crm-budget-col-6,
            .crm-budget-col-4,
            .crm-budget-col-3,
            .crm-budget-col-2,
            .crm-budget-col-1 {
                grid-column: auto;
            }

            .crm-budget-items-toolbar {
                align-items: stretch;
            }

            .crm-budget-totals {
                min-width: 100%;
            }
        }
    </style>
<?php
$extraHead = ob_get_clean();

ob_start();
?>
<?php
    $renderOptions = static function (array $options, string $selected, string $placeholder = '-- Sin definir --'): string {
        $html = '<option value="">' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '</option>';
        $found = false;

        foreach ($options as $option) {
            $codigo = trim((string) ($option['codigo'] ?? ''));
            $descripcion = trim((string) ($option['descripcion'] ?? ''));
            if ($codigo === '') {
                continue;
            }

            $isSelected = $selected !== '' && $selected === $codigo;
            if ($isSelected) {
                $found = true;
            }

            $html .= '<option value="' . htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') . '"' . ($isSelected ? ' selected' : '') . '>'
                . htmlspecialchars(($descripcion !== '' ? $descripcion : $codigo), ENT_QUOTES, 'UTF-8')
                . '</option>';
        }

        if (!$found && $selected !== '') {
            $html .= '<option value="' . htmlspecialchars($selected, ENT_QUOTES, 'UTF-8') . '" selected>Guardado localmente (' . htmlspecialchars($selected, ENT_QUOTES, 'UTF-8') . ')</option>';
        }

        return $html;
    };

    $estadoLabel = match ((string) ($presupuesto['estado'] ?? 'borrador')) {
        'emitido' => 'Emitido',
        'anulado' => 'Anulado',
        default => 'Borrador',
    };

    // Lock blindado: el presupuesto queda en SOLO LECTURA si:
    //  - fue emitido (estado='emitido'), o
    //  - fue enviado a Tango exitosamente (nro_comprobante_tango poblado).
    // Patrón replicado de PDS. Para hacer cambios el operador debe usar
    // "Nueva versión" desde el header (release 1.29.x).
    $_isEmitido     = (($presupuesto['estado'] ?? '') === 'emitido');
    $_isSentToTango = trim((string) ($presupuesto['nro_comprobante_tango'] ?? '')) !== '';
    $_isLocked      = $_isEmitido || $_isSentToTango;
    ?>
    <div class="container-fluid mt-2 mb-3 rxn-responsive-container crm-budget-shell">
        <div class="rxn-module-header mb-2">
            <div>
                <h1 class="h3 fw-bold mb-0">
                    <i class="bi bi-file-earmark-spreadsheet me-2"></i>
                    <?= $formMode === 'edit' ? 'Presupuesto #' . (int) ($presupuesto['numero'] ?? $presupuesto['id'] ?? 0) : 'Nuevo Presupuesto' ?>
                    <?php
                        $_versionNumero  = (int) ($presupuesto['version_numero'] ?? 1);
                        $_versionPadreId = (int) ($presupuesto['version_padre_id'] ?? 0);
                    ?>
                    <?php if ($_versionNumero > 1 || $_versionPadreId > 0): ?>
                        <a href="/mi-empresa/crm/presupuestos/<?= $_versionPadreId ?>/editar"
                           class="badge bg-info-subtle text-info-emphasis ms-1 align-middle text-decoration-none"
                           style="font-size: 0.55em;"
                           title="Versión <?= $_versionNumero ?> de este presupuesto. Cliquea para ir al original (Presupuesto #<?= $_versionPadreId ?>).">
                            v<?= $_versionNumero ?> · ver origen #<?= $_versionPadreId ?>
                        </a>
                    <?php endif; ?>
                    <span class="badge bg-secondary-subtle text-secondary ms-1 align-middle" style="font-size: 0.55em;"><?= htmlspecialchars($estadoLabel) ?></span>
                </h1>
            </div>
            <div class="rxn-module-actions">

                <?php if ($formMode === 'edit'): ?>
                    <form action="/mi-empresa/crm/presupuestos/<?= (int) ($presupuesto['id'] ?? 0) ?>/copiar" method="POST" class="d-inline-block m-0 p-0" >
                        <button type="submit" class="btn btn-outline-success shadow-sm" data-rxn-confirm="¿Confirma que desea duplicar este presupuesto?" data-confirm-type="info"><i class="bi bi-copy"></i> Copiar</button>
                    </form>
                    <?php if (!$_isLocked): ?>
                        <form action="/mi-empresa/crm/presupuestos/<?= (int) ($presupuesto['id'] ?? 0) ?>/nueva-version" method="POST" class="d-inline-block m-0 p-0">
                            <button type="submit" class="btn btn-outline-info shadow-sm" data-rxn-confirm="¿Generar una nueva versión de este presupuesto? La actual queda como referencia y la nueva arranca como borrador editable." data-confirm-type="info" title="Crea una nueva versión vinculada al original — útil para iterar precios o condiciones manteniendo trazabilidad"><i class="bi bi-layers-half"></i> Nueva versión</button>
                        </form>
                    <?php else: ?>
                        <form action="/mi-empresa/crm/presupuestos/<?= (int) ($presupuesto['id'] ?? 0) ?>/nueva-version" method="POST" class="d-inline-block m-0 p-0">
                            <button type="submit" class="btn btn-info shadow-sm" data-rxn-confirm="Este presupuesto está blindado (enviado a Tango o emitido). ¿Generar una nueva versión editable a partir de éste?" data-confirm-type="info" title="Bloqueado por Tango — usá Nueva versión para ajustar"><i class="bi bi-layers-half"></i> Nueva versión</button>
                        </form>
                    <?php endif; ?>
                    <form action="/mi-empresa/crm/presupuestos/<?= (int) ($presupuesto['id'] ?? 0) ?>/eliminar" method="POST" class="d-inline-block m-0 p-0">
                        <button type="submit" class="btn btn-outline-danger shadow-sm" data-rxn-confirm="¿Enviar presupuesto a la papelera?" data-confirm-type="danger"><i class="bi bi-trash"></i> Eliminar</button>
                    </form>
                    <form action="/mi-empresa/crm/presupuestos/<?= (int) ($presupuesto['id'] ?? 0) ?>/enviar-correo" method="POST" class="d-inline-block m-0 p-0">
                        <button type="submit" class="btn btn-outline-primary shadow-sm position-relative" data-rxn-confirm="¿Enviar el presupuesto por correo electrónico al cliente?" data-confirm-type="info">
                            <i class="bi bi-envelope-paper"></i> Enviar
                            <?php
                            $count = (int) ($presupuesto['correos_enviados_count'] ?? 0);
                            $ultimoEnvio = $presupuesto['correos_ultimo_envio_at'] ?? null;
                            $ultimoError = $presupuesto['correos_ultimo_error'] ?? null;
                            $ultimoErrorAt = $presupuesto['correos_ultimo_error_at'] ?? null;
                            include BASE_PATH . '/app/shared/views/components/correo_envio_dot.php';
                            ?>
                        </button>
                    </form>
                    <?php if (($presupuesto['estado'] ?? '') !== 'anulado'): ?>
                        <?php if (empty($presupuesto['nro_comprobante_tango'])): ?>
                            <form action="/mi-empresa/crm/presupuestos/<?= (int) ($presupuesto['id'] ?? 0) ?>/sync-tango" method="POST" class="d-inline-block m-0 p-0">
                                <button type="submit" class="btn btn-outline-warning shadow-sm" data-rxn-confirm="¿Estás seguro que querés enviar el presupuesto a Tango? Esta acción integrará el mismo como un pedido ingresado." data-confirm-type="warning"><i class="bi bi-cloud-arrow-up"></i> Enviar a Tango</button>
                            </form>
                        <?php else: ?>
                            <button type="button" class="btn btn-success shadow-sm" disabled><i class="bi bi-check-all"></i> Enviado a Tango (#<?= htmlspecialchars($presupuesto['nro_comprobante_tango']) ?>)</button>
                        <?php endif; ?>
                        
                        <?php if (!empty($presupuesto['tango_sync_log'])): ?>
                            <button type="button" class="btn btn-outline-info px-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#tangoInspectorModal" title="Inspeccionar conexión a Tango Connect">
                                <i class="bi bi-info-circle"></i>
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a href="/mi-empresa/crm/presupuestos/<?= (int) ($presupuesto['id'] ?? 0) ?>/imprimir" class="btn btn-outline-dark shadow-sm" target="_blank" rel="noopener noreferrer"><i class="bi bi-printer"></i> Imprimir</a>
                <?php endif; ?>
                <a href="/mi-empresa/crm/formularios-impresion/crm_presupuesto" class="btn btn-outline-dark"><i class="bi bi-easel2"></i> Formulario</a>
                <?php if (!$_isLocked): ?>
                    <button type="submit" form="crm-presupuesto-form" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Guardar</button>
                <?php endif; ?>
                <a href="<?= htmlspecialchars((string) $helpPath) ?>" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener noreferrer" title="Ayuda"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="<?= htmlspecialchars($presupuestoBackHref) ?>" class="btn btn-outline-secondary btn-sm" title="<?= htmlspecialchars($presupuestoBackTitle) ?>" data-rxn-back><i class="bi bi-arrow-left"></i> Volver</a>
            </div>
        </div>

        <?php require BASE_PATH . '/app/shared/views/components/module_notes_panel.php'; ?>

        <?php $flash = \App\Core\Flash::get(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars((string) $flash['type']) ?> shadow-sm mb-3" role="alert">
                <?= htmlspecialchars((string) $flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($catalogSyncWarning)): ?>
            <div class="alert alert-warning shadow-sm mb-3" role="alert">
                <?= htmlspecialchars((string) $catalogSyncWarning) ?>
            </div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger shadow-sm mb-3 sticky-top" id="crm-budget-error-banner" role="alert" style="top: 0; z-index: 50;">
                <div class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> Revisá los datos del presupuesto antes de guardar</div>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $message): ?>
                        <li><?= htmlspecialchars((string) $message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php
            // Helper para mensajes inline de error bajo cada campo. La key del array
            // $errors es el mismo nombre del campo (ej: 'cliente_id', 'fecha').
            $errorMsg = static function (array $errors, string $key): string {
                if (!isset($errors[$key])) return '';
                return '<div class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1"></i>'
                    . htmlspecialchars((string) $errors[$key], ENT_QUOTES, 'UTF-8') . '</div>';
            };
        ?>
        <?php if ($_isEmitido): ?>
            <div class="alert alert-info shadow-sm border-0 bg-info bg-opacity-10 mb-3" role="alert">
                <i class="bi bi-lock-fill text-info mt-1 me-2 float-start"></i>
                <div class="text-info-emphasis ps-4">Este presupuesto ya fue emitido y se encuentra blindado en Solo Lectura (Inmodificable).</div>
            </div>
        <?php elseif ($_isSentToTango): ?>
            <div class="alert alert-success shadow-sm border-0 bg-success bg-opacity-10 mb-3" role="alert">
                <i class="bi bi-lock-fill text-success mt-1 me-2 float-start"></i>
                <div class="text-success-emphasis ps-4">
                    Este presupuesto ya fue enviado a Tango (Pedido <strong>#<?= htmlspecialchars((string) $presupuesto['nro_comprobante_tango']) ?></strong>) — está en Solo Lectura.
                    Para hacer cambios, usá <strong>Nueva versión</strong> desde el header.
                </div>
            </div>
        <?php endif; ?>

        <?php
        $_tangoSent = (($presupuesto['tango_sync_status'] ?? '') === 'success') ? '1' : '0';
        $_mailSent  = ((int) ($presupuesto['correos_enviados_count'] ?? 0) > 0) ? '1' : '0';
        ?>
        <form id="crm-presupuesto-form" class="crm-budget-form" action="<?= htmlspecialchars((string) $formAction) ?>" method="POST" novalidate data-rxn-form-intercept="1"
              data-tango-sent="<?= $_tangoSent ?>"
              data-mail-sent="<?= $_mailSent ?>"
              data-from-copy="<?= !empty($isFromCopy) ? '1' : '0' ?>">
            <?php if (!empty($presupuesto['tratativa_id'])): ?>
                <input type="hidden" name="tratativa_id" value="<?= htmlspecialchars((string) $presupuesto['tratativa_id']) ?>">
                <div class="alert alert-info border-0 small mb-3">
                    <i class="bi bi-briefcase-fill"></i> Este presupuesto forma parte de la
                    <a href="/mi-empresa/crm/tratativas/<?= (int) $presupuesto['tratativa_id'] ?>" class="alert-link">Tratativa #<?= (int) $presupuesto['tratativa_id'] ?></a>.
                    Usá ← Volver para regresar a la tratativa.
                </div>
            <?php endif; ?>
            <fieldset <?= $_isLocked ? 'disabled' : '' ?>>
            <div class="card rxn-form-card mb-3">
                <div class="card-body">
                    <div class="crm-budget-summary mb-3">
                        <div class="crm-budget-chip">
                            <div class="crm-budget-chip-title">Presupuesto</div>
                            <div class="crm-budget-chip-value">#<?= (int) ($presupuesto['numero'] ?? 0) ?></div>
                        </div>
                        <div class="crm-budget-chip">
                            <div class="crm-budget-chip-title">Estado</div>
                            <div class="crm-budget-chip-value"><?= htmlspecialchars($estadoLabel) ?></div>
                        </div>
                        <div class="crm-budget-chip">
                            <div class="crm-budget-chip-title">Renglones</div>
                            <div class="crm-budget-chip-value" data-item-count><?= count($presupuesto['items'] ?? []) ?></div>
                        </div>
                        <div class="crm-budget-chip">
                            <div class="crm-budget-chip-title">Total</div>
                            <div class="crm-budget-chip-value" data-summary-total>$<?= number_format((float) ($presupuesto['total'] ?? 0), 2, ',', '.') ?></div>
                        </div>
                        <?php if (!empty($presupuesto['tango_sync_status'])): ?>
                        <div class="crm-budget-chip <?= $presupuesto['tango_sync_status'] === 'success' ? 'bg-success bg-opacity-10 border-success' : 'bg-danger bg-opacity-10 border-danger' ?>">
                            <div class="crm-budget-chip-title <?= $presupuesto['tango_sync_status'] === 'success' ? 'text-success' : 'text-danger' ?>">Tango Sync</div>
                            <div class="crm-budget-chip-value <?= $presupuesto['tango_sync_status'] === 'success' ? 'text-success' : 'text-danger' ?>">
                                <?= $presupuesto['tango_sync_status'] === 'success' ? '<i class="bi bi-check-circle-fill"></i> #' . htmlspecialchars((string) ($presupuesto['nro_comprobante_tango'] ?? '')) : '<i class="bi bi-exclamation-triangle-fill"></i> Error' ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="crm-budget-section-title">Cabecera comercial</div>
                    <div class="crm-budget-grid mb-2">
                        <div class="crm-budget-col-2">
                            <label class="form-label">Numero interno</label>
                            <input type="text" class="form-control bg-light text-muted" value="<?= htmlspecialchars((string) ($presupuesto['numero'] ?? 'NUEVO')) ?>" disabled readonly>
                        </div>
                        
                        <div class="crm-budget-col-2">
                            <label for="presupuesto-fecha" class="form-label">Fecha <span class="text-danger" title="Obligatorio">*</span></label>
                            <input type="datetime-local" class="form-control <?= isset($errors['fecha']) ? 'is-invalid' : '' ?>" id="presupuesto-fecha" name="fecha" value="<?= htmlspecialchars((string) ($presupuesto['fecha'] ?? '')) ?>" required>
                            <?= $errorMsg($errors, 'fecha') ?>
                        </div>

                        <div class="crm-budget-col-3">
                            <label class="form-label">Cliente <span class="text-danger" title="Obligatorio">*</span></label>
                            <div class="crm-picker-wrap" data-client-picker data-picker-url="/mi-empresa/crm/presupuestos/clientes/sugerencias" data-context-url="/mi-empresa/crm/presupuestos/clientes/contexto">
                                <input type="hidden" name="cliente_id" value="<?= htmlspecialchars((string) ($presupuesto['cliente_id'] ?? '')) ?>" class="crm-picker-hidden" data-picker-hidden>
                                <input type="text" class="form-control <?= isset($errors['cliente_id']) ? 'is-invalid' : '' ?>" name="cliente_nombre" value="<?= htmlspecialchars((string) ($presupuesto['cliente_nombre'] ?? '')) ?>" placeholder="Buscar cliente CRM..." autocomplete="off" data-picker-input>
                                <div class="rxn-search-suggestions crm-picker-results d-none" data-picker-results></div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                                <span class="crm-budget-client-pill" data-client-id-pill><?= (string) ($presupuesto['cliente_id'] ?? '') !== '' ? 'Cliente #' . htmlspecialchars((string) $presupuesto['cliente_id']) : 'Sin cliente' ?></span>
                                <span class="small text-muted text-truncate" data-client-documento><?= (string) ($presupuesto['cliente_documento'] ?? '') !== '' ? 'Doc: ' . htmlspecialchars((string) $presupuesto['cliente_documento']) : 'Sin doc' ?></span>
                            </div>
                            <div class="form-text crm-picker-meta" data-picker-meta>Defaults comerciales listos.</div>
                            <?= $errorMsg($errors, 'cliente_id') ?>
                            <!-- P2 — Warning Tango: cliente sin id_gva14_tango. Lo llena el JS al elegir cliente. -->
                            <div class="form-text text-warning d-none" data-client-tango-warning>
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                Este cliente no tiene relación comercial Tango — el envío a Tango va a fallar.
                            </div>
                            <input type="hidden" name="cliente_documento" value="<?= htmlspecialchars((string) ($presupuesto['cliente_documento'] ?? '')) ?>" data-cliente-documento-hidden>
                        </div>

                        <div class="crm-budget-col-2">
                            <label for="presupuesto-estado" class="form-label">Estado</label>
                            <select class="form-select" id="presupuesto-estado" name="estado">
                                <option value="borrador" <?= ($presupuesto['estado'] ?? 'borrador') === 'borrador' ? 'selected' : '' ?>>Borrador</option>
                                <option value="emitido" <?= ($presupuesto['estado'] ?? '') === 'emitido' ? 'selected' : '' ?>>Emitido</option>
                                <option value="anulado" <?= ($presupuesto['estado'] ?? '') === 'anulado' ? 'selected' : '' ?>>Anulado</option>
                            </select>
                        </div>

                        <div class="crm-budget-col-1">
                            <label for="presupuesto-cotizacion" class="form-label">Cotización</label>
                            <input type="number" step="0.0001" min="0" class="form-control text-end <?= isset($errors['cotizacion']) ? 'is-invalid' : '' ?>" id="presupuesto-cotizacion" name="cotizacion" value="<?= htmlspecialchars((string) ($presupuesto['cotizacion'] ?? 1)) ?>" title="Cotización del dólar al momento del presupuesto. Viaja a Tango como COTIZACION.">
                            <?= $errorMsg($errors, 'cotizacion') ?>
                            <!-- P2 — Warning si cotización es 0 (ojo: rompe importes en USD) -->
                            <div class="form-text text-warning d-none" data-cotizacion-warning>
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                Cotización en 0 puede romper importes en USD.
                            </div>
                        </div>

                        <div class="crm-budget-col-2">
                            <label for="presupuesto-deposito" class="form-label">Deposito</label>
                            <select class="form-select" id="presupuesto-deposito" name="deposito_codigo" data-catalog-select="deposito">
                                <?= $renderOptions($catalogs['depositos'] ?? [], (string) ($presupuesto['deposito_codigo'] ?? ''), '-- Deposito --') ?>
                            </select>
                        </div>

                        <div class="crm-budget-col-3">
                            <label for="presupuesto-condicion" class="form-label">Condicion de venta</label>
                            <select class="form-select" id="presupuesto-condicion" name="condicion_codigo" data-catalog-select="condicion_venta">
                                <?= $renderOptions($catalogs['condiciones'] ?? [], (string) ($presupuesto['condicion_codigo'] ?? ''), '-- Condicion --') ?>
                            </select>
                        </div>

                        <div class="crm-budget-col-3">
                            <label for="presupuesto-lista" class="form-label">Lista de precios <span class="text-danger" title="Obligatorio">*</span></label>
                            <select class="form-select <?= isset($errors['lista_codigo']) ? 'is-invalid' : '' ?>" id="presupuesto-lista" name="lista_codigo" data-lista-select data-catalog-select="lista_precio">
                                <?= $renderOptions($catalogs['listas'] ?? [], (string) ($presupuesto['lista_codigo'] ?? ''), '-- Lista --') ?>
                            </select>
                            <?= $errorMsg($errors, 'lista_codigo') ?>
                        </div>

                        <div class="crm-budget-col-3">
                            <label for="presupuesto-vendedor" class="form-label">Vendedor</label>
                            <select class="form-select" id="presupuesto-vendedor" name="vendedor_codigo" data-catalog-select="vendedor">
                                <?= $renderOptions($catalogs['vendedores'] ?? [], (string) ($presupuesto['vendedor_codigo'] ?? ''), '-- Vendedor --') ?>
                            </select>
                        </div>

                        <div class="crm-budget-col-3">
                            <label for="clasificacion_codigo" class="form-label">Clasificación <span class="text-danger" title="Obligatorio">*</span></label>
                            <div class="crm-picker-wrap" data-picker data-picker-url="/mi-empresa/crm/pedidos-servicio/clasificaciones/sugerencias" data-picker-allow-manual="1">
                                <input type="hidden" name="clasificacion_id_tango" id="clasificacion_id_tango" data-picker-extra-hidden value="<?= htmlspecialchars((string) ($presupuesto['clasificacion_id_tango'] ?? '')) ?>">
                                <input type="hidden" class="crm-picker-hidden" data-picker-hidden value="<?= htmlspecialchars((string) ($presupuesto['clasificacion_codigo'] ?? '')) ?>">
                                <input type="hidden" name="clasificacion_descripcion" id="clasificacion_descripcion" value="<?= htmlspecialchars((string) ($presupuesto['clasificacion_descripcion'] ?? '')) ?>">
                                <input type="text" class="form-control <?= isset($errors['clasificacion_codigo']) ? 'is-invalid' : '' ?>" id="clasificacion_codigo" name="clasificacion_codigo" value="<?= htmlspecialchars((string) ($presupuesto['clasificacion_codigo'] ?? '')) ?>" autocomplete="off" placeholder="Clasificación" data-picker-input>
                                <div class="rxn-search-suggestions crm-picker-results d-none" data-picker-results></div>
                            </div>
                            <div class="form-text text-truncate" data-clasificacion-desc-display><?= trim((string) ($presupuesto['clasificacion_descripcion'] ?? '')) !== '' ? htmlspecialchars((string) $presupuesto['clasificacion_descripcion'], ENT_QUOTES, 'UTF-8') : '&nbsp;' ?></div>
                            <?= $errorMsg($errors, 'clasificacion_codigo') ?>
                            <!-- P2 — Warning Tango: clasificación sin id_gva81_tango. Lo llena el JS al elegir. -->
                            <div class="form-text text-warning d-none" data-clasif-tango-warning>
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                Esta clasificación no tiene ID Tango mapeado — el envío a Tango va a fallar.
                            </div>
                        </div>

                        <div class="crm-budget-col-3">
                            <label for="presupuesto-transporte" class="form-label">Transporte</label>
                            <select class="form-select" id="presupuesto-transporte" name="transporte_codigo" data-catalog-select="transporte">
                                <?= $renderOptions($catalogs['transportes'] ?? [], (string) ($presupuesto['transporte_codigo'] ?? ''), '-- Transporte --') ?>
                            </select>
                        </div>

                        <div class="crm-budget-col-3">
                            <label for="presupuesto-proximo-contacto" class="form-label">Próximo contacto</label>
                            <input type="datetime-local" class="form-control <?= isset($errors['proximo_contacto']) ? 'is-invalid' : '' ?>" id="presupuesto-proximo-contacto" name="proximo_contacto" value="<?= htmlspecialchars((string) ($presupuesto['proximo_contacto'] ?? '')) ?>">
                            <?= $errorMsg($errors, 'proximo_contacto') ?>
                            <!-- P2 — Warning si próximo contacto es pasado -->
                            <div class="form-text text-warning d-none" data-proximo-contacto-warning>
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                Próximo contacto en el pasado — ¿quisiste agendarlo a futuro?
                            </div>
                        </div>

                        <div class="crm-budget-col-3">
                            <label for="presupuesto-vigencia" class="form-label">Vigencia</label>
                            <input type="datetime-local" class="form-control <?= isset($errors['vigencia']) ? 'is-invalid' : '' ?>" id="presupuesto-vigencia" name="vigencia" value="<?= htmlspecialchars((string) ($presupuesto['vigencia'] ?? '')) ?>">
                            <?= $errorMsg($errors, 'vigencia') ?>
                            <!-- P2 — Warning si vigencia es anterior a la fecha del presupuesto -->
                            <div class="form-text text-warning d-none" data-vigencia-warning>
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                Vigencia anterior a la fecha del presupuesto.
                            </div>
                        </div>

                        <?php for ($i = 1; $i <= 5; $i++):
                            $leyendaKey = 'leyenda_' . $i;
                            $leyendaValue = (string) ($presupuesto[$leyendaKey] ?? '');
                        ?>
                            <div class="crm-budget-col-2">
                                <label for="presupuesto-leyenda-<?= $i ?>" class="form-label">Leyenda <?= $i ?></label>
                                <input type="text" class="form-control crm-budget-leyenda-input" id="presupuesto-leyenda-<?= $i ?>" name="<?= $leyendaKey ?>" value="<?= htmlspecialchars($leyendaValue) ?>" maxlength="60" size="40" placeholder="Leyenda <?= $i ?>">
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <div class="card rxn-form-card mb-3">
                <div class="card-body">
                    <div class="crm-budget-section-title">Cuerpo del presupuesto</div>

                    <div class="small text-muted mb-2"><strong>Carga rápida:</strong> Busque un artículo, revise cantidad, precio, bonificación y presione <b>Enter</b> o el botón Agregar. <span class="ms-lg-3"><i class="bi bi-info-circle"></i> La lista de precios activa resuelve los valores monetarios.</span></div>
                    
                    <div class="d-flex align-items-end gap-2 flex-wrap mb-3 p-2 rounded border" style="background: var(--bs-secondary-bg, rgba(0,0,0,0.03)); border-color: var(--bs-border-color) !important;">
                        <div class="crm-picker-wrap flex-grow-1" style="min-width: 250px;" data-article-picker data-picker-url="/mi-empresa/crm/presupuestos/articulos/sugerencias" data-context-url="/mi-empresa/crm/presupuestos/articulos/contexto">
                            <label class="form-label fw-semibold text-primary" style="font-size: 0.85rem">Buscar artículo</label>
                            <input type="hidden" value="" class="crm-picker-hidden" data-picker-hidden>
                            <input type="text" class="form-control border-primary shadow-sm" value="" placeholder="Cod. o desc. en CRM..." autocomplete="off" data-picker-input id="inline-picker-input">
                            <div class="rxn-search-suggestions crm-picker-results d-none" data-picker-results></div>
                        </div>

                        <input type="hidden" id="inline-articulo-id" value="">
                        <input type="hidden" id="inline-articulo-codigo" value="">
                        <input type="hidden" id="inline-articulo-descripcion" value="">
                        <input type="hidden" id="inline-precio-origen" value="manual">

                        <div style="width: 85px;">
                            <label class="form-label crm-budget-chip-title mb-1">CANT.</label>
                            <input type="number" step="0.0001" min="0" class="form-control text-end" id="inline-qty" value="1" tabindex="0">
                        </div>
                        
                        <div style="width: 120px;">
                            <label class="form-label crm-budget-chip-title mb-1">PRECIO</label>
                            <input type="number" step="0.0001" min="0" class="form-control text-end" id="inline-price" value="0" tabindex="0">
                        </div>

                        <div style="width: 90px;">
                            <label class="form-label crm-budget-chip-title mb-1">STOCK</label>
                            <input type="text" class="form-control text-end" id="inline-stock" value="—" readonly tabindex="-1" style="background-color: #f8f9fa !important; color: #000 !important; border-color: var(--bs-border-color-translucent);">
                        </div>

                        <div style="width: 80px;">
                            <label class="form-label crm-budget-chip-title mb-1">% BONIF</label>
                            <input type="number" step="0.0001" min="0" max="100" class="form-control text-end" id="inline-bonus" value="0" tabindex="0">
                        </div>

                        <div style="width: 130px;">
                            <label class="form-label crm-budget-chip-title mb-1">IMPORTE TEMP</label>
                            <input type="text" class="form-control text-end fw-bold" id="inline-temp-total" value="$0,00" readonly tabindex="-1" style="background-color: #f8f9fa !important; color: #000 !important; border-color: var(--bs-border-color-translucent);">
                        </div>

                        <div>
                            <button type="button" class="btn btn-primary fw-bold" id="inline-add-btn" tabindex="0" title="Agregar al presupuesto"><i class="bi bi-plus-lg"></i></button>
                        </div>
                    </div>

                    <?php if (isset($errors['items'])): ?>
                        <div class="alert alert-danger py-2 mb-3" id="crm-budget-items-error">
                            <i class="bi bi-exclamation-circle me-1"></i>
                            <?= htmlspecialchars((string) $errors['items']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="crm-budget-items-card">
                        <div class="table-responsive rxn-table-responsive">
                            <table class="table table-hover align-middle table-sm mb-0 crm-budget-line-table">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 165px;">Codigo</th>
                                        <th style="min-width: 260px;">Descripcion</th>
                                        <th style="width: 100px;">Cantidad</th>
                                        <th style="width: 90px;">Stock</th>
                                        <th style="width: 135px;">Precio</th>
                                        <th style="width: 110px;">Bonif %</th>
                                        <th style="width: 135px;">Importe</th>
                                        <th style="width: 70px;"></th>
                                    </tr>
                                </thead>
                                <tbody data-items-body>
                                    <?php if (($presupuesto['items'] ?? []) === []): ?>
                                        <tr data-empty-row>
                                            <td colspan="8" class="crm-budget-empty-lines">Todavia no hay renglones. Busca un articulo para empezar a armar el presupuesto.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach (($presupuesto['items'] ?? []) as $index => $item): ?>
                                            <?php
                                                $itemDesc       = (string) ($item['articulo_descripcion'] ?? '');
                                                $itemOriginal   = (string) ($item['articulo_descripcion_original'] ?? $itemDesc);
                                                $itemModified   = $itemOriginal !== '' && $itemDesc !== '' && $itemDesc !== $itemOriginal;
                                            ?>
                                            <tr data-item-row>
                                                <td>
                                                    <input type="hidden" name="items[<?= $index ?>][articulo_id]" value="<?= htmlspecialchars((string) ($item['articulo_id'] ?? '')) ?>" data-item-field="articulo_id">
                                                    <input type="hidden" name="items[<?= $index ?>][precio_origen]" value="<?= htmlspecialchars((string) ($item['precio_origen'] ?? 'manual')) ?>" data-item-field="precio_origen">
                                                    <input type="hidden" name="items[<?= $index ?>][lista_codigo_aplicada]" value="<?= htmlspecialchars((string) ($item['lista_codigo_aplicada'] ?? '')) ?>" data-item-field="lista_codigo_aplicada">
                                                    <input type="hidden" name="items[<?= $index ?>][articulo_descripcion_original]" value="<?= htmlspecialchars($itemOriginal) ?>" data-item-field="articulo_descripcion_original">
                                                    <input type="text" class="form-control form-control-sm" name="items[<?= $index ?>][articulo_codigo]" value="<?= htmlspecialchars((string) ($item['articulo_codigo'] ?? '')) ?>" data-item-field="articulo_codigo">
                                                </td>
                                                <td class="crm-budget-line-desc">
                                                    <textarea class="form-control form-control-sm crm-budget-desc-textarea<?= $itemModified ? ' is-modified' : '' ?>" rows="3" name="items[<?= $index ?>][articulo_descripcion]" data-item-field="articulo_descripcion" data-item-desc-modified="<?= $itemModified ? '1' : '0' ?>" title="Texto largo soportado: el sistema parte automáticamente la descripción en bloques de 50 caracteres (respetando saltos de línea) y los envía a Tango como DESCRIPCION_ARTICULO + DESCRIPCION_ADICIONAL_DTO. Una línea por concepto si querés controlar el corte."><?= htmlspecialchars($itemDesc) ?></textarea>
                                                    <div class="form-text mt-1 d-flex align-items-center gap-2 flex-wrap">
                                                        <span>Origen: <span data-item-origin-label><?= htmlspecialchars((string) strtoupper((string) ($item['precio_origen'] ?? 'manual'))) ?></span></span>
                                                        <span class="badge bg-warning-subtle text-warning-emphasis<?= $itemModified ? '' : ' d-none' ?>" data-item-desc-badge title="La descripción fue editada y se enviará a Tango sobrescribiendo el nombre original del catálogo">Editada</span>
                                                        <span class="text-muted small" data-item-desc-chunks-label></span>
                                                    </div>
                                                </td>
                                                <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm" name="items[<?= $index ?>][cantidad]" value="<?= htmlspecialchars((string) ($item['cantidad'] ?? 1)) ?>" data-item-field="cantidad"></td>
                                                <td><input type="text" class="form-control form-control-sm text-end text-muted" value="—" readonly tabindex="-1" data-item-stock style="background: transparent; border-color: transparent;"></td>
                                                <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm" name="items[<?= $index ?>][precio_unitario]" value="<?= htmlspecialchars((string) ($item['precio_unitario'] ?? 0)) ?>" data-item-field="precio_unitario"></td>
                                                <td><input type="number" step="0.0001" min="0" max="100" class="form-control form-control-sm" name="items[<?= $index ?>][bonificacion_porcentaje]" value="<?= htmlspecialchars((string) ($item['bonificacion_porcentaje'] ?? 0)) ?>" data-item-field="bonificacion_porcentaje"></td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm crm-budget-line-amount" value="$<?= number_format((float) ($item['importe_neto'] ?? 0), 2, ',', '.') ?>" readonly data-item-amount>
                                                    <input type="hidden" name="items[<?= $index ?>][importe_neto]" value="<?= htmlspecialchars((string) ($item['importe_neto'] ?? 0)) ?>" data-item-field="importe_neto">
                                                </td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-outline-danger btn-sm" data-remove-item title="Quitar renglon"><i class="bi bi-x-lg"></i></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card rxn-form-card">
                <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-start">
                    <div class="small text-muted">
                        El backend recalcula importes y totales al guardar. Lo que ves aca es una previsualizacion operativa para trabajar mas rapido en CRM.
                    </div>
                    <div class="crm-budget-totals ms-lg-auto">
                        <div class="crm-budget-total-row">
                            <span>Subtotal</span>
                            <span class="crm-budget-total-value" data-total-subtotal>$<?= number_format((float) ($presupuesto['subtotal'] ?? 0), 2, ',', '.') ?></span>
                        </div>
                        <div class="crm-budget-total-row">
                            <span>Descuento</span>
                            <span class="crm-budget-total-value" data-total-descuento>$<?= number_format((float) ($presupuesto['descuento_total'] ?? 0), 2, ',', '.') ?></span>
                        </div>
                        <div class="crm-budget-total-row is-grand">
                            <span>Total</span>
                            <span class="crm-budget-total-value" data-total-general>$<?= number_format((float) ($presupuesto['total'] ?? 0), 2, ',', '.') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            </fieldset>
        </form>

        <?php
            $ownerType = 'crm_presupuesto';
            $ownerId   = ($formMode === 'edit' && !empty($presupuesto['id'])) ? (int) $presupuesto['id'] : null;
            $panelTitle = 'Archivos adjuntos del presupuesto';
            include BASE_PATH . '/app/shared/views/partials/attachments-panel.php';
        ?>
    </div>

<?php
$tangoPayload = '';
$tangoResponse = '';
if (!empty($presupuesto['tango_sync_log'])) {
    $logStr = (string) $presupuesto['tango_sync_log'];
    $logData = json_decode($logStr, true);
    if (is_array($logData)) {
        $tangoPayload = isset($logData['payload']) && $logData['payload'] ? (is_array($logData['payload']) || is_object($logData['payload']) ? json_encode($logData['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $logData['payload']) : '';
        $tangoResponse = isset($logData['response']) && $logData['response'] ? (is_array($logData['response']) || is_object($logData['response']) ? json_encode($logData['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $logData['response']) : (isset($logData['error']) ? $logData['error'] : '');
    } else {
        $tangoPayload = '';
        $tangoResponse = $logStr;
    }
}
?>

<?php if ($tangoPayload !== '' || $tangoResponse !== ''): ?>
<div class="modal fade" id="tangoInspectorModal" tabindex="-1" aria-labelledby="tangoInspectorModalLabel" aria-hidden="true" data-bs-theme="dark">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title" id="tangoInspectorModalLabel"><i class="bi bi-info-circle text-info me-2"></i> Inspeccionando conexión a Tango Connect</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body bg-dark text-light font-monospace small" style="white-space: pre-wrap">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-white">Último Payload Procesado</h6>
                    <button type="button" class="btn btn-sm btn-outline-info copy-payload-btn" title="Copiar al portapapeles" onclick="navigator.clipboard.writeText(document.getElementById('tangoPayloadPre').innerText).then(() => { let prev = this.innerHTML; this.innerHTML = '<i class=\'bi bi-check2\'></i> Copiado!'; setTimeout(() => this.innerHTML = prev, 1500); })">
                        <i class="bi bi-clipboard"></i> Copiar
                    </button>
                </div>
                <hr class="mt-0 border-secondary">
                <div class="mb-4 text-warning" id="tangoPayloadPre"><?= htmlspecialchars($tangoPayload) ?></div>
                
                <h6 class="text-white">Última Respuesta / Error</h6>
                <hr class="border-secondary">
                <div class="mb-2 text-info"><?= htmlspecialchars($tangoResponse) ?></div>
            </div>
            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
ob_start();
?>
<?php
    // Cache-busting con filemtime: cada vez que el JS cambie, el browser baja la
    // versión nueva sin necesidad de hard-refresh (Ctrl+Shift+R).
    $_jsPath = BASE_PATH . '/public/js/crm-presupuestos-form.js';
    $_jsVer  = is_file($_jsPath) ? filemtime($_jsPath) : time();
?>
<script src="/js/crm-presupuestos-form.js?v=<?= (int) $_jsVer ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (!window.RxnShortcuts) {
            console.warn('[Presupuesto form] RxnShortcuts no disponible al DOMContentLoaded');
            return;
        }

        RxnShortcuts.register({
            id: 'presupuesto-enviar-tango',
            keys: ['Alt+P'],
            description: 'Enviar Presupuesto a Tango',
            group: 'Presupuesto',
            scope: 'global',
            when: () => !!document.querySelector('form[action*="/sync-tango"] button[type="submit"]:not([disabled])'),
            action: (e) => {
                e.preventDefault();
                const btn = document.querySelector('form[action*="/sync-tango"] button[type="submit"]:not([disabled])');
                if (btn) btn.click();
            }
        });

        RxnShortcuts.register({
            id: 'presupuesto-enviar-correo',
            keys: ['Alt+E'],
            description: 'Enviar Presupuesto por correo',
            group: 'Presupuesto',
            scope: 'global',
            when: () => !!document.querySelector('form[action*="/enviar-correo"] button[type="submit"]:not([disabled])'),
            action: (e) => {
                e.preventDefault();
                const btn = document.querySelector('form[action*="/enviar-correo"] button[type="submit"]:not([disabled])');
                if (btn) btn.click();
            }
        });
    });
</script>
<script>
/**
 * P0 — Pre-validación client-side antes del submit.
 * P1 — Dirty check al salir (Volver, links, beforeunload).
 * P2 — Warnings inline (vigencia, próximo contacto, cotización, cliente/clasif sin Tango id).
 *
 * Este bloque vive INLINE en la vista (no en crm-presupuestos-form.js) porque depende
 * de selectores que la vista define explícitamente.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('crm-presupuesto-form');
        if (!form) return;

        // ============= P0 — Pre-validación client-side =============

        function showError(input, message) {
            if (!input) return;
            input.classList.add('is-invalid');
            // Buscar o crear el feedback inline.
            var parent = input.closest('.crm-budget-col-2, .crm-budget-col-3, .crm-budget-col-4, .crm-budget-col-1') || input.parentElement;
            if (!parent) return;
            var feedback = parent.querySelector('.crm-budget-clientside-feedback');
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback d-block crm-budget-clientside-feedback';
                feedback.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>';
                parent.appendChild(feedback);
            }
            // El span con el mensaje:
            var msgSpan = feedback.querySelector('.crm-budget-clientside-msg');
            if (!msgSpan) {
                msgSpan = document.createElement('span');
                msgSpan.className = 'crm-budget-clientside-msg';
                feedback.appendChild(msgSpan);
            }
            msgSpan.textContent = message;
        }

        function clearErrors() {
            form.querySelectorAll('.is-invalid').forEach(function (el) {
                el.classList.remove('is-invalid');
            });
            form.querySelectorAll('.crm-budget-clientside-feedback').forEach(function (el) {
                el.remove();
            });
            // Sacar el banner sticky del round-trip server (si existía).
            var banner = document.getElementById('crm-budget-clientside-banner');
            if (banner) banner.remove();
        }

        function showBanner(errors) {
            var existing = document.getElementById('crm-budget-clientside-banner');
            if (existing) existing.remove();
            var banner = document.createElement('div');
            banner.id = 'crm-budget-clientside-banner';
            banner.className = 'alert alert-danger shadow-sm mb-3 sticky-top';
            banner.style.top = '0';
            banner.style.zIndex = '50';
            banner.setAttribute('role', 'alert');
            var html = '<div class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> Revisá los datos del presupuesto antes de guardar</div><ul class="mb-0 ps-3">';
            errors.forEach(function (msg) {
                html += '<li>' + msg.replace(/[<>&]/g, function (c) {
                    return { '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c];
                }) + '</li>';
            });
            html += '</ul>';
            banner.innerHTML = html;
            // Insertar al inicio del shell del form.
            var shell = form.closest('.crm-budget-shell') || form.parentElement;
            if (shell) shell.insertBefore(banner, shell.firstChild);
        }

        function validateBeforeSubmit() {
            clearErrors();
            var errors = [];
            var firstInvalid = null;

            // Fecha
            var fecha = document.getElementById('presupuesto-fecha');
            if (!fecha || !String(fecha.value || '').trim()) {
                showError(fecha, 'La fecha del presupuesto es obligatoria.');
                errors.push('Falta la fecha del presupuesto.');
                firstInvalid = firstInvalid || fecha;
            }

            // Cliente
            var clientHidden = form.querySelector('[data-client-picker] [data-picker-hidden]');
            var clientInput = form.querySelector('[data-client-picker] [data-picker-input]');
            var clientId = clientHidden ? String(clientHidden.value || '').trim() : '';
            if (!clientId || clientId === '0') {
                showError(clientInput, 'Seleccioná un cliente desde la base CRM.');
                errors.push('Falta el cliente.');
                firstInvalid = firstInvalid || clientInput;
            }

            // Lista de precios
            var lista = document.getElementById('presupuesto-lista');
            if (!lista || !String(lista.value || '').trim()) {
                showError(lista, 'Seleccioná una lista de precios.');
                errors.push('Falta la lista de precios.');
                firstInvalid = firstInvalid || lista;
            }

            // Clasificación
            var clasifInput = document.getElementById('clasificacion_codigo');
            var clasifValue = clasifInput ? String(clasifInput.value || '').trim() : '';
            if (clasifValue === '') {
                showError(clasifInput, 'Seleccioná una clasificación.');
                errors.push('Falta la clasificación.');
                firstInvalid = firstInvalid || clasifInput;
            }

            // Items: al menos 1 con cantidad > 0
            var rows = form.querySelectorAll('[data-items-body] [data-item-row]');
            var validRows = 0;
            rows.forEach(function (row) {
                var qtyEl = row.querySelector('[data-item-field="cantidad"]');
                var qty = qtyEl ? Number(qtyEl.value || 0) : 0;
                if (qty > 0) validRows++;
            });
            if (validRows === 0) {
                errors.push('Agregá al menos un renglón con cantidad mayor a cero.');
                // Foco al picker de búsqueda inline si no hay items.
                var inlinePicker = document.getElementById('inline-picker-input');
                firstInvalid = firstInvalid || inlinePicker;
            }

            if (errors.length > 0) {
                showBanner(errors);
                if (firstInvalid && typeof firstInvalid.focus === 'function') {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(function () { firstInvalid.focus(); }, 200);
                }
                return false;
            }

            return true;
        }

        // Interceptar submit. Listener en CAPTURE para correr antes del submit
        // de crm-presupuestos-form.js que solo desbloquea cabecera.
        form.addEventListener('submit', function (e) {
            if (!validateBeforeSubmit()) {
                e.preventDefault();
                e.stopImmediatePropagation();
            }
        }, true);

        // Limpiar el is-invalid de un campo cuando el operador empieza a corregirlo.
        form.addEventListener('input', function (e) {
            if (e.target.classList && e.target.classList.contains('is-invalid')) {
                e.target.classList.remove('is-invalid');
                var feedback = e.target.parentElement && e.target.parentElement.querySelector('.crm-budget-clientside-feedback');
                if (feedback) feedback.remove();
            }
        });

        // ============= P1 — Dirty check al salir =============

        var initialSnapshot = null;
        function snapshotForm() {
            // Snapshot de los valores del form. Lo ideal sería FormData, pero hay items
            // dinámicos. Usamos un join simple de todos los named inputs.
            var values = [];
            form.querySelectorAll('[name]').forEach(function (el) {
                values.push(el.name + '=' + (el.value || ''));
            });
            return values.join('|');
        }

        // Esperamos a que el JS principal del form haya terminado de inicializar
        // (Flatpickr, defaults, etc) para tomar un snapshot fresco.
        setTimeout(function () { initialSnapshot = snapshotForm(); }, 600);

        function isFormDirty() {
            if (initialSnapshot === null) return false;
            return snapshotForm() !== initialSnapshot;
        }

        // Limpiar dirty al submit exitoso (form se redirige al server, no necesitamos confirmar).
        form.addEventListener('submit', function () {
            // El submit ya pasó la pre-validación, asumimos que va a guardar OK.
            initialSnapshot = null; // bloquea el beforeunload mientras navega.
            window.removeEventListener('beforeunload', beforeUnloadHandler);
        });

        function beforeUnloadHandler(e) {
            if (!isFormDirty()) return undefined;
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
        window.addEventListener('beforeunload', beforeUnloadHandler);

        // Interceptar click en botón Volver (data-rxn-back) y links del menú lateral.
        function confirmLeave(e, href) {
            if (!isFormDirty()) return true;
            e.preventDefault();
            e.stopImmediatePropagation();
            var msg = 'Tenés cambios sin guardar en el presupuesto. ¿Salir igual? Vas a perder lo cargado.';
            if (window.rxnConfirm) {
                window.rxnConfirm({
                    title: 'Confirmar salida',
                    message: msg,
                    confirmText: 'Salir y perder',
                    cancelText: 'Seguir editando',
                    type: 'warning',
                    onConfirm: function () {
                        window.removeEventListener('beforeunload', beforeUnloadHandler);
                        if (href) window.location.href = href;
                    }
                });
            } else if (confirm(msg)) {
                window.removeEventListener('beforeunload', beforeUnloadHandler);
                if (href) window.location.href = href;
            }
            return false;
        }

        var backBtn = document.querySelector('a[data-rxn-back]');
        if (backBtn) {
            backBtn.addEventListener('click', function (e) { confirmLeave(e, backBtn.href); }, true);
        }

        // Links del menú lateral (sidebar) — interceptarlos por delegación.
        document.body.addEventListener('click', function (e) {
            var link = e.target.closest('a[href]');
            if (!link) return;
            // No interceptar links dentro del propio form (picker results, etc).
            if (form.contains(link)) return;
            // No interceptar el botón back (ya manejado).
            if (link.matches('[data-rxn-back]')) return;
            // Solo links que cambian de página (no anchors internos ni javascript:).
            var href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
            // No interceptar links externos en target=_blank.
            if (link.target === '_blank') return;
            confirmLeave(e, href);
        }, true);

        // ============= P2 — Warnings inline =============

        function toggleWarning(el, show) {
            if (!el) return;
            el.classList.toggle('d-none', !show);
        }

        // Cotización en 0
        var cotInput = document.getElementById('presupuesto-cotizacion');
        var cotWarning = document.querySelector('[data-cotizacion-warning]');
        function checkCotizacion() {
            if (!cotInput) return;
            var val = parseFloat(String(cotInput.value || '').replace(',', '.'));
            toggleWarning(cotWarning, !isNaN(val) && val === 0);
        }
        if (cotInput) {
            cotInput.addEventListener('input', checkCotizacion);
            cotInput.addEventListener('change', checkCotizacion);
            checkCotizacion();
        }

        // Próximo contacto en el pasado
        var pcInput = document.getElementById('presupuesto-proximo-contacto');
        var pcWarning = document.querySelector('[data-proximo-contacto-warning]');
        function checkProximoContacto() {
            if (!pcInput || !pcInput.value) { toggleWarning(pcWarning, false); return; }
            var val = new Date(pcInput.value.replace(' ', 'T'));
            var now = new Date();
            toggleWarning(pcWarning, val.getTime() < now.getTime());
        }
        if (pcInput) {
            pcInput.addEventListener('input', checkProximoContacto);
            pcInput.addEventListener('change', checkProximoContacto);
            checkProximoContacto();
        }

        // Vigencia anterior a la fecha del presupuesto
        var vigInput = document.getElementById('presupuesto-vigencia');
        var fechaInput = document.getElementById('presupuesto-fecha');
        var vigWarning = document.querySelector('[data-vigencia-warning]');
        function checkVigencia() {
            if (!vigInput || !vigInput.value || !fechaInput || !fechaInput.value) { toggleWarning(vigWarning, false); return; }
            var v = new Date(vigInput.value.replace(' ', 'T'));
            var f = new Date(fechaInput.value.replace(' ', 'T'));
            toggleWarning(vigWarning, v.getTime() < f.getTime());
        }
        if (vigInput) {
            vigInput.addEventListener('input', checkVigencia);
            vigInput.addEventListener('change', checkVigencia);
        }
        if (fechaInput) {
            fechaInput.addEventListener('input', checkVigencia);
            fechaInput.addEventListener('change', checkVigencia);
        }
        checkVigencia();

        // Cliente sin id_gva14_tango — el endpoint /clientes/contexto no devuelve
        // hoy ese flag, así que para detectarlo nos colgamos del response global y
        // si es necesario hacemos un fetch extra. Por simplicidad: warning visual
        // basado en si el clientIdPill muestra "Sin cliente". Cobertura ampliada
        // requeriría modificar el endpoint contexto — lo dejo como follow-up.
        // (El warning se muestra solo cuando el cliente seleccionado NO trae
        // id_gva14_tango; el JS principal aplica la clase cuando corresponda.)

        // Clasificación sin id_gva81_tango: el hidden #clasificacion_id_tango
        // queda vacío si no se mapeó. Watch al input principal y al hidden.
        var clasifIdHidden = document.getElementById('clasificacion_id_tango');
        var clasifInputCheck = document.getElementById('clasificacion_codigo');
        var clasifWarning = document.querySelector('[data-clasif-tango-warning]');
        function checkClasifTango() {
            if (!clasifInputCheck) return;
            var hasCodigo = String(clasifInputCheck.value || '').trim() !== '';
            var hasIdTango = clasifIdHidden && String(clasifIdHidden.value || '').trim() !== '';
            toggleWarning(clasifWarning, hasCodigo && !hasIdTango);
        }
        if (clasifInputCheck) {
            clasifInputCheck.addEventListener('input', checkClasifTango);
            clasifInputCheck.addEventListener('change', checkClasifTango);
            // El picker setea el hidden de id_tango, así que polling suave.
            setInterval(checkClasifTango, 1500);
            checkClasifTango();
        }
    });
})();
</script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
