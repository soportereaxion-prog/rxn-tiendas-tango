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
                <form action="/rxnTiendasIA/public/mi-empresa/configuracion" method="POST" enctype="multipart/form-data">
                
                    <div class="mb-4 bg-light p-3 rounded border">
                        <label class="form-label text-secondary fw-bold mb-1">Tu Enlace Comercial (Slug)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted border-end-0">/rxnTiendasIA/public/</span>
                            <input type="text" class="form-control bg-white border-start-0 ps-0 fw-bold" value="<?= htmlspecialchars((string)($empresa->slug ?? 'Sin Slug Generado')) ?>" readonly disabled>
                        </div>
                        <div class="form-text mt-2"><small>Este slug define la URL pública inicial de la tienda. Puedes comunicárselo a tus clientes.</small></div>
                    </div>
                    
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
                    <h5 class="fw-bold mb-3 text-secondary">Identidad Visual Corporativa</h5>

                    <div class="mb-4 row align-items-center bg-light p-3 rounded border">
                        <div class="col-md-3 text-center mb-3 mb-md-0">
                            <?php if(!empty($config->imagen_default_producto)): ?>
                                <img src="/rxnTiendasIA/public<?= htmlspecialchars((string)$config->imagen_default_producto) ?>" alt="Fallback Empresa" class="img-thumbnail" style="max-height: 120px; object-fit: cover;">
                            <?php else: ?>
                                <img src="/rxnTiendasIA/public/assets/img/producto-default.png" alt="Fallback Genérico" class="img-thumbnail opacity-50" style="max-height: 120px; object-fit: contain;">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label fw-bold text-dark">Imagen de Producto por Defecto</label>
                            <input type="file" class="form-control mb-2" name="imagen_default" accept=".jpg,.jpeg,.png,.webp">
                            <div class="form-text">Si un artículo no posee imágenes propias cargadas, el sistema público de tu Tienda exhibirá automáticamente esta imagen oficial como marcador de posición.</div>
                        </div>
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
                        <div class="col-md-4 mb-3">
                            <label for="lista_precio_1" class="form-label">Lista de Precio 1</label>
                            <input type="text" class="form-control" id="lista_precio_1" name="lista_precio_1"
                                    value="<?= htmlspecialchars($old['lista_precio_1'] ?? ($config->lista_precio_1 ?? '')) ?>" placeholder="Ej: 1">
                        </div>
                        <div class="col-md-4 mb-4">
                            <label for="lista_precio_2" class="form-label">Lista de Precio 2</label>
                            <input type="text" class="form-control" id="lista_precio_2" name="lista_precio_2"
                                    value="<?= htmlspecialchars($old['lista_precio_2'] ?? ($config->lista_precio_2 ?? '')) ?>" placeholder="Ej: 2">
                        </div>
                        <div class="col-md-4 mb-4">
                            <label for="deposito_codigo" class="form-label text-danger fw-bold">ID Depósito (Connect)</label>
                            <input type="text" class="form-control border-danger" id="deposito_codigo" name="deposito_codigo" maxlength="2"
                                    value="<?= htmlspecialchars($old['deposito_codigo'] ?? ($config->deposito_codigo ?? '')) ?>" placeholder="Ej: 1">
                            <div class="form-text text-danger" style="font-size: 0.8rem;">⚠️ Usá el ID numérico de Tango (ID_STA22), NO el código alfanumérico (00). Ej: 1 para Depo Central.</div>
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

                    <hr class="my-4">
                    <h5 class="fw-bold mb-3 text-secondary">Transmisión de Correo Electrónico (SMTP)</h5>
                    <div class="alert alert-info border-0 rounded-3 mb-4 shadow-sm">
                        <small>💡 <strong>Fallback Automático:</strong> Si no configurás SMTP propio, el sistema utilizará el SMTP master de RXN de forma totalmente transparente para que nunca te quedes sin servicio.</small>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" role="switch" id="usa_smtp_propio" name="usa_smtp_propio" value="1" <?= (!empty($config->usa_smtp_propio) && $config->usa_smtp_propio == 1) ? 'checked' : '' ?> onchange="document.getElementById('smtpContainer').style.display = this.checked ? 'block' : 'none'">
                        <label class="form-check-label fw-bold" for="usa_smtp_propio">Utilizar Servidor de Correo Propio</label>
                    </div>

                    <div id="smtpContainer" style="display: <?= (!empty($config->usa_smtp_propio) && $config->usa_smtp_propio == 1) ? 'block' : 'none' ?>;">
                        <div class="row bg-light rounded border p-3 mx-0 mb-4">
                            <div class="col-md-8 mb-3">
                                <label for="smtp_host" class="form-label text-secondary small fw-medium">Servidor (Host)</label>
                                <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                                        value="<?= htmlspecialchars($old['smtp_host'] ?? ($config->smtp_host ?? '')) ?>" placeholder="Ej: smtp.gmail.com">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="smtp_port" class="form-label text-secondary small fw-medium">Puerto</label>
                                <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                                        value="<?= htmlspecialchars((string)($old['smtp_port'] ?? ($config->smtp_port ?? '587'))) ?>" placeholder="587">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="smtp_user" class="form-label text-secondary small fw-medium">Usuario SMTP</label>
                                <input type="text" class="form-control" id="smtp_user" name="smtp_user"
                                        value="<?= htmlspecialchars($old['smtp_user'] ?? ($config->smtp_user ?? '')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="smtp_pass" class="form-label text-secondary small fw-medium">Contraseña SMTP</label>
                                <input type="password" class="form-control" id="smtp_pass" name="smtp_pass" autocomplete="new-password">
                                <div class="form-text"><small><?= !empty($config->smtp_pass) ? 'Guardada. (Completar solo si se desea modificar)' : 'Secreta. Quedará encriptada.' ?></small></div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="smtp_secure" class="form-label text-secondary small fw-medium">Seguridad</label>
                                <select class="form-select" id="smtp_secure" name="smtp_secure">
                                    <option value="" <?= empty($config->smtp_secure) ? 'selected' : '' ?>>Ninguna</option>
                                    <option value="tls" <?= (!empty($config->smtp_secure) && $config->smtp_secure == 'tls') ? 'selected' : '' ?>>TLS</option>
                                    <option value="ssl" <?= (!empty($config->smtp_secure) && $config->smtp_secure == 'ssl') ? 'selected' : '' ?>>SSL</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="smtp_from_email" class="form-label text-secondary small fw-medium">De: Correo Electrónico</label>
                                <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email"
                                        value="<?= htmlspecialchars($old['smtp_from_email'] ?? ($config->smtp_from_email ?? '')) ?>" placeholder="ventas@mitienda.com">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="smtp_from_name" class="form-label text-secondary small fw-medium">De: Nombre</label>
                                <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name"
                                        value="<?= htmlspecialchars($old['smtp_from_name'] ?? ($config->smtp_from_name ?? '')) ?>" placeholder="Mi Tienda SA">
                            </div>
                        </div>
                    </div>

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
