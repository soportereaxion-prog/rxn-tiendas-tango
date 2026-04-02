<?php
$editorConfig = [
    'documentKey' => (string) ($document['document_key'] ?? ''),
    'pageConfig' => $pageConfig,
    'objects' => $objects,
    'fonts' => $fonts,
    'variables' => $variables,
    'availableFonts' => $availableFonts,
    'sampleContext' => $sampleContext,
    'backgroundUrl' => $backgroundUrl,
];
$editorConfigJson = json_encode($editorConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$pageTitle = $document['label'] ?? 'Canvas de impresion';
ob_start();
?>
<style>
.print-editor-shell {
            max-width: 1680px;
        }

        .print-editor-layout {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr) 320px;
            gap: 1rem;
            align-items: start;
        }

        .print-editor-panel {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
        }

        .print-editor-panel .card-body {
            padding: 1rem;
        }

        .print-editor-section-title {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6c757d;
            margin-bottom: 0.75rem;
        }

        .print-editor-sheet-card {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 20px;
            background: linear-gradient(180deg, rgba(248,250,252,1), rgba(241,245,249,0.96));
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.5), 0 16px 36px rgba(15, 23, 42, 0.08);
        }

        .print-editor-sheet-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1rem 0;
        }

        .print-sheet-wrap {
            padding: 1rem;
            overflow: auto;
        }

        .print-sheet {
            position: relative;
            width: 820px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }

        .print-sheet::before {
            content: '';
            display: block;
            width: 100%;
            padding-top: 141.4285%;
        }

        .print-sheet.is-landscape::before {
            padding-top: 70.707%;
        }

        .print-sheet__stage {
            position: absolute;
            inset: 0;
            background-size: 100% 100%;
            background-position: center;
            background-repeat: no-repeat;
        }

        .print-sheet__stage.has-grid {
            background-image:
                linear-gradient(rgba(15, 23, 42, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, 0.05) 1px, transparent 1px),
                var(--print-sheet-background-image, none);
            background-size: var(--print-grid-size, 7.8px) var(--print-grid-size, 7.8px), var(--print-grid-size, 7.8px) var(--print-grid-size, 7.8px), 100% 100%;
            background-position: 0 0, 0 0, center;
        }

        .print-sheet__stage:not(.has-grid) {
            background-image: var(--print-sheet-background-image, none);
        }

        .print-object {
            position: absolute;
            user-select: none;
            cursor: move;
            transition: box-shadow 0.15s ease, outline-color 0.15s ease;
        }

        .print-object.is-selected {
            outline: 2px dashed rgba(13, 110, 253, 0.9);
            outline-offset: 1px;
            box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.12);
        }

        .print-object.is-line {
            cursor: move;
        }

        .print-object__inner {
            width: 100%;
            height: 100%;
        }

        .print-object-list {
            display: grid;
            gap: 0.45rem;
        }

        .print-object-list button {
            text-align: left;
        }

        .print-variable-group + .print-variable-group {
            margin-top: 0.9rem;
        }

        .print-variable-chip {
            display: inline-flex;
            width: 100%;
            justify-content: flex-start;
            align-items: center;
            gap: 0.45rem;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 12px;
            background: rgba(248,250,252,0.9);
            padding: 0.45rem 0.55rem;
            font-size: 0.85rem;
        }

        .print-background-preview {
            display: block;
            width: 100%;
            max-height: 180px;
            object-fit: contain;
            border-radius: 14px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: #fff;
        }

        .print-editor-version-list {
            display: grid;
            gap: 0.45rem;
        }

        .print-editor-version-item {
            padding: 0.55rem 0.7rem;
            border-radius: 12px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            background: rgba(248,250,252,0.92);
        }

        .print-editor-sticky-save {
            position: sticky;
            top: 1rem;
            z-index: 8;
        }

        @media (max-width: 1399.98px) {
            .print-editor-layout {
                grid-template-columns: 280px minmax(0, 1fr);
            }

            .print-editor-layout > :last-child {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 991.98px) {
            .print-editor-layout {
                grid-template-columns: 1fr;
            }

            .print-sheet {
                width: min(100%, 760px);
            }
        }
</style>
<?php
$extraHead = ob_get_clean();

ob_start();
?>

    <div class="container-fluid mt-4 mb-4 rxn-responsive-container print-editor-shell">
        <div class="rxn-module-header mb-3">
            <div>
                <h2 class="mb-1">Canvas de impresion - <?= htmlspecialchars((string) ($document['label'] ?? 'Formulario')) ?></h2>
                <p class="text-muted mb-0">Hoja A4 editable con fondo, fuentes, variables y objetos posicionados para unificar la mecanica documental de la plataforma.</p>
            </div>
            <div class="rxn-module-actions">
                
                <button type="submit" form="print-form-editor-form" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Guardar version</button>
                <a href="/rxnTiendasIA/public/mi-empresa/crm/presupuestos" class="btn btn-outline-secondary">Ir a Presupuestos</a>
                <a href="<?= htmlspecialchars((string) $basePath) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver a Formularios</a>
            </div>
        </div>

        <?php require BASE_PATH . '/app/shared/views/components/module_notes_panel.php'; ?>

        <?php $flash = \App\Core\Flash::get(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars((string) $flash['type']) ?> shadow-sm mb-3" role="alert">
                <?= htmlspecialchars((string) $flash['message']) ?>
            </div>
        <?php endif; ?>

        <form id="print-form-editor-form" action="<?= htmlspecialchars((string) $basePath) ?>/<?= rawurlencode((string) ($document['document_key'] ?? '')) ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="page_config_json" id="print-page-config-json">
            <input type="hidden" name="objects_json" id="print-objects-json">
            <input type="hidden" name="fonts_json" id="print-fonts-json">

            <div class="print-editor-layout">
                <div class="print-editor-panel">
                    <div class="card-body">
                        <div class="print-editor-section-title">Herramientas</div>
                        <div class="d-grid gap-2 mb-4">
                            <button type="button" class="btn btn-outline-primary" data-add-object="text"><i class="bi bi-type"></i> Agregar texto</button>
                            <button type="button" class="btn btn-outline-primary" data-add-object="variable"><i class="bi bi-braces"></i> Agregar variable</button>
                            <button type="button" class="btn btn-outline-primary" data-add-object="image"><i class="bi bi-image"></i> Agregar imagen</button>
                            <button type="button" class="btn btn-outline-primary" data-add-object="line"><i class="bi bi-slash-lg"></i> Agregar linea</button>
                            <button type="button" class="btn btn-outline-primary" data-add-object="rect"><i class="bi bi-square"></i> Agregar rectangulo</button>
                            <button type="button" class="btn btn-outline-danger" data-delete-object disabled><i class="bi bi-trash"></i> Eliminar objeto</button>
                        </div>

                        <div class="print-editor-section-title">Variables disponibles</div>
                        <input type="text" class="form-control form-control-sm mb-3" id="print-variable-search" placeholder="Buscar variable...">
                        <div id="print-variables-container">
                            <?php foreach (($variables ?? []) as $group): ?>
                                <div class="print-variable-group">
                                <div class="small fw-bold text-muted mb-2"><?= htmlspecialchars((string) ($group['group'] ?? 'Variables')) ?></div>
                                <div class="d-grid gap-2">
                                    <?php foreach (($group['items'] ?? []) as $item): ?>
                                        <button type="button" class="print-variable-chip" data-add-variable="<?= htmlspecialchars((string) ($item['source'] ?? '')) ?>">
                                            <i class="bi bi-braces text-primary"></i>
                                            <span><?= htmlspecialchars((string) ($item['label'] ?? ($item['source'] ?? 'Variable'))) ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="print-editor-section-title mt-4">Objetos del canvas</div>
                        <div class="print-object-list" data-object-list></div>
                    </div>
                </div>

                <div class="print-editor-sheet-card">
                                        <div class="print-editor-sheet-toolbar">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge text-bg-dark"><?= htmlspecialchars((string) ($document['document_key'] ?? '')) ?></span>
                            <span class="badge text-bg-light border">A4</span>
                            <span class="badge text-bg-light border">Version activa: <?= $activeVersion ? '#' . (int) ($activeVersion['version'] ?? 0) : 'sin guardar' ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-zoom="out" title="Alejar"><i class="bi bi-zoom-out"></i></button>
                            <span class="small fw-bold" id="print-zoom-label" style="min-width: 3rem; text-align: center;">100%</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-zoom="in" title="Acercar"><i class="bi bi-zoom-in"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-zoom="fit" title="Ajustar al ancho"><i class="bi bi-arrows-angle-contract"></i> Ajustar</button>
                        </div>
                    </div>

                    <div class="print-sheet-wrap">
                        <div class="print-sheet" data-print-sheet>
                            <div class="print-sheet__stage has-grid" data-print-sheet-stage></div>
                        </div>
                    </div>
                </div>

                <div class="print-editor-panel print-editor-sticky-save">
                    <div class="card-body">
                        <div class="print-editor-section-title">Pagina y fondo</div>
                        <div class="mb-3">
                            <label for="print-orientation" class="form-label">Orientacion</label>
                            <select class="form-select" id="print-orientation" data-page-prop="orientation">
                                <option value="portrait">Vertical</option>
                                <option value="landscape">Horizontal</option>
                            </select>
                        </div>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="print-grid-enabled" data-page-prop="grid_enabled">
                            <label class="form-check-label" for="print-grid-enabled">Mostrar grilla</label>
                        </div>
                        <div class="mb-3">
                            <label for="print-background-image" class="form-label">Imagen de fondo</label>
                            <input type="file" class="form-control" id="print-background-image" name="background_image" accept=".png,.jpg,.jpeg,.webp" data-background-input>
                            <div class="form-text">PNG, JPG o WEBP. Se guarda como asset del formulario.</div>
                        </div>
                        <div class="mb-3 form-check">
                            <input class="form-check-input" type="checkbox" id="print-clear-background" name="clear_background" value="1">
                            <label class="form-check-label" for="print-clear-background">Quitar fondo actual al guardar</label>
                        </div>
                        <div class="mb-3 <?= $backgroundUrl === '' ? 'd-none' : '' ?>" data-background-preview-wrap>
                            <div class="small text-muted fw-bold mb-2">Preview de fondo</div>
                            <img src="<?= htmlspecialchars($backgroundUrl) ?>" alt="Preview de fondo del formulario" class="print-background-preview" data-background-preview>
                        </div>

                        <div class="print-editor-section-title mt-4">Objeto seleccionado</div>
                        <div data-object-empty-state class="text-muted small mb-3">Selecciona un objeto del canvas para editar sus propiedades.</div>

                        <div data-object-properties class="d-none">
                            <div class="mb-2 small text-muted">Tipo: <strong data-selected-type>--</strong></div>
                            <div class="mb-3">
                                <label for="prop-content" class="form-label">Texto / contenido</label>
                                <textarea class="form-control" id="prop-content" rows="2" data-object-prop="content"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="prop-source" class="form-label">Variable</label>
                                <select class="form-select" id="prop-source" data-object-prop="source">
                                    <option value="">-- Seleccionar variable --</option>
                                    <?php foreach (($variables ?? []) as $group): ?>
                                        <optgroup label="<?= htmlspecialchars((string) ($group['group'] ?? 'Variables')) ?>">
                                            <?php foreach (($group['items'] ?? []) as $item): ?>
                                                <option value="<?= htmlspecialchars((string) ($item['source'] ?? '')) ?>"><?= htmlspecialchars((string) ($item['label'] ?? ($item['source'] ?? 'Variable'))) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label for="prop-x" class="form-label">X (mm)</label>
                                    <input type="number" step="0.1" class="form-control" id="prop-x" data-object-prop="x_mm">
                                </div>
                                <div class="col-6">
                                    <label for="prop-y" class="form-label">Y (mm)</label>
                                    <input type="number" step="0.1" class="form-control" id="prop-y" data-object-prop="y_mm">
                                </div>
                                <div class="col-6">
                                    <label for="prop-w" class="form-label">Ancho (mm)</label>
                                    <input type="number" step="0.1" class="form-control" id="prop-w" data-object-prop="w_mm">
                                </div>
                                <div class="col-6">
                                    <label for="prop-h" class="form-label">Alto (mm)</label>
                                    <input type="number" step="0.1" class="form-control" id="prop-h" data-object-prop="h_mm">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="prop-font-family" class="form-label">Fuente</label>
                                <select class="form-select" id="prop-font-family" data-style-prop="font_family">
                                    <?php foreach (($availableFonts ?? []) as $font): ?>
                                        <option value="<?= htmlspecialchars((string) ($font['value'] ?? '')) ?>"><?= htmlspecialchars((string) ($font['label'] ?? 'Fuente')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label for="prop-font-size" class="form-label">Tamano</label>
                                    <input type="number" step="0.1" class="form-control" id="prop-font-size" data-style-prop="font_size_pt">
                                </div>
                                <div class="col-6">
                                    <label for="prop-font-weight" class="form-label">Peso</label>
                                    <select class="form-select" id="prop-font-weight" data-style-prop="font_weight">
                                        <option value="400">Regular</option>
                                        <option value="700">Negrita</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label for="prop-color" class="form-label">Color</label>
                                    <input type="color" class="form-control form-control-color w-100" id="prop-color" data-style-prop="color">
                                </div>
                                <div class="col-6">
                                    <label for="prop-align" class="form-label">Alineacion</label>
                                    <select class="form-select" id="prop-align" data-style-prop="align">
                                        <option value="left">Izquierda</option>
                                        <option value="center">Centro</option>
                                        <option value="right">Derecha</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label for="prop-stroke" class="form-label">Trazo</label>
                                    <input type="color" class="form-control form-control-color w-100" id="prop-stroke" data-style-prop="stroke">
                                </div>
                                <div class="col-6">
                                    <label for="prop-fill" class="form-label">Relleno</label>
                                    <input type="color" class="form-control form-control-color w-100" id="prop-fill" data-style-prop="fill">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="prop-stroke-width" class="form-label">Espesor trazo (mm)</label>
                                <input type="number" step="0.05" class="form-control" id="prop-stroke-width" data-style-prop="stroke_width_mm">
                            </div>
                        </div>

                        <div class="print-editor-section-title mt-4">Versiones guardadas</div>
                        <div class="print-editor-version-list">
                            <?php if ($versions === []): ?>
                                <div class="print-editor-version-item small text-muted">Todavia no hay versiones guardadas para este formulario.</div>
                            <?php else: ?>
                                <?php foreach ($versions as $version): ?>
                                    <div class="print-editor-version-item small">
                                        <div class="fw-bold">Version #<?= (int) ($version['version'] ?? 0) ?></div>
                                        <div class="text-muted"><?= htmlspecialchars((string) ($version['created_at'] ?? '--')) ?></div>
                                        <?php if (!empty($version['notes'])): ?><div class="mt-1"><?= htmlspecialchars((string) $version['notes']) ?></div><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="print-editor-section-title mt-4">Notas de version</div>
                        <textarea class="form-control" name="version_notes" rows="3" placeholder="Ej: ajuste de header, cambio de fondo, nueva variable para cliente..." data-version-notes></textarea>
                    </div>
                </div>
            </div>
        </form>
    </div>



<?php
$content = ob_get_clean();

ob_start();
?>
    <script>
        window.printFormsEditorConfig = <?= $editorConfigJson ?: '{}' ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/rxnTiendasIA/public/js/print-forms-editor.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>



