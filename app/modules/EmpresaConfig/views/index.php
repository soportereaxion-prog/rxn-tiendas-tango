<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Empresa - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: none; }
    </style>
</head>
<body>
    <div class="container mt-5" style="max-width: 600px;">
        <div class="mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h2>Configuración de la Empresa</h2>
                <p class="text-muted">Gestión del entorno operativo actual.</p>
            </div>
            <span class="badge bg-info text-dark">Contexto Activo: ID #<?= htmlspecialchars((string) \App\Core\Context::getEmpresaId()) ?></span>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Configuración guardada exitosamente.</div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form action="/rxnTiendasIA/public/mi-empresa/configuracion" method="POST">
                    
                    <div class="mb-3">
                        <label for="nombre_fantasia" class="form-label">Nombre de Fantasía</label>
                        <input type="text" class="form-control" id="nombre_fantasia" name="nombre_fantasia"
                               value="<?= htmlspecialchars($old['nombre_fantasia'] ?? ($config->nombre_fantasia ?? '')) ?>">
                    </div>

                    <div class="mb-3">
                        <label for="email_contacto" class="form-label">Email de Contacto</label>
                        <input type="email" class="form-control" id="email_contacto" name="email_contacto"
                               value="<?= htmlspecialchars($old['email_contacto'] ?? ($config->email_contacto ?? '')) ?>">
                    </div>

                    <div class="mb-4">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="telefono" name="telefono"
                               value="<?= htmlspecialchars($old['telefono'] ?? ($config->telefono ?? '')) ?>">
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold mb-3 text-secondary">Integración Tango Connect</h5>

                    <div class="mb-3">
                        <label for="tango_api_url" class="form-label">URL de Connect (Base API)</label>
                        <input type="text" class="form-control" id="tango_api_url" name="tango_api_url"
                               value="<?= htmlspecialchars($old['tango_api_url'] ?? ($config->tango_api_url ?? '')) ?>">
                        <div class="form-text">Usualmente <code>https://nexosync.tangonexo.com</code> o similar.</div>
                    </div>

                    <div class="mb-3">
                        <label for="tango_connect_key" class="form-label">Número de Llave / Client Key</label>
                        <input type="text" class="form-control" id="tango_connect_key" name="tango_connect_key"
                               value="<?= htmlspecialchars($old['tango_connect_key'] ?? ($config->tango_connect_key ?? '')) ?>" placeholder="Ej: 000357/017">
                    </div>

                    <div class="mb-3">
                        <label for="tango_connect_company_id" class="form-label">ID de Empresa (Connect)</label>
                        <input type="text" class="form-control" id="tango_connect_company_id" name="tango_connect_company_id"
                               value="<?= htmlspecialchars((string)($old['tango_connect_company_id'] ?? ($config->tango_connect_company_id ?? ''))) ?>" placeholder="Ej: 1">
                    </div>

                    <div class="mb-4">
                        <label for="cantidad_articulos_sync" class="form-label">Cantidad de Artículos a Sincronizar (Por Lote)</label>
                        <input type="number" class="form-control" id="cantidad_articulos_sync" name="cantidad_articulos_sync" min="1"
                               value="<?= htmlspecialchars((string)($old['cantidad_articulos_sync'] ?? ($config->cantidad_articulos_sync ?? 50))) ?>">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="lista_precio_1" class="form-label">ID Lista de Precio 1</label>
                            <input type="text" class="form-control" id="lista_precio_1" name="lista_precio_1"
                                    value="<?= htmlspecialchars($old['lista_precio_1'] ?? ($config->lista_precio_1 ?? '')) ?>" placeholder="Ej: 1">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="lista_precio_2" class="form-label">ID Lista de Precio 2</label>
                            <input type="text" class="form-control" id="lista_precio_2" name="lista_precio_2"
                                    value="<?= htmlspecialchars($old['lista_precio_2'] ?? ($config->lista_precio_2 ?? '')) ?>" placeholder="Ej: 2">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="tango_connect_token" class="form-label">Token de Acceso</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="tango_connect_token" name="tango_connect_token"
                                   value="<?= htmlspecialchars($old['tango_connect_token'] ?? ($config->tango_connect_token ?? '')) ?>">
                            <button class="btn btn-outline-secondary" type="button" id="toggleTokenEye" title="Mostrar/Ocultar">
                                👁️
                            </button>
                        </div>
                    </div>

                    <script>
                    document.getElementById('toggleTokenEye').addEventListener('click', function() {
                        const input = document.getElementById('tango_connect_token');
                        if (input.type === 'password') {
                            input.type = 'text';
                            this.innerText = '🙈';
                        } else {
                            input.type = 'password';
                            this.innerText = '👁️';
                        }
                    });
                    </script>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="/rxnTiendasIA/public/" class="btn btn-light">Volver a Inicio</a>
                        <button type="submit" class="btn btn-primary px-4">Guardar Configuración</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</body>
</html>
