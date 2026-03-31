<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $formMode === 'edit' ? 'Editar Pedido de Servicio' : 'Nuevo Pedido de Servicio' ?> - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
    <style>
        .crm-service-shell {
            max-width: 1460px;
        }

        .crm-service-form .card-body {
            padding: 1.25rem 1.35rem;
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
            gap: 0.9rem 1rem;
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
            gap: 0.85rem;
        }

        .crm-summary-chip {
            border: 1px solid rgba(13, 110, 253, 0.12);
            border-radius: 14px;
            background: linear-gradient(180deg, rgba(255,255,255,0.95), rgba(247,249,253,0.98));
            padding: 0.75rem 0.9rem;
        }

        .crm-summary-chip-title {
            font-size: 0.74rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6c757d;
            margin-bottom: 0.3rem;
            font-weight: 700;
        }

        .crm-summary-chip-value {
            font-size: 1.15rem;
            font-weight: 700;
            line-height: 1.2;
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
</head>
<body class="rxn-page-shell">
    <div class="container-fluid mt-4 mb-4 rxn-responsive-container crm-service-shell">
        <div class="rxn-module-header mb-4">
            <div>
                <h2 class="mb-1"><?= $formMode === 'edit' ? 'Pedido de Servicio #' . (int) $pedido['numero'] : 'Nuevo Pedido de Servicio' ?></h2>
                <p class="text-muted mb-0">CRM operativo con control de inicio, cierre, descuento horario y calculo neto.</p>
            </div>
            <div class="rxn-module-actions">
                <?php require BASE_PATH . '/app/shared/views/components/user_action_menu.php'; ?>
                <button type="submit" form="crm-pedido-servicio-form" name="action" value="save" class="btn btn-primary"><i class="bi bi-check2-circle"></i> <?= $formMode === 'edit' ? 'Guardar pedido' : 'Crear pedido' ?></button>
                <button type="submit" form="crm-pedido-servicio-form" name="action" value="tango" class="btn btn-success" onclick="return confirm('¿Confirma que desea enviar este pedido de servicio a Tango?');"><i class="bi bi-cloud-upload"></i> Guardar y Enviar a Tango</button>
                <a href="<?= htmlspecialchars((string) $helpPath) ?>" class="btn btn-outline-info" target="_blank" rel="noopener noreferrer"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="<?= htmlspecialchars((string) $basePath) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver al listado</a>
            </div>
        </div>

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
                    <div class="rxn-form-section mb-4">
                        <div class="rxn-form-section-title">Encabezado operativo</div>
                        <div class="crm-sheet-grid">
                            <div class="crm-col-2">
                                <label class="form-label mb-1">Numero</label>
                                <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars((string) $pedido['numero']) ?>" disabled>
                                <div class="form-text crm-compact-help">Correlativo interno.</div>
                            </div>

                            <div class="crm-col-3">
                                <label for="fecha_inicio" class="form-label mb-1">Fecha y hora de inicio</label>
                                <input type="datetime-local" step="1" class="form-control form-control-sm <?= isset($errors['fecha_inicio']) ? 'is-invalid' : '' ?>" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars((string) $pedido['fecha_inicio']) ?>" required data-calc-start>
                                <?php if (isset($errors['fecha_inicio'])): ?><div class="invalid-feedback"><?= htmlspecialchars((string) $errors['fecha_inicio']) ?></div><?php endif; ?>
                            </div>

                            <div class="crm-col-3">
                                <label for="fecha_finalizado" class="form-label mb-1">Finalizado</label>
                                <input type="datetime-local" step="1" class="form-control form-control-sm <?= isset($errors['fecha_finalizado']) ? 'is-invalid' : '' ?>" id="fecha_finalizado" name="fecha_finalizado" value="<?= htmlspecialchars((string) $pedido['fecha_finalizado']) ?>" required data-calc-end>
                                <?php if (isset($errors['fecha_finalizado'])): ?><div class="invalid-feedback"><?= htmlspecialchars((string) $errors['fecha_finalizado']) ?></div><?php endif; ?>
                            </div>

                            <div class="crm-col-2">
                                <label for="clasificacion_codigo" class="form-label mb-1">Clasificacion</label>
                                <div class="crm-picker-wrap" data-picker data-picker-url="/rxnTiendasIA/public/mi-empresa/crm/pedidos-servicio/clasificaciones/sugerencias" data-picker-allow-manual="1">
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

                            <div class="crm-col-2">
                                <label for="solicito" class="form-label mb-1">Solicito</label>
                                <input type="text" class="form-control form-control-sm <?= isset($errors['solicito']) ? 'is-invalid' : '' ?>" id="solicito" name="solicito" value="<?= htmlspecialchars((string) $pedido['solicito']) ?>" maxlength="150" required>
                                <?php if (isset($errors['solicito'])): ?><div class="invalid-feedback"><?= htmlspecialchars((string) $errors['solicito']) ?></div><?php endif; ?>
                            </div>

                            <div class="crm-col-2">
                                <label for="nro_pedido" class="form-label mb-1">Nro de pedido</label>
                                <input type="text" class="form-control form-control-sm" id="nro_pedido" name="nro_pedido" value="<?= htmlspecialchars((string) $pedido['nro_pedido']) ?>" maxlength="80">
                            </div>

                            <div class="crm-col-6">
                                <label for="cliente_nombre" class="form-label mb-1">Cliente</label>
                                <div class="crm-picker-wrap" data-picker data-picker-url="/rxnTiendasIA/public/mi-empresa/crm/pedidos-servicio/clientes/sugerencias">
                                    <input type="hidden" name="cliente_id" value="<?= htmlspecialchars((string) $pedido['cliente_id']) ?>" data-picker-hidden>
                                    <input type="text" class="form-control form-control-sm <?= isset($errors['cliente_id']) ? 'is-invalid' : '' ?>" id="cliente_nombre" name="cliente_nombre" value="<?= htmlspecialchars((string) $pedido['cliente_nombre']) ?>" autocomplete="off" placeholder="Buscar cliente por nombre, razon social, email o documento" data-picker-input>
                                    <div class="rxn-search-suggestions d-none" data-picker-results></div>
                                </div>
                                <?php if (isset($errors['cliente_id'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars((string) $errors['cliente_id']) ?></div><?php endif; ?>
                                <div class="form-text crm-picker-meta crm-compact-help" data-picker-meta><?= $pedido['cliente_id'] !== '' ? 'Cliente vinculado #' . htmlspecialchars((string) $pedido['cliente_id']) : 'Snapshot local del cliente seleccionado.' ?></div>
                            </div>

                            <div class="crm-col-10">
                                <label for="articulo_nombre" class="form-label mb-1">Articulo</label>
                                <div class="crm-picker-wrap" data-picker data-picker-url="/rxnTiendasIA/public/mi-empresa/crm/pedidos-servicio/articulos/sugerencias">
                                    <input type="hidden" name="articulo_id" value="<?= htmlspecialchars((string) $pedido['articulo_id']) ?>" data-picker-hidden>
                                    <input type="text" class="form-control form-control-sm <?= isset($errors['articulo_id']) ? 'is-invalid' : '' ?>" id="articulo_nombre" name="articulo_nombre" value="<?= htmlspecialchars((string) $pedido['articulo_nombre']) ?>" autocomplete="off" placeholder="Buscar articulo por codigo, nombre o descripcion" data-picker-input>
                                    <div class="rxn-search-suggestions d-none" data-picker-results></div>
                                </div>
                                <?php if (isset($errors['articulo_id'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars((string) $errors['articulo_id']) ?></div><?php endif; ?>
                                <div class="form-text crm-picker-meta crm-compact-help" data-picker-meta><?= $pedido['articulo_id'] !== '' ? 'Articulo vinculado #' . htmlspecialchars((string) $pedido['articulo_id']) : 'Snapshot local del articulo operativo.' ?></div>
                            </div>

                        </div>
                    </div>

                    <div class="rxn-form-section mb-4 bg-light p-3 border rounded">
                        <div class="crm-summary-strip">
                            <div class="crm-summary-chip">
                                <div class="crm-summary-chip-title">Numero</div>
                                <div class="crm-summary-chip-value">#<?= htmlspecialchars((string) $pedido['numero']) ?></div>
                            </div>
                            <div class="crm-summary-chip">
                                <div class="crm-summary-chip-title">Tiempo bruto</div>
                                <div class="crm-summary-chip-value" data-calc-gross><?= htmlspecialchars((string) $pedido['duracion_bruta_hhmmss']) ?></div>
                            </div>
                            <div class="crm-summary-chip">
                                <div class="crm-summary-chip-title">Descuento aplicado</div>
                                <div class="crm-summary-chip-value text-warning" data-calc-discount-preview><?= htmlspecialchars((string) $pedido['descuento']) ?></div>
                            </div>
                            <div class="crm-summary-chip">
                                <div class="crm-summary-chip-title">Tiempo neto</div>
                                <div class="crm-summary-chip-value text-success" data-calc-net><?= htmlspecialchars((string) $pedido['duracion_neta_hhmmss']) ?></div>
                            </div>
                            <div class="crm-summary-chip">
                                <div class="crm-summary-chip-title">Estado</div>
                                <div class="crm-summary-chip-value"><?php if ($pedido['estado_ui'] === 'finalizado'): ?><span class="badge bg-success">Finalizado</span><?php else: ?><span class="badge bg-warning text-dark">Abierto</span><?php endif; ?></div>
                                <div class="small text-muted mt-2">Preparado para la sincro futura segun parametros del tenant.</div>
                            </div>
                            <div class="crm-summary-chip">
                                <div class="crm-summary-chip-title">Snapshot</div>
                                <div class="small text-muted">Cliente y articulo quedan guardados localmente para sostener historico y futuras sincros.</div>
                                <div class="fw-semibold mt-2 small" data-calc-side-net><?= htmlspecialchars((string) $pedido['duracion_neta_hhmmss']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-section mb-4">
                        <div class="rxn-form-section-title">Detalle tecnico</div>
                        <div class="rxn-form-grid">
                            <div class="rxn-form-span-12">
                                <label for="diagnostico" class="form-label">Diagnostico</label>
                                <textarea class="form-control" id="diagnostico" name="diagnostico" rows="8" placeholder="Describe el contexto, pruebas, observaciones y resolucion prevista."><?= htmlspecialchars((string) $pedido['diagnostico']) ?></textarea>
                            </div>
                            <div class="rxn-form-span-12">
                                <label for="motivo_descuento" class="form-label">Motivo de descuento</label>
                                <textarea class="form-control" id="motivo_descuento" name="motivo_descuento" rows="4" placeholder="Explica el motivo si aplicaste un descuento de tiempo."><?= htmlspecialchars((string) $pedido['motivo_descuento']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-actions d-flex gap-2 justify-content-end">
                        <a href="<?= htmlspecialchars((string) $basePath) ?>" class="btn btn-light me-auto">Cancelar</a>
                        <button type="submit" name="action" value="tango" class="btn btn-success py-2 fw-bold" onclick="return confirm('¿Confirma que desea enviar este pedido de servicio a Tango?');">Guardar y Enviar a Tango</button>
                        <button type="submit" name="action" value="save" class="btn btn-primary py-2 fw-bold"><?= $formMode === 'edit' ? 'Guardar cambios' : 'Crear pedido de servicio' ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/rxnTiendasIA/public/js/crm-pedidos-servicio-form.js"></script>
</body>
</html>
