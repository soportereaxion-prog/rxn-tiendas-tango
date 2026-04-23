<?php
$pageTitle = 'RXN LIVE - ' . htmlspecialchars($datasetInfo['name']);
$pivotMetadata = $datasetInfo['pivot_metadata'] ?? [];
$groupableFields = array_filter($pivotMetadata, fn($m) => !empty($m['groupable']));
$aggregatableFields = array_filter($pivotMetadata, fn($m) => !empty($m['aggregatable']));
ob_start();
?>
<style>
    /* min-width:0 en la cadena Flex para que el contenido intrínseco no infle
       a los ancestros — comportamiento nativo de .table-responsive (overflow-x
       auto de Bootstrap) toma la responsabilidad del scroll horizontal. */
    .rxn-live-shell,
    .rxn-live-shell [class^="col-"],
    .rxn-live-shell [class*=" col-"],
    .rxn-live-shell .card,
    .rxn-live-shell .card-body,
    .rxn-live-shell .tab-content,
    .rxn-live-shell .tab-pane,
    .rxn-live-shell .table-responsive {
        min-width: 0;
    }
    /* Layout flex del card de la tabla: garantiza que el tab-pane ocupe
       EXACTAMENTE lo que sobra entre card-header (tabs) y card-footer
       (paginación), sin hueco cuando el chart-card del col de al lado
       fuerza al col-lg-8 a ser más alto que el contenido natural de la
       tabla. El max-height del card (no del pane) se setea en JS según
       el viewport real.
       Ver installRxnLivePaneSizer al final del archivo. */
    .rxn-live-shell #tableSectionCol > .card {
        display: flex;
        flex-direction: column;
    }
    .rxn-live-shell #tableSectionCol > .card > .card-body.tab-content {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    /* El tab-pane activo se estira al alto disponible del tab-content y
       es el que scrollea internamente. Los inactivos (display:none) no
       afectan layout. */
    .rxn-live-shell #tableSectionCol > .card > .card-body.tab-content > .tab-pane.active {
        flex: 1 1 auto;
        min-height: 0;
    }
    .rxn-live-shell .rxn-live-pane {
        overflow-y: auto;
    }
</style>
<div class="rxn-live-shell">
<?php if (!empty($safeMode)): ?>
<div class="alert alert-warning d-flex align-items-center justify-content-between mb-3 py-2" role="alert">
    <div>
        <i class="bi bi-shield-exclamation me-2"></i>
        <strong>Safe Mode activo</strong> &mdash; ignorando vistas guardadas y filtros. Usar para destrabar un dataset con config corrupto.
    </div>
    <a href="/rxn_live/dataset?dataset=<?= urlencode($datasetKey) ?>" class="btn btn-sm btn-outline-dark">
        <i class="bi bi-arrow-clockwise"></i> Salir de Safe Mode
    </a>
</div>
<?php endif; ?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h1 class="h3 mb-0 text-white mt-1"><?= htmlspecialchars($datasetInfo['name']) ?></h1>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap flex-md-nowrap">
        <!-- SELECT DE VISTAS (PRESETS) -->
        <?php if (!empty($myViews)): ?>
            <select id="savedViewsDropdown" class="form-select form-select-sm bg-dark text-white border-secondary" style="max-width: 240px;" onchange="loadSelectedView()">
                <option value="">[ Vista Base ]</option>
                <?php
                $activeViewId = $_GET['view_id'] ?? '';
                $currentUserId = (int)($currentUserId ?? 0);
                foreach($myViews as $v):
                    $isSelected = ((string)$v['id'] === (string)$activeViewId) ? 'selected' : '';
                    $viewOwnerId = isset($v['usuario_id']) ? (int)$v['usuario_id'] : 0;
                    $ownerName = $v['usuario_nombre'] ?? '';
                    $isSystem = str_starts_with((string)$v['id'], 'default_');
                    $isMine = !$isSystem && $viewOwnerId === $currentUserId && $currentUserId > 0;
                    // Las vistas del sistema y las propias se muestran con el nombre a secas.
                    // Las ajenas se prefijan con el nombre del dueño entre paréntesis para que quede claro que no son propias.
                    $displayLabel = $v['nombre'] ?? 'Vista Sin Nombre';
                    if (!$isSystem && !$isMine && $ownerName !== '') {
                        $displayLabel .= ' — ' . $ownerName;
                    }
                ?>
                    <option value="<?= htmlspecialchars((string)$v['id']) ?>"
                            data-config="<?= htmlspecialchars(json_encode($v['config']), ENT_QUOTES) ?>"
                            data-nombre="<?= htmlspecialchars($v['nombre'] ?? 'Vista Sin Nombre', ENT_QUOTES) ?>"
                            data-owner-id="<?= htmlspecialchars((string)$viewOwnerId) ?>"
                            data-is-mine="<?= $isMine ? '1' : '0' ?>"
                            <?= $isSelected ?>>
                        <?= htmlspecialchars($displayLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <!-- BTN GUARDAR VISTA -->
        <div class="btn-group">
            <button type="button" id="btnSaveView" class="btn btn-primary btn-sm" onclick="saveCurrentView()" title="Sobrescribir vista actual">
                <i class="bi bi-floppy"></i> Guardar
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="promptSaveView()" title="Guardar como nueva vista">
                <i class="bi bi-plus-lg"></i> Nueva Vista
            </button>
            <button type="button" id="btnDeleteView" class="btn btn-outline-danger btn-sm" onclick="promptDeleteView()" title="Eliminar vista seleccionada" style="display: none;">
                <i class="bi bi-trash"></i> Eliminar
            </button>
        </div>
        <script>window.rxnCurrentUserId = <?= (int)($currentUserId ?? 0) ?>;</script>

        <!-- EXPORT BAR -->
        <form method="POST" action="/rxn_live/exportar" id="exportDatasetForm" class="d-inline">
            <input type="hidden" name="dataset" value="<?= htmlspecialchars($datasetKey) ?>">
            <?php 
            foreach ($filters as $fk => $fv): 
                if (is_array($fv)) {
                    foreach ($fv as $sk => $sv) {
                        if (is_array($sv)) {
                            foreach ($sv as $ssk => $ssv) {
                                echo '<input type="hidden" name="'.htmlspecialchars((string)$fk).'['.htmlspecialchars((string)$sk).']['.htmlspecialchars((string)$ssk).']" value="'.htmlspecialchars((string)$ssv).'">';
                            }
                        } else {
                            echo '<input type="hidden" name="'.htmlspecialchars((string)$fk).'['.htmlspecialchars((string)$sk).']" value="'.htmlspecialchars((string)$sv).'">';
                        }
                    }
                } elseif ($fv !== '') { ?>
                <input type="hidden" name="<?= htmlspecialchars((string)$fk) ?>" value="<?= htmlspecialchars((string)$fv) ?>">
            <?php } endforeach; ?>
            <div class="btn-group">
                <button type="submit" name="format" value="csv" class="btn btn-success btn-sm bg-gradient" style="border-radius: 0.25rem 0 0 0.25rem;"><i class="bi bi-filetype-csv me-1"></i> CSV</button>
                <button type="submit" name="format" value="xlsx" class="btn btn-success btn-sm bg-gradient" style="border-radius: 0 0.25rem 0.25rem 0; border-left: 1px solid rgba(255,255,255,0.2);"><i class="bi bi-file-earmark-excel-fill me-1"></i> Excel</button>
            </div>
        </form>
        <a href="/rxn_live" class="btn btn-outline-secondary btn-sm" title="Volver a Datasets"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-dark border-secondary border-opacity-50 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-end gap-2">
                    <select class="form-select form-select-sm bg-dark text-white border-secondary" style="width: auto;" id="globalDateFormatSelect" onchange="changeGlobalDateFormat()" title="Formato de Fechas">
                        <option value="Y-m-d">YYYY-MM-DD (Base)</option>
                        <option value="d/m/Y">DD/MM/YYYY</option>
                        <option value="d-m-Y">DD-MM-YYYY</option>
                        <option value="d/m/y">DD/MM/YY</option>
                        <option value="d-m-y">DD-MM-YY</option>
                    </select>
                    <a href="javascript:void(0)" onclick="clearAllFilters()" class="btn btn-outline-secondary btn-sm px-3" title="Eliminar todos los filtros (Mantiene opciones de vista y gráfico)">Limpiar Filtros</a>
                    <button type="button" onclick="window.location.reload()" accesskey="a" class="btn btn-outline-info btn-sm px-3" title="Actualizar Datos Frescos [Alt+A]"><i class="bi bi-arrow-clockwise me-1"></i>Actualizar</button>
                    <button type="button" onclick="fullReset()" class="btn btn-danger btn-sm px-3 bg-opacity-25 border-danger" title="Borrar TODOS los filtros, re-establecer todas las columnas ocultas y recargar la vista al estado por defecto">Reinicio Total</button>
                    <div class="vr border-secondary mx-1"></div>
                    <button type="button" class="btn btn-outline-secondary btn-sm px-2" id="toggleChartBtn" title="Alternar Gráfico" onclick="toggleViewSection('chart')">
                        <i class="bi text-info bi-graph-up"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm px-2" id="toggleTableBtn" title="Alternar Tabla" onclick="toggleViewSection('table')">
                        <i class="bi text-info bi-table"></i>
                    </button>
                    <div class="vr border-secondary mx-1"></div>
                    <button type="button" class="btn btn-outline-secondary btn-sm px-2" id="toggleWrapBtn" title="Alternar ajuste de texto al ancho de columna (wrap vs truncar)" onclick="toggleWrapText()">
                        <i class="bi text-info bi-text-wrap"></i>
                        <span class="ms-1 small d-none d-lg-inline" id="toggleWrapLabel">Ajustar</span>
                    </button>
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-outline-secondary btn-sm px-2" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Columnas">
                            <i class="bi bi-layout-three-columns"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow p-2" style="min-width: 220px; font-size: 0.85rem;" id="colSelectorDropdown">
                            <!-- Llenado por JS -->
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4" id="viewLayoutRow">
    <!-- CHART SECTION -->
    <div class="col-lg-4" id="chartSectionCol">
        <div class="card bg-dark border-secondary border-opacity-50 h-100 shadow-sm">
            <div class="card-header border-secondary border-opacity-50 bg-transparent d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-white"><i class="bi bi-graph-up me-2 text-primary"></i>Gráfico Analítico</h6>
                <button class="btn btn-sm btn-link text-muted p-0" type="button" data-bs-toggle="collapse" data-bs-target="#chartSettingsCollapse" title="Configurar Gráfico">
                    <i class="bi bi-gear-fill"></i>
                </button>
            </div>
            <div class="collapse border-bottom border-secondary border-opacity-50" id="chartSettingsCollapse">
                <div class="card-body bg-dark py-2" style="font-size: 0.8rem;">
                    <div class="mb-2">
                        <label class="text-muted small mb-1">Agrupar por (X)</label>
                        <select class="form-select form-select-sm bg-dark text-white border-secondary" id="chartGroupCol" onchange="renderDynamicChart()"></select>
                    </div>
                    <div class="mb-2">
                        <label class="text-muted small mb-1">Valor (Y)</label>
                        <div class="d-flex gap-1">
                            <select class="form-select form-select-sm bg-dark text-white border-secondary w-50" id="chartValCol" onchange="renderDynamicChart()"></select>
                            <select class="form-select form-select-sm bg-dark text-info border-secondary w-50" id="chartOp" onchange="renderDynamicChart()">
                                <option value="SUM">Sumar</option>
                                <option value="COUNT">Recuento</option>
                                <option value="AVG">Promediar</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="text-muted small mb-1">Tipo</label>
                        <select class="form-select form-select-sm bg-dark text-white border-secondary" id="chartType" onchange="renderDynamicChart()">
                            <option value="bar">Barras</option>
                            <option value="line">Líneas</option>
                            <option value="doughnut">Dona</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center p-2" style="min-height: 280px; position:relative;">
                <canvas id="rxnChart"></canvas>
            </div>
        </div>
    </div>

    <!-- TABLE SECTION -->
    <div class="col-lg-8" id="tableSectionCol" style="min-width: 0;">
        <div class="card bg-dark border-secondary border-opacity-50 h-100 shadow-sm" style="max-width: 100%;">
            <div class="card-header border-secondary border-opacity-50 bg-transparent px-0 pt-0 pb-0 d-flex justify-content-between align-items-end">
                <ul class="nav nav-tabs px-3 m-0" id="datasetTabs" role="tablist" style="border-bottom: none;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active text-white px-2 py-3 bg-transparent" id="plana-tab" data-bs-toggle="tab" data-bs-target="#plana" type="button" role="tab" style="border:none; border-bottom: 2px solid #0d6efd; border-radius:0;"><i class="bi bi-table me-2"></i>Vista Plana</button>
                    </li>
                    <?php if (!empty($pivotMetadata)): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-muted px-2 py-3 bg-transparent" id="pivot-tab" data-bs-toggle="tab" data-bs-target="#pivot" type="button" role="tab" style="border:none; border-radius:0;"><i class="bi bi-layout-split me-2"></i>Tabla Dinámica</button>
                    </li>
                    <?php endif; ?>
                </ul>
                <span class="badge bg-secondary rounded-pill m-3"><?= $totalRegistros ?> regs</span>
            </div>
            <div class="card-body p-0 tab-content rxn-scrollbar" id="datasetTabsContent">
                <!-- TAB PLANA -->
                <div class="tab-pane fade show active table-responsive rxn-scrollbar rxn-live-pane" id="plana" role="tabpanel" style="min-height: 260px; overflow-y: auto;">
                    <?php if (empty($datasetRows)): ?>
                        <div class="text-center p-5 text-muted">
                            <i class="bi bi-inboxes mb-2 fs-3"></i><br>
                            No se encontraron resultados para los filtros actuales.
                        </div>
                    <?php else: ?>
                        <div id="groupByZone" class="rxn-group-zone"
                             ondragover="handleGroupZoneDragOver(event)"
                             ondragleave="handleGroupZoneDragLeave(event)"
                             ondrop="handleGroupZoneDrop(event)">
                            <div class="rxn-group-zone-empty text-muted small">
                                <i class="bi bi-arrows-move me-1"></i>
                                Arrastrá aquí los encabezados de columna para agrupar (hasta <?= 3 /* MAX_GROUP_LEVELS */ ?> niveles).
                            </div>
                            <div id="groupByChips" class="d-flex flex-wrap gap-2 align-items-center" style="display: none;"></div>
                        </div>
                        <div id="planaResultContainer"></div>
                    <?php endif; ?>
                </div>

                <!-- TAB PIVOT -->
                <?php if (!empty($pivotMetadata)): ?>
                <div class="tab-pane fade" id="pivot" role="tabpanel">
                    <div class="p-3 border-bottom border-secondary border-opacity-50" style="background-color: rgba(0,0,0,0.15);">
                        <div class="row g-3 px-2">
                            <!-- Rows -->
                            <div class="col-md-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="text-muted small fw-bold mb-0"><i class="bi bi-list-nested me-1"></i> FILAS</label>
                                    <button class="btn btn-link text-primary p-0 btn-sm text-decoration-none" onclick="addPivotSlot('row')"><i class="bi bi-plus-circle"></i></button>
                                </div>
                                <div id="pivotContainerRow" class="d-flex flex-column gap-2"></div>
                            </div>
                            
                            <!-- Columns -->
                            <div class="col-md-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="text-muted small fw-bold mb-0"><i class="bi bi-layout-three-columns me-1"></i> COLUMNAS</label>
                                    <button class="btn btn-link text-primary p-0 btn-sm text-decoration-none" onclick="addPivotSlot('col')"><i class="bi bi-plus-circle"></i></button>
                                </div>
                                <div id="pivotContainerCol" class="d-flex flex-column gap-2"></div>
                            </div>
                            
                            <!-- Values -->
                            <div class="col-md-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="text-muted small fw-bold mb-0"><i class="bi bi-calculator me-1"></i> VALORES</label>
                                    <button class="btn btn-link text-primary p-0 btn-sm text-decoration-none" onclick="addPivotSlot('val')"><i class="bi bi-plus-circle"></i></button>
                                </div>
                                <div id="pivotContainerVal" class="d-flex flex-column gap-2"></div>
                            </div>
                            
                            <!-- Options -->
                            <div class="col-md-3">
                                <label class="text-muted small fw-bold mb-2"><i class="bi bi-gear me-1"></i> OPCIONES</label>
                                <div class="bg-dark border border-secondary rounded p-2 border-opacity-50">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="pivotSortDesc">
                                        <label class="form-check-label text-white small" style="font-size: 0.8rem;" for="pivotSortDesc">Ordenar Mayor a Menor</label>
                                    </div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="pivotShowRowTotals" checked>
                                        <label class="form-check-label text-white small" style="font-size: 0.8rem;" for="pivotShowRowTotals">Subtotales por Fila</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="pivotShowColTotals" checked>
                                        <label class="form-check-label text-white small" style="font-size: 0.8rem;" for="pivotShowColTotals">Totales Generales</label>
                                    </div>
                                </div>
                                <div class="mt-2 text-end">
                                    <button class="btn btn-primary btn-sm w-100" onclick="renderPivot()" id="btnPivotRender"><i class="bi bi-play-fill"></i> Renderizar Matriz</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive p-0 rxn-scrollbar rxn-live-pane" id="pivotResultContainer" style="min-height: 260px; overflow-y: auto;">
                        <div class="text-center p-5 text-muted">Configurá los campos arriba y pulsá Renderizar Matriz.</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php
                // Paginación + selector "Mostrar". El footer aparece siempre que haya registros.
                // Si "Todos" está activo o el total entra en una página, se ocultan los botones Ant/Sig
                // pero el selector queda para que el usuario pueda volver a paginar.
                $perPageOptions = ['50' => '50', '100' => '100', '250' => '250', '500' => '500', 'all' => 'Todos'];
                $isAll = ($perPage === 'all');
                $totalPages = ($isAll || $limit <= 0) ? 1 : (int)max(1, ceil($totalRegistros / $limit));
                $baseQs = http_build_query(array_merge($filters, ['dataset' => $datasetKey]));
                $linkFor = function (array $extra) use ($filters, $datasetKey) {
                    return '?' . http_build_query(array_merge($filters, ['dataset' => $datasetKey], $extra));
                };
            ?>
            <?php if ($totalRegistros > 0): ?>
            <div class="card-footer border-top border-secondary border-opacity-50 bg-transparent d-flex flex-wrap justify-content-between align-items-center py-2 gap-2">
                <div class="d-flex align-items-center gap-2 text-white-50 small">
                    <label for="perPageSelect" class="mb-0">Mostrar:</label>
                    <select id="perPageSelect" class="form-select form-select-sm bg-dark text-white border-secondary" style="width: auto;" onchange="changePerPage(this.value)">
                        <?php foreach ($perPageOptions as $val => $lbl): ?>
                            <option value="<?= htmlspecialchars($val) ?>" <?= ($perPage === $val) ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="ms-2"><?= number_format($totalRegistros, 0, ',', '.') ?> registro<?= $totalRegistros === 1 ? '' : 's' ?></span>
                </div>
                <?php if (!$isAll && $totalRegistros > $limit): ?>
                <nav>
                    <ul class="pagination pagination-sm m-0">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link bg-dark text-white border-secondary" href="<?= htmlspecialchars($linkFor(['page' => $page - 1, 'per_page' => $perPage])) ?>">Ant</a>
                        </li>
                        <li class="page-item disabled">
                            <span class="page-link bg-dark text-white border-secondary px-3"><?= $page ?> de <?= $totalPages ?></span>
                        </li>
                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link bg-dark text-white border-secondary" href="<?= htmlspecialchars($linkFor(['page' => $page + 1, 'per_page' => $perPage])) ?>">Sig</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Ocultar barra si hay pocos registros, o dar estilo minimalista en webkit */
.rxn-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.rxn-scrollbar::-webkit-scrollbar-track { background: transparent; }
.rxn-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }
.rxn-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.4); }

/* === Agrupación estilo Tango === */
.rxn-group-zone {
    padding: 8px 14px;
    border: 1px dashed rgba(255,255,255,0.18);
    background-color: rgba(255,255,255,0.03);
    border-radius: 4px;
    margin: 8px 12px 4px 12px;
    transition: background-color 120ms ease, border-color 120ms ease;
    min-height: 38px;
    display: flex;
    align-items: center;
}
.rxn-group-zone.is-active {
    border-color: rgba(13, 110, 253, 0.6);
    background-color: rgba(13, 110, 253, 0.08);
}
.rxn-group-zone.is-dragover {
    border-color: rgba(13, 202, 240, 0.85);
    background-color: rgba(13, 202, 240, 0.12);
    border-style: solid;
}
.rxn-group-zone-empty {
    flex: 1;
    text-align: center;
    pointer-events: none;
    opacity: 0.7;
}
.rxn-group-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    background-color: rgba(13, 110, 253, 0.18);
    border: 1px solid rgba(13, 110, 253, 0.45);
    color: #fff;
    border-radius: 16px;
    font-size: 0.78rem;
    font-weight: 500;
}
.rxn-group-chip .rxn-group-chip-level {
    background-color: rgba(255,255,255,0.18);
    color: #fff;
    width: 18px; height: 18px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    font-weight: 700;
}
.rxn-group-chip .rxn-group-chip-remove {
    cursor: pointer;
    opacity: 0.7;
    font-size: 0.85rem;
    line-height: 1;
    padding: 0 2px;
}
.rxn-group-chip .rxn-group-chip-remove:hover { opacity: 1; color: #ff8a8a; }
.rxn-group-arrow {
    color: rgba(255,255,255,0.35);
    margin: 0 2px;
    font-size: 0.7rem;
}

/* Cuando un header se está arrastrando, da feedback visual */
th.rxn-th-dragging { opacity: 0.4; }

/* Filas de grupo en la tabla agrupada */
tr.rxn-group-row { cursor: pointer; user-select: none; }
tr.rxn-group-row:hover td { background-color: rgba(13, 110, 253, 0.10) !important; }
tr.rxn-group-row td {
    background-color: rgba(13, 110, 253, 0.06);
    font-weight: 600;
    border-top: 1px solid rgba(255,255,255,0.08);
}
tr.rxn-group-row.rxn-group-level-0 td { background-color: rgba(13, 110, 253, 0.13); font-size: 0.88rem; }
tr.rxn-group-row.rxn-group-level-1 td { background-color: rgba(13, 110, 253, 0.09); }
tr.rxn-group-row.rxn-group-level-2 td { background-color: rgba(13, 110, 253, 0.05); font-weight: 500; }
.rxn-group-caret { display: inline-block; width: 14px; transition: transform 120ms ease; }
.rxn-group-label { color: rgba(255,255,255,0.95); }
.rxn-group-fieldname { color: rgba(255,255,255,0.55); font-weight: normal; font-size: 0.75rem; margin-right: 4px; }
.rxn-group-count {
    color: rgba(255,255,255,0.55);
    font-weight: normal;
    font-size: 0.72rem;
    margin-left: 6px;
    background-color: rgba(255,255,255,0.08);
    padding: 1px 7px;
    border-radius: 9px;
}
</style>
</div><!-- /.rxn-live-shell -->

<?php
$content = ob_get_clean();

ob_start();
?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const rawDatasetRows = <?= json_encode($datasetRows ?? []) ?>;
const pivotMetadata = <?= json_encode($pivotMetadata ?? []) ?>;
// Safe Mode flag: si está activo, el front salta hidratación de sessionStorage
// y no aplica view_id cacheado. Es el escape hatch ante vistas/filtros que rompen la UI.
window.rxnSafeMode = <?= !empty($safeMode) ? 'true' : 'false' ?>;

let filteredDatasetRows = [...rawDatasetRows];

let globalDateFormat = 'Y-m-d';

function formatRxnDate(val, format) {
    if (!val) return val;
    let m = String(val).match(/^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}):(\d{2}):(\d{2}))?$/);
    if (!m) return val;
    let Y = m[1], mStr = m[2], d = m[3];
    let H = m[4] || '', i = m[5] || '';
    
    let res = format;
    res = res.replace('Y', Y).replace('y', Y.substring(2)).replace('m', mStr).replace('d', d);
    if (H && format !== 'Y-m-d') res += ' ' + H + ':' + i;
    return res;
}

function changeGlobalDateFormat() {
    let sel = document.getElementById('globalDateFormatSelect');
    if (sel) {
        globalDateFormat = sel.value;
        renderPlana();
        saveVolatileState();
    }
}

// Cambia el tamaño de página (incluido "Todos") sin perder filtros activos.
// Resetea page=1 porque saltar de "50 → Todos" en page 7 deja un offset que devolvería 0 filas.
function changePerPage(newPerPage) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', newPerPage);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

// --- LOGICA DE VISIBILIDAD DE PANELES ---
let chartVisible = true;
let tableVisible = true;

function toggleViewSection(section) {
    if (section === 'chart') {
        chartVisible = !chartVisible;
        if (!chartVisible) tableVisible = true;
    } else {
        tableVisible = !tableVisible;
        if (!tableVisible) chartVisible = true;
    }
    applyViewVisibility();
    saveVolatileState();
}

function applyViewVisibility() {
    const chartCol = document.getElementById('chartSectionCol');
    const tableCol = document.getElementById('tableSectionCol');

    if (!chartCol || !tableCol) return;

    if (!chartVisible) {
        chartCol.classList.add('d-none');
        tableCol.classList.remove('d-none', 'col-lg-8');
        tableCol.classList.add('col-lg-12');
    } else if (!tableVisible) {
        tableCol.classList.add('d-none');
        chartCol.classList.remove('d-none', 'col-lg-4');
        chartCol.classList.add('col-lg-12');
    } else {
        chartCol.classList.remove('d-none', 'col-lg-12');
        chartCol.classList.add('col-lg-4');
        tableCol.classList.remove('d-none', 'col-lg-12');
        tableCol.classList.add('col-lg-8');
    }
    document.getElementById('toggleChartBtn').className = chartVisible ? "btn btn-outline-secondary btn-sm px-2" : "btn btn-secondary btn-sm px-2 opacity-50";
    document.getElementById('toggleTableBtn').className = tableVisible ? "btn btn-outline-secondary btn-sm px-2" : "btn btn-secondary btn-sm px-2 opacity-50";
    
    // Resize chart to adapt to new container size
    if (typeof window.dispatchEvent === 'function') {
        window.setTimeout(() => window.dispatchEvent(new Event('resize')), 50);
    }
}

function fullReset() {
    let base = '<?= htmlspecialchars($datasetKey) ?>';
    sessionStorage.removeItem('rxn_live_volatile_' + base);
    let currentUrlForReset = new URL(window.location.href);
    let targetParams = new URLSearchParams();
    targetParams.set('dataset', base);
    targetParams.set('reset_view', '1');
    if (currentUrlForReset.searchParams.has('view_id')) {
        targetParams.set('view_id', currentUrlForReset.searchParams.get('view_id'));
    }
    window.location.href = currentUrlForReset.pathname + '?' + targetParams.toString();
}

function clearAllFilters() {
    let u = new URL(window.location.href);
    let keysToDelete = [];
    for (let [k,v] of u.searchParams.entries()) {
        if (k.startsWith('f[') || k === 'b_query' || k === 'query' || k === 'estado' || k === 'razon_social') {
            keysToDelete.push(k);
        }
    }
    keysToDelete.forEach(k => u.searchParams.delete(k));
    u.searchParams.delete('page');
    u.searchParams.set('reset_filters', '1');
    
    // Solo purgar los filtros en el state volatil
    let base = '<?= htmlspecialchars($datasetKey) ?>';
    let volatileKey = 'rxn_live_volatile_' + base;
    let vState = sessionStorage.getItem(volatileKey);
    if (vState) {
        try {
            let state = JSON.parse(vState);
            state.flatFilters = {};
            state.flatDiscreteFilters = {};
            state.urlFilters = {};
            sessionStorage.setItem(volatileKey, JSON.stringify(state));
        } catch(e){}
    }
    
    window.location.href = u.toString();
}

// --- LOGICA FLAT VIEW ---
let flatSortCol = null;
let flatSortAsc = true;
let hiddenCols = [];
let orderedCols = [];
let flatFilters = {};
let flatDiscreteFilters = {};
// Widths custom por columna (px). {colName: 180, ...} — persistidos en volatile state y vista guardada.
let colWidths = {};
// Toggle global: false = truncar con ellipsis (default), true = wrap (ajustar al ancho, celda crece en alto).
let wrapText = false;

// --- AGRUPACIÓN ESTILO TANGO ---
// Hasta 3 niveles de agrupación. Orden = anidación: groupByCols[0] es el grupo de mayor jerarquía.
// Cuando hay agrupación activa se fuerza per_page=all (Charly: "que actúe sobre todos los registros").
const MAX_GROUP_LEVELS = 3;
let groupByCols = [];
// Estado expand/collapse por path. Key = "valorN1|valorN2|valorN3" (hasta el nivel correspondiente).
// Default = expandido (Charly: "no va a aparecer nada agrupado por defecto, todo expandido").
// Solo guardamos los paths COLAPSADOS para no inflar sessionStorage con todos los expandidos.
let groupCollapseState = {};

function getValidOrderedCols(baseCols) {
    let valid = orderedCols.filter(c => baseCols.includes(c));
    let missing = baseCols.filter(c => !valid.includes(c));
    return valid.concat(missing);
}

function buildColumnSelector() {
    let dropdown = document.getElementById('colSelectorDropdown');
    if (!dropdown || !rawDatasetRows || rawDatasetRows.length === 0) return;
    
    let baseCols = Object.keys(rawDatasetRows[0]);
    let cols = orderedCols.length > 0 ? getValidOrderedCols(baseCols) : baseCols;
    
    let html = `<li><span class="dropdown-item-text text-white fw-bold mb-1 border-bottom border-secondary pb-1 d-block"><i class="bi bi-eye"></i> Visibilidad de Columnas</span></li>`;
    html += `<li class="px-2 mb-2"><input type="text" class="form-control form-control-sm bg-dark text-white border-secondary" id="colMenuFilter" placeholder="Buscar columna..." onkeyup="filterColumnSelector()" onclick="event.stopPropagation();"></li>`;
    
    cols.forEach(col => {
        let isChecked = !hiddenCols.includes(col);
        let label = (pivotMetadata[col] && pivotMetadata[col].label) ? pivotMetadata[col].label : col.toUpperCase();
        
        html += `
        <li class="col-selector-item" draggable="true" data-col="${col}" ondragstart="colDragStart(event)" ondragover="colDragOver(event)" ondrop="colDrop(event)" ondragend="colDragEnd(event)" style="cursor: grab;">
            <div class="form-check text-truncate px-3 py-1 m-0 d-flex align-items-center">
                <i class="bi bi-grip-vertical text-secondary me-1" style="font-size: 0.8rem;"></i>
                <input class="form-check-input ms-0 me-2 flat-col-toggle" type="checkbox" value="${col}" id="chk_col_${col}" ${isChecked ? 'checked' : ''} onchange="toggleFlatCol(this)">
                <label class="form-check-label text-light w-100" style="cursor:grab;" for="chk_col_${col}" title="${label}">
                    ${label}
                </label>
            </div>
        </li>`;
    });
    
    dropdown.innerHTML = html;
}

function filterColumnSelector() {
    let filter = document.getElementById('colMenuFilter').value.toLowerCase();
    let items = document.querySelectorAll('#colSelectorDropdown .col-selector-item');
    items.forEach(item => {
        let text = item.innerText || item.textContent;
        if (text.toLowerCase().indexOf(filter) > -1) {
            item.style.display = "";
        } else {
            item.style.display = "none";
        }
    });
}

let draggedCol = null;

function colDragStart(e) {
    draggedCol = e.currentTarget.getAttribute('data-col');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', draggedCol);
    e.currentTarget.style.opacity = '0.4';
}

function colDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    let target = e.currentTarget;
    if (target.getAttribute('data-col') !== draggedCol) {
        target.classList.add('bg-secondary', 'bg-opacity-25');
    }
}

function colDragEnd(e) {
    e.currentTarget.style.opacity = '1';
    let items = document.querySelectorAll('#colSelectorDropdown .col-selector-item');
    items.forEach(item => item.classList.remove('bg-secondary', 'bg-opacity-25'));
}

function colDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    let items = document.querySelectorAll('#colSelectorDropdown .col-selector-item');
    items.forEach(item => item.classList.remove('bg-secondary', 'bg-opacity-25'));
    
    let targetCol = e.currentTarget.getAttribute('data-col');
    if (draggedCol === targetCol || !draggedCol) {
        return;
    }
    
    let baseCols = Object.keys(rawDatasetRows[0]);
    let currentOrder = orderedCols.length > 0 ? getValidOrderedCols(baseCols) : baseCols;
    
    let fromIndex = currentOrder.indexOf(draggedCol);
    let toIndex = currentOrder.indexOf(targetCol);
    
    if (fromIndex !== -1 && toIndex !== -1) {
        currentOrder.splice(fromIndex, 1);
        currentOrder.splice(toIndex, 0, draggedCol);
        orderedCols = currentOrder;
        buildColumnSelector(); 
        renderPlana(); 
        saveVolatileState();
    }
}

function toggleFlatCol(checkbox) {
    let col = checkbox.value;
    if (checkbox.checked) {
        hiddenCols = hiddenCols.filter(c => c !== col);
    } else {
        if (!hiddenCols.includes(col)) hiddenCols.push(col);
    }
    renderPlana();
    saveVolatileState();
}

function applyLocalFilters() {
    filteredDatasetRows = rawDatasetRows.filter(row => {
        for (let col in flatFilters) {
            let filterTerm = flatFilters[col].toLowerCase();
            if (!filterTerm) continue;
            let valStr = row[col] !== null && row[col] !== undefined ? String(row[col]) : '';
            
            let isDateCol = pivotMetadata[col] && (pivotMetadata[col].type === 'date' || pivotMetadata[col].type === 'datetime' || pivotMetadata[col].type === 'timestamp');
            if (globalDateFormat !== 'Y-m-d' && isDateCol && valStr !== '') {
                valStr = formatRxnDate(valStr, globalDateFormat);
            }
            
            let val = valStr.toLowerCase();
            
            if (filterTerm.includes('%')) {
                let escaped = filterTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                let pattern = '^' + escaped.replace(/%/g, '.*') + '$';
                let regex = new RegExp(pattern);
                if (!regex.test(val)) return false;
            } else {
                if (!val.includes(filterTerm)) return false;
            }
        }
        
        for (let col in flatDiscreteFilters) {
            if (flatDiscreteFilters[col] && flatDiscreteFilters[col].length > 0) {
                let val = row[col];
                let isDateCol = pivotMetadata[col] && (pivotMetadata[col].type === 'date' || pivotMetadata[col].type === 'datetime' || pivotMetadata[col].type === 'timestamp');
                if (globalDateFormat !== 'Y-m-d' && isDateCol && val !== null && val !== '') {
                    val = formatRxnDate(String(val), globalDateFormat);
                }
                if (val === null || val === undefined || val === '') val = '(Vacío)';
                val = String(val);
                if (!flatDiscreteFilters[col].includes(val)) return false;
            }
        }
        
        return true;
    });

    if (flatSortCol) {
        const colMeta = pivotMetadata[flatSortCol];
        const isDateCol = colMeta && (colMeta.type === 'date' || colMeta.type === 'datetime' || colMeta.type === 'timestamp');
        const isNumericCol = colMeta && colMeta.type === 'numeric';

        filteredDatasetRows.sort((a, b) => {
            let va = a[flatSortCol];
            let vb = b[flatSortCol];

            // Nulls always go to the bottom
            if (va === null || va === undefined || va === '') return 1;
            if (vb === null || vb === undefined || vb === '') return -1;

            if (isDateCol) {
                // Siempre comparar sobre el valor ISO original del dataset (YYYY-MM-DD o YYYY-MM-DD HH:MM:SS)
                // que ordena lexicográficamente de forma correcta sin importar el formato visual elegido
                let da = String(va), db = String(vb);
                return flatSortAsc ? da.localeCompare(db) : db.localeCompare(da);
            }

            if (isNumericCol || (!isNaN(parseFloat(va)) && !isNaN(parseFloat(vb)) && isFinite(va) && isFinite(vb))) {
                return flatSortAsc ? parseFloat(va) - parseFloat(vb) : parseFloat(vb) - parseFloat(va);
            }

            va = String(va);
            vb = String(vb);
            return flatSortAsc ? va.localeCompare(vb, 'es', {sensitivity: 'base'}) : vb.localeCompare(va, 'es', {sensitivity: 'base'});
        });
    }
    
    renderPlana();
    let pivotContainer = document.getElementById('pivotResultContainer');
    if (pivotContainer && pivotContainer.innerHTML.indexOf('<table') !== -1) {
        renderPivot();
    }
    renderDynamicChart();
    saveVolatileState();
}

function handleFlatSort(col) {
    if (flatSortCol === col) {
        if (!flatSortAsc) {
            flatSortCol = null; // toggle off third click
        } else {
            flatSortAsc = false;
        }
    } else {
        flatSortCol = col;
        flatSortAsc = true;
    }
    applyLocalFilters();
}

function handleFlatFilter(col, term) {
    flatFilters[col] = term;
    applyLocalFilters();
}

function toggleAdvVal2(col) {
    let op = document.getElementById('adv_op_' + col).value;
    let input2 = document.getElementById('adv_val2_' + col);
    if (input2) {
        input2.style.display = (op === 'entre') ? 'block' : 'none';
    }
}

function buildDiscreteDropdown(col) {
    let div = document.getElementById('discreteDropdown_' + col);
    if (!div) return;
    
    // Advanced Filter State
    let u = new URLSearchParams(window.location.search);
    let advOp = u.get(`f[${col}][op]`) || 'contiene';
    let advVal = u.get(`f[${col}][val]`) || '';
    let advVal2 = u.get(`f[${col}][val2]`) || '';
    let isAdvActive = u.has(`f[${col}][op]`);
    
    let uniqueValues = {};
    if (rawDatasetRows && rawDatasetRows.length > 0) {
        rawDatasetRows.forEach(r => {
            let val = r[col];
            let isDateCol = pivotMetadata[col] && (pivotMetadata[col].type === 'date' || pivotMetadata[col].type === 'datetime' || pivotMetadata[col].type === 'timestamp');
            if (globalDateFormat !== 'Y-m-d' && isDateCol && val !== null && val !== '') {
                val = formatRxnDate(String(val), globalDateFormat);
            }
            if(val === null || val === undefined || val === '') val = '(Vacío)';
            val = String(val);
            uniqueValues[val] = (uniqueValues[val] || 0) + 1;
        });
    }
    
    let sortedKeys = Object.keys(uniqueValues).sort();
    let activeFilters = flatDiscreteFilters[col] || [];
    
    let isDateCol = pivotMetadata[col] && (pivotMetadata[col].type === 'date' || pivotMetadata[col].type === 'datetime' || pivotMetadata[col].type === 'timestamp');
    let inputType = 'text';
    let advValRender = advVal;
    let advVal2Render = advVal2;
    
    if (isDateCol) {
        inputType = pivotMetadata[col].type === 'date' ? 'date' : 'datetime-local';
        if (inputType === 'datetime-local') {
            if (advVal) advValRender = advVal.replace(' ', 'T').substring(0, 16);
            if (advVal2) advVal2Render = advVal2.replace(' ', 'T').substring(0, 16);
        }
    }
    
    let html = `
        <div class="px-2 pb-2 border-bottom border-light mb-2 border-opacity-25" onclick="event.stopPropagation();">
            <div class="mb-2 text-warning fw-bold" style="font-size:0.85rem;"><i class="bi bi-hdd-network"></i> Filtro Motor BD</div>
            <select class="form-select form-select-sm mb-2 bg-dark text-white border-secondary" id="adv_op_${col}" onchange="toggleAdvVal2('${col}')">
                <option value="contiene" ${advOp==='contiene'?'selected':''}>Contiene</option>
                <option value="no_contiene" ${advOp==='no_contiene'?'selected':''}>No contiene</option>
                <option value="empieza_con" ${advOp==='empieza_con'?'selected':''}>Empieza con</option>
                <option value="termina_con" ${advOp==='termina_con'?'selected':''}>Termina con</option>
                <option value="igual" ${advOp==='igual'?'selected':''}>Igual</option>
                <option value="distinto" ${advOp==='distinto'?'selected':''}>Distinto</option>
                <option value="mayor_que" ${advOp==='mayor_que'?'selected':''}>Mayor a (... >)</option>
                <option value="menor_que" ${advOp==='menor_que'?'selected':''}>Menor a (... <)</option>
                <option value="entre" ${advOp==='entre'?'selected':''}>Entre (Valores/Fechas)</option>
            </select>
            <input type="${inputType}" step="1" class="form-control form-control-sm mt-1 mb-1 bg-dark text-white border-secondary" id="adv_val_${col}" value="${advValRender}" placeholder="Límite o valor...">
            <input type="${inputType}" step="1" class="form-control form-control-sm mb-2 bg-dark text-white border-secondary" id="adv_val2_${col}" value="${advVal2Render}" placeholder="Límite superior..." style="display: ${advOp === 'entre' ? 'block' : 'none'};">
            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-light" onclick="clearAdvancedFilter('${col}')">Borrar BD</button>
                <button type="button" class="btn btn-sm btn-warning text-dark fw-bold" onclick="applyAdvancedFilter('${col}')">Aplicar BD</button>
            </div>
        </div>

        <div class="px-2 pb-1" onclick="event.stopPropagation();">
            <div class="mb-2 text-info fw-bold" style="font-size:0.85rem;"><i class="bi bi-funnel-fill"></i> Selección Local</div>
            <input type="text" class="form-control form-control-sm bg-dark text-white border-secondary mb-2" placeholder="Buscar en lista..." onkeyup="filterDiscreteList(this, '${col}')">
            <div class="d-flex justify-content-between mb-2">
                <button class="btn btn-link text-info text-decoration-none p-0" style="font-size: 0.8rem;" type="button" onclick="toggleAllDiscrete(true, '${col}')">Marcar Todo</button>
                <button class="btn btn-link text-info text-decoration-none p-0" style="font-size: 0.8rem;" type="button" onclick="toggleAllDiscrete(false, '${col}')">Ninguno</button>
            </div>
            <div class="discrete-list rxn-scrollbar" id="discreteList_${col}" style="max-height: 150px; overflow-y: auto;">
    `;
    
    sortedKeys.forEach((k, idx) => {
        let isChecked = (activeFilters.length === 0) || activeFilters.includes(k);
        let escapedK = k.replace(/"/g, '&quot;');
        let domId = 'chk_' + col + '_' + idx;
        html += `
            <div class="form-check discrete-item" style="cursor: pointer;">
                <input class="form-check-input border-secondary bg-dark" type="checkbox" value="${escapedK}" id="${domId}" ${isChecked ? 'checked' : ''}>
                <label class="form-check-label text-truncate text-white small w-100" style="cursor: pointer; user-select: none;" for="${domId}" title="${escapedK}">
                    ${k} <span class="text-muted">(${uniqueValues[k]})</span>
                </label>
            </div>
        `;
    });
    
    html += `
            </div>
            <div class="d-flex justify-content-between mt-2 pt-2 border-top border-light border-opacity-25">
                <button class="btn btn-outline-secondary btn-sm" type="button" onclick="clearDiscreteFilter('${col}')">Limpiar Local</button>
                <button class="btn btn-info text-dark fw-bold btn-sm" type="button" onclick="applyDiscreteUI('${col}')">Aplicar Local</button>
            </div>
        </div>`;
    div.innerHTML = html;

    // Aplicar Flatpickr (formato 24hs) a los inputs datetime-local recién renderizados.
    if (window.RxnDateTime && typeof window.RxnDateTime.initAll === 'function') {
        window.RxnDateTime.initAll(div);
    }

    // Forzar a Popper a recalcular la posición en base al nuevo contenido para que no se encime / corte
    setTimeout(() => { window.dispatchEvent(new Event('resize')); }, 50);
}

function filterDiscreteList(input, col) {
    let term = input.value.toLowerCase();
    let container = document.getElementById('discreteList_' + col);
    if(!container) return;
    let items = container.querySelectorAll('.discrete-item');
    items.forEach(item => {
        let label = item.querySelector('label').innerText.toLowerCase();
        item.style.display = label.includes(term) ? '' : 'none';
    });
}

function toggleAllDiscrete(checked, col) {
    let container = document.getElementById('discreteList_' + col);
    if(!container) return;
    let visibleCheckboxes = Array.from(container.querySelectorAll('.discrete-item'))
        .filter(item => item.style.display !== 'none')
        .map(item => item.querySelector('input[type="checkbox"]'));
        
    visibleCheckboxes.forEach(cb => cb.checked = checked);
}

function applyDiscreteUI(col) {
    let div = document.getElementById('discreteList_' + col);
    if (!div) return;
    let checkboxes = div.querySelectorAll('input[type="checkbox"]');
    let selected = [];
    let allSelected = true;
    checkboxes.forEach(cb => {
        if (cb.checked) {
            selected.push(cb.value);
        } else {
            allSelected = false;
        }
    });
    
    if (allSelected || selected.length === 0) {
        delete flatDiscreteFilters[col];
    } else {
        flatDiscreteFilters[col] = selected;
    }
    
    applyLocalFilters();
}

function clearDiscreteFilter(col) {
    delete flatDiscreteFilters[col];
    applyLocalFilters();
}

function applyAdvancedFilter(col) {
    let op = document.getElementById('adv_op_' + col).value;
    let val = document.getElementById('adv_val_' + col).value.trim();
    if (!val) {
        alert("Ingrese un valor para el filtro de base de datos.");
        return;
    }
    
    val = val.replace('T', ' '); // Normalizar datetime-local
    
    let params = new URLSearchParams(window.location.search);
    params.set(`f[${col}][op]`, op);
    params.set(`f[${col}][val]`, val);

    if (op === 'entre') {
        let elVal2 = document.getElementById('adv_val2_' + col);
        let val2Str = elVal2 ? elVal2.value.trim() : '';
        if (!val2Str) {
            alert("Ingrese el límite superior para el filtro.");
            return;
        }
        val2Str = val2Str.replace('T', ' '); // Normalizar datetime-local
        params.set(`f[${col}][val2]`, val2Str);
    } else {
        params.delete(`f[${col}][val2]`);
    }

    params.delete('page');
    saveVolatileState(params); // Guarda los parametros futuros en sessionStorage para evitar desfasaje en carga
    window.location.search = params.toString();
}

function clearAdvancedFilter(col) {
    let params = new URLSearchParams(window.location.search);
    params.delete(`f[${col}][op]`);
    params.delete(`f[${col}][val]`);
    params.delete(`f[${col}][val2]`);
    params.delete('page');
    params.set('reset_filters', '1');
    saveVolatileState(params); // Guardar limpio para evitar discrepancia
    window.location.search = params.toString();
}

function renderPlana() {
    const container = document.getElementById('planaResultContainer');
    if (!container) return;

    let baseCols = [];
    if (rawDatasetRows && rawDatasetRows.length > 0) {
        baseCols = Object.keys(rawDatasetRows[0]);
    } else if (pivotMetadata && Object.keys(pivotMetadata).length > 0) {
        baseCols = Object.keys(pivotMetadata);
    }
    
    // Si aun asi no hay columnas, entonces no podemos hacer la grilla
    if (baseCols.length === 0) {
         container.innerHTML = `<div class="text-center p-5 text-muted">
             <i class="bi bi-inboxes mb-2 fs-3"></i><br>
             El sistema no dispone de columnas para mostrar.
         </div>`;
         return;
    }

    // Refrescar la zona de agrupación (chips arriba) en cada render para mantenerla sincronizada
    // con el estado y aplicar las clases visuales (is-active si hay chips).
    renderGroupZone();

    let colsAll = orderedCols.length > 0 ? getValidOrderedCols(baseCols) : baseCols;
    let visibleCols = colsAll.filter(c => !hiddenCols.includes(c));

    let numMetricsSum = {};
    let numericCols = [];
    visibleCols.forEach(c => {
        if (pivotMetadata[c] && pivotMetadata[c].type === 'numeric') {
            numMetricsSum[c] = 0;
            numericCols.push(c);
        }
    });
    // Pre-calcular el total general una sola vez. Antes lo iba sumando en el forEach del tbody,
    // pero ahora con render agrupado el forEach ya no aplica — calculamos acá y reusamos.
    if (numericCols.length > 0 && filteredDatasetRows && filteredDatasetRows.length > 0) {
        filteredDatasetRows.forEach(row => {
            numericCols.forEach(c => {
                let n = parseFloat(row[c]);
                if (!isNaN(n)) numMetricsSum[c] += n;
            });
        });
    }

    // Estilos dependientes del modo wrap. table-layout fixed es clave para que el width aplicado
    // al <th> sea respetado — si no, el browser re-reparte ancho según contenido y se pierde el resize.
    let tableStyle = `font-size: 0.85rem; border-collapse: separate; border-spacing: 0;`;
    if (wrapText || Object.keys(colWidths).length > 0) {
        tableStyle += ` table-layout: fixed; width: auto;`;
    }
    let html = `<table class="table table-dark table-striped table-hover table-sm m-0" style="${tableStyle}">`;

    // THEAD
    html += `<thead class="text-muted" style="position: sticky; top: 0; background-color: #212529; z-index: 2; box-shadow: 0 1px 0 rgba(255,255,255,0.1);">`;
    html += `<tr>`;
    visibleCols.forEach(col => {
        let sortIcon = '<i class="bi bi-arrow-down-up text-secondary ms-1" style="font-size:0.7rem"></i>';
        if (flatSortCol === col) {
            sortIcon = flatSortAsc ? '<i class="bi bi-arrow-down text-info ms-1"></i>' : '<i class="bi bi-arrow-up text-info ms-1"></i>';
        }
        let thClass = (pivotMetadata[col] && pivotMetadata[col].type === 'numeric') ? 'text-end' : '';
        // En modo wrap sacamos text-nowrap para que el label del header también pueda cortarse si es muy largo.
        let nowrapClass = wrapText ? '' : 'text-nowrap';
        let widthStyle = colWidths[col] ? `width: ${colWidths[col]}px; min-width: ${colWidths[col]}px; max-width: ${colWidths[col]}px;` : '';
        // Headers draggables hacia la rxn-group-zone. El click sigue funcionando para sort
        // porque drag se dispara solo si hay movimiento real con el botón apretado.
        // Si la columna ya está en groupByCols se deshabilita el drag (ya está agrupada).
        let isAlreadyGrouped = groupByCols.includes(col);
        let draggableAttr = isAlreadyGrouped ? '' : 'draggable="true" ondragstart="handleHeaderDragStart(event, this)" ondragend="handleHeaderDragEnd(event, this)"';
        html += `<th class="fw-bold ${nowrapClass} px-3 py-2 border-bottom-0 pb-1 ${thClass}" style="cursor: pointer; user-select: none; position: relative; ${widthStyle}" data-col="${col}" onclick="handleFlatSort('${col}')" ${draggableAttr}>
                    <span class="align-middle">${(pivotMetadata[col] && pivotMetadata[col].label) ? pivotMetadata[col].label : col.toUpperCase()} <span class="ms-1">${sortIcon}</span></span>
                    <span class="rxn-col-resizer" data-col="${col}" onclick="event.stopPropagation()" onmousedown="startColResize(event, '${col}')" title="Arrastrar para ajustar ancho" style="position: absolute; top: 0; right: 0; width: 6px; height: 100%; cursor: col-resize; user-select: none;"></span>
                 </th>`;
    });
    html += `</tr><tr>`;
    visibleCols.forEach(col => {
        let v = (flatFilters && flatFilters[col]) ? flatFilters[col] : '';
        let escapedV = String(v).replace(/"/g, '&quot;');
        let isLocalActive = flatDiscreteFilters && flatDiscreteFilters[col] && flatDiscreteFilters[col].length > 0;
        let isDbActive = new URLSearchParams(window.location.search).has(`f[${col}][op]`);
        let isDiscreteActive = isLocalActive || isDbActive;
        let btnClass = isDiscreteActive ? 'btn-primary' : 'btn-dark dropdown-toggle-split border-secondary';
        let iconHtml = isDiscreteActive ? '<i class="bi bi-funnel-fill text-warning"></i>' : '<i class="bi bi-funnel"></i>';
        
        let filterWidthStyle = colWidths[col] ? `width: ${colWidths[col]}px; min-width: ${colWidths[col]}px; max-width: ${colWidths[col]}px;` : '';
        html += `<th class="px-1 py-1 pt-0 border-bottom-0" style="${filterWidthStyle}">
                    <div class="input-group input-group-sm m-0">
                        <input type="text" data-filter-col="${col}" class="form-control bg-dark text-white border-secondary" style="font-size: 0.75rem; padding: 2px 6px; min-width: 0;" placeholder="Filtrar..." value="${escapedV}" oninput="handleFlatFilter('${col}', this.value)">
                        <button class="btn ${btnClass} text-white" type="button" data-bs-toggle="dropdown" aria-expanded="false" onclick="buildDiscreteDropdown('${col}')">
                            ${iconHtml}
                        </button>
                        <div class="dropdown-menu dropdown-menu-dark p-2 shadow border-secondary dropdown-menu-end" id="discreteDropdown_${col}" style="width: 250px; z-index: 1050;" onclick="event.stopPropagation()">
                            <div class="text-center text-muted small"><span class="spinner-border spinner-border-sm"></span> Cargando opciones...</div>
                        </div>
                    </div>
                 </th>`;
    });
    html += `</tr>`;
    html += `</thead>`;
    
    // TBODY
    html += `<tbody>`;
    if (!rawDatasetRows || rawDatasetRows.length === 0) {
        html += `<tr><td colspan="${visibleCols.length}" class="text-center p-5 text-muted">
            <i class="bi bi-inboxes mb-2 fs-3"></i><br>
            No se encontraron resultados para los filtros actuales en el servidor.
        </td></tr>`;
    } else if (filteredDatasetRows.length === 0) {
        html += `<tr><td colspan="${visibleCols.length}" class="text-center p-5 text-muted">
            <i class="bi bi-funnel mb-2 fs-3"></i><br>
            No hay resultados que coincidan con los filtros locales de columna.
        </td></tr>`;
    } else if (groupByCols.length > 0) {
        // Modo agrupado: render recursivo con expand/collapse + subtotales por grupo.
        html += buildGroupedRowsHtml(filteredDatasetRows, 0, '', visibleCols, numericCols);
    } else {
        filteredDatasetRows.forEach(row => {
            html += buildDetailRowHtml(row, visibleCols, 0);
        });
    }
    html += `</tbody>`;
    
    // TFOOT
    if (Object.keys(numMetricsSum).length > 0) {
        html += `<tfoot style="position: sticky; bottom: 0; background-color: #2b3035; z-index: 1;"><tr>`;
        visibleCols.forEach(col => {
            if (numMetricsSum[col] !== undefined) {
                html += `<td class="text-nowrap px-3 py-2 border-secondary text-end fw-bold text-info font-monospace">${numMetricsSum[col].toLocaleString('es-AR', {minimumFractionDigits:4, maximumFractionDigits:4})}</td>`;
            } else {
                html += `<td class="border-secondary ${col === visibleCols[0] ? 'px-3 py-2 fw-bold text-white' : ''}">${col === visibleCols[0] ? 'TOTAL' : ''}</td>`;
            }
        });
        html += `</tr></tfoot>`;
    }
    
    html += `</table>`;

    let activeInputCol = null;
    let activeSelectionStart = null;
    let activeSelectionEnd = null;
    if (document.activeElement && document.activeElement.tagName === 'INPUT' && document.activeElement.hasAttribute('data-filter-col')) {
        activeInputCol = document.activeElement.getAttribute('data-filter-col');
        try { activeSelectionStart = document.activeElement.selectionStart; } catch(e){}
        try { activeSelectionEnd = document.activeElement.selectionEnd; } catch(e){}
    }

    container.innerHTML = html;

    if (activeInputCol) {
        let input = container.querySelector(`input[data-filter-col="${activeInputCol}"]`);
        if (input) {
            input.focus();
            try { input.setSelectionRange(activeSelectionStart, activeSelectionEnd); } catch(e){}
        }
    }
    
    updateExportForm();
}

// ===================================================================
// === AGRUPACIÓN ESTILO TANGO — helpers, drag/drop y render        ===
// ===================================================================

function rxnEscapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function rxnEscapeJsArg(s) {
    // Para inyectar dentro de un onclick="foo('...')". Escapamos backslash, comilla simple y newlines.
    if (s === null || s === undefined) return '';
    return String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, '\\n').replace(/\r/g, '');
}

// Render del chip individual de la zona de agrupación.
function buildGroupChipHtml(col, level) {
    let label = (pivotMetadata[col] && pivotMetadata[col].label) ? pivotMetadata[col].label : col.toUpperCase();
    return `<span class="rxn-group-chip" data-col="${rxnEscapeHtml(col)}" title="Quitar agrupación">
                <span class="rxn-group-chip-level">${level + 1}</span>
                <span>${rxnEscapeHtml(label)}</span>
                <span class="rxn-group-chip-remove" onclick="removeGroupCol('${rxnEscapeJsArg(col)}'); event.stopPropagation();">&times;</span>
            </span>`;
}

// Refresca el contenido visible de la rxn-group-zone (chips o placeholder).
// Se llama desde renderPlana en cada render para mantener todo coherente.
function renderGroupZone() {
    const zone = document.getElementById('groupByZone');
    const chips = document.getElementById('groupByChips');
    const empty = zone ? zone.querySelector('.rxn-group-zone-empty') : null;
    if (!zone || !chips || !empty) return;

    if (groupByCols.length === 0) {
        chips.innerHTML = '';
        chips.style.display = 'none';
        empty.style.display = '';
        zone.classList.remove('is-active');
        return;
    }

    let html = '';
    groupByCols.forEach((col, i) => {
        if (i > 0) html += `<span class="rxn-group-arrow"><i class="bi bi-chevron-right"></i></span>`;
        html += buildGroupChipHtml(col, i);
    });
    chips.innerHTML = html;
    chips.style.display = '';
    empty.style.display = 'none';
    zone.classList.add('is-active');
}

// Drag desde un <th> de la grilla.
function handleHeaderDragStart(event, thEl) {
    let col = thEl.getAttribute('data-col');
    if (!col) return;
    event.dataTransfer.effectAllowed = 'copyMove';
    event.dataTransfer.setData('text/x-rxn-col', col);
    // Fallback genérico — algunos browsers requieren text/plain para que el drag funcione.
    event.dataTransfer.setData('text/plain', col);
    thEl.classList.add('rxn-th-dragging');
    // Marca la zone como receptiva visualmente desde que arranca el drag.
    const zone = document.getElementById('groupByZone');
    if (zone) zone.classList.add('is-active');
}

function handleHeaderDragEnd(event, thEl) {
    if (thEl) thEl.classList.remove('rxn-th-dragging');
    const zone = document.getElementById('groupByZone');
    if (zone) {
        zone.classList.remove('is-dragover');
        // Si no hay agrupación activa, sacamos el highlight permanente.
        if (groupByCols.length === 0) zone.classList.remove('is-active');
    }
}

function handleGroupZoneDragOver(event) {
    // Aceptar el drop solo si viene de un header de columna.
    let types = event.dataTransfer.types;
    if (types && (Array.from(types).includes('text/x-rxn-col') || Array.from(types).includes('text/plain'))) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
        const zone = document.getElementById('groupByZone');
        if (zone) zone.classList.add('is-dragover');
    }
}

function handleGroupZoneDragLeave(event) {
    const zone = document.getElementById('groupByZone');
    if (!zone) return;
    // dragleave dispara también al pasar por hijos — chequear que el cursor salió de la zone.
    let related = event.relatedTarget;
    if (!related || !zone.contains(related)) {
        zone.classList.remove('is-dragover');
    }
}

function handleGroupZoneDrop(event) {
    event.preventDefault();
    const zone = document.getElementById('groupByZone');
    if (zone) zone.classList.remove('is-dragover');

    let col = event.dataTransfer.getData('text/x-rxn-col') || event.dataTransfer.getData('text/plain');
    if (!col) return;

    // No duplicar.
    if (groupByCols.includes(col)) return;
    if (groupByCols.length >= MAX_GROUP_LEVELS) {
        if (window.rxnAlert) {
            window.rxnAlert(`Solo se pueden anidar ${MAX_GROUP_LEVELS} niveles de agrupación.`, 'warning', 'Límite alcanzado');
        } else {
            alert(`Solo se pueden anidar ${MAX_GROUP_LEVELS} niveles de agrupación.`);
        }
        return;
    }

    let wasFirstGroup = (groupByCols.length === 0);
    groupByCols.push(col);
    saveVolatileState();

    // Si es el primer chip y todavía estamos paginados, redirigimos a per_page=all.
    // Charly: la agrupación tiene que actuar sobre TODOS los registros, sin paginación.
    if (wasFirstGroup && rxnNeedsAllForGrouping()) {
        rxnRedirectToAllPages();
        return;
    }

    renderPlana();
}

// True si hay más registros en el server de los que tenemos cargados en rawDatasetRows.
// Ese gap es lo que justifica forzar per_page=all al activar la primera agrupación.
function rxnNeedsAllForGrouping() {
    const totalServer = <?= (int)$totalRegistros ?>;
    const loaded = (rawDatasetRows && rawDatasetRows.length) || 0;
    return totalServer > loaded;
}

function rxnRedirectToAllPages() {
    // Persistimos el estado actual (incluyendo el chip recién agregado) para que al recargar
    // la app se hidrate y re-renderice agrupado automáticamente.
    saveVolatileState();
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', 'all');
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function removeGroupCol(col) {
    let idx = groupByCols.indexOf(col);
    if (idx === -1) return;
    groupByCols.splice(idx, 1);
    // Limpiar collapse states que dejaron de ser relevantes (los paths que arrancan con esta col en su nivel).
    // Approach simple y seguro: si no hay más agrupaciones, limpiar todo. Si quedan, dejamos el state —
    // las keys obsoletas no molestan porque `buildGroupedRowsHtml` solo consulta paths existentes.
    if (groupByCols.length === 0) groupCollapseState = {};
    saveVolatileState();
    renderPlana();
}

function toggleGroupCollapse(path) {
    if (groupCollapseState[path]) {
        delete groupCollapseState[path];
    } else {
        groupCollapseState[path] = true;
    }
    saveVolatileState();
    renderPlana();
}

// Suma cada columna numérica sobre las rows del grupo. Devuelve {col: sum}.
function computeGroupSubtotals(rows, numericCols) {
    let out = {};
    numericCols.forEach(c => out[c] = 0);
    rows.forEach(r => {
        numericCols.forEach(c => {
            let n = parseFloat(r[c]);
            if (!isNaN(n)) out[c] += n;
        });
    });
    return out;
}

// Aplica formato visual a la key de agrupación (sobre todo fechas si globalDateFormat != Y-m-d).
function formatGroupKey(val, col) {
    if (val === null || val === undefined || val === '') return '(Vacío)';
    let isDate = pivotMetadata[col] && (pivotMetadata[col].type === 'date' || pivotMetadata[col].type === 'datetime' || pivotMetadata[col].type === 'timestamp');
    if (isDate && globalDateFormat !== 'Y-m-d') {
        return formatRxnDate(String(val), globalDateFormat);
    }
    return String(val);
}

// Construye una <tr> con las celdas de detalle. indentLevel agrega padding en la primera celda
// para que las filas de detalle queden visualmente anidadas bajo su grupo padre.
function buildDetailRowHtml(row, visibleCols, indentLevel) {
    let indentPx = (indentLevel || 0) * 18;
    let html = `<tr>`;
    visibleCols.forEach((col, idx) => {
        let val = row[col];
        let isNumeric = (pivotMetadata[col] && pivotMetadata[col].type === 'numeric');
        let tdClass = isNumeric ? 'text-end font-monospace' : '';
        let printVal = val || '';

        if (isNumeric && val !== null && val !== '') {
            printVal = Number(val).toLocaleString('es-AR', {minimumFractionDigits: 4, maximumFractionDigits: 4});
        } else if (globalDateFormat !== 'Y-m-d' && pivotMetadata[col] && (pivotMetadata[col].type === 'date' || pivotMetadata[col].type === 'datetime' || pivotMetadata[col].type === 'timestamp') && val !== null && val !== '') {
            printVal = formatRxnDate(val, globalDateFormat);
        }

        let cellStyle, titleAttr = '';
        if (wrapText) {
            cellStyle = 'white-space: normal; word-break: break-word; vertical-align: top;';
        } else {
            cellStyle = 'white-space: nowrap; overflow: hidden; text-overflow: ellipsis;';
            if (printVal !== '' && printVal !== null) {
                titleAttr = ` title="${String(printVal).replace(/"/g, '&quot;')}"`;
            }
        }
        let cellWidthStyle = colWidths[col] ? `max-width: ${colWidths[col]}px;` : '';
        let firstColIndent = (idx === 0 && indentPx > 0) ? ` padding-left: ${12 + indentPx}px !important;` : '';

        html += `<td class="px-3 py-1 border-secondary border-opacity-25 ${tdClass}" style="${cellStyle} ${cellWidthStyle}${firstColIndent}"${titleAttr}>${printVal}</td>`;
    });
    html += `</tr>`;
    return html;
}

// Render recursivo: agrupa las rows por groupByCols[level] y emite una row de header por grupo
// (con caret expand/collapse + subtotales de columnas numéricas) seguida de los hijos
// (otro nivel de grupos o filas de detalle si llegamos al último nivel).
function buildGroupedRowsHtml(rows, level, parentPath, visibleCols, numericCols) {
    if (!rows || rows.length === 0) return '';

    // Caso base: ya pasamos todos los niveles de agrupación → renderizamos detalle.
    if (level >= groupByCols.length) {
        return rows.map(row => buildDetailRowHtml(row, visibleCols, level)).join('');
    }

    let groupCol = groupByCols[level];
    let groupLabel = (pivotMetadata[groupCol] && pivotMetadata[groupCol].label) ? pivotMetadata[groupCol].label : groupCol.toUpperCase();

    // Agrupar por valor (con formato visual aplicado a fechas).
    let groups = new Map();
    rows.forEach(r => {
        let key = formatGroupKey(r[groupCol], groupCol);
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(r);
    });

    // Orden alfanumérico estable para que el usuario vea siempre el mismo orden.
    let sortedKeys = Array.from(groups.keys()).sort((a, b) => a.localeCompare(b, 'es', { numeric: true, sensitivity: 'base' }));

    let html = '';
    sortedKeys.forEach(key => {
        let groupRows = groups.get(key);
        let path = parentPath ? (parentPath + '|||' + key) : key;
        let isCollapsed = !!groupCollapseState[path];
        let subtotals = computeGroupSubtotals(groupRows, numericCols);
        let caretIcon = isCollapsed ? 'bi-caret-right-fill' : 'bi-caret-down-fill';
        let indentPx = level * 18;

        html += `<tr class="rxn-group-row rxn-group-level-${Math.min(level, 2)}" data-group-path="${rxnEscapeHtml(path)}" onclick="toggleGroupCollapse('${rxnEscapeJsArg(path)}')">`;
        visibleCols.forEach((col, idx) => {
            let widthStyle = colWidths[col] ? `max-width: ${colWidths[col]}px;` : '';
            if (idx === 0) {
                html += `<td class="px-3 py-2" style="${widthStyle} padding-left: ${12 + indentPx}px !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <i class="rxn-group-caret bi ${caretIcon}"></i>
                            <span class="rxn-group-fieldname">${rxnEscapeHtml(groupLabel)}:</span>
                            <span class="rxn-group-label">${rxnEscapeHtml(key)}</span>
                            <span class="rxn-group-count">${groupRows.length}</span>
                         </td>`;
            } else if (numericCols.includes(col)) {
                let v = subtotals[col];
                let formatted = (v !== undefined && v !== null) ? Number(v).toLocaleString('es-AR', {minimumFractionDigits: 4, maximumFractionDigits: 4}) : '';
                html += `<td class="text-end font-monospace px-3 py-2" style="${widthStyle}">${formatted}</td>`;
            } else {
                html += `<td class="px-3 py-2" style="${widthStyle}"></td>`;
            }
        });
        html += `</tr>`;

        if (!isCollapsed) {
            html += buildGroupedRowsHtml(groupRows, level + 1, path, visibleCols, numericCols);
        }
    });

    return html;
}

document.addEventListener('DOMContentLoaded', () => {
    buildColumnSelector();

    // Toggle inicial del botón Eliminar Vista según la opción pre-seleccionada del dropdown
    toggleDeleteViewButton();

    let u = new URL(window.location.href);
    let viewIdParam = u.searchParams.get('view_id');
    let dropdown = document.getElementById('savedViewsDropdown');
    
    // Initialize pivot with default 1 row and 1 val slot
    addPivotSlot('row');
    addPivotSlot('val');

    // Tab UI Events
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', function(e) {
            document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(b => {
                 b.style.borderBottom = 'none';
                 b.classList.remove('text-white');
                 b.classList.add('text-muted');
            });
            e.target.style.borderBottom = '2px solid #0d6efd';
            e.target.classList.remove('text-muted');
            e.target.classList.add('text-white');
            
            if(e.target.id === 'pivot-tab') renderPivot();
        });
    });
    
    // SAFE MODE: si está activo, saltamos toda hidratación de vistas/sessionStorage
    // y renderizamos directo el dataset base limpio. Además limpiamos el sessionStorage
    // del dataset para que no vuelva a contaminar la próxima carga normal.
    if (window.rxnSafeMode) {
        try {
            sessionStorage.removeItem('rxn_live_volatile_<?= htmlspecialchars($datasetKey) ?>');
        } catch(e) {}
        renderPlana();
        return;
    }

    if (viewIdParam && dropdown) {
        let optExists = Array.from(dropdown.options).some(o => strEquals(o.value, viewIdParam));
        if (optExists) {
            dropdown.value = viewIdParam;
            loadSelectedView(); // handles renderPlana, pivot and chart
            return;
        }
    }

    // --- RUTA: Sin view_id en URL (Vista Base o regreso navegación) ---
    let volatileBaseStr = sessionStorage.getItem('rxn_live_volatile_<?= htmlspecialchars($datasetKey) ?>');
    let volatileBase = null;
    if (volatileBaseStr) {
        try { volatileBase = JSON.parse(volatileBaseStr); } catch(e) {}
    }

    // Si hay estado previo guardado para Vista Base (view_id vacío), restaurarlo
    if (volatileBase && (!volatileBase.view_id || volatileBase.view_id === '')) {
        // Restaurar filtros locales
        if (volatileBase.flatFilters) flatFilters = volatileBase.flatFilters;
        if (volatileBase.flatDiscreteFilters) flatDiscreteFilters = volatileBase.flatDiscreteFilters;
        if (volatileBase.globalDateFormat) {
            globalDateFormat = volatileBase.globalDateFormat;
            let dfSel = document.getElementById('globalDateFormatSelect');
            if (dfSel) dfSel.value = globalDateFormat;
        }
        if (volatileBase.hiddenCols) hiddenCols = volatileBase.hiddenCols;
        if (volatileBase.orderedCols) orderedCols = volatileBase.orderedCols;
        if (volatileBase.flatSortCol !== undefined) flatSortCol = volatileBase.flatSortCol;
        if (volatileBase.flatSortAsc !== undefined) flatSortAsc = volatileBase.flatSortAsc;
        // Widths + wrap (features nuevas — defensive contra volatile state viejo que no los tenga).
        if (volatileBase.colWidths && typeof volatileBase.colWidths === 'object' && !Array.isArray(volatileBase.colWidths)) {
            colWidths = {};
            for (let k in volatileBase.colWidths) {
                let w = parseInt(volatileBase.colWidths[k], 10);
                if (Number.isFinite(w) && w >= 40 && w <= 800) colWidths[k] = w;
            }
        }
        if (volatileBase.wrapText !== undefined) wrapText = !!volatileBase.wrapText;
        applyWrapBtnState();
        // Agrupación estilo Tango (defensive: si el volatile state es viejo y no tiene estos campos, quedan vacíos).
        if (Array.isArray(volatileBase.groupByCols)) {
            let seenG = new Set();
            groupByCols = volatileBase.groupByCols
                .filter(c => typeof c === 'string')
                .filter(c => { if (seenG.has(c)) return false; seenG.add(c); return true; })
                .slice(0, MAX_GROUP_LEVELS);
        }
        if (volatileBase.groupCollapseState && typeof volatileBase.groupCollapseState === 'object' && !Array.isArray(volatileBase.groupCollapseState)) {
            groupCollapseState = volatileBase.groupCollapseState;
        }
        // Chart config
        if (volatileBase.chartConfig) {
            chartConfig = Object.assign(chartConfig, volatileBase.chartConfig);
        }
    }

    renderPlana();

    // Nota: antes de 2026-04-15 acá se inyectaba un input hidden `theme` detectando dark/light
    // para el XLSX. Se removió — el export ahora usa paleta Excel clásica fija independiente del tema.

    // Siempre inicializar el gráfico al final (Vista Base y navegación)
    populateChartSelectors();
    renderDynamicChart();
});

function updateExportForm() {
    let exportForm = document.getElementById('exportDatasetForm');
    if (!exportForm) return;

    // Limpiar TODOS los inputs dinámicos previos (antes excluíamos `theme`, pero `theme` ya no se usa).
    exportForm.querySelectorAll('.dynamic-export-input').forEach(el => el.remove());

    let addHidden = (name, value) => {
        let input = document.createElement('input');
        input.className = 'dynamic-export-input';
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        exportForm.appendChild(input);
    };

    if (hiddenCols && hiddenCols.length > 0) addHidden('hidden_cols', JSON.stringify(hiddenCols));
    if (orderedCols && orderedCols.length > 0) addHidden('ordered_cols', JSON.stringify(orderedCols));

    if (flatSortCol) {
        addHidden('sort_col', flatSortCol);
        addHidden('sort_asc', flatSortAsc ? '1' : '0');
    }

    // Filtros por columna (texto "contiene") — ANTES no se enviaban y el export salía sin filtrar.
    // El backend los aplica en memoria replicando el formato visual de fechas (ver RxnLiveController::exportar).
    if (flatFilters && Object.keys(flatFilters).length > 0) {
        // Limpiar entradas vacías para no ensuciar el payload.
        let clean = {};
        for (let k in flatFilters) {
            if (flatFilters[k] !== '' && flatFilters[k] !== null && flatFilters[k] !== undefined) {
                clean[k] = flatFilters[k];
            }
        }
        if (Object.keys(clean).length > 0) addHidden('flat_filters', JSON.stringify(clean));
    }

    // Filtros discretos (dropdown con checkboxes de valores únicos) — también faltaban de mandarse.
    if (flatDiscreteFilters && Object.keys(flatDiscreteFilters).length > 0) {
        let clean = {};
        for (let k in flatDiscreteFilters) {
            if (Array.isArray(flatDiscreteFilters[k]) && flatDiscreteFilters[k].length > 0) {
                clean[k] = flatDiscreteFilters[k];
            }
        }
        if (Object.keys(clean).length > 0) addHidden('discrete_filters', JSON.stringify(clean));
    }

    // Formato de fecha global — el backend lo usa para replicar el formateo visual al aplicar flat_filters
    // y discrete_filters sobre columnas de fecha.
    if (globalDateFormat && globalDateFormat !== 'Y-m-d') {
        addHidden('global_date_format', globalDateFormat);
    }

    // Widths custom de columnas (px). El backend los convierte a Excel width units (≈ px/7) y los
    // aplica via OpenSpout\Options::setColumnWidth antes de escribir filas.
    if (colWidths && Object.keys(colWidths).length > 0) {
        addHidden('col_widths', JSON.stringify(colWidths));
    }
}

function strEquals(a, b) {
    return String(a) === String(b);
}

/**
 * Resize de columnas por drag en el borde derecho del <th>.
 * - mousedown captura X inicial y width actual.
 * - mousemove recalcula (min 40px, max 800px para evitar widths absurdos).
 * - mouseup persiste en sessionStorage via saveVolatileState().
 *
 * No re-renderizamos toda la tabla durante el drag (haría flicker) — aplicamos
 * el width directo al DOM y al soltar mandamos re-render para que quede limpio.
 */
let _rxnResizeState = null;
function startColResize(e, col) {
    e.preventDefault();
    e.stopPropagation();
    let th = e.currentTarget.closest('th');
    if (!th) return;
    let startX = e.clientX;
    let startWidth = th.offsetWidth;
    _rxnResizeState = { col: col, startX: startX, startWidth: startWidth, th: th };
    document.body.style.cursor = 'col-resize';
    document.body.style.userSelect = 'none';
    document.addEventListener('mousemove', _onColResizeMove);
    document.addEventListener('mouseup', _onColResizeEnd);
}
function _onColResizeMove(e) {
    if (!_rxnResizeState) return;
    let delta = e.clientX - _rxnResizeState.startX;
    let newW = Math.max(40, Math.min(800, _rxnResizeState.startWidth + delta));
    colWidths[_rxnResizeState.col] = newW;
    // Aplicar directo al th sin re-render para evitar flicker durante el drag.
    let table = _rxnResizeState.th.closest('table');
    if (table) {
        table.querySelectorAll(`th[data-col="${_rxnResizeState.col}"]`).forEach(el => {
            el.style.width = newW + 'px';
            el.style.minWidth = newW + 'px';
            el.style.maxWidth = newW + 'px';
        });
    }
}
function _onColResizeEnd(e) {
    document.removeEventListener('mousemove', _onColResizeMove);
    document.removeEventListener('mouseup', _onColResizeEnd);
    document.body.style.cursor = '';
    document.body.style.userSelect = '';
    if (_rxnResizeState) {
        _rxnResizeState = null;
        // Re-render para aplicar el width a los <td> también (que no tocamos durante el drag).
        renderPlana();
        saveVolatileState();
    }
}

/**
 * Toggle global entre modo "truncar + tooltip" (default) y "ajustar al ancho" (wrap).
 * Global por decisión de diseño (2026-04-15) — por columna sería ruido al pepe.
 */
function toggleWrapText() {
    wrapText = !wrapText;
    let btn = document.getElementById('toggleWrapBtn');
    if (btn) {
        if (wrapText) {
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-info');
        } else {
            btn.classList.add('btn-outline-secondary');
            btn.classList.remove('btn-info');
        }
    }
    renderPlana();
    saveVolatileState();
}

function applyWrapBtnState() {
    let btn = document.getElementById('toggleWrapBtn');
    if (!btn) return;
    if (wrapText) {
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-info');
    } else {
        btn.classList.add('btn-outline-secondary');
        btn.classList.remove('btn-info');
    }
}
// --- FIN LOGICA FLAT VIEW ---

let pivotState = {
    rows: [], // [{field: 'fecha', format: 'YYYY-MM'}, ...]
    cols: [], // [{field: 'estado'}]
    vals: []  // [{field: 'total', op: 'SUM'}]
};

// Columnas internas que NO deben aparecer nunca en el pivot (IDs técnicos, FK, texto libre largo).
// Si una columna aparece en el dataset pero no está en pivotMetadata y tampoco está acá,
// se la auto-incluye como groupable. Así, si mañana se agrega una columna útil a la view SQL
// sin tocar el PHP, igual aparece en los selects del pivot. Si la nueva columna es ruido,
// agregarla a esta lista.
const PIVOT_INTERNAL_COLS = ['empresa_id', 'cliente_id', 'id_pedidoservicio', 'diagnostico', 'tango_estado'];

function getFieldOptions(purpose) {
    let options = '<option value="">-- Seleccione --</option>';
    let seen = new Set();

    for (let [key, meta] of Object.entries(pivotMetadata)) {
        if (purpose === 'group' && meta.groupable) {
            options += `<option value="${key}">${meta.label}</option>`;
            seen.add(key);
        }
        if (purpose === 'val' && meta.aggregatable) {
            options += `<option value="${key}">${meta.label}</option>`;
            seen.add(key);
        }
    }

    // Fallback defensivo: columnas presentes en el dataset que no están declaradas en pivotMetadata
    // se ofrecen como groupable (string). Para 'val' no aplica — sumar/promediar requiere que el
    // usuario haya marcado explícitamente la columna como numérica en pivotMetadata.
    if (purpose === 'group' && rawDatasetRows && rawDatasetRows.length > 0) {
        for (let col of Object.keys(rawDatasetRows[0])) {
            if (seen.has(col)) continue;
            if (PIVOT_INTERNAL_COLS.includes(col)) continue;
            if (pivotMetadata[col]) continue; // declarada pero no groupable: respetar la decisión
            options += `<option value="${col}">${col.toUpperCase()}</option>`;
        }
    }

    return options;
}

function addPivotSlot(type) {
    const containerId = type === 'row' ? 'pivotContainerRow' : (type === 'col' ? 'pivotContainerCol' : 'pivotContainerVal');
    const container = document.getElementById(containerId);
    const wrapper = document.createElement('div');
    wrapper.className = 'd-flex flex-column gap-1 pivot-slot-wrapper bg-dark p-2 rounded border border-secondary border-opacity-25';
    
    const uid = 'slot_' + Math.random().toString(36).substr(2, 9);
    
    let innerHtml = `<div class="d-flex gap-1 align-items-center">`;
    
    if (type === 'row' || type === 'col') {
        innerHtml += `<select class="form-select form-select-sm bg-dark text-white border-secondary pivot-slot-field" data-slot-id="${uid}" onchange="handleFieldChange(this, '${type}', '${uid}')">${getFieldOptions('group')}</select>`;
    } else {
        innerHtml += `<select class="form-select form-select-sm bg-dark text-white border-secondary pivot-slot-field" style="flex: 1.5;" data-slot-id="${uid}" onchange="handleFieldChange(this, '${type}', '${uid}')">${getFieldOptions('val')}</select>`;
        innerHtml += `<select class="form-select form-select-sm bg-dark text-info border-secondary pivot-slot-op" style="flex: 1;">
                        <option value="SUM">Sumar</option>
                        <option value="COUNT">Recuento</option>
                        <option value="AVG">Promedio</option>
                      </select>`;
    }
    
    innerHtml += `<button class="btn btn-outline-danger btn-sm" onclick="this.closest('.pivot-slot-wrapper').remove()"><i class="bi bi-x"></i></button></div>`;
    wrapper.innerHTML = innerHtml;
    container.appendChild(wrapper);
}

function handleFieldChange(selectEl, type, uid) {
    const val = selectEl.value;
    const wrapper = selectEl.closest('.pivot-slot-wrapper');
    let existingOpt = wrapper.querySelector('.date-format-opt');
    if (existingOpt) existingOpt.remove();

    if (val && pivotMetadata[val] && pivotMetadata[val].type === 'date' && (type === 'row' || type === 'col')) {
        let opt = document.createElement('div');
        opt.className = 'date-format-opt d-flex align-items-center mt-1';
        opt.innerHTML = `<span class="text-muted small me-2" style="font-size:0.7rem;">Agrupar:</span>
                         <select class="form-select form-select-sm bg-dark text-warning border-secondary pivot-slot-date" style="font-size: 0.75rem;">
                            <option value="">Día (YYYY-MM-DD)</option>
                            <option value="YYYY-MM">Mes (YYYY-MM)</option>
                            <option value="YYYY">Año (YYYY)</option>
                         </select>`;
        wrapper.appendChild(opt);
    }
}

function readPivotState() {
    pivotState.rows = [];
    pivotState.cols = [];
    pivotState.vals = [];
    
    document.querySelectorAll('#pivotContainerRow .pivot-slot-wrapper').forEach(w => {
        let f = w.querySelector('.pivot-slot-field').value;
        if (f) {
            let dSelect = w.querySelector('.pivot-slot-date');
            pivotState.rows.push({ field: f, dateFmt: dSelect ? dSelect.value : '' });
        }
    });
    
    document.querySelectorAll('#pivotContainerCol .pivot-slot-wrapper').forEach(w => {
        let f = w.querySelector('.pivot-slot-field').value;
        if (f) {
            let dSelect = w.querySelector('.pivot-slot-date');
            pivotState.cols.push({ field: f, dateFmt: dSelect ? dSelect.value : '' });
        }
    });
    
    document.querySelectorAll('#pivotContainerVal .pivot-slot-wrapper').forEach(w => {
        let f = w.querySelector('.pivot-slot-field').value;
        let op = w.querySelector('.pivot-slot-op').value;
        if (f) pivotState.vals.push({ field: f, op: op });
    });
}

function formatVal(n, op) {
    if (isNaN(n) || n === null) return '';
    const count = (op === 'COUNT' || isNaN(n));
    return Number(n).toLocaleString('es-AR', {minimumFractionDigits: count ? 0 : 4, maximumFractionDigits: count ? 0 : 4});
}

function aggArray(arr, op) {
    if (!arr || arr.length === 0) return 0;
    let sum = arr.reduce((a, b) => a + b, 0);
    if (op === 'SUM') return sum;
    if (op === 'COUNT') return arr.length;
    if (op === 'AVG') return sum / arr.length;
    return 0;
}

function extractDate(val, fmt) {
    if (!val) return 'Nd';
    let s = String(val);
    if (fmt === 'YYYY-MM') return s.substring(0, 7);
    if (fmt === 'YYYY') return s.substring(0, 4);
    return s.substring(0, 10);
}

function renderPivot() {
    const container = document.getElementById('pivotResultContainer');
    if (!container || !filteredDatasetRows || filteredDatasetRows.length === 0) return;
    
    readPivotState();
    if (pivotState.rows.length === 0 || pivotState.vals.length === 0) {
        container.innerHTML = `<div class="alert alert-warning m-3">Se requiere al menos 1 FILA y 1 VALOR.</div>`;
        return;
    }

    const isDesc = document.getElementById('pivotSortDesc').checked;
    const showRowTot = document.getElementById('pivotShowRowTotals').checked;
    const showColTot = document.getElementById('pivotShowColTotals').checked;

    let grid = {}; // grid[rowKey][colKey][valIdx] = [values]
    let rowKeys = new Set();
    let colKeys = new Set();

    filteredDatasetRows.forEach(r => {
        let rKParts = pivotState.rows.map(ro => {
            let raw = r[ro.field] || 'Sin Def';
            if (pivotMetadata[ro.field].type === 'date') raw = extractDate(raw, ro.dateFmt);
            return raw;
        });
        let rK = rKParts.join(' | ');
        
        let cKParts = pivotState.cols.map(co => {
            let raw = r[co.field] || 'Sin Def';
            if (pivotMetadata[co.field].type === 'date') raw = extractDate(raw, co.dateFmt);
            return raw;
        });
        let cK = cKParts.length > 0 ? cKParts.join(' | ') : 'Valores';

        rowKeys.add(rK);
        colKeys.add(cK);

        if (!grid[rK]) grid[rK] = {};
        if (!grid[rK][cK]) {
            grid[rK][cK] = pivotState.vals.map(() => []);
        }

        pivotState.vals.forEach((vo, idx) => {
            let rawV = parseFloat(r[vo.field]);
            grid[rK][cK][idx].push(isNaN(rawV) ? 0 : rawV);
        });
    });

    let rvArr = Array.from(rowKeys).sort();
    if (isDesc) rvArr.reverse();
    
    let cvArr = Array.from(colKeys).sort();

    let html = `<table class="table table-dark table-bordered table-sm m-0" style="font-size: 0.85rem; border-color: rgba(255,255,255,0.1);">\n`;
    
    // T-HEAD
    html += `<thead class="text-muted" style="position: sticky; top: 0; background-color: #212529; z-index:4;">`;
    
    // Top Header: Label for Rows
    let rowHeadersLabel = pivotState.rows.map(ro => pivotMetadata[ro.field].label).join(' > ');
    html += `<tr><th class="px-3 py-2 border-secondary bg-dark align-bottom" rowspan="2" style="position: sticky; left:0; z-index:5; border-right: 2px solid #495057 !important;">${rowHeadersLabel}</th>`;
    
    cvArr.forEach(c => {
        html += `<th class="px-3 py-2 border-secondary text-center" colspan="${pivotState.vals.length}" style="border-left: 2px solid #495057 !important; border-right: 2px solid #495057 !important; background-color: rgba(255,255,255,0.02);">${c}</th>`;
    });
    if (showColTot && pivotState.cols.length > 0) {
        html += `<th class="px-3 py-2 border-secondary text-center fw-bold text-white bg-dark" colspan="${pivotState.vals.length}" style="border-left: 2px solid #495057 !important;">TOTALES</th>`;
    }
    html += `</tr><tr>`;
    
    // Sub Headers: Metrics
    cvArr.forEach(c => {
        pivotState.vals.forEach((vo, idx) => {
            let isFirst = idx === 0;
            let style = isFirst ? 'border-left: 2px solid #495057 !important;' : '';
            html += `<th class="px-3 py-1 border-secondary text-end small" style="${style}"><span class="badge bg-secondary opacity-75 fw-normal">${vo.op}</span> ${pivotMetadata[vo.field].label}</th>`;
        });
    });
    if (showColTot && pivotState.cols.length > 0) {
        pivotState.vals.forEach((vo, idx) => {
            let isFirst = idx === 0;
            let style = isFirst ? 'border-left: 2px solid #495057 !important;' : '';
            html += `<th class="px-3 py-1 border-secondary text-end small text-white bg-dark" style="${style}"><span class="badge bg-primary opacity-75 fw-normal">${vo.op}</span> ${pivotMetadata[vo.field].label}</th>`;
        });
    }
    html += `</tr></thead><tbody>`;

    // T-BODY
    rvArr.forEach(r => {
        html += `<tr><td class="px-3 py-1 border-secondary fw-bold bg-dark" style="position: sticky; left:0; z-index:3; border-right: 2px solid #495057 !important;">${r}</td>`;
        let rowGrandTotals = pivotState.vals.map(() => []);

        cvArr.forEach(c => {
            pivotState.vals.forEach((vo, vIdx) => {
                let pts = (grid[r] && grid[r][c] && grid[r][c][vIdx]) ? grid[r][c][vIdx] : [];
                let rValAgg = aggArray(pts, vo.op);
                rowGrandTotals[vIdx].push(...pts);
                let isFirst = vIdx === 0;
                let style = isFirst ? 'border-left: 2px solid #495057 !important;' : '';
                html += `<td class="px-3 py-1 border-secondary text-end font-monospace" style="${style}">${formatVal(rValAgg, vo.op)}</td>`;
            });
        });
        
        if (showColTot && pivotState.cols.length > 0) {
            pivotState.vals.forEach((vo, vIdx) => {
                let rValTotAgg = aggArray(rowGrandTotals[vIdx], vo.op);
                let isFirst = vIdx === 0;
                let style = isFirst ? 'border-left: 2px solid #495057 !important;' : '';
                html += `<td class="px-3 py-1 border-secondary text-end fw-bold text-white bg-dark font-monospace" style="${style}">${formatVal(rValTotAgg, vo.op)}</td>`;
            });
        }
        html += `</tr>`;
    });
    html += `</tbody>`;

    // T-FOOT
    if (showColTot || showRowTot) {
        html += `<tfoot class="border-secondary fw-bold" style="position: sticky; bottom: 0; background-color: #2b3035; z-index:4;"><tr>`;
        html += `<td class="px-3 py-2 text-end text-white" style="position: sticky; left:0; z-index:5; background-color: #2b3035; border-right: 2px solid #495057 !important;">TOTAL GENERAL</td>`;
        
        let grandGrandTotals = pivotState.vals.map(() => []);

        cvArr.forEach(c => {
            pivotState.vals.forEach((vo, vIdx) => {
                let colGlobalPts = [];
                rvArr.forEach(r => {
                    if (grid[r] && grid[r][c] && grid[r][c][vIdx]) colGlobalPts.push(...grid[r][c][vIdx]);
                });
                grandGrandTotals[vIdx].push(...colGlobalPts);
                let isFirst = vIdx === 0;
                let style = isFirst ? 'border-left: 2px solid #495057 !important;' : '';
                html += `<td class="px-3 py-2 text-end text-white" style="${style}">${formatVal(aggArray(colGlobalPts, vo.op), vo.op)}</td>`;
            });
        });

        if (showColTot && pivotState.cols.length > 0) {
            pivotState.vals.forEach((vo, vIdx) => {
                let isFirst = vIdx === 0;
                let style = isFirst ? 'border-left: 2px solid #495057 !important;' : '';
                html += `<td class="px-3 py-2 text-end text-info bg-dark" style="${style}">${formatVal(aggArray(grandGrandTotals[vIdx], vo.op), vo.op)}</td>`;
            });
        }
        html += `</tr></tfoot>`;
    }
    
    html += `</table>`;
    container.innerHTML = html;
}

// Unified DOMContentLoaded: see handler above (~line 838)


let chartConfig = {
    groupCol: <?= json_encode($datasetInfo['chart_group_col'] ?? '') ?>,
    valCol: <?= json_encode($datasetInfo['chart_val_col'] ?? '') ?>,
    type: <?= json_encode($datasetInfo['chart_type'] ?? 'bar') ?>,
    op: 'SUM'
};

function populateChartSelectors() {
    let groupSelect = document.getElementById('chartGroupCol');
    let valSelect = document.getElementById('chartValCol');
    
    groupSelect.innerHTML = '';
    valSelect.innerHTML = '';
    
    let firstGroup = null;
    let firstVal = null;
    
    for (let [key, meta] of Object.entries(pivotMetadata)) {
        if (meta.groupable) {
            groupSelect.appendChild(new Option(meta.label, key));
            if (!firstGroup) firstGroup = key;
        }
        if (meta.aggregatable) {
            valSelect.appendChild(new Option(meta.label, key));
            if (!firstVal) firstVal = key;
        }
    }
    
    // Auto-healing: si el valor guardado no existe como opción, usar el primero disponible
    if (!chartConfig.groupCol || !groupSelect.querySelector(`option[value="${chartConfig.groupCol}"]`)) {
        chartConfig.groupCol = firstGroup;
    }
    if (!chartConfig.valCol || !valSelect.querySelector(`option[value="${chartConfig.valCol}"]`)) {
        chartConfig.valCol = firstVal;
    }
    
    groupSelect.value = chartConfig.groupCol;
    valSelect.value = chartConfig.valCol;
    document.getElementById('chartOp').value = chartConfig.op || 'SUM';
    document.getElementById('chartType').value = chartConfig.type || 'bar';
}

let rxnChartInstance = null;
const rxnChartColors = [
    'rgba(13, 110, 253, 0.85)',
    'rgba(102, 16, 242, 0.85)',
    'rgba(214, 51, 132, 0.85)',
    'rgba(220, 53, 69, 0.85)',
    'rgba(253, 126, 20, 0.85)',
    'rgba(255, 193, 7, 0.85)',
    'rgba(25, 135, 84, 0.85)',
    'rgba(32, 201, 151, 0.85)',
    'rgba(13, 202, 240, 0.85)'
];

function readChartUI() {
    chartConfig.groupCol = document.getElementById('chartGroupCol').value;
    chartConfig.valCol = document.getElementById('chartValCol').value;
    chartConfig.op = document.getElementById('chartOp').value;
    chartConfig.type = document.getElementById('chartType').value;
}

function renderDynamicChart() {
    readChartUI();
    const ctx = document.getElementById('rxnChart').getContext('2d');
    
    if (!chartConfig.groupCol || !chartConfig.valCol || !filteredDatasetRows || filteredDatasetRows.length === 0) {
        if (rxnChartInstance) { rxnChartInstance.destroy(); rxnChartInstance = null; }
        saveVolatileState();
        return;
    }
    
    let aggMap = {};
    filteredDatasetRows.forEach(row => {
        let valRaw = row[chartConfig.groupCol];
        let label = 'Sin definir';
        if (valRaw !== null && valRaw !== '') label = String(valRaw);
        
        let pVal = parseFloat(row[chartConfig.valCol]);
        if (isNaN(pVal)) pVal = 0;
        
        if (!aggMap[label]) aggMap[label] = { sum: 0, count: 0 };
        aggMap[label].sum += pVal;
        aggMap[label].count += 1;
    });
    
    let resultArr = [];
    for (let k in aggMap) {
        let finalVal = 0;
        if (chartConfig.op === 'SUM') finalVal = aggMap[k].sum;
        if (chartConfig.op === 'COUNT') finalVal = aggMap[k].count;
        if (chartConfig.op === 'AVG') finalVal = aggMap[k].sum / aggMap[k].count;
        resultArr.push({ label: k, value: finalVal });
    }
    
    let isTemporal = pivotMetadata[chartConfig.groupCol] && pivotMetadata[chartConfig.groupCol].type === 'date';
    if (isTemporal) {
        resultArr.sort((a, b) => a.label.localeCompare(b.label)); // Order by Date ASC
        resultArr = resultArr.slice(-20); // Mostrar los últimos 20 períodos
    } else {
        resultArr.sort((a, b) => b.value - a.value); // Order by Value DESC
        resultArr = resultArr.slice(0, 15);
    }
    // Protección extra: pie/doughnut con demasiados segmentos son invisibles
    if ((chartConfig.type === 'doughnut' || chartConfig.type === 'pie') && resultArr.length > 12) {
        resultArr = resultArr.slice(0, 12);
    }
    
    let labels = resultArr.map(x => x.label);
    let values = resultArr.map(x => x.value);
    let metaLabel = pivotMetadata[chartConfig.valCol] ? pivotMetadata[chartConfig.valCol].label : '';
    let chartLabel = chartConfig.op + ' de ' + metaLabel;
    
    if (rxnChartInstance) rxnChartInstance.destroy();
    
    rxnChartInstance = new Chart(ctx, {
        type: chartConfig.type,
        data: {
            labels: labels,
            datasets: [{
                label: chartLabel,
                data: values,
                backgroundColor: (chartConfig.type === 'doughnut' || chartConfig.type === 'pie') ? rxnChartColors : rxnChartColors[0],
                borderColor: '#212529',
                borderWidth: (chartConfig.type === 'doughnut' || chartConfig.type === 'pie') ? 2 : 1,
                borderRadius: chartConfig.type === 'bar' ? 4 : 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: (chartConfig.type === 'doughnut' || chartConfig.type === 'pie'),
                    position: 'bottom',
                    labels: { color: '#adb5bd', padding: 15, boxWidth: 12 }
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            let val = context.raw;
                            return ' ' + Number(val).toLocaleString('es-AR', {minimumFractionDigits: chartConfig.op === 'COUNT' ? 0 : 4, maximumFractionDigits: chartConfig.op === 'COUNT' ? 0 : 4});
                        }
                    }
                }
            },
            scales: chartConfig.type === 'bar' || chartConfig.type === 'line' ? {
                x: { ticks: { color: '#868e96' }, grid: { display: false } },
                y: { ticks: { color: '#868e96' }, grid: { color: 'rgba(255,255,255,0.05)' }, beginAtZero: true }
            } : {}
        }
    });
    saveVolatileState();
}

function extractViewConfig(overrideParams = null) {
    let pivotConfig = { rows: [], cols: [], vals: [] };
    
    // Parse Rows
    let elRows = document.getElementById('pivotContainerRow') || document.getElementById('pivotRows');
    if (elRows) {
        elRows.querySelectorAll('.pivot-slot').forEach(el => {
            pivotConfig.rows.push({ field: el.dataset.field, dateFmt: el.dataset.date });
        });
    }
    
    // Parse Cols
    let elCols = document.getElementById('pivotContainerCol') || document.getElementById('pivotCols');
    if (elCols) {
        elCols.querySelectorAll('.pivot-slot').forEach(el => {
            pivotConfig.cols.push({ field: el.dataset.field, dateFmt: el.dataset.date });
        });
    }
    
    // Parse Vals
    let elVals = document.getElementById('pivotContainerVal') || document.getElementById('pivotVals');
    if (elVals) {
        elVals.querySelectorAll('.pivot-slot').forEach(el => {
            pivotConfig.vals.push({ field: el.dataset.field, op: el.dataset.op });
        });
    }

    let activeTabBtn = document.querySelector('button[data-bs-toggle="tab"].active');
    let tabActivo = activeTabBtn ? activeTabBtn.dataset.bsTarget : '#plana';

    let elSortDesc = document.getElementById('pivotSortDesc');
    let elRowTot = document.getElementById('pivotShowRowTotals') || document.getElementById('pivotRowTot');
    let elColTot = document.getElementById('pivotShowColTotals') || document.getElementById('pivotColTot');

    let pivotOptions = {
        sortDesc: elSortDesc ? elSortDesc.checked : true,
        rowTot: elRowTot ? elRowTot.checked : true,
        colTot: elColTot ? elColTot.checked : true
    };
    
    // Capturar filtros avanzados actuales desde la URL o overrides directos pre-navegación
    let urlFilters = {};
    try {
        let currentUrl = new URL(window.location.href);
        let entriesIter = overrideParams ? overrideParams.entries() : currentUrl.searchParams.entries();
        for (let [k, v] of entriesIter) {
            if (k.startsWith('f[') || k === 'b_query' || k === 'query' || k === 'estado' || k === 'razon_social') {
                urlFilters[k] = v;
            }
        }
    } catch(e) {}

    return {
        chartVisible: chartVisible,
        tableVisible: tableVisible,
        hiddenCols: hiddenCols,
        orderedCols: orderedCols,
        flatSortCol: flatSortCol,
        flatSortAsc: flatSortAsc,
        urlFilters: urlFilters, 
        tab_activo: tabActivo,
        pivotState: pivotConfig,
        pivotOptions: pivotOptions,
        chartConfig: chartConfig,
        flatFilters: flatFilters,
        flatDiscreteFilters: flatDiscreteFilters,
        globalDateFormat: globalDateFormat,
        colWidths: colWidths,
        wrapText: wrapText,
        groupByCols: groupByCols,
        groupCollapseState: groupCollapseState,
        view_id: document.getElementById('savedViewsDropdown') ? document.getElementById('savedViewsDropdown').value : ''
    };
}

function saveVolatileState(overrideParams = null) {
    let state = extractViewConfig(overrideParams);
    sessionStorage.setItem('rxn_live_volatile_<?= htmlspecialchars($datasetKey) ?>', JSON.stringify(state));
}

let viewSaveModalInstance = null;

/**
 * Muestra/oculta los botones Guardar y Eliminar según la vista seleccionada en el dropdown.
 * Reglas (las vistas se comparten por empresa, pero el ownership para editar/borrar es del dueño):
 *  - Vista Base (sin value) → Guardar visible (crea nueva), Eliminar oculto.
 *  - Vista del sistema (id "default_*") → Guardar oculto, Eliminar oculto.
 *  - Vista propia (data-is-mine="1") → Guardar visible (sobrescribe), Eliminar visible.
 *  - Vista ajena (data-is-mine="0") → Guardar oculto, Eliminar oculto. Para modificar, usar "Nueva Vista".
 */
function toggleDeleteViewButton() {
    const btnDelete = document.getElementById('btnDeleteView');
    const btnSave = document.getElementById('btnSaveView');
    const dropdown = document.getElementById('savedViewsDropdown');
    if (!btnDelete && !btnSave) return;

    const val = dropdown ? dropdown.value : '';
    const opt = (dropdown && dropdown.selectedOptions && dropdown.selectedOptions[0]) || null;
    const isSystem = val && String(val).startsWith('default_');
    const isMine = opt ? opt.getAttribute('data-is-mine') === '1' : false;
    const hasSelection = !!val;

    // Delete solo si es vista propia.
    if (btnDelete) btnDelete.style.display = (hasSelection && !isSystem && isMine) ? '' : 'none';

    // Guardar (sobrescribir) solo tiene sentido si es propia. En Vista Base también lo dejamos visible
    // porque allí crea una nueva. En system y ajenas se oculta.
    if (btnSave) {
        const showSave = !hasSelection || (!isSystem && isMine);
        btnSave.style.display = showSave ? '' : 'none';
    }
}

function promptDeleteView() {
    const dropdown = document.getElementById('savedViewsDropdown');
    if (!dropdown || !dropdown.value) return;
    const viewId = dropdown.value;
    if (String(viewId).startsWith('default_')) {
        (window.rxnAlert || alert)('No se pueden eliminar las vistas del sistema.', 'warning', 'Atención');
        return;
    }
    const option = dropdown.options[dropdown.selectedIndex];
    const nombre = option ? option.getAttribute('data-nombre') || 'Vista sin nombre' : 'Vista sin nombre';

    const doDelete = () => {
        const fd = new FormData();
        fd.append('view_id', viewId);
        fetch('/rxn_live/eliminar-vista', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    // Limpiar sessionStorage del dataset (la vista borrada podía tener state cacheado)
                    try { sessionStorage.removeItem('rxn_live_volatile_<?= htmlspecialchars($datasetKey) ?>'); } catch(e) {}
                    // Redirigir al dataset limpio, sin view_id
                    const u = new URL(window.location.href);
                    u.searchParams.delete('view_id');
                    window.location.href = u.toString();
                } else {
                    (window.rxnAlert || alert)('No se pudo eliminar: ' + (res.message || 'error'), 'danger', 'Error');
                }
            })
            .catch(e => {
                console.error(e);
                (window.rxnAlert || alert)('Error de red al eliminar la vista.', 'danger', 'Error de red');
            });
    };

    const msg = `Se va a eliminar la vista "${nombre}". Esta acción no se puede deshacer.`;
    if (window.rxnConfirm) {
        window.rxnConfirm({
            title: 'Atención',
            message: msg,
            type: 'danger',
            okText: 'Eliminar',
            okClass: 'btn-danger',
            onConfirm: doDelete,
        });
    } else if (confirm(msg)) {
        doDelete();
    }
}

function saveCurrentView() {
    let dropdown = document.getElementById('savedViewsDropdown');
    if (!dropdown || !dropdown.value || String(dropdown.value).startsWith('default_')) {
        promptSaveView();
        return;
    }
    
    let viewId = dropdown.value;
    let option = dropdown.options[dropdown.selectedIndex];
    let nombre = option.getAttribute('data-nombre');
    
    let btn = document.querySelector('button[onclick="saveCurrentView()"]');
    let origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    let configJson = JSON.stringify(extractViewConfig());
    let fd = new FormData();
    fd.append('dataset', '<?= htmlspecialchars($datasetKey) ?>');
    fd.append('nombre', nombre);
    fd.append('config', configJson);
    fd.append('view_id', viewId);

    fetch('/rxn_live/guardar-vista', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                let u = new URL(window.location.href);
                u.searchParams.set('view_id', res.view_id);
                if (window.location.href === u.toString()) {
                    window.location.reload();
                } else {
                    window.location.href = u.toString();
                }
            } else {
                (window.rxnAlert || alert)("Error al sobrescribir: " + (res.message || 'Desconocido'), 'danger', 'No se pudo guardar la vista');
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        })
        .catch(e => {
            console.error(e);
            (window.rxnAlert || alert)("Error de red al sobrescribir la vista.", 'danger', 'Error de red');
            btn.disabled = false;
            btn.innerHTML = origHtml;
        });
}

function promptSaveView() {    
    // Create modal if it doesn't exist
    let modalEl = document.getElementById('saveViewModal');
    if (!modalEl) {
        modalEl = document.createElement('div');
        modalEl.className = 'modal fade';
        modalEl.id = 'saveViewModal';
        modalEl.setAttribute('tabindex', '-1');
        modalEl.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content bg-dark text-light border-secondary">
                    <div class="modal-header border-secondary border-opacity-50">
                        <h5 class="modal-title"><i class="bi bi-floppy me-2 text-primary"></i>Guardar Vista</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="saveViewModalAlert" class="alert d-none" role="alert"></div>
                        <p class="text-muted small">Guardará filtros actuales, armado de tabla dinámica y ordenamiento.</p>
                        <div class="mb-3">
                            <label class="form-label text-muted small">Nombre de la vista</label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="saveViewName" placeholder="Ej: Mis ventas del mes (Pendientes)">
                        </div>
                    </div>
                    <div class="modal-footer border-secondary border-opacity-50 pb-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary btn-sm" id="btnSaveViewConfirm">Guardar</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modalEl);
        viewSaveModalInstance = new bootstrap.Modal(modalEl);

        document.getElementById('btnSaveViewConfirm').addEventListener('click', function() {
            let nombre = document.getElementById('saveViewName').value.trim();
            if(!nombre) {
                document.getElementById('saveViewName').focus();
                return;
            }
            
            let btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

            let configJson = JSON.stringify(extractViewConfig());
            let fd = new FormData();
            fd.append('dataset', '<?= htmlspecialchars($datasetKey) ?>');
            fd.append('nombre', nombre);
            fd.append('config', configJson);

            fetch('/rxn_live/guardar-vista', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        let u = new URL(window.location.href);
                        u.searchParams.set('view_id', res.view_id);
                        if (window.location.href === u.toString()) {
                            window.location.reload();
                        } else {
                            window.location.href = u.toString();
                        }
                    } else {
                        let alertEl = document.getElementById('saveViewModalAlert');
                        alertEl.className = 'alert alert-danger py-2 small';
                        alertEl.innerText = "Error: " + (res.message || 'Desconocido');
                        alertEl.classList.remove('d-none');
                        btn.disabled = false;
                        btn.innerText = 'Guardar';
                    }
                })
                .catch(e => {
                    console.error(e);
                    let alertEl = document.getElementById('saveViewModalAlert');
                    alertEl.className = 'alert alert-danger py-2 small';
                    alertEl.innerText = "Error de red al guardar la vista.";
                    alertEl.classList.remove('d-none');
                    btn.disabled = false;
                    btn.innerText = 'Guardar';
                });
        });
    }
    
    document.getElementById('saveViewName').value = '';
    document.getElementById('saveViewModalAlert').classList.add('d-none');
    document.getElementById('btnSaveViewConfirm').disabled = false;
    document.getElementById('btnSaveViewConfirm').innerText = 'Guardar';
    
    viewSaveModalInstance.show();
    setTimeout(() => document.getElementById('saveViewName').focus(), 400);
}

function hydratePivotSlots(type, items) {
    const containerId = type === 'row' ? 'pivotContainerRow' : (type === 'col' ? 'pivotContainerCol' : 'pivotContainerVal');
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    
    items.forEach(item => {
        addPivotSlot(type);
        let lastWrapper = container.lastElementChild;
        let selField = lastWrapper.querySelector('.pivot-slot-field');
        if (selField) selField.value = item.field;
        
        handleFieldChange(selField, type, lastWrapper.querySelector('.pivot-slot-field').dataset.slotId);
        
        if (type === 'row' || type === 'col') {
            if (item.dateFmt) {
                let dateOpt = lastWrapper.querySelector('.pivot-slot-date');
                if (dateOpt) dateOpt.value = item.dateFmt;
            }
        } else {
            let selOp = lastWrapper.querySelector('.pivot-slot-op');
            if (selOp) selOp.value = item.op;
        }
    });
}

/**
 * Circuit breaker anti-loop: detecta cuando loadSelectedView() está disparando
 * redirects en cascada (síntoma: la UI "titila" tan rápido que el usuario no puede
 * abrir DevTools). Si detecta ≥5 redirects en <2s sobre el mismo dataset, corta
 * la cadena y muestra un banner diagnóstico con la info necesaria para entender la causa.
 *
 * Siempre activo (defensivo permanente). Con `?debug_loop=1` en URL se activa el modo
 * verbose: logs en consola en cada ciclo + historial completo de URLs + config dumpeado
 * en el banner.
 *
 * Retorna true si se debe ABORTAR el redirect (loop detectado), false si se puede continuar.
 */
function detectAndBreakRedirectLoop(nextUrl, viewId, config) {
    const datasetKey = '<?= htmlspecialchars($datasetKey) ?>';
    const storageKey = 'rxn_live_loop_detector_' + datasetKey;
    const THRESHOLD = 5;       // redirects
    const WINDOW_MS = 2000;    // en 2 segundos
    const now = Date.now();
    const isDebug = new URLSearchParams(window.location.search).get('debug_loop') === '1';

    let state = null;
    try { state = JSON.parse(sessionStorage.getItem(storageKey) || 'null'); }
    catch(e) { state = null; }

    if (!state || (now - state.firstAt) > WINDOW_MS) {
        // Reset: ventana nueva o primera vez
        state = { count: 1, firstAt: now, history: [window.location.href] };
    } else {
        state.count += 1;
        state.history.push(window.location.href);
        if (state.history.length > 10) state.history.shift();
    }

    if (isDebug) {
        try {
            console.warn('[RxnLive LoopDetector]', {
                count: state.count,
                elapsedMs: now - state.firstAt,
                nextUrl: nextUrl,
                currentUrl: window.location.href,
                viewId: viewId
            });
        } catch(e) {}
    }

    if (state.count >= THRESHOLD) {
        // Loop confirmado: limpiar estado que pudo causarlo y mostrar banner
        try { sessionStorage.removeItem(storageKey); } catch(e) {}
        try { sessionStorage.removeItem('rxn_live_volatile_' + datasetKey); } catch(e) {}
        try {
            console.error('[RxnLive] Loop de redirect detectado y cortado', {
                count: state.count,
                windowMs: WINDOW_MS,
                currentUrl: window.location.href,
                nextUrl: nextUrl,
                viewId: viewId,
                history: state.history,
                config: config
            });
        } catch(e) {}
        showLoopBrokenBanner({
            viewId: viewId,
            currentUrl: window.location.href,
            nextUrl: nextUrl,
            history: state.history,
            config: config,
            isDebug: isDebug
        });
        return true;
    }

    try { sessionStorage.setItem(storageKey, JSON.stringify(state)); } catch(e) {}
    return false;
}

/**
 * Banner persistente cuando el circuit breaker corta un loop.
 * Muestra diff exacto de URLs + (en modo debug) historial completo y config aplicado.
 * CTA obligatorio a Safe Mode para salir limpiamente.
 */
function showLoopBrokenBanner(info) {
    const existing = document.getElementById('rxnLoopBreakerBanner');
    if (existing) return;
    const banner = document.createElement('div');
    banner.id = 'rxnLoopBreakerBanner';
    banner.className = 'alert alert-danger mb-3';
    banner.setAttribute('role', 'alert');
    banner.style.border = '2px solid #b02a37';
    const safeUrl = `/rxn_live/dataset?dataset=<?= urlencode($datasetKey) ?>&safe_mode=1`;

    const escapeHtml = (s) => String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    let debugBlock = '';
    if (info.isDebug) {
        const histText = (info.history || []).map((u, i) => (i+1) + '. ' + u).join('\n');
        const cfgText = info.config ? JSON.stringify(info.config, null, 2) : '(no disponible)';
        debugBlock = `
            <details class="mt-2" open>
                <summary class="small"><strong>Debug info (debug_loop=1)</strong></summary>
                <div class="small mt-2">
                    <div><strong>Historial de URLs visitadas:</strong></div>
                    <pre class="bg-light p-2 mb-2" style="max-height:200px; overflow:auto; color:#000; font-size:11px;">${escapeHtml(histText)}</pre>
                    <div><strong>Config aplicado:</strong></div>
                    <pre class="bg-light p-2" style="max-height:200px; overflow:auto; color:#000; font-size:11px;">${escapeHtml(cfgText)}</pre>
                </div>
            </details>
        `;
    } else {
        debugBlock = `<div class="small mt-1 text-muted">Para ver historial de URLs y config aplicado, recargá con <code>?debug_loop=1</code> en la URL.</div>`;
    }

    banner.innerHTML = `
        <div class="d-flex align-items-start justify-content-between flex-wrap">
            <div style="flex:1; min-width:280px;">
                <h5 class="alert-heading mb-1"><i class="bi bi-slash-circle-fill me-2"></i>Loop de recarga detectado y frenado</h5>
                <p class="mb-1">La vista ${info.viewId ? '<code>' + escapeHtml(info.viewId) + '</code>' : 'seleccionada'} entró en un loop de redirects (≥5 recargas en &lt;2s). Se cortó automáticamente para que puedas usar el dataset.</p>
                <div class="small">
                    <div><strong>URL actual:</strong> <code style="word-break:break-all;">${escapeHtml(info.currentUrl || '-')}</code></div>
                    <div><strong>URL que iba a redirigir:</strong> <code style="word-break:break-all;">${escapeHtml(info.nextUrl || '-')}</code></div>
                </div>
                ${debugBlock}
            </div>
            <div class="ms-3 mt-2">
                <a href="${safeUrl}" class="btn btn-sm btn-outline-dark">
                    <i class="bi bi-shield-exclamation"></i> Abrir en Safe Mode
                </a>
            </div>
        </div>
    `;
    const container = document.querySelector('.container, main, body');
    if (container) container.insertBefore(banner, container.firstChild);
}

/**
 * Muestra un banner de fallback cuando applyViewConfig() explota.
 * Da al usuario un CTA claro para salir con Safe Mode sin que tenga que recordar la URL.
 */
function showViewConfigError(err, viewId) {
    try { console.error('[RxnLive] applyViewConfig falló:', err); } catch(e) {}
    const existing = document.getElementById('rxnViewConfigErrorBanner');
    if (existing) return; // Ya se mostró, no duplicar
    const banner = document.createElement('div');
    banner.id = 'rxnViewConfigErrorBanner';
    banner.className = 'alert alert-danger d-flex align-items-center justify-content-between mb-3';
    banner.setAttribute('role', 'alert');
    const safeUrl = `/rxn_live/dataset?dataset=<?= urlencode($datasetKey) ?>&safe_mode=1`;
    banner.innerHTML = `
        <div>
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>La vista seleccionada tiene un config corrupto.</strong>
            Se cargó una configuración vacía para evitar que la pantalla quede titilando.
            ${viewId ? ` <span class="text-muted small">(view_id: ${viewId})</span>` : ''}
        </div>
        <a href="${safeUrl}" class="btn btn-sm btn-outline-dark ms-3">
            <i class="bi bi-shield-exclamation"></i> Abrir en Safe Mode
        </a>
    `;
    const container = document.querySelector('.container, main, body');
    if (container) container.insertBefore(banner, container.firstChild);
}

function applyViewConfig(config, isVolatile = false) {
    // Guard: si config no es objeto, cargar vista base vacía.
    if (!config || typeof config !== 'object' || Array.isArray(config)) {
        config = {};
    }

    flatSortCol = (typeof config.flatSortCol === 'string' || config.flatSortCol === null) ? config.flatSortCol : null;
    flatSortAsc = config.flatSortAsc !== undefined ? !!config.flatSortAsc : true;

    if (config.flatFilters && typeof config.flatFilters === 'object' && !Array.isArray(config.flatFilters)) {
        flatFilters = config.flatFilters;
    } else {
        flatFilters = {};
    }

    if (config.flatDiscreteFilters && typeof config.flatDiscreteFilters === 'object' && !Array.isArray(config.flatDiscreteFilters)) {
        flatDiscreteFilters = config.flatDiscreteFilters;
    } else {
        flatDiscreteFilters = {};
    }

    if (typeof config.globalDateFormat === 'string' && config.globalDateFormat) {
        globalDateFormat = config.globalDateFormat;
        let dfSel = document.getElementById('globalDateFormatSelect');
        if (dfSel) dfSel.value = globalDateFormat;
    }

    if (Array.isArray(config.hiddenCols)) {
        hiddenCols = config.hiddenCols.filter(c => typeof c === 'string');
    } else {
        hiddenCols = [];
    }

    if (Array.isArray(config.orderedCols)) {
        orderedCols = config.orderedCols.filter(c => typeof c === 'string');
    } else {
        orderedCols = [];
    }

    // Hidratar groupByCols: capear a MAX_GROUP_LEVELS y deduplicar por las dudas.
    if (Array.isArray(config.groupByCols)) {
        let seenGroup = new Set();
        groupByCols = config.groupByCols
            .filter(c => typeof c === 'string')
            .filter(c => { if (seenGroup.has(c)) return false; seenGroup.add(c); return true; })
            .slice(0, MAX_GROUP_LEVELS);
    } else {
        groupByCols = [];
    }
    if (config.groupCollapseState && typeof config.groupCollapseState === 'object' && !Array.isArray(config.groupCollapseState)) {
        groupCollapseState = config.groupCollapseState;
    } else {
        groupCollapseState = {};
    }

    // Hidratar widths y wrap toggle (features nuevas — defensive: si el config viejo no las tiene, quedan en defaults).
    if (config.colWidths && typeof config.colWidths === 'object' && !Array.isArray(config.colWidths)) {
        // Defensive: filtrar a números positivos en rango razonable para evitar values corruptos.
        colWidths = {};
        for (let k in config.colWidths) {
            let w = parseInt(config.colWidths[k], 10);
            if (Number.isFinite(w) && w >= 40 && w <= 800) {
                colWidths[k] = w;
            }
        }
    } else {
        colWidths = {};
    }
    wrapText = !!config.wrapText;
    applyWrapBtnState();

    buildColumnSelector();
    
    if (config.chartVisible !== undefined) chartVisible = !!config.chartVisible;
    else chartVisible = true;
    
    if (config.tableVisible !== undefined) tableVisible = !!config.tableVisible;
    else tableVisible = true;
    
    let currentUrlObj = new URL(window.location.href);
    let stateChanged = false;
    
    // Solo forzar recargas por urlFilters en las vistas guardadas (no volátil)
    if (!isVolatile && config.urlFilters !== undefined &&
        config.urlFilters && typeof config.urlFilters === 'object' && !Array.isArray(config.urlFilters)) {
        let keysToDelete = [];
        for (let [k, v] of currentUrlObj.searchParams.entries()) {
            if (k.startsWith('f[') || k === 'b_query' || k === 'query' || k === 'estado' || k === 'razon_social') {
                keysToDelete.push(k);
            }
        }
        keysToDelete.forEach(k => {
            currentUrlObj.searchParams.delete(k);
            stateChanged = true;
        });

        for (let k in config.urlFilters) {
            // Rechazamos keys/values no-string — evita que un config corrupto inyecte objetos en la URL
            const val = config.urlFilters[k];
            if (typeof k !== 'string' || typeof val !== 'string') continue;
            if (currentUrlObj.searchParams.get(k) !== val) {
                currentUrlObj.searchParams.set(k, val);
                stateChanged = true;
            }
        }
    }
    
    let dropdown = document.getElementById('savedViewsDropdown');
    
    // Si estamos aplicando una configuración (no volatil) y la URL no la refleja, forzar ajuste
    if (!isVolatile && dropdown && dropdown.value) {
        if (currentUrlObj.searchParams.get('view_id') !== String(dropdown.value)) {
            currentUrlObj.searchParams.set('view_id', dropdown.value);
            currentUrlObj.searchParams.delete('page');
            stateChanged = true;
        }
    }
    
    if (stateChanged && typeof window.history.replaceState === 'function') {
        window.history.replaceState(null, '', currentUrlObj.toString());
    }
    
    if (config.chartConfig && typeof config.chartConfig === 'object' && !Array.isArray(config.chartConfig)) {
        chartConfig = Object.assign({}, chartConfig, config.chartConfig);
        populateChartSelectors();
        renderDynamicChart();
    } else {
        // Sin config guardado (o inválido): igualmente poblar selectors y dibujar con defaults
        populateChartSelectors();
        renderDynamicChart();
    }

    if (config.pivotOptions && typeof config.pivotOptions === 'object') {
        if (document.getElementById('pivotSortDesc')) document.getElementById('pivotSortDesc').checked = !!config.pivotOptions.sortDesc;
        if (document.getElementById('pivotRowTot')) document.getElementById('pivotRowTot').checked = !!config.pivotOptions.rowTot;
        if (document.getElementById('pivotColTot')) document.getElementById('pivotColTot').checked = !!config.pivotOptions.colTot;
    }

    if (config.pivotState && typeof config.pivotState === 'object') {
        const rows = Array.isArray(config.pivotState.rows) ? config.pivotState.rows : [];
        const cols = Array.isArray(config.pivotState.cols) ? config.pivotState.cols : [];
        const vals = Array.isArray(config.pivotState.vals) ? config.pivotState.vals : [];
        hydratePivotSlots('row', rows);
        hydratePivotSlots('col', cols);
        hydratePivotSlots('val', vals);
    }

    applyViewVisibility();
    
    // Forzar el re-render total de la tabla Plana para que regenere los Inputs de búsqueda locales y pinte los embudos según la URL.
    renderPlana();
    
    applyLocalFilters();

    if (config.tab_activo) {
        let btn = document.querySelector(`button[data-bs-target="${config.tab_activo}"]`);
        if (btn) {
            var tab = new bootstrap.Tab(btn);
            tab.show();
        }
    }
}

function loadSelectedView() {
    let dropdown = document.getElementById('savedViewsDropdown');

    // Toggle Eliminar según la vista seleccionada
    toggleDeleteViewButton();

    let currentUrl = new URL(window.location.href);
    if (currentUrl.searchParams.get('reset_view') == '1') {
        sessionStorage.removeItem('rxn_live_volatile_<?= htmlspecialchars($datasetKey) ?>');
        return; // Deja que el PHP y la recarga actúen
    }

    let volatileStateStr = sessionStorage.getItem('rxn_live_volatile_<?= htmlspecialchars($datasetKey) ?>');
    let vState = null;
    if (volatileStateStr) {
        try { vState = JSON.parse(volatileStateStr); } catch(e) {}
    }

    if (!dropdown || !dropdown.value || String(dropdown.value).startsWith('default_')) {
        if (vState && (!vState.view_id || String(vState.view_id).startsWith('default_') || vState.view_id === '')) {
            try {
                applyViewConfig(vState, true);
            } catch (err) {
                // vState corrupto en sessionStorage — limpiar y mostrar banner
                try { sessionStorage.removeItem('rxn_live_volatile_<?= htmlspecialchars($datasetKey) ?>'); } catch(e) {}
                showViewConfigError(err, null);
                renderPlana();
            }
            return;
        }
        
        // El volatile pertenece a otra vista → limpiar y renderizar con defaults
        // (ya estamos sin view_id en la URL, no hace falta redirigir)
        if (vState && !currentUrl.searchParams.has('view_id')) {
            sessionStorage.removeItem('rxn_live_volatile_<?= htmlspecialchars($datasetKey) ?>');
            // Solo renderizar con defaults — DOMContentLoaded ya cargó todo
            return;
        }
        
        // Solo redirigir si venimos de URL con view_id (para limpiar la URL)
        if (currentUrl.searchParams.has('view_id')) {
            let url = new URL(window.location.origin + window.location.pathname);
            if (currentUrl.searchParams.has('dataset')) {
                url.searchParams.set('dataset', currentUrl.searchParams.get('dataset'));
            }
            url.searchParams.set('reset_view', '1');
            if (!detectAndBreakRedirectLoop(url.toString(), null, null)) {
                window.location.href = url.toString();
            }
        }
        return;
    }

    // Si existe memoria volatil y pertenece A ESTA vista (coincide view_id), preferimos la memoria volatil
    if (vState && String(vState.view_id) === String(dropdown.value)) {
        try {
            applyViewConfig(vState, true);
        } catch (err) {
            // vState corrupto → limpiar sessionStorage y reintentar cargando desde data-config.
            // Si también falla, cae en el catch del bloque siguiente.
            try { sessionStorage.removeItem('rxn_live_volatile_<?= htmlspecialchars($datasetKey) ?>'); } catch(e) {}
            try {
                const fallbackCfg = JSON.parse(dropdown.options[dropdown.selectedIndex].getAttribute('data-config') || '{}');
                applyViewConfig(fallbackCfg, false);
            } catch (err2) {
                showViewConfigError(err2, dropdown.value);
                renderPlana();
            }
        }
        return; // Fin de la recarga. No forzamos un window.location.href para evitar loop infinito
    }

    // En su defecto (o fue cambio de id directo del Dropdown), arrancar cargando desde BD HTML
    try {
        let optConfig = dropdown.options[dropdown.selectedIndex].getAttribute('data-config');
        let config = JSON.parse(optConfig);
        
        // Antes de aplicar visualmente, chequear si el cambio de ID requiere recarga backend (por haber urlFilters diferentes)
        let urlTarget = new URL(window.location.href);
        let keysToDelete = [];
        for (let [k, v] of urlTarget.searchParams.entries()) {
             if (k.startsWith('f[') || k === 'b_query' || k === 'query' || k === 'estado' || k === 'razon_social') {
                 keysToDelete.push(k);
             }
        }
        keysToDelete.forEach(k => urlTarget.searchParams.delete(k));

        if (config.urlFilters) {
             for (let k in config.urlFilters) {
                  urlTarget.searchParams.set(k, config.urlFilters[k]);
             }
        }
        urlTarget.searchParams.delete('page');
        urlTarget.searchParams.set('view_id', dropdown.value);

        sessionStorage.removeItem('rxn_live_volatile_<?= htmlspecialchars($datasetKey) ?>');

        if (urlTarget.toString() !== window.location.href) {
            if (!detectAndBreakRedirectLoop(urlTarget.toString(), dropdown.value, config)) {
                window.location.href = urlTarget.toString();
            }
            return;
        }

        applyViewConfig(config, false);
    } catch (e) {
        // Vista con config corrupto: mostramos banner con CTA a Safe Mode en vez de dejar la UI muerta.
        showViewConfigError(e, dropdown ? dropdown.value : null);
        // Intentar render mínimo para que al menos se vean los datos base
        try { renderPlana(); } catch (e2) {}
    }
}

/**
 * Sizer dinámico del card de la tabla de RxnLive. Reemplaza el `max-height: 70vh`
 * inline que derramaba en datasets densos (ej: PDS con 45 filas).
 *
 * Approach: setear max-height al CARD (no al tab-pane interno). El card es
 * flex-column (ver <style> arriba), así que el tab-pane se acomoda entre
 * header y footer sin dejar hueco, aun cuando el chart-card del col vecino
 * fuerce al col-lg-8 a ser más alto. El chart-card también recibe el mismo
 * max-height para que el row no se estire más allá de lo disponible.
 *
 * Dos pases:
 *   1) Estimativo: maxHeight = innerHeight − card.top − BOTTOM_RESERVE.
 *   2) Correctivo (doble rAF): si el <html> todavía desborda el viewport
 *      (footer global de la app, copyrights, márgenes), descuenta ese
 *      overflow del maxHeight. Así nunca aparece scroll vertical del body.
 *
 * Se dispara en load, resize (debounced), shown.bs.tab, y cuando se togglea
 * chart/tabla (via el dispatch('resize') que ya hace applyViewVisibility).
 */
(function installRxnLivePaneSizer() {
    const BOTTOM_RESERVE = 12;
    const MIN_CARD_HEIGHT = 320;
    const SAFETY_MARGIN = 4;

    function getCards() {
        return {
            table: document.querySelector('#tableSectionCol > .card'),
            chart: document.querySelector('#chartSectionCol > .card'),
        };
    }

    function applyMaxHeight(height) {
        const { table, chart } = getCards();
        if (table) table.style.maxHeight = height + 'px';
        // Sincronizamos el chart para que el row no se estire más de lo que
        // el table-card soporta (por align-items:stretch de Bootstrap .row).
        if (chart) chart.style.maxHeight = height + 'px';
    }

    function sizeInitial() {
        const { table } = getCards();
        if (!table) return 0;
        const top = table.getBoundingClientRect().top;
        const estimate = window.innerHeight - top - BOTTOM_RESERVE;
        const finalH = Math.max(MIN_CARD_HEIGHT, Math.round(estimate));
        applyMaxHeight(finalH);
        return finalH;
    }

    function correctForBodyOverflow() {
        const { table } = getCards();
        if (!table) return;
        const html = document.documentElement;
        const overflow = html.scrollHeight - html.clientHeight;
        if (overflow <= 0) return;
        const current = parseFloat(table.style.maxHeight) || 0;
        const adjusted = Math.max(MIN_CARD_HEIGHT, Math.round(current - overflow - SAFETY_MARGIN));
        applyMaxHeight(adjusted);
    }

    function sizeAll() {
        sizeInitial();
        // Doble rAF: el primero deja al browser aplicar el maxHeight nuevo;
        // el segundo mide scrollHeight sobre el layout ya actualizado. Sin
        // esto, la corrección se calcula contra el layout previo.
        requestAnimationFrame(() => {
            requestAnimationFrame(correctForBodyOverflow);
        });
    }

    let resizeTimer = null;
    function scheduleSize() {
        if (resizeTimer) return;
        resizeTimer = window.setTimeout(() => {
            resizeTimer = null;
            sizeAll();
        }, 60);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', sizeAll);
    } else {
        sizeAll();
    }
    // Pase extra tras 'load' para capturar altos de imágenes / fuentes que
    // resuelven tarde y pueden alterar el scrollHeight.
    window.addEventListener('load', sizeAll);
    window.addEventListener('resize', scheduleSize);
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(t => {
        t.addEventListener('shown.bs.tab', sizeAll);
    });

    // Exponer para forzar re-sizing desde afuera (ej: tras renderizar pivot
    // o cargar filas asíncronas que cambien el layout interno).
    window.rxnLiveResizePanes = sizeAll;
})();

/**
 * Watchdog contra loops de resize. Algunos configs rotos de Chart.js pueden disparar
 * ResizeObserver en cascada que congela el event loop del browser. Si detectamos que
 * window.dispatchEvent(new Event('resize')) se dispara más de N veces por segundo,
 * suprimimos dispatches adicionales durante un rato y loggeamos.
 */
(function installResizeWatchdog() {
    if (!window.dispatchEvent) return;
    const THRESHOLD = 40;   // eventos en la ventana
    const WINDOW_MS = 1000; // tamaño de la ventana
    const SUPPRESS_MS = 3000; // tiempo de supresión tras disparar

    let timestamps = [];
    let suppressedUntil = 0;
    const original = window.dispatchEvent.bind(window);

    window.dispatchEvent = function(ev) {
        try {
            if (ev && ev.type === 'resize') {
                const now = Date.now();
                if (now < suppressedUntil) {
                    // Descartamos dispatches de resize mientras estamos en modo supresión
                    return true;
                }
                timestamps.push(now);
                // Mantener solo los últimos WINDOW_MS
                while (timestamps.length && timestamps[0] < now - WINDOW_MS) timestamps.shift();
                if (timestamps.length > THRESHOLD) {
                    suppressedUntil = now + SUPPRESS_MS;
                    timestamps = [];
                    try {
                        console.warn(`[RxnLive] Watchdog: ${THRESHOLD}+ resize dispatches en <${WINDOW_MS}ms, suprimiendo por ${SUPPRESS_MS}ms.`);
                    } catch(e) {}
                    return true;
                }
            }
        } catch (e) {
            // Nunca dejamos que el watchdog tire dispatches legítimos por un bug suyo
        }
        return original(ev);
    };
})();
</script>

<?php
$extraScripts = ob_get_clean();

require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
