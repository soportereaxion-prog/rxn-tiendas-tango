<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Empresa - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: var(--bg-color, #f8f9fa); }
        .card { border-radius: 12px; border: 1px solid rgba(0,0,0,0.05); }
        .form-label { font-size: 0.9rem; font-weight: 600; color: #495057; }
    </style>
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body>
    <div class="container-xl mt-5 mb-5">
        <div class="mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold mb-1">Configuración de la Empresa</h2>
                <p class="text-muted mb-0">Gestión del entorno operativo actual.</p>
            </div>
            <div>
                <span class="badge bg-info text-dark me-2">Contexto Activo: ID #<?= htmlspecialchars((string) \App\Core\Context::getEmpresaId()) ?></span>
                <a href="/rxnTiendasIA/public/" class="btn btn-outline-secondary btn-sm">Volver al Módulo</a>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success shadow-sm rounded-3 border-0 border-start border-4 border-success">Configuración guardada exitosamente.</div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger shadow-sm rounded-3 border-0 border-start border-4 border-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="/rxnTiendasIA/public/mi-empresa/configuracion" method="POST" enctype="multipart/form-data">

            <!-- 1. DATOS GENERALES -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h5 class="fw-bold text-primary mb-0">1. Datos Generales</h5>
                </div>
                <div class="card-body">
                    <div class="row gx-4 gy-3">
                        <div class="col-12">
                            <label class="form-label text-secondary mb-1">Tu Enlace Comercial (Slug)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0">/rxnTiendasIA/public/</span>
                                <input type="text" class="form-control bg-light border-start-0 ps-0 fw-bold text-primary" value="<?= htmlspecialchars((string)($empresa->slug ?? 'Sin Slug Generado')) ?>" readonly disabled>
                            </div>
                            <div class="form-text"><small>Este slug define la URL pública inicial de la tienda. Puedes comunicárselo a tus clientes.</small></div>
                        </div>

                        <div class="col-md-6">
                            <label for="nombre_fantasia" class="form-label">Nombre de Fantasía</label>
                            <input type="text" class="form-control" id="nombre_fantasia" name="nombre_fantasia"
                                   value="<?= htmlspecialchars($old['nombre_fantasia'] ?? ($config->nombre_fantasia ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="email_contacto" class="form-label">Email de Contacto</label>
                            <input type="email" class="form-control" id="email_contacto" name="email_contacto"
                                   value="<?= htmlspecialchars($old['email_contacto'] ?? ($config->email_contacto ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="telefono" class="form-label">Teléfono / Celular</label>
                            <input type="text" class="form-control" id="telefono" name="telefono"
                                   value="<?= htmlspecialchars($old['telefono'] ?? ($config->telefono ?? '')) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. IDENTIDAD DE MARCA B2C -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h5 class="fw-bold text-primary mb-0">2. Identidad de Marca B2C (Store Front)</h5>
                </div>
                <div class="card-body">
                    <div class="row gx-4 gy-4">
                        <div class="col-md-6">
                            <label class="form-label">Logo de Cabecera</label>
                            <input type="file" class="form-control" name="logo" accept=".jpg,.jpeg,.png,.svg,.webp">
                            <?php if(!empty($empresa->logo_url)): ?>
                                <div class="mt-2 p-2 bg-light border rounded text-center" style="max-width: 200px;">
                                    <img src="/rxnTiendasIA/public<?= htmlspecialchars((string)$empresa->logo_url) ?>" style="max-height: 40px; object-fit: contain;">
                                </div>
                            <?php endif; ?>
                            <div class="form-text"><small>Se recomienda formato PNG transparente o SVG.</small></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ícono del Navegador (Favicon)</label>
                            <input type="file" class="form-control" name="favicon" accept=".ico,.png,.svg">
                            <?php if(!empty($empresa->favicon_url)): ?>
                                <div class="mt-2 p-2 bg-light border rounded d-inline-block text-center">
                                    <img src="/rxnTiendasIA/public<?= htmlspecialchars((string)$empresa->favicon_url) ?>" height="24">
                                </div>
                            <?php endif; ?>
                            <div class="form-text"><small>Aparecerá en la pestaña del navegador web.</small></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Color Primario</label>
                            <div class="input-group">
                                <span class="input-group-text p-1 bg-white">
                                    <input type="color" class="form-control form-control-color border-0 p-0 m-0" name="color_primary" value="<?= htmlspecialchars((string)($empresa->color_primary ?? '#000000')) ?>" title="Elegir color principal" style="width: 30px; height: 30px;">
                                </span>
                                <input type="text" class="form-control" value="<?= htmlspecialchars((string)($empresa->color_primary ?? '#000000')) ?>" readonly disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Color Secundario</label>
                            <div class="input-group">
                                <span class="input-group-text p-1 bg-white">
                                    <input type="color" class="form-control form-control-color border-0 p-0 m-0" name="color_secondary" value="<?= htmlspecialchars((string)($empresa->color_secondary ?? '#6c757d')) ?>" title="Elegir color de acento" style="width: 30px; height: 30px;">
                                </span>
                                <input type="text" class="form-control" value="<?= htmlspecialchars((string)($empresa->color_secondary ?? '#6c757d')) ?>" readonly disabled>
                            </div>
                        </div>

                        <div class="col-12 mt-2">
                            <h6 class="fw-bold mb-3 border-bottom pb-2 text-secondary">Datos del Footer (Públicos)</h6>
                            <div class="row gx-4 gy-3">
                                <div class="col-md-12">
                                    <label class="form-label">Texto de Presentación Breve</label>
                                    <textarea class="form-control" name="footer_text" rows="2" placeholder="Somos la tienda #1 en distribución..."><?= htmlspecialchars((string)($empresa->footer_text ?? '')) ?></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Dirección Física</label>
                                    <input type="text" class="form-control" name="footer_address" placeholder="Av Siempreviva 123" value="<?= htmlspecialchars((string)($empresa->footer_address ?? '')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">WhatsApp / Teléfono</label>
                                    <input type="text" class="form-control" name="footer_phone" placeholder="+54 9 11 0000-0000" value="<?= htmlspecialchars((string)($empresa->footer_phone ?? '')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Instagram URL</label>
                                    <input type="url" class="form-control" name="footer_socials" placeholder="https://instagram.com/mitienda" value="<?= htmlspecialchars((string)($empresa->footer_socials ?? '')) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. IDENTIDAD VISUAL CORPORATIVA -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h5 class="fw-bold text-primary mb-0">3. Identidad Visual Corporativa</h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-9 col-lg-10 order-2 order-md-1">
                            <label class="form-label">Imagen de Producto por Defecto</label>
                            <input type="file" class="form-control mb-2" name="imagen_default" accept=".jpg,.jpeg,.png,.webp">
                            <div class="form-text"><small>Si un artículo de Tango no posee imágenes sincronizadas en el sistema público, se exhibirá automáticamente este placeholder visual.</small></div>
                        </div>
                        <div class="col-md-3 col-lg-2 order-1 order-md-2 text-center mb-3 mb-md-0">
                            <?php if(!empty($config->imagen_default_producto)): ?>
                                <img src="/rxnTiendasIA/public<?= htmlspecialchars((string)$config->imagen_default_producto) ?>" alt="Fallback Empresa" class="img-thumbnail rounded-3 shadow-sm" style="max-height: 100px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-light border rounded-3 d-flex align-items-center justify-content-center mx-auto shadow-sm" style="height: 100px; width: 100px; border-style: dashed !important;">
                                    <span class="text-muted small">Sin Img</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. INTEGRACIÓN TANGO CONNECT -->
            <div class="card shadow-sm mb-4 border-warning border-opacity-50 border-start border-4">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h5 class="fw-bold text-primary mb-0">4. Integración Tango Connect</h5>
                </div>
                <div class="card-body">
                    <div class="row gx-4 gy-3">
                        <div class="col-md-6">
                            <label for="tango_api_url" class="form-label">URL de Connect (Base API)</label>
                            <input type="text" class="form-control" id="tango_api_url" name="tango_api_url"
                                   value="<?= htmlspecialchars($old['tango_api_url'] ?? ($config->tango_api_url ?? '')) ?>" placeholder="https://nexosync.tangonexo.com">
                        </div>
                        <div class="col-md-6">
                            <label for="tango_connect_company_id" class="form-label">ID de Empresa (Connect)</label>
                            <input type="text" class="form-control" id="tango_connect_company_id" name="tango_connect_company_id"
                                   value="<?= htmlspecialchars((string)($old['tango_connect_company_id'] ?? ($config->tango_connect_company_id ?? ''))) ?>" placeholder="Ej: 1">
                        </div>

                        <div class="col-md-6">
                            <label for="tango_connect_key" class="form-label">Número de Llave / Client Key</label>
                            <input type="text" class="form-control" id="tango_connect_key" name="tango_connect_key"
                                   value="<?= htmlspecialchars($old['tango_connect_key'] ?? ($config->tango_connect_key ?? '')) ?>" placeholder="Ej: 000357/017">
                        </div>
                        <div class="col-md-6">
                            <label for="cantidad_articulos_sync" class="form-label">Artículos por Lote en Sincronización</label>
                            <input type="number" class="form-control" id="cantidad_articulos_sync" name="cantidad_articulos_sync" min="1"
                                   value="<?= htmlspecialchars((string)($old['cantidad_articulos_sync'] ?? ($config->cantidad_articulos_sync ?? 50))) ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="lista_precio_1" class="form-label">Lista de Precio Default (1)</label>
                            <input type="text" class="form-control" id="lista_precio_1" name="lista_precio_1"
                                    value="<?= htmlspecialchars($old['lista_precio_1'] ?? ($config->lista_precio_1 ?? '')) ?>" placeholder="Ej: 1">
                        </div>
                        <div class="col-md-4">
                            <label for="lista_precio_2" class="form-label">Lista de Precio Alternate (2)</label>
                            <input type="text" class="form-control" id="lista_precio_2" name="lista_precio_2"
                                    value="<?= htmlspecialchars($old['lista_precio_2'] ?? ($config->lista_precio_2 ?? '')) ?>" placeholder="Ej: 2">
                        </div>
                        <div class="col-md-4">
                            <label for="deposito_codigo" class="form-label text-danger">ID Depósito Tango (ID_STA22)</label>
                            <input type="text" class="form-control border-danger" id="deposito_codigo" name="deposito_codigo" maxlength="2"
                                    value="<?= htmlspecialchars($old['deposito_codigo'] ?? ($config->deposito_codigo ?? '')) ?>" placeholder="Ej: 1">
                            <div class="form-text text-danger" style="font-size: 0.75rem;">Usa el ID relacional nativo (ej: 1), no el 00.</div>
                        </div>

                        <div class="col-12 mt-2">
                            <label for="tango_connect_token" class="form-label">Token de Acceso</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="tango_connect_token" name="tango_connect_token"
                                       value="<?= htmlspecialchars($old['tango_connect_token'] ?? ($config->tango_connect_token ?? '')) ?>">
                                <button class="btn btn-outline-secondary" type="button" id="toggleTokenEye" title="Mostrar/Ocultar">👁️</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. SMTP / CORREO -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-primary mb-0">5. Transmisión de Correo Electrónico (SMTP)</h5>
                    <div class="form-check form-switch m-0 p-0 d-flex align-items-center">
                        <label class="form-check-label fw-bold me-5 pe-2" for="usa_smtp_propio" style="cursor: pointer;">Forzar Cuenta SMTP Propia</label>
                        <input class="form-check-input ms-0 mt-0" style="width: 2.5em; height: 1.25em; cursor: pointer;" type="checkbox" role="switch" id="usa_smtp_propio" name="usa_smtp_propio" value="1" <?= (!empty($config->usa_smtp_propio) && $config->usa_smtp_propio == 1) ? 'checked' : '' ?>>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info border-0 rounded-3 mb-3 bg-opacity-10 shadow-sm d-flex align-items-center gap-3">
                        <span class="fs-4">💡</span> 
                        <small><strong>Fallback Automático de RXN:</strong> Si la llave SMTP está apagada, el sistema utilizará de forma totalmente transparente nuestro SMTP Global de alta reputación garantizando que los correos logísticos lleguen a la bandeja de entrada de tus clientes.</small>
                    </div>

                    <div id="smtpContainer" class="p-3 bg-light rounded border mt-3" style="display: <?= (!empty($config->usa_smtp_propio) && $config->usa_smtp_propio == 1) ? 'block' : 'none' ?>;">
                        <h6 class="fw-bold mb-3 border-bottom pb-2">Credenciales Extranjeras</h6>
                        <div class="row gx-4 gy-3">
                            <div class="col-md-9">
                                <label for="smtp_host" class="form-label text-secondary small">Servidor (Host)</label>
                                <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                                        value="<?= htmlspecialchars($old['smtp_host'] ?? ($config->smtp_host ?? '')) ?>" placeholder="Ej: smtp.pepito.com">
                            </div>
                            <div class="col-md-3">
                                <label for="smtp_port" class="form-label text-secondary small">Puerto</label>
                                <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                                        value="<?= htmlspecialchars((string)($old['smtp_port'] ?? ($config->smtp_port ?? '587'))) ?>" placeholder="587">
                            </div>

                            <div class="col-md-6">
                                <label for="smtp_user" class="form-label text-secondary small">Usuario SMTP / Auth Email</label>
                                <input type="email" class="form-control" id="smtp_user" name="smtp_user"
                                        value="<?= htmlspecialchars($old['smtp_user'] ?? ($config->smtp_user ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="smtp_pass" class="form-label text-secondary small">Contraseña / App Password</label>
                                <input type="password" class="form-control" id="smtp_pass" name="smtp_pass" autocomplete="new-password" placeholder="<?= !empty($config->smtp_pass) ? 'Guardada. Tipee para reemplazarla.' : 'Contraseña secreta' ?>">
                            </div>

                            <div class="col-md-4">
                                <label for="smtp_secure" class="form-label text-secondary small">Seguridad Túnel</label>
                                <select class="form-select" id="smtp_secure" name="smtp_secure">
                                    <option value="" <?= empty($config->smtp_secure) ? 'selected' : '' ?>>Ninguna o Default</option>
                                    <option value="tls" <?= (!empty($config->smtp_secure) && $config->smtp_secure == 'tls') ? 'selected' : '' ?>>TLS (Recomendada)</option>
                                    <option value="ssl" <?= (!empty($config->smtp_secure) && $config->smtp_secure == 'ssl') ? 'selected' : '' ?>>SSL (Antigua)</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="smtp_from_email" class="form-label text-secondary small">De: Casilla Remitente</label>
                                <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email"
                                        value="<?= htmlspecialchars($old['smtp_from_email'] ?? ($config->smtp_from_email ?? '')) ?>" placeholder="ventas@mitienda.com">
                            </div>
                            <div class="col-md-4">
                                <label for="smtp_from_name" class="form-label text-secondary small">De: Nombre Visible</label>
                                <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name"
                                        value="<?= htmlspecialchars($old['smtp_from_name'] ?? ($config->smtp_from_name ?? '')) ?>" placeholder="Mi Tienda SA">
                            </div>
                            
                            <div class="col-12 mt-3 text-end">
                                <button type="button" id="btn-test-tenant-smtp" class="btn btn-dark btn-sm rounded-pill px-4 shadow-sm">⚡ Probar Conexión SMTP</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-3 mt-4 mb-5 pb-5 sticky-bottom" style="z-index: 1020; bottom: 0;">
                <div class="bg-white p-3 rounded-pill shadow-lg border">
                    <button type="submit" class="btn btn-success px-5 fw-bold rounded-pill shadow-sm">💾 Guardar Cambios Reales</button>
                </div>
            </div>

        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Password Visibility Toggle
            const toggleEye = document.getElementById('toggleTokenEye');
            if(toggleEye) {
                toggleEye.addEventListener('click', function() {
                    const input = document.getElementById('tango_connect_token');
                    if (input.type === 'password') {
                        input.type = 'text';
                        this.innerText = '🙈';
                    } else {
                        input.type = 'password';
                        this.innerText = '👁️';
                    }
                });
            }

            // SMTP Switcher Logic
            const toggleSmtp = document.getElementById('usa_smtp_propio');
            const smtpFields = document.getElementById('smtpContainer'); // Fixed ID from previous code

            if (toggleSmtp && smtpFields) {
                toggleSmtp.addEventListener('change', function() {
                    smtpFields.style.display = this.checked ? 'block' : 'none';
                });
            }

            // Ajax SMTP Validator
            const testBtn = document.getElementById('btn-test-tenant-smtp');
            if (testBtn) {
                testBtn.addEventListener('click', async (e) => {
                    const originalText = testBtn.innerText;
                    testBtn.innerText = '⏳ Realizando Handshake SSL...';
                    testBtn.disabled = true;

                    try {
                        const formData = new FormData(document.querySelector('form'));
                        const res = await fetch('/rxnTiendasIA/public/mi-empresa/configuracion/test-smtp', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const json = await res.json();
                        if (json.success) {
                            alert('✅ CONEXIÓN EXITOSA:\n' + json.message);
                        } else {
                            alert('❌ ERROR FATAL SMTP:\n' + json.message);
                        }
                    } catch (error) {
                        alert('Error de RED o CORS al intentar contactar al Validador PHP.');
                    } finally {
                        testBtn.innerText = originalText;
                        testBtn.disabled = false;
                    }
                });
            }
        });
    </script>
</body>
</html>
