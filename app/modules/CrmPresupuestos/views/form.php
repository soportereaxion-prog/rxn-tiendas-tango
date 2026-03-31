<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $formMode === 'edit' ? 'Editar Presupuesto CRM' : 'Nuevo Presupuesto CRM' ?> - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
    <style>
        .crm-budget-shell {
            max-width: 1480px;
        }

        .crm-budget-form .card-body {
            padding: 0.85rem 1rem;
        }

        .crm-budget-form .form-label {
            margin-bottom: 0.24rem !important;
        }

        .crm-budget-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 0.45rem 0.65rem;
        }

        .crm-budget-col-12 { grid-column: span 12; }
        .crm-budget-col-8 { grid-column: span 8; }
        .crm-budget-col-6 { grid-column: span 6; }
        .crm-budget-col-4 { grid-column: span 4; }
        .crm-budget-col-3 { grid-column: span 3; }
        .crm-budget-col-2 { grid-column: span 2; }

        .crm-budget-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.55rem;
        }

        .crm-budget-chip {
            border: 1px solid rgba(13, 110, 253, 0.1);
            border-radius: 14px;
            background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(247,249,253,0.98));
            padding: 0.6rem 0.75rem;
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
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.96);
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
            gap: 0.4rem;
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
            .crm-budget-col-2 {
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
            .crm-budget-col-2 {
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
</head>
<body class="rxn-page-shell">
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
    ?>
    <div class="container-fluid mt-4 mb-4 rxn-responsive-container crm-budget-shell">
        <div class="rxn-module-header mb-3">
            <div>
                <h2 class="mb-1"><?= $formMode === 'edit' ? 'Presupuesto #' . (int) $presupuesto['numero'] : 'Nuevo Presupuesto CRM' ?></h2>
                <p class="text-muted mb-0">Pantalla operativa de cabecera comercial y renglones acumulables para preparar el circuito documental del CRM.</p>
            </div>
            <div class="rxn-module-actions">
                <?php require BASE_PATH . '/app/shared/views/components/user_action_menu.php'; ?>
                <?php if ($formMode === 'edit'): ?>
                    <form action="/rxnTiendasIA/public/mi-empresa/crm/presupuestos/<?= (int) ($presupuesto['id'] ?? 0) ?>/copiar" method="POST" class="d-inline-block m-0 p-0" >
                        <button type="submit" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-copy"></i> Copiar</button>
                    </form>
                    <form action="/rxnTiendasIA/public/mi-empresa/crm/presupuestos/<?= (int) ($presupuesto['id'] ?? 0) ?>/enviar-correo" method="POST" class="d-inline-block m-0 p-0">
                        <button type="submit" class="btn btn-outline-primary shadow-sm" data-rxn-confirm="¿Enviar el presupuesto por correo electrónico al cliente?" data-confirm-type="info"><i class="bi bi-envelope-paper"></i> Enviar</button>
                    </form>
                    <a href="/rxnTiendasIA/public/mi-empresa/crm/presupuestos/<?= (int) ($presupuesto['id'] ?? 0) ?>/imprimir" class="btn btn-outline-dark shadow-sm" target="_blank" rel="noopener noreferrer"><i class="bi bi-printer"></i> Imprimir</a>
                <?php endif; ?>
                <a href="/rxnTiendasIA/public/mi-empresa/crm/formularios-impresion/crm_presupuesto" class="btn btn-outline-dark"><i class="bi bi-easel2"></i> Formulario</a>
                <a href="<?= htmlspecialchars((string) $syncCatalogosPath) ?>" class="btn btn-outline-warning" data-rxn-confirm="¿Sincronizar catalogos comerciales CRM antes de seguir trabajando?" data-confirm-type="warning"><i class="bi bi-arrow-repeat"></i> Sync Catalogos</a>
                <?php if (($presupuesto['estado'] ?? '') !== 'emitido'): ?>
                    <button type="submit" form="crm-presupuesto-form" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Guardar</button>
                <?php endif; ?>
                <a href="<?= htmlspecialchars((string) $helpPath) ?>" class="btn btn-outline-info" target="_blank" rel="noopener noreferrer"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="<?= htmlspecialchars((string) $basePath) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver al listado</a>
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
            <div class="alert alert-danger shadow-sm mb-3" role="alert">
                <div class="fw-bold mb-2">Revisa los datos del presupuesto</div>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $message): ?>
                        <li><?= htmlspecialchars((string) $message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (($presupuesto['estado'] ?? '') === 'emitido'): ?>
            <div class="alert alert-info shadow-sm border-0 bg-info bg-opacity-10 mb-3" role="alert">
                <i class="bi bi-lock-fill text-info mt-1 me-2 float-start"></i>
                <div class="text-info-emphasis ps-4">Este presupuesto ya fue emitido y se encuentra blindado en Solo Lectura (Inmodificable).</div>
            </div>
        <?php endif; ?>

        <form id="crm-presupuesto-form" class="crm-budget-form" action="<?= htmlspecialchars((string) $formAction) ?>" method="POST" novalidate>
            <fieldset <?= ($presupuesto['estado'] ?? '') === 'emitido' ? 'disabled' : '' ?>>
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
                    </div>

                    <div class="crm-budget-section-title">Cabecera comercial</div>
                    <div class="crm-budget-grid mb-2">
                        <div class="crm-budget-col-2">
                            <label for="presupuesto-fecha" class="form-label">Fecha</label>
                            <input type="datetime-local" class="form-control <?= isset($errors['fecha']) ? 'is-invalid' : '' ?>" id="presupuesto-fecha" name="fecha" value="<?= htmlspecialchars((string) ($presupuesto['fecha'] ?? '')) ?>" required>
                        </div>

                        <div class="crm-budget-col-6">
                            <label class="form-label">Cliente</label>
                            <div class="crm-picker-wrap" data-client-picker data-picker-url="/rxnTiendasIA/public/mi-empresa/crm/presupuestos/clientes/sugerencias" data-context-url="/rxnTiendasIA/public/mi-empresa/crm/presupuestos/clientes/contexto">
                                <input type="hidden" name="cliente_id" value="<?= htmlspecialchars((string) ($presupuesto['cliente_id'] ?? '')) ?>" class="crm-picker-hidden" data-picker-hidden>
                                <input type="text" class="form-control <?= isset($errors['cliente_id']) ? 'is-invalid' : '' ?>" name="cliente_nombre" value="<?= htmlspecialchars((string) ($presupuesto['cliente_nombre'] ?? '')) ?>" placeholder="Buscar cliente CRM por nombre, documento o codigo..." autocomplete="off" data-picker-input>
                                <div class="rxn-search-suggestions crm-picker-results d-none" data-picker-results></div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                                <span class="crm-budget-client-pill" data-client-id-pill><?= (string) ($presupuesto['cliente_id'] ?? '') !== '' ? 'Cliente #' . htmlspecialchars((string) $presupuesto['cliente_id']) : 'Sin cliente' ?></span>
                                <span class="small text-muted" data-client-documento><?= (string) ($presupuesto['cliente_documento'] ?? '') !== '' ? 'Doc: ' . htmlspecialchars((string) $presupuesto['cliente_documento']) : 'Sin documento cargado' ?></span>
                            </div>
                            <div class="form-text crm-picker-meta" data-picker-meta><?= (string) ($presupuesto['cliente_id'] ?? '') !== '' ? 'Defaults comerciales listos para autocompletar la cabecera.' : 'Selecciona un cliente para autocompletar condicion, lista, vendedor y transporte.' ?></div>
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
                            <label for="presupuesto-lista" class="form-label">Lista de precios</label>
                            <select class="form-select <?= isset($errors['lista_codigo']) ? 'is-invalid' : '' ?>" id="presupuesto-lista" name="lista_codigo" data-lista-select data-catalog-select="lista_precio">
                                <?= $renderOptions($catalogs['listas'] ?? [], (string) ($presupuesto['lista_codigo'] ?? ''), '-- Lista --') ?>
                            </select>
                        </div>

                        <div class="crm-budget-col-3">
                            <label for="presupuesto-vendedor" class="form-label">Vendedor</label>
                            <select class="form-select" id="presupuesto-vendedor" name="vendedor_codigo" data-catalog-select="vendedor">
                                <?= $renderOptions($catalogs['vendedores'] ?? [], (string) ($presupuesto['vendedor_codigo'] ?? ''), '-- Vendedor --') ?>
                            </select>
                        </div>

                        <div class="crm-budget-col-3">
                            <label for="presupuesto-transporte" class="form-label">Transporte</label>
                            <select class="form-select" id="presupuesto-transporte" name="transporte_codigo" data-catalog-select="transporte">
                                <?= $renderOptions($catalogs['transportes'] ?? [], (string) ($presupuesto['transporte_codigo'] ?? ''), '-- Transporte --') ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card rxn-form-card mb-3">
                <div class="card-body">
                    <div class="crm-budget-section-title">Cuerpo del presupuesto</div>

                    <div class="crm-budget-items-toolbar mb-3">
                        <div class="crm-budget-picker-col">
                            <label class="form-label">Buscar articulo para agregar renglon</label>
                            <div class="crm-picker-wrap" data-article-picker data-picker-url="/rxnTiendasIA/public/mi-empresa/crm/presupuestos/articulos/sugerencias" data-context-url="/rxnTiendasIA/public/mi-empresa/crm/presupuestos/articulos/contexto">
                                <input type="hidden" value="" class="crm-picker-hidden" data-picker-hidden>
                                <input type="text" class="form-control" value="" placeholder="Buscar por codigo o descripcion en Articulos CRM..." autocomplete="off" data-picker-input>
                                <div class="rxn-search-suggestions crm-picker-results d-none" data-picker-results></div>
                            </div>
                            <div class="form-text crm-picker-meta" data-picker-meta>Al seleccionar un articulo se agrega un renglon editable al cuerpo.</div>
                        </div>
                        <div class="small text-muted">La lista activa se usa para intentar resolver el precio. Si no existe precio catalogado, el renglon queda editable y manual.</div>
                    </div>

                    <?php if (isset($errors['items'])): ?>
                        <div class="alert alert-danger py-2 mb-3"><?= htmlspecialchars((string) $errors['items']) ?></div>
                    <?php endif; ?>

                    <div class="crm-budget-items-card">
                        <div class="table-responsive rxn-table-responsive">
                            <table class="table table-hover align-middle table-sm mb-0 crm-budget-line-table">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 165px;">Codigo</th>
                                        <th style="min-width: 280px;">Descripcion</th>
                                        <th style="width: 110px;">Cantidad</th>
                                        <th style="width: 145px;">Precio</th>
                                        <th style="width: 120px;">Bonif %</th>
                                        <th style="width: 135px;">Importe</th>
                                        <th style="width: 70px;"></th>
                                    </tr>
                                </thead>
                                <tbody data-items-body>
                                    <?php if (($presupuesto['items'] ?? []) === []): ?>
                                        <tr data-empty-row>
                                            <td colspan="7" class="crm-budget-empty-lines">Todavia no hay renglones. Busca un articulo para empezar a armar el presupuesto.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach (($presupuesto['items'] ?? []) as $index => $item): ?>
                                            <tr data-item-row>
                                                <td>
                                                    <input type="hidden" name="items[<?= $index ?>][articulo_id]" value="<?= htmlspecialchars((string) ($item['articulo_id'] ?? '')) ?>" data-item-field="articulo_id">
                                                    <input type="hidden" name="items[<?= $index ?>][precio_origen]" value="<?= htmlspecialchars((string) ($item['precio_origen'] ?? 'manual')) ?>" data-item-field="precio_origen">
                                                    <input type="hidden" name="items[<?= $index ?>][lista_codigo_aplicada]" value="<?= htmlspecialchars((string) ($item['lista_codigo_aplicada'] ?? '')) ?>" data-item-field="lista_codigo_aplicada">
                                                    <input type="text" class="form-control form-control-sm" name="items[<?= $index ?>][articulo_codigo]" value="<?= htmlspecialchars((string) ($item['articulo_codigo'] ?? '')) ?>" data-item-field="articulo_codigo">
                                                </td>
                                                <td class="crm-budget-line-desc">
                                                    <textarea class="form-control form-control-sm" rows="2" name="items[<?= $index ?>][articulo_descripcion]" data-item-field="articulo_descripcion"><?= htmlspecialchars((string) ($item['articulo_descripcion'] ?? '')) ?></textarea>
                                                    <div class="form-text mt-1">Origen: <span data-item-origin-label><?= htmlspecialchars((string) strtoupper((string) ($item['precio_origen'] ?? 'manual'))) ?></span></div>
                                                </td>
                                                <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm" name="items[<?= $index ?>][cantidad]" value="<?= htmlspecialchars((string) ($item['cantidad'] ?? 1)) ?>" data-item-field="cantidad"></td>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-confirm-modal.js"></script>
    <script src="/rxnTiendasIA/public/js/crm-presupuestos-form.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-shortcuts.js"></script>
</body>
</html>

