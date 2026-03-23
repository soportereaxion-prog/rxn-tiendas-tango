<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Artículos - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: none; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Catálogo de Artículos</h2>
                <p class="text-muted">Inventario PUSH Operativo Multiempresa.</p>
            </div>
            <a href="/rxnTiendasIA/public/" class="btn btn-outline-secondary">← Volver al Panel</a>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <span class="badge bg-primary text-light fs-6 py-2 px-3">🛒 Total en BD Local: <?= count($articulos) ?></span>
                    <a href="/rxnTiendasIA/public/mi-empresa/sync/articulos" class="btn btn-warning btn-sm fw-bold shadow-sm" onclick="return confirm('¿Forzar una Petición de Sincronización API contra los servidores de Axoft Connect? Esta operación encolará un proceso Batch.');">⟲ Forzar Sync Connect</a>
                </div>
                
                <form action="/rxnTiendasIA/public/mi-empresa/articulos/eliminar-masivo" method="POST" onsubmit="return confirm('¿Confirma eliminar todos los elementos seleccionados permanentemente?');">
                    <div class="mb-3">
                        <button type="submit" class="btn btn-outline-danger btn-sm">🗑️ Eliminar Seleccionados</button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="check-all" onclick="document.querySelectorAll('.check-item').forEach(e => e.checked = this.checked);"></th>
                                    <th>Código / SKU</th>
                                    <th>Descripción</th>
                                    <th>Descripción Adicional</th>
                                    <th>Precio ($)</th>
                                    <th>Estado</th>
                                    <th>Última Sincro</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($articulos)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <div class="mb-2">⚠️</div>
                                            El Catálogo Maestro está vacío todavía.<br>
                                            <small>Haz clic en "Forzar Sync Connect" en el panel superior para inyectar datos reales.</small>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($articulos as $art): ?>
                                        <tr>
                                            <td><input type="checkbox" name="ids[]" value="<?= $art['id'] ?>" class="form-check-input check-item"></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars((string)$art['codigo_externo']) ?></span></td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars((string)$art['nombre']) ?></td>
                                            <td><small class="text-muted"><?= htmlspecialchars((string)($art['descripcion'] ?? '---')) ?></small></td>
                                            <td class="fw-semibold text-success">$<?= number_format((float)$art['precio'], 2, ',', '.') ?></td>
                                            <td>
                                                <?php if($art['activo']): ?>
                                                    <span class="badge bg-success bg-opacity-75">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger bg-opacity-75">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><small class="text-secondary"><?= htmlspecialchars((string)$art['fecha_ultima_sync']) ?></small></td>
                                            <td>
                                                <a href="/rxnTiendasIA/public/mi-empresa/articulos/editar?id=<?= $art['id'] ?>" class="btn btn-sm btn-outline-secondary py-0">✏️ Editar</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
