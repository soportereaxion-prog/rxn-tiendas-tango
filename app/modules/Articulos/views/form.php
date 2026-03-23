<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar Artículo - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: none; }
    </style>
</head>
<body>
    <div class="container mt-5" style="max-width: 700px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Modificar Artículo</h2>
            <a href="/rxnTiendasIA/public/mi-empresa/articulos" class="btn btn-outline-secondary">← Volver al Catálogo</a>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <form action="/rxnTiendasIA/public/mi-empresa/articulos/editar?id=<?= $articulo->id ?>" method="POST">
                    
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
                        <label for="precio" class="form-label">Precio Vigente Local</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" class="form-control" id="precio" name="precio" value="<?= $articulo->precio !== null ? htmlspecialchars((string)$articulo->precio) : '' ?>">
                        </div>
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
