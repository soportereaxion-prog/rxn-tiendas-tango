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

        <?php $flash = \App\Core\Flash::get(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars((string)$flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                <strong><?= $flash['type'] === 'success' ? '✔' : '⚠️' ?></strong> <?= htmlspecialchars((string)$flash['message']) ?>
                
                <?php if (!empty($flash['stats'])): ?>
                    <ul class="mb-0 mt-2 fs-6">
                        <li>Recibidos en Capa de Red: <b class="text-primary"><?= (int)($flash['stats']['recibidos'] ?? 0) ?></b></li>
                        <li>Nuevos Localmente: <b class="text-success"><?= (int)($flash['stats']['insertados'] ?? 0) ?></b></li>
                        <li>Actualizados: <b class="text-info"><?= (int)($flash['stats']['actualizados'] ?? 0) ?></b></li>
                        <li>Omitidos (Limpieza Mapper): <b class="text-secondary"><?= (int)($flash['stats']['omitidos'] ?? 0) ?></b></li>
                    </ul>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <span class="badge bg-primary text-light fs-6 py-2 px-3">🛒 Total en BD Local: <?= count($articulos) ?></span>
                    <div class="d-flex gap-2">
                        <form action="/rxnTiendasIA/public/mi-empresa/articulos/purgar" method="POST" class="d-inline" onsubmit="return confirm('⚠️ ATENCIÓN: Esta acción purgará ABSOLUTAMENTE TODO EL CATÁLOGO de tu empresa. Deberás volver a sincronizar. ¿Deseas continuar?');">
                            <button type="submit" class="btn btn-danger btn-sm fw-bold shadow-sm">🗑️ Purgar Todo</button>
                        </form>
                        <a href="/rxnTiendasIA/public/mi-empresa/sync/precios" class="btn btn-outline-success btn-sm fw-bold shadow-sm" onclick="return confirm('¿Forzar una Petición de Sincronización de PRECIOS (Process 20091)? Esta operación sobre escribirá los precios vigentes según las Listas Configuradas.');">⟲ Sync Precios (L1/L2)</a>
                        <a href="/rxnTiendasIA/public/mi-empresa/sync/articulos" class="btn btn-warning btn-sm fw-bold shadow-sm" onclick="return confirm('¿Forzar Sincronización del MAESTRO DE ARTÍCULOS (Process 87)? Esta operación puede demorar según tu cuota.');">⟲ Sync Artículos</a>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <form action="/rxnTiendasIA/public/mi-empresa/articulos/eliminar-masivo" method="POST" id="formEliminarMasivo" class="d-inline" onsubmit="return confirm('¿Confirma eliminar todos los elementos seleccionados permanentemente?');">
                        <button type="submit" class="btn btn-outline-danger btn-sm">🗑️ Eliminar Seleccionados</button>
                    </form>
                    
                    <form action="/rxnTiendasIA/public/mi-empresa/articulos" method="GET" class="d-flex" style="width: 300px;">
                        <input type="text" name="search" class="form-control form-control-sm border-info me-2" placeholder="🔎 Buscar por código o desc..." value="<?= htmlspecialchars((string)$search) ?>">
                        <button type="submit" class="btn btn-info btn-sm text-white">Buscar</button>
                    </form>
                </div>

                <form action="/rxnTiendasIA/public/mi-empresa/articulos/eliminar-masivo" method="POST" id="hiddenFormDelete">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-sm" style="font-size: 0.9rem;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="check-all" onclick="document.querySelectorAll('.check-item').forEach(e => e.checked = this.checked);"></th>
                                    <th>Código / SKU</th>
                                    <th>Descripción</th>
                                    <th>Descripción Adicional</th>
                                    <th class="text-nowrap">P. L1 ($)</th>
                                    <th class="text-nowrap">P. L2 ($)</th>
                                    <th>Estado</th>
                                    <th>Última Sincro</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($articulos)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-5 text-muted">
                                            <div class="mb-2">⚠️</div>
                                            El Catálogo Maestro está vacío todavía o no hay coincidencias.<br>
                                            <small>Haz clic en "Sync Artículos" para inyectar datos reales.</small>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($articulos as $art): ?>
                                        <tr>
                                            <td><input type="checkbox" name="ids[]" form="formEliminarMasivo" value="<?= $art['id'] ?>" class="form-check-input check-item"></td>
                                            <td class="text-nowrap"><span class="badge bg-secondary"><?= htmlspecialchars((string)$art['codigo_externo']) ?></span></td>
                                            <td class="fw-bold text-dark text-wrap" style="max-width: 250px;"><?= htmlspecialchars((string)$art['nombre']) ?></td>
                                            <td class="text-wrap" style="max-width: 200px;"><small class="text-muted"><?= htmlspecialchars((string)($art['descripcion'] ?? '---')) ?></small></td>
                                            <td class="fw-semibold text-primary text-nowrap">$<?= $art['precio_lista_1'] !== null ? number_format((float)$art['precio_lista_1'], 2, ',', '.') : '--' ?></td>
                                            <td class="fw-semibold text-success text-nowrap">$<?= $art['precio_lista_2'] !== null ? number_format((float)$art['precio_lista_2'], 2, ',', '.') : '--' ?></td>
                                            <td>
                                                <?php if($art['activo']): ?>
                                                    <span class="badge bg-success bg-opacity-75">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger bg-opacity-75">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-nowrap"><small class="text-secondary"><?= htmlspecialchars((string)$art['fecha_ultima_sync']) ?></small></td>
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

                <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center pagination-sm">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode((string)$search) ?>">Anterior</a>
                        </li>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode((string)$search) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode((string)$search) ?>">Siguiente</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>

            </div>
        </div>
    </div>
</body>
</html>
