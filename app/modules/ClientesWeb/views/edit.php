<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente Web - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: none; }
    </style>
</head>
<body>
    <div class="container mt-5 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Editar Cliente #<?= $cliente['id'] ?></h2>
                <p class="text-muted">Resolución comercial y actualización de datos.</p>
            </div>
            <a href="/rxnTiendasIA/public/mi-empresa/clientes" class="btn btn-outline-secondary">← Volver al Listado</a>
        </div>

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

        <div class="row">
            <div class="col-md-8">
                <!-- Formulario Principal -->
                <div class="card mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 text-primary">Datos del Cliente Web</h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="/rxnTiendasIA/public/mi-empresa/clientes/<?= $cliente['id'] ?>/editar" method="POST" id="formCliente">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Nombre</label>
                                    <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars((string)$cliente['nombre']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Apellido</label>
                                    <input type="text" name="apellido" class="form-control" value="<?= htmlspecialchars((string)$cliente['apellido']) ?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars((string)$cliente['email']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Teléfono</label>
                                    <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars((string)$cliente['telefono']) ?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Documento (CUIT/DNI)</label>
                                    <input type="text" name="documento" class="form-control" value="<?= htmlspecialchars((string)$cliente['documento']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Razón Social</label>
                                    <input type="text" name="razon_social" class="form-control" value="<?= htmlspecialchars((string)$cliente['razon_social']) ?>">
                                </div>
                            </div>

                            <hr>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Dirección</label>
                                    <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars((string)$cliente['direccion']) ?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Localidad</label>
                                    <input type="text" name="localidad" class="form-control" value="<?= htmlspecialchars((string)$cliente['localidad']) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Provincia</label>
                                    <input type="text" name="provincia" class="form-control" value="<?= htmlspecialchars((string)$cliente['provincia']) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Cógido Postal</label>
                                    <input type="text" name="codigo_postal" class="form-control" value="<?= htmlspecialchars((string)$cliente['codigo_postal']) ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="activo" id="activo" <?= $cliente['activo'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="activo">Cliente Activo</label>
                                </div>
                            </div>

                            <!-- Oculto para que el submit al mismo form mande lo de tango también o podamos aislarlo.
                                 Mejor dejamos todo en el form -->
                            
                            <hr class="mt-4 mb-4">
                            
                            <h5 class="text-info mb-3">Vínculo Comercial Tango</h5>
                            <p class="text-muted small">El código de cliente permite enlazar este usuario web con el ERP para despachar sus pedidos correctamente.</p>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-info">Código de Cliente Tango</label>
                                    <div class="input-group">
                                        <input type="text" name="codigo_tango" class="form-control border-info" placeholder="Ej: JUAN01" value="<?= htmlspecialchars((string)$cliente['codigo_tango']) ?>">
                                        <button type="submit" formaction="/rxnTiendasIA/public/mi-empresa/clientes/<?= $cliente['id'] ?>/validar-tango" class="btn btn-outline-info" title="Consumir API y validar existencia">🔍 Validar en Tango</button>
                                    </div>
                                    <small class="form-text text-muted">Asegúrate de guardar el registro si haces modificaciones.</small>
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary px-4 shadow-sm">💾 Guardar Cambios Locales</button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Panel Lateral de Resumen Técnico -->
                <div class="card shadow-sm border-0 bg-light">
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
                                    <span class="badge bg-secondary fw-normal"><?= $cliente['id_gva01_condicion_venta'] ?: 'N/d' ?></span>
                                </li>
                                <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0">
                                    <span>Lista Precios</span>
                                    <span class="badge bg-secondary fw-normal"><?= $cliente['id_gva10_lista_precios'] ?: 'N/d' ?></span>
                                </li>
                                <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0">
                                    <span>Vendedor</span>
                                    <span class="badge bg-secondary fw-normal"><?= $cliente['id_gva23_vendedor'] ?: 'N/d' ?></span>
                                </li>
                                <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0">
                                    <span>Transporte</span>
                                    <span class="badge bg-secondary fw-normal"><?= $cliente['id_gva24_transporte'] ?: 'N/d' ?></span>
                                </li>
                            </ul>

                            <!-- Botón de Envío de Órdenes Pendientes -->
                            <form action="/rxnTiendasIA/public/mi-empresa/clientes/<?= $cliente['id'] ?>/enviar-pendientes" method="POST" class="d-grid gap-2 mt-3" onsubmit="return confirm('¿Revisar y enviar todas las ventas web no sincronizadas pertenecientes a este cliente hacia el módulo de Tango Rest?');">
                                <button type="submit" class="btn btn-outline-success btn-sm font-weight-bold">
                                    🚚 Enviar Pendientes a Tango
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-warning py-2 mb-3">
                                <div class="fw-bold text-dark"><small>⚠️ NO VINCULADO</small></div>
                                <div style="font-size: 0.8rem;" class="mt-1">Los pedidos de este cliente no se podrán enviar al ERP hasta validar el Código Tango.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
