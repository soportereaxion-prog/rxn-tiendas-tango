<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar Artículo - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: none; }
    </style>
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5" style="max-width: 700px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Modificar Artículo</h2>
            <a href="/rxnTiendasIA/public/mi-empresa/articulos" class="btn btn-outline-secondary">← Volver al Catálogo</a>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <form action="/rxnTiendasIA/public/mi-empresa/articulos/editar?id=<?= $articulo->id ?>" method="POST" enctype="multipart/form-data">
                    
                    <!-- Galería Multi-Imagen (Fase 5) -->
                    <?php $totalImagenes = count($imagenes ?? []); ?>
                    
                    <div class="mb-4 bg-light p-3 border rounded">
                        <label class="form-label text-dark fw-bold mb-3">Galería Visual (Máximo 5 imágenes)</label>
                        
                        <?php if ($totalImagenes > 0): ?>
                            <div class="row g-3 mb-3">
                                <?php foreach (($imagenes ?? []) as $img): ?>
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="position-relative border rounded p-1 text-center bg-white shadow-sm <?= $img['es_principal'] ? 'border-primary border-2' : '' ?>">
                                            <?php if ($img['es_principal']): ?>
                                                <span class="position-absolute top-0 start-0 translate-middle p-1 bg-primary border border-light rounded-circle fw-bold text-white shadow" title="Portada Principal" style="font-size: 0.8rem; z-index: 10;">⭐</span>
                                            <?php endif; ?>
                                            
                                            <div style="height: 120px; overflow: hidden;" class="rounded mb-2">
                                                <img src="/rxnTiendasIA/public<?= htmlspecialchars((string)$img['ruta']) ?>" class="w-100 h-100" style="object-fit: cover;">
                                            </div>
                                            
                                            <div class="d-flex justify-content-between gap-1">
                                                <?php if (!$img['es_principal']): ?>
                                                    <button type="submit" name="set_main_img" value="<?= $img['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2 fw-medium" title="Marcar como Principal">Tapa</button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-primary py-0 px-2 fw-medium disabled">Tapa</button>
                                                <?php endif; ?>
                                                <button type="submit" name="delete_img" value="<?= $img['id'] ?>" class="btn btn-sm btn-outline-danger py-0 px-2 fw-medium" title="Eliminar Imagen" onclick="return confirm('¿Seguro que deseas eliminar esta imagen local?');">Quitar</button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-secondary text-center">No hay imágenes propias nativas anexadas a este SKU.</div>
                        <?php endif; ?>

                        <?php if ($totalImagenes < 5): ?>
                            <div class="mt-3">
                                <label class="form-label text-muted d-block" style="font-size: 0.85rem;">Puedes adjuntar <strong><?= 5 - $totalImagenes ?></strong> fotos adicionales (.JPG / .PNG / .WEBP).</label>
                                <input type="file" class="form-control" name="imagenes[]" accept=".jpg,.jpeg,.png,.webp" multiple>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mt-3 mb-0"><small>⚠️ Has alcanzado el límite transaccional de 5 imágenes asociadas. Remueve una subida anterior para incorporar nuevas variantes.</small></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted">Código / SKU Ext</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars((string)$articulo->codigo_externo) ?>" disabled>
                        <small class="text-muted">Este campo es la llave remota con Tango Connect y no admite edición de usuario.</small>
                    </div>

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Descripción Principal (Nombre)</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars((string)$articulo->nombre) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción Adicional (Sinonimia)</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= htmlspecialchars((string)$articulo->descripcion) ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="precio" class="form-label">Precio Vigente Local (Histórico)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" class="form-control" id="precio" name="precio" value="<?= $articulo->precio !== null ? htmlspecialchars((string)$articulo->precio) : '' ?>">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="precio_lista_1" class="form-label text-primary fw-bold">Importe Lista 1</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">$</span>
                                <input type="number" step="0.01" class="form-control" id="precio_lista_1" name="precio_lista_1" value="<?= $articulo->precio_lista_1 !== null ? htmlspecialchars((string)$articulo->precio_lista_1) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="precio_lista_2" class="form-label text-success fw-bold">Importe Lista 2</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">$</span>
                                <input type="number" step="0.01" class="form-control" id="precio_lista_2" name="precio_lista_2" value="<?= $articulo->precio_lista_2 !== null ? htmlspecialchars((string)$articulo->precio_lista_2) : '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4 bg-light p-3 rounded border border-warning border-opacity-25">
                        <label class="form-label text-warning fw-bold mb-1">Stock Actual (Editable - Testing)</label>
                        <input type="number" step="0.01" class="form-control fw-bold text-dark bg-white" id="stock_actual" name="stock_actual" value="<?= $articulo->stock_actual !== null ? htmlspecialchars((string)$articulo->stock_actual) : '0' ?>" style="max-width: 200px;">
                        <div class="form-text text-muted mt-2"><small>⚠️ Habilitado transitoriamente para pruebas. Un Sync de Tango Connect pisará este valor local.</small></div>
                    </div>

                    <div class="mb-4 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" <?= $articulo->activo ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activo">Artículo Activo Comerciable</label>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary py-2 fw-bold">Guardar Modificaciones Locales</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
