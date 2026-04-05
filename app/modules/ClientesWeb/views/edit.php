<?php
$codigoTangoActual = htmlspecialchars((string) ($cliente['codigo_tango'] ?? ''));
$idGva14Actual = htmlspecialchars((string) ($cliente['id_gva14_tango'] ?? ''));
$razonTangoActual = htmlspecialchars((string) ($cliente['razon_social'] ?? ''));
$gva01Actual = htmlspecialchars((string) ($cliente['id_gva01_condicion_venta'] ?? ''));
$gva10Actual = htmlspecialchars((string) ($cliente['id_gva10_lista_precios'] ?? ''));
$gva23Actual = htmlspecialchars((string) ($cliente['id_gva23_vendedor'] ?? ''));
$gva24Actual = htmlspecialchars((string) ($cliente['id_gva24_transporte'] ?? ''));
$gva01InternoActual = htmlspecialchars((string) ($cliente['id_gva01_tango'] ?? ''));
$gva10InternoActual = htmlspecialchars((string) ($cliente['id_gva10_tango'] ?? ''));
$gva23InternoActual = htmlspecialchars((string) ($cliente['id_gva23_tango'] ?? ''));
$gva24InternoActual = htmlspecialchars((string) ($cliente['id_gva24_tango'] ?? ''));
$clienteTieneRelacionTango = !empty($cliente['id_gva14_tango']);
$clienteTieneRelacionesLocales = $clienteTieneRelacionTango
    || $gva01Actual !== ''
    || $gva10Actual !== ''
    || $gva23Actual !== ''
    || $gva24Actual !== '';

$basePath = $basePath ?? '/mi-empresa/clientes';
$dashboardPath = $dashboardPath ?? '/mi-empresa/dashboard';
$moduleNotesKey = $moduleNotesKey ?? 'clientes_web';
$moduleNotesLabel = $moduleNotesLabel ?? 'Clientes Web';
$pageTitle = $pageTitle ?? 'Editar Cliente Web';
$editTitle = $editTitle ?? 'Editar Cliente Web';
$backLabel = $backLabel ?? 'Volver al Listado';
$isCrm = $isCrm ?? false;
?>
<?php
$pageTitle = 'RXN Suite';
ob_start();
?>
<div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell">
        <div class="rxn-module-header mb-4">
            <div>
                <h2><?= htmlspecialchars((string) $editTitle) ?> #<?= $cliente['id'] ?></h2>
                <p class="text-muted">Resolución comercial y actualización de datos.</p>
            </div>
            <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-outline-secondary">← <?= htmlspecialchars((string) $backLabel) ?></a>
        </div>

        <?php
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <?php $flash_success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']); ?>
        <?php $flash_error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']); ?>
        
        <?php if ($flash_success): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <strong>✔</strong> <?= htmlspecialchars((string)$flash_success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($flash_error): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <strong>⚠️</strong> <?= htmlspecialchars((string)$flash_error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-12 col-xl-8">
                <!-- Formulario Principal -->
                <div class="card rxn-crud-card mb-4">
                    <div class="card-header  py-3">
                        <h5 class="mb-0 text-primary">Datos del Cliente Web</h5>
                    </div>
                    <div class="card-body p-4 p-lg-5">
                        <form action="<?= htmlspecialchars($basePath) ?>/<?= $cliente['id'] ?>/editar" method="POST" id="formCliente">
                            <div class="rxn-form-section">
                                <div class="rxn-form-section-title">Datos del cliente</div>
                                <div class="rxn-form-grid">
                                <div class="rxn-form-span-6">
                                    <label class="form-label fw-bold">Nombre</label>
                                    <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars((string)$cliente['nombre']) ?>" required>
                                </div>
                                <div class="rxn-form-span-6">
                                    <label class="form-label fw-bold">Apellido</label>
                                    <input type="text" name="apellido" class="form-control" value="<?= htmlspecialchars((string)$cliente['apellido']) ?>">
                                </div>
                                <div class="rxn-form-span-6">
                                    <label class="form-label fw-bold">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars((string)$cliente['email']) ?>" required>
                                </div>
                                <div class="rxn-form-span-6">
                                    <label class="form-label fw-bold">Teléfono</label>
                                    <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars((string)$cliente['telefono']) ?>">
                                </div>
                                <div class="rxn-form-span-6">
                                    <label class="form-label fw-bold">Documento (CUIT/DNI)</label>
                                    <input type="text" name="documento" class="form-control" value="<?= htmlspecialchars((string)$cliente['documento']) ?>">
                                </div>
                                <div class="rxn-form-span-6">
                                    <label class="form-label fw-bold">Razón Social</label>
                                    <input type="text" name="razon_social" class="form-control" value="<?= htmlspecialchars((string)$cliente['razon_social']) ?>">
                                </div>
                                <div class="rxn-form-span-12">
                                    <label class="form-label fw-bold">Dirección</label>
                                    <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars((string)$cliente['direccion']) ?>">
                                </div>
                                <div class="rxn-form-span-4">
                                    <label class="form-label fw-bold">Localidad</label>
                                    <input type="text" name="localidad" class="form-control" value="<?= htmlspecialchars((string)$cliente['localidad']) ?>">
                                </div>
                                <div class="rxn-form-span-4">
                                    <label class="form-label fw-bold">Provincia</label>
                                    <input type="text" name="provincia" class="form-control" value="<?= htmlspecialchars((string)$cliente['provincia']) ?>">
                                </div>
                                <div class="rxn-form-span-4">
                                    <label class="form-label fw-bold">Cógido Postal</label>
                                    <input type="text" name="codigo_postal" class="form-control" value="<?= htmlspecialchars((string)$cliente['codigo_postal']) ?>">
                                </div>
                                </div>
                            </div>

                            <div class="rxn-form-section">
                                <div class="rxn-form-switches">
                                    <div class="rxn-form-switch-card">
                                        <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" name="activo" id="activo" <?= $cliente['activo'] ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="activo">Cliente Activo</label>
                                    <div class="form-text mb-0">Mantiene habilitado al cliente para operar y relacionarse con pedidos.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Oculto para que el submit al mismo form mande lo de tango también o podamos aislarlo.
                                 Mejor dejamos todo en el form -->
                            
                            <div class="rxn-form-section border-top pt-4 mt-4">
                            <div class="rxn-form-section-title text-info">Vínculo Comercial Tango</div>
                            <p class="text-muted small">Buscá clientes reales en Tango, filtrá mientras escribís y guardá el vínculo comercial usando su ID interno.</p>
                              <div class="rxn-form-grid">
                                <div class="rxn-form-span-8">
                                    <label class="form-label fw-bold text-info">Código de Cliente Tango</label>
                                    <input type="hidden" name="tango_selected_id_gva14" id="tango_selected_id_gva14" value="<?= $idGva14Actual ?>">
                                    <input type="hidden" name="tango_remote_sync_requested" id="tango_remote_sync_requested" value="0">
                                    <div class="input-group position-relative">
                                        <input type="text" name="codigo_tango" id="codigo_tango" class="form-control border-info" placeholder="Escribí código o razón social..." value="<?= $codigoTangoActual ?>" autocomplete="off" data-original-codigo="<?= $codigoTangoActual ?>" data-original-id="<?= $idGva14Actual ?>">
                                        <button type="button" id="btn-obtener-clientes-tango" class="btn btn-outline-info" title="Buscar candidatos desde Tango">🔎 Obtener clientes de Tango</button>
                                    </div>
                                    <div id="tango-search-status" class="form-text text-muted mt-1">La pantalla muestra primero lo guardado localmente. Connect solo se consulta cuando apretás este botón.</div>
                                    <div id="tango-search-results" class="list-group position-relative mt-2 shadow-sm" style="display:none; z-index: 20;"></div>
                                    <div id="tango-selected-pill" class="mt-2 <?= ($cliente['id_gva14_tango'] ? '' : 'd-none') ?>">
                                        <span class="badge text-bg-info-subtle border border-info text-info-emphasis px-3 py-2">
                                            Cliente Tango vinculado: <span id="tango-selected-label"><?= trim($codigoTangoActual . ' - ' . $razonTangoActual, ' -') ?></span>
                                        </span>
                                    </div>
                                    <small class="form-text text-muted">Si cambiás manualmente el código sin elegir un cliente del buscador, el vínculo Tango anterior se limpia al guardar.</small>
                                </div>
                            </div>
                            </div>

                            <div id="tango-relations-wrapper" class="border rounded-3 p-3 mt-3 <?= $clienteTieneRelacionesLocales ? '' : 'd-none' ?>" data-loaded="false">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h6 class="mb-1 text-info fw-bold">Overrides comerciales para Pedidos</h6>
                                        <p class="small text-muted mb-0">Se precargan desde Tango si el cliente ya los trae, pero podés corregirlos desde la web antes de guardar.</p>
                                    </div>
                                    <span class="badge rounded-pill text-bg-warning">Manual + Tango</span>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Condición de venta</label>
                                        <input type="hidden" name="id_gva01_tango" id="id_gva01_tango" value="<?= $gva01InternoActual ?>">
                                        <select class="form-select tango-relation-select" id="id_gva01_condicion_venta" name="id_gva01_condicion_venta" data-type="gva01" data-original="<?= $gva01Actual ?>">
                                            <?php if ($gva01Actual !== ''): ?>
                                                <option value="<?= $gva01Actual ?>" selected>Guardado localmente (<?= $gva01Actual ?>)</option>
                                            <?php else: ?>
                                                <option value="">-- Sin definir --</option>
                                            <?php endif; ?>
                                        </select>
                                        <div class="mt-2"><span class="badge text-bg-secondary d-none" id="badge-source-gva01">Sin resolver</span></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Lista de precios</label>
                                        <input type="hidden" name="id_gva10_tango" id="id_gva10_tango" value="<?= $gva10InternoActual ?>">
                                        <select class="form-select tango-relation-select" id="id_gva10_lista_precios" name="id_gva10_lista_precios" data-type="gva10" data-original="<?= $gva10Actual ?>">
                                            <?php if ($gva10Actual !== ''): ?>
                                                <option value="<?= $gva10Actual ?>" selected>Guardado localmente (<?= $gva10Actual ?>)</option>
                                            <?php else: ?>
                                                <option value="">-- Sin definir --</option>
                                            <?php endif; ?>
                                        </select>
                                        <div class="mt-2"><span class="badge text-bg-secondary d-none" id="badge-source-gva10">Sin resolver</span></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Vendedor</label>
                                        <input type="hidden" name="id_gva23_tango" id="id_gva23_tango" value="<?= $gva23InternoActual ?>">
                                        <select class="form-select tango-relation-select" id="id_gva23_vendedor" name="id_gva23_vendedor" data-type="gva23" data-original="<?= $gva23Actual ?>">
                                            <?php if ($gva23Actual !== ''): ?>
                                                <option value="<?= $gva23Actual ?>" selected>Guardado localmente (<?= $gva23Actual ?>)</option>
                                            <?php else: ?>
                                                <option value="">-- Sin definir --</option>
                                            <?php endif; ?>
                                        </select>
                                        <div class="mt-2"><span class="badge text-bg-secondary d-none" id="badge-source-gva23">Sin resolver</span></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Transporte</label>
                                        <input type="hidden" name="id_gva24_tango" id="id_gva24_tango" value="<?= $gva24InternoActual ?>">
                                        <select class="form-select tango-relation-select" id="id_gva24_transporte" name="id_gva24_transporte" data-type="gva24" data-original="<?= $gva24Actual ?>">
                                            <?php if ($gva24Actual !== ''): ?>
                                                <option value="<?= $gva24Actual ?>" selected>Guardado localmente (<?= $gva24Actual ?>)</option>
                                            <?php else: ?>
                                                <option value="">-- Sin definir --</option>
                                            <?php endif; ?>
                                        </select>
                                        <div class="mt-2"><span class="badge text-bg-secondary d-none" id="badge-source-gva24">Sin resolver</span></div>
                                    </div>
                                </div>
                            </div>

                            <div class="rxn-form-actions">
                                <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-light">Cancelar</a>
                                <button type="submit" class="btn btn-primary px-4 shadow-sm">💾 Guardar Cambios Locales</button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <!-- Panel Lateral de Resumen Técnico -->
                <div class="card shadow-sm border-0 bg-light rxn-form-sticky-side">
                    <div class="card-body">
                        <h6 class="text-uppercase text-muted fw-bold mb-3">🔍 Estado de Integración</h6>
                        
                        <?php if($cliente['id_gva14_tango']): ?>
                            <div class="alert alert-success py-2 mb-3">
                                <div class="fw-bold"><small>✔ VINCULADO CORRECTAMENTE</small></div>
                            </div>
                            
                            <ul class="list-group list-group-flush fs-6 mb-3">
                                <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0">
                                    <span>ID GVA14 (Interno)</span>
                                    <span class="badge bg-dark fw-normal"><?= $cliente['id_gva14_tango'] ?></span>
                                </li>
                                <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0">
                                    <span>Condición Venta</span>
                                    <div class="text-end">
                                        <span class="badge bg-secondary fw-normal" id="summary-gva01-code"><?= $cliente['id_gva01_condicion_venta'] ?: 'N/d' ?></span>
                                        <div class="small text-muted mt-1" id="summary-gva01-label">Sin descripción</div>
                                        <div class="small mt-1" id="summary-gva01-source"></div>
                                    </div>
                                </li>
                                <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0">
                                    <span>Lista Precios</span>
                                    <div class="text-end">
                                        <span class="badge bg-secondary fw-normal" id="summary-gva10-code"><?= $cliente['id_gva10_lista_precios'] ?: 'N/d' ?></span>
                                        <div class="small text-muted mt-1" id="summary-gva10-label">Sin descripción</div>
                                        <div class="small mt-1" id="summary-gva10-source"></div>
                                    </div>
                                </li>
                                <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0">
                                    <span>Vendedor</span>
                                    <div class="text-end">
                                        <span class="badge bg-secondary fw-normal" id="summary-gva23-code"><?= $cliente['id_gva23_vendedor'] ?: 'N/d' ?></span>
                                        <div class="small text-muted mt-1" id="summary-gva23-label">Sin descripción</div>
                                        <div class="small mt-1" id="summary-gva23-source"></div>
                                    </div>
                                </li>
                                <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0">
                                    <span>Transporte</span>
                                    <div class="text-end">
                                        <span class="badge bg-secondary fw-normal" id="summary-gva24-code"><?= $cliente['id_gva24_transporte'] ?: 'N/d' ?></span>
                                        <div class="small text-muted mt-1" id="summary-gva24-label">Sin descripción</div>
                                        <div class="small mt-1" id="summary-gva24-source"></div>
                                    </div>
                                </li>
                            </ul>

                            <!-- Botón de Envío de Órdenes Pendientes -->
                            <?php if (!$isCrm): ?>
                            <form action="<?= htmlspecialchars($basePath) ?>/<?= $cliente['id'] ?>/enviar-pendientes" method="POST" class="d-grid gap-2 mt-3">
                                <button type="submit" class="btn btn-outline-success btn-sm font-weight-bold" data-rxn-confirm="¿Revisar y enviar todas las ventas web no sincronizadas pertenecientes a este cliente hacia el módulo de Tango Rest?" data-confirm-type="warning">
                                    🚚 Enviar Pendientes a Tango
                                </button>
                            </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning py-2 mb-3">
                                <div class="fw-bold text-dark"><small>⚠️ NO VINCULADO</small></div>
                                <div style="font-size: 0.8rem;" class="mt-1">Los pedidos de este cliente no se podrán enviar al ERP hasta vincular un cliente Tango válido.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
<?php
$content = ob_get_clean();
ob_start();
?>
<script>
        document.addEventListener('DOMContentLoaded', () => {
            const input = document.getElementById('codigo_tango');
            const hiddenId = document.getElementById('tango_selected_id_gva14');
            const button = document.getElementById('btn-obtener-clientes-tango');
            const results = document.getElementById('tango-search-results');
            const status = document.getElementById('tango-search-status');
            const selectedPill = document.getElementById('tango-selected-pill');
            const selectedLabel = document.getElementById('tango-selected-label');
            const relationWrapper = document.getElementById('tango-relations-wrapper');
            const remoteSyncRequested = document.getElementById('tango_remote_sync_requested');
            const relationSelects = {
                gva01: document.getElementById('id_gva01_condicion_venta'),
                gva10: document.getElementById('id_gva10_lista_precios'),
                gva23: document.getElementById('id_gva23_vendedor'),
                gva24: document.getElementById('id_gva24_transporte')
            };
            const relationHiddenIds = {
                gva01: document.getElementById('id_gva01_tango'),
                gva10: document.getElementById('id_gva10_tango'),
                gva23: document.getElementById('id_gva23_tango'),
                gva24: document.getElementById('id_gva24_tango')
            };
            const relationCatalogKeys = {
                gva01: 'condiciones_venta',
                gva10: 'listas_precios',
                gva23: 'vendedores',
                gva24: 'transportes'
            };
            const relationSummaries = {
                gva01: {
                    code: document.getElementById('summary-gva01-code'),
                    label: document.getElementById('summary-gva01-label'),
                    source: document.getElementById('summary-gva01-source'),
                    badge: document.getElementById('badge-source-gva01')
                },
                gva10: {
                    code: document.getElementById('summary-gva10-code'),
                    label: document.getElementById('summary-gva10-label'),
                    source: document.getElementById('summary-gva10-source'),
                    badge: document.getElementById('badge-source-gva10')
                },
                gva23: {
                    code: document.getElementById('summary-gva23-code'),
                    label: document.getElementById('summary-gva23-label'),
                    source: document.getElementById('summary-gva23-source'),
                    badge: document.getElementById('badge-source-gva23')
                },
                gva24: {
                    code: document.getElementById('summary-gva24-code'),
                    label: document.getElementById('summary-gva24-label'),
                    source: document.getElementById('summary-gva24-source'),
                    badge: document.getElementById('badge-source-gva24')
                }
            };
            let relationMetadata = null;
            let currentTangoDefaults = null;

            if (!input || !hiddenId || !button || !results || !status || !selectedPill || !selectedLabel || !relationWrapper || !remoteSyncRequested) {
                return;
            }

            let debounceTimer = null;

            const hideResults = () => {
                results.style.display = 'none';
                results.innerHTML = '';
            };

            const resetSelectionIfNeeded = () => {
                const originalCodigo = input.dataset.originalCodigo || '';
                const originalId = input.dataset.originalId || '';

                if (input.value.trim() !== originalCodigo || hiddenId.value !== originalId) {
                    hiddenId.value = '';
                    remoteSyncRequested.value = '0';
                    selectedLabel.textContent = '';
                    selectedPill.classList.add('d-none');
                }
            };

            const fillRelationSelect = (type, options, selectedCode = '') => {
                const select = relationSelects[type];
                const hidden = relationHiddenIds[type];
                if (!select || !hidden) {
                    return;
                }

                const normalizedSelected = selectedCode !== null && selectedCode !== undefined ? String(selectedCode).trim() : '';
                select.innerHTML = '<option value="">-- Sin definir --</option>';

                (options || []).forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.codigo;
                    option.textContent = item.label;
                    option.dataset.internalId = item.id_interno;
                    if (normalizedSelected !== '' && normalizedSelected === String(item.codigo)) {
                        option.selected = true;
                        hidden.value = item.id_interno;
                    }
                    select.appendChild(option);
                });

                if (!select.value) {
                    hidden.value = '';
                }
            };

            const getCatalogOption = (type, code) => {
                if (!relationMetadata) {
                    return null;
                }

                const items = relationMetadata[relationCatalogKeys[type]] || [];
                return items.find((item) => String(item.codigo) === String(code)) || null;
            };

            const paintRelationSource = (type, currentCode, tangoDefaultCode) => {
                const summary = relationSummaries[type];
                if (!summary || !summary.badge || !summary.source) {
                    return;
                }

                summary.badge.classList.remove('d-none', 'text-bg-primary', 'text-bg-warning', 'text-bg-secondary');

                if (!currentCode) {
                    summary.badge.classList.add('text-bg-secondary');
                    summary.badge.textContent = 'Sin definir';
                    summary.source.textContent = 'Todavia no tiene valor asignado.';
                    return;
                }

                if (!relationMetadata) {
                    summary.badge.classList.add('text-bg-secondary');
                    summary.badge.textContent = 'Guardado local';
                    summary.source.textContent = 'Persistido localmente. Consultá Connect solo si querés refrescar o comparar.';
                    return;
                }

                if (tangoDefaultCode && String(currentCode) === String(tangoDefaultCode)) {
                    summary.badge.classList.add('text-bg-primary');
                    summary.badge.textContent = 'Heredado de Tango';
                    summary.source.textContent = 'Coincide con la configuración actual del cliente en Tango.';
                    return;
                }

                summary.badge.classList.add('text-bg-warning');
                summary.badge.textContent = 'Override web';
                summary.source.textContent = tangoDefaultCode
                    ? `Base Tango: ${tangoDefaultCode}`
                    : 'Tango no define este valor o fue corregido desde web.';
            };

            const refreshRelationSummary = (type) => {
                const select = relationSelects[type];
                const summary = relationSummaries[type];
                if (!select || !summary) {
                    return;
                }

                const value = select.value || '';
                const option = value !== '' ? getCatalogOption(type, value) : null;
                if (summary.code) {
                    summary.code.textContent = value !== '' ? value : 'N/d';
                }
                if (summary.label) {
                    summary.label.textContent = option ? option.label : (value !== '' ? 'Descripción pendiente' : 'Sin descripción');
                }

                const tangoDefaultCode = currentTangoDefaults ? (currentTangoDefaults[type] || '') : '';
                paintRelationSource(type, value, tangoDefaultCode);
            };

            const refreshAllRelationSummaries = () => {
                Object.keys(relationSelects).forEach((type) => refreshRelationSummary(type));
            };

            const renderRelationSelectors = (defaults = {}, useDefaultsForSelection = false) => {
                if (!relationMetadata) {
                    return;
                }

                relationWrapper.classList.remove('d-none');
                currentTangoDefaults = defaults || currentTangoDefaults || {};
                Object.entries(relationCatalogKeys).forEach(([type, key]) => {
                    const select = relationSelects[type];
                    const original = select ? (select.dataset.original || '') : '';
                    const selectedCode = useDefaultsForSelection && Object.prototype.hasOwnProperty.call(defaults, type)
                        ? (defaults[type] || '')
                        : (select && select.value !== '' ? select.value : original);
                    fillRelationSelect(type, relationMetadata[key] || [], selectedCode);
                });
                refreshAllRelationSummaries();
            };

            const loadRelationMetadata = async (clienteIdGva14 = '', useDefaultsForSelection = false) => {
                relationWrapper.classList.remove('d-none');

                if (relationMetadata && !clienteIdGva14) {
                    renderRelationSelectors(currentTangoDefaults || {}, useDefaultsForSelection);
                    return true;
                }

                status.textContent = 'Cargando catálogos comerciales desde Tango...';

                try {
                    const query = clienteIdGva14 ? `?cliente_id_gva14=${encodeURIComponent(clienteIdGva14)}` : '';
                    const response = await fetch(`<?= $basePath ?>/metadata-tango${query}`);
                    const json = await response.json();

                    if (!json.success) {
                        status.textContent = json.message || 'No pude cargar los catálogos comerciales de Tango.';
                        return false;
                    }

                    relationMetadata = json.data || {};
                    currentTangoDefaults = relationMetadata.defaults || currentTangoDefaults || {};
                    relationWrapper.dataset.loaded = 'true';
                    renderRelationSelectors(currentTangoDefaults || {}, useDefaultsForSelection);
                    status.textContent = 'Catálogos comerciales listos. Podés aceptar los valores de Tango o corregirlos acá.';
                    return true;
                } catch (error) {
                    status.textContent = 'Falló la carga de catálogos comerciales.';
                    return false;
                }
            };

            const applyResolvedDefaults = async (defaults = {}) => {
                currentTangoDefaults = defaults || {};
                const loaded = await loadRelationMetadata('', true);
                if (!loaded) {
                    return;
                }

                renderRelationSelectors(currentTangoDefaults, true);
            };

            const renderResults = (items) => {
                if (!items.length) {
                    results.innerHTML = '<div class="list-group-item small text-muted">No encontré clientes con ese filtro.</div>';
                    results.style.display = 'block';
                    return;
                }

                results.innerHTML = '';
                items.forEach((item) => {
                    const row = document.createElement('button');
                    row.type = 'button';
                    row.className = 'list-group-item list-group-item-action';
                    const title = document.createElement('div');
                    title.className = 'fw-semibold';
                    title.textContent = item.codigo;

                    const subtitle = document.createElement('div');
                    subtitle.className = 'small text-muted';
                    subtitle.textContent = item.razon_social || 'Sin razón social';

                    row.appendChild(title);
                    row.appendChild(subtitle);
                    row.addEventListener('click', () => {
                        input.value = item.codigo;
                        hiddenId.value = item.id_gva14;
                        remoteSyncRequested.value = '1';
                        selectedLabel.textContent = item.label;
                        selectedPill.classList.remove('d-none');
                        applyResolvedDefaults(item.defaults || {});
                        status.textContent = 'Cliente Tango seleccionado. Revisá los selectores comerciales y guardá para persistir el vínculo.';
                        hideResults();
                    });
                    results.appendChild(row);
                });
                results.style.display = 'block';
            };

            const searchTango = async () => {
                const query = input.value.trim();
                if (query.length < 2) {
                    hideResults();
                    status.textContent = 'Escribí al menos 2 caracteres para buscar. Al guardar, se persiste el ID_GVA14 del cliente seleccionado.';
                    return;
                }

                status.textContent = 'Buscando clientes en Tango...';

                try {
                    const response = await fetch(`<?= $basePath ?>/buscar-tango?q=${encodeURIComponent(query)}`);
                    const json = await response.json();

                    if (!json.success) {
                        status.textContent = json.message || 'No pude consultar Tango en este momento.';
                        hideResults();
                        return;
                    }

                    renderResults(Array.isArray(json.data) ? json.data : []);
                    status.textContent = 'Seleccioná un cliente de la lista para vincularlo.';
                } catch (error) {
                    hideResults();
                    status.textContent = 'Falló la consulta remota de clientes Tango.';
                }
            };

            input.addEventListener('input', () => {
                resetSelectionIfNeeded();
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(searchTango, 300);
            });

            Object.entries(relationSelects).forEach(([type, select]) => {
                const hidden = relationHiddenIds[type];
                if (!select || !hidden) {
                    return;
                }

                select.addEventListener('change', () => {
                    const selectedOption = select.options[select.selectedIndex];
                    hidden.value = selectedOption && selectedOption.value !== '' ? (selectedOption.dataset.internalId || '') : '';
                    refreshRelationSummary(type);
                });
            });

            button.addEventListener('click', async () => {
                const clienteIdForDefaults = hiddenId.value || input.dataset.originalId || '';
                const loaded = await loadRelationMetadata(clienteIdForDefaults);
                if (loaded && input.value.trim().length >= 2) {
                    searchTango();
                }
            });

            refreshAllRelationSummaries();

            document.addEventListener('click', (event) => {
                if (!results.contains(event.target) && event.target !== input && event.target !== button) {
                    hideResults();
                }
            });
        });
    </script>
    <script src="/js/rxn-confirm-modal.js"></script>
    <script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
