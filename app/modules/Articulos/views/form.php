<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string) ($editTitle ?? 'Modificar Articulo')) ?> - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="rxn-page-shell">
    <?php
    $basePath = $basePath ?? '/rxnTiendasIA/public/mi-empresa/articulos';
    $moduleNotesKey = $moduleNotesKey ?? 'articulos';
    $moduleNotesLabel = $moduleNotesLabel ?? 'Articulos';
    $showCategories = $showCategories ?? true;
    ?>
    <div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell">
        <div class="rxn-module-header mb-4">
            <h2 class="mb-0"><?= htmlspecialchars((string) ($editTitle ?? 'Modificar Articulo')) ?></h2>
            <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> <?= htmlspecialchars((string) ($backLabel ?? 'Volver')) ?></a>
        </div>

        <?php require BASE_PATH . '/app/shared/views/components/module_notes_panel.php'; ?>

        <div class="card rxn-form-card">
            <div class="card-body p-4 p-lg-5">
                <form action="<?= htmlspecialchars($basePath) ?>/editar?id=<?= (int) $articulo->id ?>" method="POST" enctype="multipart/form-data">
                    <?php $totalImagenes = count($imagenes ?? []); ?>

                    <div class="rxn-form-section mb-4 bg-light p-3 p-lg-4 border rounded">
                        <label class="form-label text-dark fw-bold mb-3">Galeria Visual (Maximo 5 imagenes)</label>

                        <?php if ($totalImagenes > 0): ?>
                            <div class="row g-3 mb-3">
                                <?php foreach (($imagenes ?? []) as $img): ?>
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="position-relative border rounded p-1 text-center bg-white shadow-sm <?= $img['es_principal'] ? 'border-primary border-2' : '' ?>">
                                            <?php if ($img['es_principal']): ?>
                                                <span class="position-absolute top-0 start-0 translate-middle p-1 bg-primary border border-light rounded-circle fw-bold text-white shadow" title="Portada Principal" style="font-size: 0.8rem; z-index: 10;">*</span>
                                            <?php endif; ?>

                                            <div style="height: 120px; overflow: hidden;" class="rounded mb-2">
                                                <img src="/rxnTiendasIA/public<?= htmlspecialchars((string) $img['ruta']) ?>" class="w-100 h-100" style="object-fit: cover;">
                                            </div>

                                            <div class="d-flex justify-content-between gap-1">
                                                <?php if (!$img['es_principal']): ?>
                                                    <button type="submit" name="set_main_img" value="<?= (int) $img['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2 fw-medium" title="Marcar como principal">Tapa</button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-primary py-0 px-2 fw-medium disabled">Tapa</button>
                                                <?php endif; ?>
                                                <button type="submit" name="delete_img" value="<?= (int) $img['id'] ?>" class="btn btn-sm btn-outline-danger py-0 px-2 fw-medium" title="Eliminar imagen" data-rxn-confirm="¿Seguro que deseas eliminar esta imagen local?" data-confirm-type="danger">Quitar</button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-secondary text-center">No hay imagenes propias asociadas a este SKU.</div>
                        <?php endif; ?>

                        <?php if ($totalImagenes < 5): ?>
                            <div class="mt-3">
                                <label class="form-label text-muted d-block" style="font-size: 0.85rem;">Puedes adjuntar <strong><?= 5 - $totalImagenes ?></strong> fotos adicionales (.JPG / .PNG / .WEBP).</label>
                                <input type="file" class="form-control" name="imagenes[]" accept=".jpg,.jpeg,.png,.webp" multiple>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mt-3 mb-0"><small>Has alcanzado el limite de 5 imagenes asociadas.</small></div>
                        <?php endif; ?>
                    </div>

                    <div class="rxn-form-section">
                        <div class="rxn-form-section-title">Ficha comercial</div>
                        <div class="rxn-form-grid">
                            <div class="rxn-form-span-4">
                                <label class="form-label text-muted">Codigo / SKU Ext</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars((string) $articulo->codigo_externo) ?>" disabled>
                                <small class="text-muted">Este campo se usa como ancla remota o identificador operativo y no admite edicion.</small>
                            </div>

                            <div class="rxn-form-span-8">
                                <label for="nombre" class="form-label">Descripcion Principal (Nombre)</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars((string) $articulo->nombre) ?>" required>
                            </div>

                            <div class="rxn-form-span-12">
                                <label for="descripcion" class="form-label">Descripcion Adicional</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= htmlspecialchars((string) $articulo->descripcion) ?></textarea>
                            </div>

                            <?php if ($showCategories): ?>
                                <div class="rxn-form-span-6">
                                    <label for="categoria_id" class="form-label">Categoria comercial</label>
                                    <select class="form-select" id="categoria_id" name="categoria_id">
                                        <option value="">Sin categoria</option>
                                        <?php foreach (($categorias ?? []) as $categoria): ?>
                                            <option value="<?= (int) $categoria->id ?>" <?= (int) ($articulo->categoria_id ?? 0) === (int) $categoria->id ? 'selected' : '' ?>>
                                                <?= htmlspecialchars((string) $categoria->nombre) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">La asignacion queda guardada localmente y sobrevive a futuras integraciones.</div>
                                </div>
                            <?php endif; ?>

                            <div class="rxn-form-span-4">
                                <label for="precio" class="form-label">Precio Vigente Local</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" class="form-control" id="precio" name="precio" value="<?= $articulo->precio !== null ? htmlspecialchars((string) $articulo->precio) : '' ?>">
                                </div>
                            </div>

                            <div class="rxn-form-span-4">
                                <label for="precio_lista_1" class="form-label text-primary fw-bold">Importe Lista 1</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">$</span>
                                    <input type="number" step="0.01" class="form-control" id="precio_lista_1" name="precio_lista_1" value="<?= $articulo->precio_lista_1 !== null ? htmlspecialchars((string) $articulo->precio_lista_1) : '' ?>">
                                </div>
                            </div>

                            <div class="rxn-form-span-4">
                                <label for="precio_lista_2" class="form-label text-success fw-bold">Importe Lista 2</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">$</span>
                                    <input type="number" step="0.01" class="form-control" id="precio_lista_2" name="precio_lista_2" value="<?= $articulo->precio_lista_2 !== null ? htmlspecialchars((string) $articulo->precio_lista_2) : '' ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-section bg-light p-3 p-lg-4 rounded border border-warning border-opacity-25">
                        <div class="rxn-form-grid">
                            <div class="rxn-form-span-4">
                                <label class="form-label text-warning fw-bold mb-1">Stock Actual</label>
                                <input type="number" step="0.01" class="form-control fw-bold text-dark bg-white" id="stock_actual" name="stock_actual" value="<?= $articulo->stock_actual !== null ? htmlspecialchars((string) $articulo->stock_actual) : '0' ?>">
                                <div class="form-text text-muted mt-2"><small>Editable localmente. Si el circuito suma una integracion futura, este valor podra ser recalculado.</small></div>
                            </div>

                            <div class="rxn-form-span-8 d-flex align-items-end">
                                <div class="rxn-form-switch-card w-100">
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" <?= $articulo->activo ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="activo">Articulo activo comerciable</label>
                                        <div class="form-text mb-0">Controla si el articulo queda disponible para la operacion del entorno actual.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-actions">
                        <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-light">Cancelar</a>
                        <button type="submit" class="btn btn-primary py-2 fw-bold">Guardar Modificaciones Locales</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-confirm-modal.js"></script>
</body>
</html>
