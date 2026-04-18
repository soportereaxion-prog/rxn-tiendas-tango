<?php
$pageTitle = $formMode === 'edit' ? 'Editar Pedido de Servicio - rxn_suite' : 'Nuevo Pedido de Servicio - rxn_suite';
$usePageHeader = false; // Mantiene el layout header apagado explícitamente

ob_start();
?>
    <style>
        .crm-service-shell {
            max-width: 1460px;
        }

        .crm-service-form .card-body {
            padding: 0.95rem 1.05rem;
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
            min-height: 1.15rem;
        }

        .crm-sheet-grid {
            display: grid;
            grid-template-columns: repeat(16, minmax(0, 1fr));
            gap: 0.65rem 0.85rem;
        }

        .crm-col-16 { grid-column: span 16; }
        .crm-col-12 { grid-column: span 12; }
        .crm-col-10 { grid-column: span 10; }
        .crm-col-8 { grid-column: span 8; }
        .crm-col-6 { grid-column: span 6; }
        .crm-col-5 { grid-column: span 5; }
        .crm-col-4 { grid-column: span 4; }
        .crm-col-3 { grid-column: span 3; }
        .crm-col-2 { grid-column: span 2; }

        .crm-summary-strip {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 0.55rem;
        }

        .crm-summary-chip {
            border: 1px solid var(--bs-border-color);
            border-radius: 14px;
            background-color: var(--bs-body-bg);
            padding: 0.5rem 0.75rem;
        }

        .crm-summary-chip-title {
            font-size: 0.74rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--bs-secondary);
            margin-bottom: 0.3rem;
            font-weight: 700;
        }

        .crm-summary-chip-value {
            font-size: 1.15rem;
            font-weight: 700;
            line-height: 1.2;
            color: var(--bs-body-color);
        }

        .crm-compact-help {
            font-size: 0.8rem;
        }

        @media (max-width: 1199.98px) {
            .crm-sheet-grid {
                grid-template-columns: repeat(12, minmax(0, 1fr));
            }

            .crm-col-16,
            .crm-col-12,
            .crm-col-10,
            .crm-col-8,
            .crm-col-6,
            .crm-col-5,
            .crm-col-4,
            .crm-col-3,
            .crm-col-2 {
                grid-column: span 12;
            }

            .crm-summary-strip {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .crm-summary-strip,
            .crm-sheet-grid {
                grid-template-columns: 1fr;
            }

            .crm-col-16,
            .crm-col-12,
            .crm-col-10,
            .crm-col-8,
            .crm-col-6,
            .crm-col-5,
            .crm-col-4,
            .crm-col-3,
            .crm-col-2 {
                grid-column: auto;
            }
        }
    </style>
<?php
$extraHead = ob_get_clean();

ob_start();
?>
<div class="bg-light border rounded-pill px-3 py-1 shadow-sm d-flex align-items-center">
    <div class="rxn-module-actions d-flex flex-wrap gap-2 align-items-center">
        <div class="d-flex align-items-center gap-1 border-end border-secondary pe-2">
                    <?php if (empty($pedido['nro_pedido'])): ?>
                        <button type="submit" form="crm-pedido-servicio-form" name="action" value="tango" class="btn btn-success btn-sm" data-rxn-confirm="¿Confirma que desea enviar este pedido de servicio a Tango?" data-confirm-type="warning"><i class="bi bi-send"></i> Enviar a Tango</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-success btn-sm" disabled><i class="bi bi-check-all"></i> Enviado a Tango</button>
                    <?php endif; ?>
                    
                    <?php if (!empty($pedido['tango_sync_payload']) || !empty($pedido['tango_sync_response'])): ?>
                        <button type="button" class="btn btn-outline-info btn-sm px-2" data-bs-toggle="modal" data-bs-target="#tangoInspectorModal" title="Inspeccionar conexión a Tango Connect">
                            <i class="bi bi-info-circle"></i>
                        </button>
                    <?php endif; ?>
                </div>

                <div class="d-flex align-items-center gap-1 border-end border-secondary pe-2">
                    <?php if ($formMode === 'edit'): ?>
                        <form action="/mi-empresa/crm/pedidos-servicio/<?= (int) ($pedido['id'] ?? 0) ?>/copiar" method="POST" class="d-inline">
                            <button type="submit" class="btn btn-outline-success btn-sm" data-rxn-confirm="¿Confirma que desea duplicar este pedido de servicio?" data-confirm-type="info" title="Copiar"><i class="bi bi-copy"></i></button>
                        </form>
                        <?php if (($pedido['estado_ui'] ?? '') === 'finalizado'): ?>
                            <?php 
                            $correoMsg = '¿Confirma enviar el pedido de servicio por correo al cliente?';
                            if (empty($pedido['nro_pedido'])) {
                                $correoMsg .= ' ¡No te olvides de enviar el PDS a Tango después!';
                            }
                            ?>
                            <form action="/mi-empresa/crm/pedidos-servicio/<?= (int) ($pedido['id'] ?? 0) ?>/enviar-correo" method="POST" class="d-inline">
                                <button type="submit" class="btn btn-outline-primary btn-sm" data-rxn-confirm="<?= htmlspecialchars($correoMsg) ?>" data-confirm-type="primary" title="Enviar por mail"><i class="bi bi-envelope"></i></button>
                            </form>
                        <?php else: ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="El PDS debe estar Finalizado para enviar por correo"><i class="bi bi-envelope"></i></button>
                        <?php endif; ?>
                        <a href="/mi-empresa/crm/pedidos-servicio/<?= (int) ($pedido['id'] ?? 0) ?>/imprimir" class="btn btn-outline-light btn-sm text-body border-secondary shadow-sm" target="_blank" title="Imprimir"><i class="bi bi-printer"></i></a>
                        <?php if (empty($pedido['nro_pedido'])): ?>
                        <form action="/mi-empresa/crm/pedidos-servicio/<?= (int) ($pedido['id'] ?? 0) ?>/eliminar" method="POST" class="d-inline">
                            <button type="submit" class="btn btn-outline-danger btn-sm" data-rxn-confirm="¿Confirma enviar este pedido a la papelera?" data-confirm-type="danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                        <?php else: ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="(window.rxnAlert || alert)('El PDS fue enviado a Tango. No se puede eliminar.', 'danger', 'Operación bloqueada'); event.stopPropagation();" title="No se puede eliminar"><i class="bi bi-trash"></i></button>
                        <?php endif; ?>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Debes guardar el pedido primero"><i class="bi bi-copy"></i></button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Debes guardar el pedido primero"><i class="bi bi-envelope"></i></button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Debes guardar el pedido primero"><i class="bi bi-printer"></i></button>
                    <?php endif; ?>
                </div>

                <div class="d-flex align-items-center gap-2 ps-1">
                    <a href="/mi-empresa/crm/formularios-impresion" class="btn btn-outline-light btn-sm text-body border-secondary shadow-sm" title="Formulario Impresión"><i class="bi bi-pc-display"></i> Form.</a>
                    <a href="<?= htmlspecialchars((string) $helpPath) ?>" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener noreferrer" title="Ayuda"><i class="bi bi-question-circle"></i></a>
                    <a href="<?= htmlspecialchars((string) $basePath) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
                    <?php if (empty($pedido['nro_pedido'])): ?>
                    <button type="submit" form="crm-pedido-servicio-form" name="action" value="save" class="btn btn-primary btn-sm rounded-pill shadow px-4"><i class="bi bi-check2-circle"></i> Guardar</button>
                    <?php endif; ?>
                </div>
            </div>
</div>
<?php
$topbarLeftHtml = ob_get_clean();
ob_start();
?>
<div class="container-fluid mt-2 mb-3 rxn-responsive-container crm-service-shell">

        <?php require BASE_PATH . '/app/shared/views/components/module_notes_panel.php'; ?>

        <?php $flash = \App\Core\Flash::get(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars((string) $flash['type']) ?> shadow-sm mb-4" role="alert">
                <?= htmlspecialchars((string) $flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger shadow-sm mb-4" role="alert">
                <div class="fw-bold mb-2">Revisa los datos del formulario</div>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars((string) $error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card rxn-form-card crm-service-form">
            <div class="card-body">
                <form id="crm-pedido-servicio-form" action="<?= htmlspecialchars((string) $formAction) ?>" method="POST" novalidate>
                    <?php if (!empty($pedido['tratativa_id'])): ?>
                        <input type="hidden" name="tratativa_id" value="<?= htmlspecialchars((string) $pedido['tratativa_id']) ?>">
                    <?php endif; ?>
                    <fieldset <?= !empty($pedido['nro_pedido']) ? 'disabled' : '' ?> class="border-0 p-0 m-0">
                    <?php if (!empty($pedido['tratativa_id'])): ?>
                        <div class="alert alert-info border-0 small mb-3">
                            <i class="bi bi-briefcase-fill"></i> Este PDS forma parte de la
                            <a href="/mi-empresa/crm/tratativas/<?= (int) $pedido['tratativa_id'] ?>" class="alert-link">Tratativa #<?= (int) $pedido['tratativa_id'] ?></a>.
                            Al guardar volverás al detalle de la tratativa.
                        </div>
                    <?php endif; ?>
                    <div class="rxn-form-section mb-2">
                        <div class="rxn-form-section-title">Encabezado operativo</div>
                        <div class="crm-sheet-grid">
                            <div class="crm-col-2">
                                <label class="form-label mb-1">Numero interno</label>
                                <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars((string) $pedido['numero']) ?>" disabled>
                            </div>

                            <div class="crm-col-3">
                                <label for="fecha_inicio" class="form-label mb-1">Fecha y hora de inicio</label>
                                <input type="datetime-local" step="1" class="form-control form-control-sm <?= isset($errors['fecha_inicio']) ? 'is-invalid' : '' ?>" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars((string) $pedido['fecha_inicio']) ?>" required data-calc-start>
                                <?php if (isset($errors['fecha_inicio'])): ?><div class="invalid-feedback"><?= htmlspecialchars((string) $errors['fecha_inicio']) ?></div><?php endif; ?>
                            </div>

                            <div class="crm-col-3">
                                <label for="fecha_finalizado" class="form-label mb-1">Finalizado</label>
                                <input type="datetime-local" step="1" class="form-control form-control-sm <?= isset($errors['fecha_finalizado']) ? 'is-invalid' : '' ?>" id="fecha_finalizado" name="fecha_finalizado" value="<?= htmlspecialchars((string) $pedido['fecha_finalizado']) ?>" data-calc-end>
                                <?php if (isset($errors['fecha_finalizado'])): ?><div class="invalid-feedback"><?= htmlspecialchars((string) $errors['fecha_finalizado']) ?></div><?php endif; ?>
                            </div>

                            <div class="crm-col-1">
                                <label class="form-label mb-1 d-block">&nbsp;</label>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-finalizado-ahora" title="Ajustar fecha finalizado a la fecha y hora actual"><i class="bi bi-clock-history"></i></button>
                            </div>

                            <div class="crm-col-2">
                                <label for="clasificacion_codigo" class="form-label mb-1">Clasificacion</label>
                                <div class="crm-picker-wrap" data-picker data-picker-url="/mi-empresa/crm/pedidos-servicio/clasificaciones/sugerencias" data-picker-allow-manual="1">
                                    <input type="hidden" name="clasificacion_id_tango" id="clasificacion_id_tango" data-picker-extra-hidden value="<?= htmlspecialchars((string) ($pedido['clasificacion_id_tango'] ?? '')) ?>">
                                    <input type="hidden" class="crm-picker-hidden" data-picker-hidden value="<?= htmlspecialchars((string) $pedido['clasificacion_codigo']) ?>">
                                    <input type="text" class="form-control form-control-sm" id="clasificacion_codigo" name="clasificacion_codigo" value="<?= htmlspecialchars((string) $pedido['clasificacion_codigo']) ?>" autocomplete="off" placeholder="Clasificacion" data-picker-input>
                                    <div class="rxn-search-suggestions d-none" data-picker-results></div>
                                </div>
                            </div>

                            <div class="crm-col-2">
                                <label for="descuento" class="form-label mb-1">Descuento</label>
                                <input type="text" class="form-control form-control-sm <?= isset($errors['descuento']) ? 'is-invalid' : '' ?>" id="descuento" name="descuento" value="<?= htmlspecialchars((string) $pedido['descuento']) ?>" placeholder="00:00:00" data-calc-discount>
                                <?php if (isset($errors['descuento'])): ?><div class="invalid-feedback"><?= htmlspecialchars((string) $errors['descuento']) ?></div><?php endif; ?>
                            </div>

                            <div class="crm-col-3">
                                <label for="cliente_nombre" class="form-label mb-1">Cliente</label>
                                <div class="crm-picker-wrap" data-picker data-picker-url="/mi-empresa/crm/pedidos-servicio/clientes/sugerencias">
                                    <input type="hidden" name="cliente_id" value="<?= htmlspecialchars((string) $pedido['cliente_id']) ?>" data-picker-hidden>
                                    <input type="text" class="form-control form-control-sm <?= isset($errors['cliente_id']) ? 'is-invalid' : '' ?>" id="cliente_nombre" name="cliente_nombre" value="<?= htmlspecialchars((string) $pedido['cliente_nombre']) ?>" autocomplete="off" placeholder="Buscar cliente..." data-picker-input>
                                    <div class="rxn-search-suggestions d-none" data-picker-results></div>
                                </div>
                                <div class="form-text crm-picker-meta crm-compact-help text-truncate" data-picker-meta><?= $pedido['cliente_id'] !== '' ? 'Cliente vinculado #' . htmlspecialchars((string) $pedido['cliente_id']) : 'Snapshot local del cliente seleccionado.' ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-section mb-2">
                        <div class="rxn-form-section-title">Detalle tecnico</div>
                        <div class="crm-sheet-grid">
                            <div class="crm-col-3">
                                <label for="solicito" class="form-label mb-1">Solicito</label>
                                <input type="text" class="form-control form-control-sm <?= isset($errors['solicito']) ? 'is-invalid' : '' ?>" id="solicito" name="solicito" value="<?= htmlspecialchars((string) $pedido['solicito']) ?>" maxlength="150" required>
                                <?php if (isset($errors['solicito'])): ?><div class="invalid-feedback"><?= htmlspecialchars((string) $errors['solicito']) ?></div><?php endif; ?>
                            </div>

                            <div class="crm-col-12">
                                <label for="articulo_nombre" class="form-label mb-1">Articulo</label>
                                <div class="crm-picker-wrap" data-picker data-picker-url="/mi-empresa/crm/pedidos-servicio/articulos/sugerencias">
                                    <input type="hidden" name="articulo_id" value="<?= htmlspecialchars((string) $pedido['articulo_id']) ?>" data-picker-hidden>
                                    <input type="text" class="form-control form-control-sm <?= isset($errors['articulo_id']) ? 'is-invalid' : '' ?>" id="articulo_nombre" name="articulo_nombre" value="<?= htmlspecialchars((string) $pedido['articulo_nombre']) ?>" autocomplete="off" placeholder="Buscar articulo por codigo, nombre o descripcion" data-picker-input>
                                    <div class="rxn-search-suggestions d-none" data-picker-results></div>
                                </div>
                                <div class="form-text crm-picker-meta crm-compact-help text-truncate" data-picker-meta><?= $pedido['articulo_id'] !== '' ? 'Articulo vinculado #' . htmlspecialchars((string) $pedido['articulo_id']) : 'Snapshot local del articulo operativo.' ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-section mb-0">
                        <div class="rxn-form-section-title">Diagnostico</div>
                        <div class="rxn-form-grid mb-1">
                            <div class="rxn-form-span-12">
                                <textarea class="form-control border-secondary" id="diagnostico" name="diagnostico" rows="4" placeholder="Describe el contexto, pruebas, observaciones y resolucion prevista."><?= htmlspecialchars((string) $pedido['diagnostico']) ?></textarea>
                            </div>
                        </div>
                        
                        <div class="p-3 border rounded shadow-sm mb-4 rxn-surface">
                            <h6 class="mb-1 fw-bold fs-6">Pega imagenes en el diagnostico</h6>
                            <small class="d-block text-secondary mb-3" style="font-size:0.75rem;">Al pegar desde el portapapeles se agregan como `#imagenN`, se suben al guardar y quedan listas para futuras referencias por mail.</small>
                            <div id="diagnostico-capturas" class="d-flex flex-wrap gap-2">
                                <?php if (isset($pedido['capturas']) && is_array($pedido['capturas'])): ?>
                                    <?php foreach ($pedido['capturas'] as $captura): ?>
                                        <div class="diagnostico-adjunto position-relative border border-secondary rounded p-1 bg-dark shadow-sm" style="width: 100px; height: 100px;">
                                            <img src="<?= htmlspecialchars((string) $captura['url']) ?>" class="w-100 h-100 object-fit-cover rounded">
                                            <div class="position-absolute bottom-0 start-0 end-0 bg-dark bg-opacity-75 text-white text-center rounded-bottom" style="font-size:0.6rem; padding: 0.1rem;"><?= htmlspecialchars((string) $captura['label']) ?></div>
                                            <?php if (!empty($captura['is_temp'])): ?>
                                                <input type="hidden" name="capturas_diagnostico_base64[]" value="<?= htmlspecialchars((string) $captura['is_temp']) ?>">
                                                <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 rounded-circle d-flex align-items-center justify-content-center" style="width: 20px; height: 20px; transform: translate(50%, -50%);" onclick="this.parentElement.remove();"><i class="bi bi-x" style="font-size:0.8rem;"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-section mt-1 mb-4 p-3 border rounded rxn-surface">
                        <div class="crm-summary-strip" style="grid-template-columns: repeat(5, minmax(0, 1fr));">
                            <div class="crm-summary-chip bg-transparent border-0 px-1 py-1">
                                <div class="crm-summary-chip-title" style="color: #9CA3AF !important;">Tiempo bruto</div>
                                <div class="crm-summary-chip-value fs-5" data-calc-gross><?= htmlspecialchars((string) $pedido['duracion_bruta_hhmmss']) ?></div>
                            </div>
                            <div class="crm-summary-chip bg-transparent border-0 px-1 py-1">
                                <div class="crm-summary-chip-title" style="color: #9CA3AF !important;">Descuento aplicado</div>
                                <div class="crm-summary-chip-value fs-5 text-warning" data-calc-discount-preview><?= htmlspecialchars((string) $pedido['descuento']) ?></div>
                            </div>
                            <div class="crm-summary-chip bg-transparent border-0 px-1 py-1">
                                <div class="crm-summary-chip-title" style="color: #9CA3AF !important;">Tiempo neto</div>
                                <div class="crm-summary-chip-value fs-5 text-success" data-calc-net><?= htmlspecialchars((string) $pedido['duracion_neta_hhmmss']) ?></div>
                            </div>
                            <div class="crm-summary-chip bg-transparent border-0 px-1 py-1">
                                <div class="crm-summary-chip-title" style="color: #9CA3AF !important;">Decimal</div>
                                <div class="crm-summary-chip-value fs-5 text-info" data-calc-decimal><?= number_format((float) ($pedido['tiempo_decimal'] ?? 0), 4, '.', '') ?></div>
                            </div>
                            <div class="crm-summary-chip bg-transparent border-0 px-1 py-1">
                                <div class="crm-summary-chip-title" style="color: #9CA3AF !important;">Estado operativo</div>
                                <div class="crm-summary-chip-value mt-1">
                                    <?php if ($pedido['estado_ui'] === 'finalizado'): ?>
                                        <span class="badge bg-success p-2 mb-2">Finalizado</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark p-2 mb-2">Abierto</span>
                                    <?php endif; ?>
                                    <?php if (!empty($pedido['nro_pedido'])): ?>
                                        <div class="badge bg-success border text-white mt-1 w-100 py-2">
                                            <i class="bi bi-cloud-check"></i> <?= htmlspecialchars((string) $pedido['nro_pedido']) ?>
                                        </div>
                                    <?php elseif (($pedido['tango_sync_status'] ?? '') === 'error'): ?>
                                        <div class="badge bg-danger border text-white mt-1 w-100 py-2">
                                            <i class="bi bi-exclamation-octagon"></i> ERROR API
                                        </div>
                                    <?php else: ?>
                                        <div class="badge bg-dark border text-muted mt-1 w-100 py-2">
                                            Pedido Tango
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-none" data-calc-side-net><?= htmlspecialchars((string) $pedido['duracion_neta_hhmmss']) ?></div>
                        </div>
                    </div>

                    <div class="rxn-form-section mb-4">
                        <div class="rxn-form-section-title">¿Por qué descuento?</div>
                        <div class="rxn-form-grid">
                            <div class="rxn-form-span-12">
                                <textarea class="form-control border-secondary <?= isset($errors['motivo_descuento']) ? 'is-invalid' : '' ?>" id="motivo_descuento" name="motivo_descuento" rows="3" placeholder="Resume el motivo o justificacion tecnica del descuento."><?= htmlspecialchars((string) $pedido['motivo_descuento']) ?></textarea>
                                <?php if (isset($errors['motivo_descuento'])): ?><div class="invalid-feedback"><?= htmlspecialchars((string) $errors['motivo_descuento']) ?></div><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    </fieldset>
                </form>
            </div>
        </div>
    </div>

    <?php if (!empty($pedido['tango_sync_payload']) || !empty($pedido['tango_sync_response'])): ?>
    <div class="modal fade" id="tangoInspectorModal" tabindex="-1" aria-labelledby="tangoInspectorModalLabel" aria-hidden="true" data-bs-theme="dark">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tangoInspectorModalLabel"><i class="bi bi-info-circle text-info me-2"></i> Inspeccionando conexión a Tango Connect</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body bg-dark text-light font-monospace small" style="white-space: pre-wrap">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Último Payload Procesado</h6>
                        <button type="button" class="btn btn-sm btn-outline-info copy-payload-btn" title="Copiar al portapapeles" onclick="navigator.clipboard.writeText(document.getElementById('tangoPayloadPre').innerText).then(() => { let prev = this.innerHTML; this.innerHTML = '<i class=\'bi bi-check2\'></i> Copiado!'; setTimeout(() => this.innerHTML = prev, 1500); })">
                            <i class="bi bi-clipboard"></i> Copiar
                        </button>
                    </div>
                    <hr class="mt-0">
                    <div class="mb-4 text-warning" id="tangoPayloadPre">
                        <?= htmlspecialchars(json_encode(json_decode((string) $pedido['tango_sync_payload'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?>
                    </div>
                    
                    <h6>Última Respuesta</h6>
                    <hr>
                    <div class="mb-2 text-info">
                        <?= htmlspecialchars(json_encode(json_decode((string) $pedido['tango_sync_response'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="/js/crm-pedidos-servicio-form.js?v=<?= time() ?>"></script>
    <?php if ($formMode === 'create' && empty($errors) && !isset($_GET['inicio'])): ?>
    <script>
        (function() {
            const fechaInput = document.getElementById('fecha_inicio');
            if (!fechaInput) return;
            let holdsFocus = false;

            fechaInput.addEventListener('focus', () => holdsFocus = true);
            fechaInput.addEventListener('change', () => holdsFocus = true);

            const pad = (n) => String(n).padStart(2, '0');
            const tick = () => {
                if (holdsFocus) return;
                const now = new Date();
                const localISOTime = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
                fechaInput.value = localISOTime;
            };

            tick();
        })();
    </script>
    <?php endif; ?>
    <script>
        (function() {
            if (!window.RxnShortcuts) return;

            RxnShortcuts.register({
                id: 'pds-enviar-tango',
                keys: ['Alt+P'],
                description: 'Enviar Pedido de Servicio a Tango',
                group: 'Pedido de Servicio',
                scope: 'global',
                when: () => !!document.querySelector('form[action*="/sync-tango"] button[type="submit"]:not([disabled])')
                          || !!document.querySelector('button[name="action"][value="tango"]:not([disabled])'),
                action: (e) => {
                    e.preventDefault();
                    const btn = document.querySelector('form[action*="/sync-tango"] button[type="submit"]:not([disabled])')
                             || document.querySelector('button[name="action"][value="tango"]:not([disabled])');
                    if (btn) btn.click();
                }
            });

            RxnShortcuts.register({
                id: 'pds-enviar-correo',
                keys: ['Alt+E'],
                description: 'Enviar Pedido de Servicio por correo',
                group: 'Pedido de Servicio',
                scope: 'global',
                when: () => !!document.querySelector('form[action*="/enviar-correo"] button[type="submit"]:not([disabled])'),
                action: (e) => {
                    e.preventDefault();
                    const btn = document.querySelector('form[action*="/enviar-correo"] button[type="submit"]:not([disabled])');
                    if (btn) btn.click();
                }
            });
        })();
    </script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
