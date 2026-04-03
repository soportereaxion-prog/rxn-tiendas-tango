<?php
$old = $old ?? [];
$categoria = $categoria ?? null;
$nombreValue = $old['nombre'] ?? ($categoria->nombre ?? '');
$slugValue = $old['slug'] ?? ($categoria->slug ?? '');
$descripcionValue = $old['descripcion_corta'] ?? ($categoria->descripcion_corta ?? '');
$ordenValue = $old['orden_visual'] ?? ($categoria->orden_visual ?? 0);
$imagenActual = $categoria->imagen_portada ?? null;
$activaChecked = $old !== []
    ? in_array($old['activa'] ?? '0', ['1', 'on'], true)
    : ((int) ($categoria->activa ?? 1) === 1);
$visibleStoreChecked = $old !== []
    ? in_array($old['visible_store'] ?? '0', ['1', 'on'], true)
    : ((int) ($categoria->visible_store ?? 1) === 1);
?>

<div class="rxn-form-section">
    <div class="rxn-form-section-title">Datos de la categoria</div>
    <div class="rxn-form-grid">
        <div class="rxn-form-span-6">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" class="form-control" id="nombre" name="nombre" required value="<?= htmlspecialchars((string) $nombreValue) ?>">
        </div>

        <div class="rxn-form-span-6">
            <label for="slug" class="form-label">Slug publico</label>
            <input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars((string) $slugValue) ?>">
            <div class="form-text">Si lo dejas vacio, se genera automaticamente a partir del nombre.</div>
        </div>

        <div class="rxn-form-span-8">
            <label for="descripcion_corta" class="form-label">Descripcion corta</label>
            <textarea class="form-control" id="descripcion_corta" name="descripcion_corta" rows="3"><?= htmlspecialchars((string) $descripcionValue) ?></textarea>
            <div class="form-text">Se usa como apoyo visual en el backoffice y en la portada del catalogo.</div>
        </div>

        <div class="rxn-form-span-4">
            <label for="orden_visual" class="form-label">Orden visual</label>
            <input type="number" min="0" step="1" class="form-control" id="orden_visual" name="orden_visual" value="<?= htmlspecialchars((string) $ordenValue) ?>">
            <div class="form-text">Menor numero, mayor prioridad en la grilla publica.</div>
        </div>
    </div>
</div>

<div class="rxn-form-section bg-light border rounded p-3 p-lg-4">
    <div class="rxn-form-section-title">Imagen de portada</div>
    <div class="row g-4 align-items-start">
        <div class="col-lg-4">
            <div class="border rounded-3  p-2 h-100">
                <?php if (!empty($imagenActual)): ?>
                    <img src="<?= htmlspecialchars((string) $imagenActual) ?>" alt="Portada categoria" class="img-fluid rounded-3 w-100" style="max-height: 220px; object-fit: cover;">
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center text-muted rounded-3" style="min-height: 180px; background: rgba(0, 0, 0, 0.04);">Sin imagen cargada</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-8">
            <label for="imagen_portada" class="form-label">Subir imagen</label>
            <input type="file" class="form-control" id="imagen_portada" name="imagen_portada" accept=".jpg,.jpeg,.png,.webp">
            <div class="form-text">Formatos permitidos: JPG, PNG o WEBP. La imagen es opcional.</div>
        </div>
    </div>
</div>

<div class="rxn-form-section">
    <div class="rxn-form-section-title">Publicacion</div>
    <div class="rxn-form-switches">
        <div class="rxn-form-switch-card">
            <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" role="switch" id="activa" name="activa" value="1" <?= $activaChecked ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="activa">Categoria activa</label>
                <div class="form-text mb-0">Permite mantenerla disponible para asignacion y uso general.</div>
            </div>
        </div>

        <div class="rxn-form-switch-card">
            <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" role="switch" id="visible_store" name="visible_store" value="1" <?= $visibleStoreChecked ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="visible_store">Visible en tienda</label>
                <div class="form-text mb-0">Si esta desactivada, no aparece en el bloque de categorias del store.</div>
            </div>
        </div>
    </div>
</div>
