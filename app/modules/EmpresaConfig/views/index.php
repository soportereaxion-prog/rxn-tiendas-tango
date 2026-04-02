<?php
$pageTitle = 'RXN Tiendas IA';
ob_start();
?>
<?php
    $basePath = $basePath ?? '/rxnTiendasIA/public/mi-empresa/configuracion';
    $dashboardPath = $dashboardPath ?? '/rxnTiendasIA/public/mi-empresa/dashboard';
    $helpPath = $helpPath ?? '/rxnTiendasIA/public/mi-empresa/ayuda?area=tiendas';
    $showStoreSlug = $showStoreSlug ?? true;
    $showStoreBranding = $showStoreBranding ?? true;
    $moduleNotesKey = $moduleNotesKey ?? 'empresa_configuracion';
    $moduleNotesLabel = $moduleNotesLabel ?? 'Configuracion de Empresa';
    $headerTitle = $headerTitle ?? 'Configuracion de la Empresa';
    $headerSubtitle = $headerSubtitle ?? 'Gestion del entorno operativo actual.';
    $consoleTitle = $consoleTitle ?? 'Consola de Configuracion';
    $consoleSubtitle = $consoleSubtitle ?? 'Personaliza la identidad online y el enlace con el ERP de #[%s].';
    $sharedScopeNotice = $sharedScopeNotice ?? '';
    $generalSectionTitle = $generalSectionTitle ?? '1. Datos Generales';
    $fallbackSectionTitle = $fallbackSectionTitle ?? '3. Identidad Visual Corporativa';
    $fallbackSectionHelp = $fallbackSectionHelp ?? 'Si un articulo de Tango no posee imagenes sincronizadas en el sistema publico, se exhibira automaticamente este placeholder visual.';
    $tangoSectionTitle = $tangoSectionTitle ?? '4. Integracion Tango Connect';
    $smtpSectionTitle = $smtpSectionTitle ?? '5. Transmision de Correo Electronico (SMTP)';
    $smtpIntroText = $smtpIntroText ?? 'Fallback Automatico de RXN: Si la llave SMTP esta apagada, el sistema utilizara de forma totalmente transparente nuestro SMTP Global de alta reputacion garantizando que los correos logisticos lleguen a la bandeja de entrada de tus clientes.';
    ?>
    <div class="container-xl mt-5 mb-5 rxn-responsive-container rxn-form-shell" style="max-width: 1280px;">
        <div class="mb-4 rxn-module-header">
            <div>
                <h2 class="fw-bold mb-1"><?= htmlspecialchars((string) $headerTitle) ?></h2>
                <p class="text-muted mb-0"><?= htmlspecialchars((string) $headerSubtitle) ?></p>
            </div>
            <div>
                <h2 class="mb-1"><?= htmlspecialchars((string) $consoleTitle) ?></h2>
                <p class="text-muted mb-0"><?= htmlspecialchars(sprintf((string) $consoleSubtitle, $_SESSION['empresa_id'] ?? '')) ?></p>
            </div>
            <div class="rxn-module-actions">
                <?php require BASE_PATH . '/app/shared/views/components/user_action_menu.php'; ?>
                <a href="<?= htmlspecialchars($helpPath) ?>" class="btn btn-outline-info shadow-sm" target="_blank" rel="noopener noreferrer"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="<?= htmlspecialchars($dashboardPath) ?>" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-arrow-left"></i> Volver al Entorno</a>
            </div>
        </div>

        <?php require BASE_PATH . '/app/shared/views/components/module_notes_panel.php'; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success shadow-sm rounded-3 border-0 border-start border-4 border-success">Configuración guardada exitosamente.</div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger shadow-sm rounded-3 border-0 border-start border-4 border-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($sharedScopeNotice !== ''): ?>
            <div class="alert alert-secondary shadow-sm rounded-4 border-0"><?= htmlspecialchars((string) $sharedScopeNotice) ?></div>
        <?php endif; ?>

        <form action="<?= htmlspecialchars($basePath) ?>" method="POST" enctype="multipart/form-data">

            <!-- 1. DATOS GENERALES -->
            <div class="card rxn-form-card shadow-sm mb-4">
                <div class="card-header  border-bottom-0 pt-4 pb-0">
                    <h5 class="fw-bold text-primary mb-0"><?= htmlspecialchars((string) $generalSectionTitle) ?></h5>
                </div>
                <div class="card-body">
                    <div class="row gx-4 gy-3">
                        <?php if ($showStoreSlug): ?>
                            <div class="col-12">
                                <label class="form-label text-secondary mb-1">Tu Enlace Comercial (Slug)</label>
                                <input type="text" class="form-control bg-light fw-bold text-primary text-start" value="<?= htmlspecialchars((string)($empresa->slug ?? 'Sin Slug Generado')) ?>" readonly disabled>
                                <div class="form-text"><small>Este slug define la URL pública inicial de la tienda. Puedes comunicárselo a tus clientes.</small></div>
                            </div>
                        <?php endif; ?>

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

            <?php if ($showStoreBranding): ?>
                <!-- 2. IDENTIDAD DE MARCA B2C -->
                <div class="card rxn-form-card shadow-sm mb-4">
                    <div class="card-header  border-bottom-0 pt-4 pb-0">
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
                                    <span class="input-group-text p-1 ">
                                        <input type="color" class="form-control form-control-color border-0 p-0 m-0" name="color_primary" value="<?= htmlspecialchars((string)($empresa->color_primary ?? '#000000')) ?>" title="Elegir color principal" style="width: 30px; height: 30px;">
                                    </span>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars((string)($empresa->color_primary ?? '#000000')) ?>" readonly disabled>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Color Secundario</label>
                                <div class="input-group">
                                    <span class="input-group-text p-1 ">
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
                                        <label class="form-label">Redes / Links Publicos</label>
                                        <textarea class="form-control" name="footer_socials" rows="2" placeholder="Instagram: @mitienda&#10;Catalogo: https://...&#10;Facebook: /mitienda"><?= htmlspecialchars((string)($empresa->footer_socials ?? '')) ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 3. IDENTIDAD VISUAL CORPORATIVA -->
            <div class="card rxn-form-card shadow-sm mb-4">
                <div class="card-header  border-bottom-0 pt-4 pb-0">
                    <h5 class="fw-bold text-primary mb-0"><?= htmlspecialchars((string) $fallbackSectionTitle) ?></h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-9 col-lg-10 order-2 order-md-1">
                            <label class="form-label">Imagen de Producto por Defecto</label>
                            <input type="file" class="form-control mb-2" name="imagen_default" accept=".jpg,.jpeg,.png,.webp">
                            <div class="form-text"><small><?= htmlspecialchars((string) $fallbackSectionHelp) ?></small></div>
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

            <?php if (isset($area) && $area === 'crm'): ?>
            <!-- TARJETA NUMERACIÓN INTERNA CRM -->
            <div class="card rxn-form-card shadow-sm mb-4">
                <div class="card-header  border-bottom-0 pt-4 pb-0">
                    <h5 class="fw-bold text-primary mb-0">Numeración y Talonarios Internos</h5>
                </div>
                <div class="card-body">
                    <div class="row gx-4 gy-3">
                        <div class="col-md-6">
                            <label for="pds_numero_base" class="form-label">Número Base para Pedido de Servicio</label>
                            <input type="number" class="form-control" id="pds_numero_base" name="pds_numero_base" min="0"
                                   value="<?= htmlspecialchars((string)($old['pds_numero_base'] ?? ($config->pds_numero_base ?? '0'))) ?>">
                            <div class="form-text"><small>El sistema sumará 1 a este número, controlando lógicamente que no exista ya.</small></div>
                        </div>
                        <div class="col-md-6">
                            <label for="presupuesto_numero_base" class="form-label">Número Base para Presupuesto</label>
                            <input type="number" class="form-control" id="presupuesto_numero_base" name="presupuesto_numero_base" min="0"
                                   value="<?= htmlspecialchars((string)($old['presupuesto_numero_base'] ?? ($config->presupuesto_numero_base ?? '0'))) ?>">
                            <div class="form-text"><small>El sistema sumará 1 a este número, controlando lógicamente que no exista ya.</small></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- TARJETA ASIGNACIÓN DE FORMULARIOS IMPRESOS -->
            <div class="card rxn-form-card shadow-sm mb-4">
                <div class="card-header  border-bottom-0 pt-4 pb-0">
                    <h5 class="fw-bold text-primary mb-0">Asignación de Formularios de Impresión</h5>
                </div>
                <div class="card-body">
                    <div class="row gx-4 gy-3">
                        <div class="col-md-6">
                            <label for="pds_email_pdf_canvas_id" class="form-label">Formulario PDF para Pedido de Servicio</label>
                            <select class="form-select" id="pds_email_pdf_canvas_id" name="pds_email_pdf_canvas_id">
                                <option value="">-- Sin formulario asignado --</option>
                                <?php foreach ($printForms ?? [] as $form): ?>
                                    <?php if ($form['document_key'] === 'crm_pds'): ?>
                                        <option value="<?= $form['id'] ?>" <?= (isset($config->pds_email_pdf_canvas_id) && $config->pds_email_pdf_canvas_id == $form['id']) ? 'selected' : '' ?>><?= htmlspecialchars($form['nombre']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text"><small>Seleccione el diseño a aplicar para la impresión o envío de PDS.</small></div>
                        </div>
                        <div class="col-md-6">
                            <label for="presupuesto_email_pdf_canvas_id" class="form-label">Formulario PDF para Presupuestos</label>
                            <select class="form-select" id="presupuesto_email_pdf_canvas_id" name="presupuesto_email_pdf_canvas_id">
                                <option value="">-- Sin formulario asignado --</option>
                                <?php foreach ($printForms ?? [] as $form): ?>
                                    <?php if ($form['document_key'] === 'crm_presupuesto'): ?>
                                        <option value="<?= $form['id'] ?>" <?= (isset($config->presupuesto_email_pdf_canvas_id) && $config->presupuesto_email_pdf_canvas_id == $form['id']) ? 'selected' : '' ?>><?= htmlspecialchars($form['nombre']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text"><small>Seleccione el diseño a aplicar para la impresión o envío de Presupuestos.</small></div>
                        </div>
                    </div>

                    <div class="row gx-4 gy-3 mt-1">
                        <div class="col-md-6">
                            <label for="pds_email_body_canvas_id" class="form-label">Cuerpo de Email para Pedido de Servicio</label>
                            <select class="form-select" id="pds_email_body_canvas_id" name="pds_email_body_canvas_id">
                                <option value="">-- Sin plantilla asignada --</option>
                                <?php foreach ($printForms ?? [] as $form): ?>
                                    <?php if ($form['document_key'] === 'crm_pds_email'): ?>
                                        <option value="<?= $form['id'] ?>" <?= (isset($config->pds_email_body_canvas_id) && $config->pds_email_body_canvas_id == $form['id']) ? 'selected' : '' ?>><?= htmlspecialchars($form['nombre']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text"><small>Plantilla HTML a inyectar en el cuerpo del correo.</small></div>
                        </div>
                        <div class="col-md-6">
                            <label for="presupuesto_email_body_canvas_id" class="form-label">Cuerpo de Email para Presupuestos</label>
                            <select class="form-select" id="presupuesto_email_body_canvas_id" name="presupuesto_email_body_canvas_id">
                                <option value="">-- Sin plantilla asignada --</option>
                                <?php foreach ($printForms ?? [] as $form): ?>
                                    <?php if ($form['document_key'] === 'crm_presupuesto_email'): ?>
                                        <option value="<?= $form['id'] ?>" <?= (isset($config->presupuesto_email_body_canvas_id) && $config->presupuesto_email_body_canvas_id == $form['id']) ? 'selected' : '' ?>><?= htmlspecialchars($form['nombre']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text"><small>Plantilla HTML a inyectar en el cuerpo del correo.</small></div>
                        </div>
                    </div>

                    <div class="row gx-4 gy-3 mt-1">
                        <div class="col-md-6">
                            <label for="pds_email_asunto" class="form-label">Asunto (Subjeto) Correo PDS</label>
                            <input type="text" class="form-control" id="pds_email_asunto" name="pds_email_asunto" value="<?= htmlspecialchars($config->pds_email_asunto ?? '') ?>" placeholder="Ej: Tu Pedido de Servicio">
                            <div class="form-text"><small>Texto predeterminado para el asunto del correo.</small></div>
                        </div>
                        <div class="col-md-6">
                            <label for="presupuesto_email_asunto" class="form-label">Asunto (Subjeto) Correo Presupuestos</label>
                            <input type="text" class="form-control" id="presupuesto_email_asunto" name="presupuesto_email_asunto" value="<?= htmlspecialchars($config->presupuesto_email_asunto ?? '') ?>" placeholder="Ej: Tu Presupuesto Comercial">
                            <div class="form-text"><small>Texto predeterminado para el asunto del correo.</small></div>
                        </div>
                    </div>

                    <div class="row gx-4 gy-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Imagen de Encabezado (Documentos)</label>
                            <div class="d-flex align-items-center gap-3">
                                <div>
                                    <input type="file" class="form-control" name="impresion_header" accept=".jpg,.jpeg,.png,.webp">
                                    <div class="form-text"><small>Logo o imagen para la cabecera (se recomienda PNG sin fondo).</small></div>
                                </div>
                                <?php if(!empty($config->impresion_header_url)): ?>
                                    <img src="/rxnTiendasIA/public<?= htmlspecialchars((string)$config->impresion_header_url) ?>" alt="Header" class="img-thumbnail rounded-2 shadow-sm" style="max-height: 50px;">
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Imagen de Pie / Footer (Documentos)</label>
                            <div class="d-flex align-items-center gap-3">
                                <div>
                                    <input type="file" class="form-control" name="impresion_footer" accept=".jpg,.jpeg,.png,.webp">
                                    <div class="form-text"><small>Imagen para el final de presupuestos o PDS.</small></div>
                                </div>
                                <?php if(!empty($config->impresion_footer_url)): ?>
                                    <img src="/rxnTiendasIA/public<?= htmlspecialchars((string)$config->impresion_footer_url) ?>" alt="Footer" class="img-thumbnail rounded-2 shadow-sm" style="max-height: 50px;">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>

            <!-- 4. INTEGRACIÓN TANGO CONNECT -->
            <div class="card rxn-form-card shadow-sm mb-4 border-warning border-opacity-50 border-start border-4">
                <div class="card-header  border-bottom-0 pt-4 pb-0">
                    <h5 class="fw-bold text-primary mb-0"><?= htmlspecialchars((string) $tangoSectionTitle) ?></h5>
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
                            <?php $valCompanyId = htmlspecialchars((string)($old['tango_connect_company_id'] ?? ($config->tango_connect_company_id ?? ''))); ?>
                            <select class="form-select tango-dynamic" id="tango_connect_company_id" name="tango_connect_company_id" data-original="<?= $valCompanyId ?>">
                                <?php if($valCompanyId): ?><option value="<?= $valCompanyId ?>">Valor local guardado (<?= $valCompanyId ?>)</option><?php else: ?><option value="">Validar conexión primero...</option><?php endif; ?>
                            </select>
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

                        <div class="col-md-4 mt-3">
                            <label for="lista_precio_1" class="form-label">Lista de Precios 1</label>
                            <?php $valL1 = htmlspecialchars((string)($old['lista_precio_1'] ?? ($config->lista_precio_1 ?? ''))); ?>
                            <select class="form-select" id="lista_precio_1" name="lista_precio_1" data-original="<?= $valL1 ?>">
                                <?php if($valL1): ?><option value="<?= $valL1 ?>">Guardado (<?= $valL1 ?>)</option><?php else: ?><option value="">Validar conexión...</option><?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mt-3">
                            <label for="lista_precio_2" class="form-label">Lista de Precios 2</label>
                            <?php $valL2 = htmlspecialchars((string)($old['lista_precio_2'] ?? ($config->lista_precio_2 ?? ''))); ?>
                            <select class="form-select" id="lista_precio_2" name="lista_precio_2" data-original="<?= $valL2 ?>">
                                <?php if($valL2): ?><option value="<?= $valL2 ?>">Guardado (<?= $valL2 ?>)</option><?php else: ?><option value="">Validar conexión...</option><?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mt-3">
                            <label for="deposito_codigo" class="form-label">Depósito de Stock</label>
                            <?php $valDep = htmlspecialchars((string)($old['deposito_codigo'] ?? ($config->deposito_codigo ?? ''))); ?>
                            <select class="form-select" id="deposito_codigo" name="deposito_codigo" data-original="<?= $valDep ?>">
                                <?php if($valDep): ?><option value="<?= $valDep ?>">Guardado (<?= $valDep ?>)</option><?php else: ?><option value="">Validar conexión...</option><?php endif; ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label for="clasificaciones_pds_raw" class="form-label">Catálogo Local de Clasificaciones PDS</label>
                            <textarea class="form-control font-monospace" id="clasificaciones_pds_raw" name="clasificaciones_pds_raw" rows="3" style="font-size:0.8rem;" placeholder='[{"codigo":"ASETGO", "descripcion":"Asesoramiento Tango"}, ...]'><?= htmlspecialchars($old['clasificaciones_pds_raw'] ?? ($config->clasificaciones_pds_raw ?? '')) ?></textarea>
                            <div class="form-text"><small>Alimenta el autocompletado del CRM sin demoras de la API externa (soporta JSON array).</small></div>
                        </div>

                        <div class="col-md-12">
                            <input type="hidden" id="tango_perfil_snapshot_json" name="tango_perfil_snapshot_json" value="<?= htmlspecialchars((string)($old['tango_perfil_snapshot_json'] ?? ($config->tango_perfil_snapshot_json ?? ''))) ?>">
                        </div>

                        <div class="col-12 mt-2">
                            <div class="row align-items-end">
                                <div class="col-md-9">
                                    <label for="tango_connect_token" class="form-label">Token de Acceso</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="tango_connect_token" name="tango_connect_token"
                                               value="<?= htmlspecialchars($old['tango_connect_token'] ?? ($config->tango_connect_token ?? '')) ?>">
                                        <button class="btn btn-outline-secondary" type="button" id="toggleTokenEye" title="Mostrar/Ocultar">👁️</button>
                                    </div>
                                </div>
                                <div class="col-md-3 mt-3 mt-md-0 d-grid">
                                    <button type="button" id="btn-validate-tango" class="btn btn-primary d-flex justify-content-center align-items-center gap-2 shadow-sm"><i class="bi bi-shield-check"></i> <span>Validar Conexión</span></button>
                                </div>
                                <div class="col-12">
                                    <div id="tango-select-hint" class="form-text text-muted">La pantalla muestra primero lo guardado localmente. Connect solo se consulta cuando validás la integración.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. SMTP / CORREO -->
            <div class="card rxn-form-card shadow-sm mb-4">
                <div class="card-header  border-bottom-0 pt-4 pb-0 rxn-module-header">
                    <h5 class="fw-bold text-primary mb-0"><?= htmlspecialchars((string) $smtpSectionTitle) ?></h5>
                    <div class="form-check form-switch m-0 p-0 d-flex align-items-center">
                        <label class="form-check-label fw-bold me-5 pe-2" for="usa_smtp_propio" style="cursor: pointer;">Forzar Cuenta SMTP Propia</label>
                        <input class="form-check-input ms-0 mt-0" style="width: 2.5em; height: 1.25em; cursor: pointer;" type="checkbox" role="switch" id="usa_smtp_propio" name="usa_smtp_propio" value="1" <?= (!empty($config->usa_smtp_propio) && $config->usa_smtp_propio == 1) ? 'checked' : '' ?>>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info border-0 rounded-3 mb-3 bg-opacity-10 shadow-sm d-flex align-items-center gap-3">
                        <span class="fs-4">💡</span> 
                        <small><?= htmlspecialchars((string) $smtpIntroText) ?></small>
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

            <div class="rxn-form-actions mt-4 mb-5 pb-2">
                <a href="<?= htmlspecialchars($dashboardPath) ?>" class="btn btn-light">Cancelar</a>
                <button type="submit" class="btn btn-success px-5 fw-bold shadow-sm">💾 Guardar Cambios Reales</button>
            </div>

        </form>
    </div>

    
<?php
$content = ob_get_clean();
ob_start();
?>
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
                        const res = await fetch('<?= htmlspecialchars($basePath) ?>/test-smtp', {
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
            // Ajax Tango Connector & Metadata Loader
            const tangoBtn = document.getElementById('btn-validate-tango');
            const tangoHint = document.getElementById('tango-select-hint');

            async function loadTangoMetadata({ validateFirst = false } = {}) {
                if (!tangoBtn) {
                    return false;
                }

                const originalBtnHtml = tangoBtn.innerHTML;
                const originalHint = tangoHint ? tangoHint.textContent : '';

                tangoBtn.innerHTML = validateFirst ? '⏳ Validando...' : '⏳ Resolviendo descripciones...';
                tangoBtn.disabled = true;
                if (tangoHint) {
                    tangoHint.textContent = validateFirst
                        ? 'Validando credenciales y sincronizando descripciones de Tango Connect...'
                        : 'Intentando resolver las descripciones guardadas desde Tango Connect...';
                }

                try {
                    const formData = new FormData(document.querySelector('form'));

                    if (validateFirst) {
                        const resAuth = await fetch('<?= htmlspecialchars($basePath) ?>/test-tango', { method: 'POST', body: formData });
                        const jsonAuth = await resAuth.json();

                        if (!jsonAuth.success) {
                            tangoBtn.innerHTML = '❌ Falla Integración';
                            tangoBtn.classList.replace('btn-primary', 'btn-danger');
                            if (tangoHint) {
                                tangoHint.textContent = 'No pude validar Tango Connect con los datos cargados.';
                            }
                            alert('Tango Connect advierte error:\n\n' + jsonAuth.message);
                            return false;
                        }

                        tangoBtn.innerHTML = '✅ Configurado y Funcional';
                        tangoBtn.classList.replace('btn-primary', 'btn-success');
                    }

                    const resMeta = await fetch('<?= htmlspecialchars($basePath) ?>/tango-metadata', { method: 'POST', body: formData });
                    const jsonMeta = await resMeta.json();

                    if (jsonMeta.success && jsonMeta.data) {
                        populateTangoSelects(jsonMeta.data);
                        if (tangoHint) {
                            tangoHint.textContent = 'Descripciones Tango resueltas. Si un valor no existe más en Connect, se conserva el guardado como referencia.';
                        }
                        return true;
                    }

                    if (tangoHint) {
                        tangoHint.textContent = 'No pude obtener metadata legible desde Tango Connect.';
                    }
                    return false;
                } catch (error) {
                    console.error("Local JS fetch error en API Connect Metadata:", error);
                    tangoBtn.innerHTML = '❌ Falla Local';
                    tangoBtn.classList.replace('btn-primary', 'btn-danger');
                    if (tangoHint) {
                        tangoHint.textContent = 'Hubo un problema local al intentar resolver metadata de Tango.';
                    }
                    if (validateFirst) {
                        alert('Problema al contactar tu propia instancia de red (CORS/Timeout). Revise consola local.');
                    }
                    return false;
                } finally {
                    setTimeout(() => {
                        if (!tangoBtn.classList.contains('btn-success')) {
                            tangoBtn.innerHTML = originalBtnHtml;
                            tangoBtn.disabled = false;
                            tangoBtn.classList.remove('btn-danger');
                            tangoBtn.classList.add('btn-primary');
                            if (tangoHint && originalHint) {
                                tangoHint.textContent = originalHint;
                            }
                        } else {
                            tangoBtn.disabled = false;
                        }
                    }, validateFirst ? 5000 : 800);
                }
            }

            const sCompanyStatic = document.getElementById('tango_connect_company_id');
            const sL1Static = document.getElementById('lista_precio_1');
            const sL2Static = document.getElementById('lista_precio_2');
            const sDepoStatic = document.getElementById('deposito_codigo');

            // Habilitar buscadores por defecto al cargar pagina (con la opcion mock si hay)
            if (sCompanyStatic) applyLocalSearchPattern(sCompanyStatic);
            if (sL1Static) applyLocalSearchPattern(sL1Static);
            if (sL2Static) applyLocalSearchPattern(sL2Static);
            if (sDepoStatic) applyLocalSearchPattern(sDepoStatic);

            if (sCompanyStatic) {
                sCompanyStatic.addEventListener('change', async () => {
                    // Si ya seleccionó una empresa válida, cargamos silenciosamente la metadata
                    if (sCompanyStatic.value) {
                        try {
                            const originalBtnHtml = tangoBtn ? tangoBtn.innerHTML : null;
                            if (tangoBtn) {
                                tangoBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Cargando perfiles...';
                                tangoBtn.disabled = true;
                            }
                            await loadTangoMetadata({ validateFirst: false });
                            if (tangoBtn && originalBtnHtml) {
                                tangoBtn.innerHTML = originalBtnHtml;
                                tangoBtn.disabled = false;
                            }
                        } catch (e) {
                            console.error(e);
                        }
                    }
                });
            }

            if (tangoBtn) {
                tangoBtn.addEventListener('click', async () => {
                    await loadTangoMetadata({ validateFirst: true });
                });
            }

            function populateTangoSelects(data) {
                const sCompany = document.getElementById('tango_connect_company_id');
                const inputSnapshot = document.getElementById('tango_perfil_snapshot_json');

                const origCompany = sCompany.value || sCompany.getAttribute('data-original');

                let companyMatched = false;
                let preHtmlCompany = '<option value="">-- Seleccioná una empresa --</option>';

                (data.empresas || []).forEach(company => {
                    let selCompany = (origCompany == company.id) ? 'selected' : '';
                    if (selCompany) companyMatched = true;
                    preHtmlCompany += `<option value="${company.id}" ${selCompany}>${company.descripcion}</option>`;
                });

                if (origCompany && !companyMatched) {
                    preHtmlCompany += `<option value="${origCompany}" selected>Valor guardado sin descripción (${origCompany})</option>`;
                }

                sCompany.innerHTML = preHtmlCompany;

                // Render Listas
                const sL1 = document.getElementById('lista_precio_1');
                const sL2 = document.getElementById('lista_precio_2');
                const sDepo = document.getElementById('deposito_codigo');

                if (sL1 && sL2 && sDepo) {
                    const origL1 = sL1.getAttribute('data-original');
                    const origL2 = sL2.getAttribute('data-original');
                    const origDep = sDepo.getAttribute('data-original');

                    let preHtmlL1 = '<option value="">-- Sin asignar --</option>';
                    let preHtmlL2 = '<option value="">-- Sin asignar --</option>';
                    
                    if (data.listas_precios) {
                        data.listas_precios.forEach(l => {
                            let sel1 = (origL1 == l.id) ? 'selected' : '';
                            let sel2 = (origL2 == l.id) ? 'selected' : '';
                            preHtmlL1 += `<option value="${l.id}" ${sel1}>${l.descripcion}</option>`;
                            preHtmlL2 += `<option value="${l.id}" ${sel2}>${l.descripcion}</option>`;
                        });
                    }

                    sL1.innerHTML = preHtmlL1;
                    sL2.innerHTML = preHtmlL2;

                    let preHtmlDep = '<option value="">-- Ignorar Stock / Sin asignar --</option>';
                    
                    if (data.depositos) {
                        data.depositos.forEach(d => {
                            let selD = (origDep == d.id) ? 'selected' : '';
                            preHtmlDep += `<option value="${d.id}" ${selD}>${d.descripcion}</option>`;
                        });
                    }

                    sDepo.innerHTML = preHtmlDep;
                }

                // Guardar Perfiles Pedido en input oculto
                if (data.perfiles_pedidos) {
                    inputSnapshot.value = JSON.stringify(data.perfiles_pedidos);
                }

                // Aplicar / Resincronizar buscadores
                if (sCompany) applyLocalSearchPattern(sCompany);
                if (sL1) applyLocalSearchPattern(sL1);
                if (sL2) applyLocalSearchPattern(sL2);
                if (sDepo) applyLocalSearchPattern(sDepo);
            }

            function applyLocalSearchPattern(selectEl) {
                if (!selectEl.dataset.searchified) {
                    selectEl.dataset.searchified = "true";
                    
                    const wrapper = document.createElement('div');
                    wrapper.className = 'position-relative w-100';
                    wrapper.style.zIndex = '999'; 
                    // Asegurar que el padre no corte
                    if (selectEl.parentElement) {
                        selectEl.parentElement.style.overflow = 'visible';
                    }
                    
                    selectEl.parentNode.insertBefore(wrapper, selectEl);
                    wrapper.appendChild(selectEl);
                    
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.className = selectEl.className.replace('form-select', 'form-control');
                    input.placeholder = 'Escriba para buscar...';
                    input.autocomplete = 'off';
                    
                    const suggestions = document.createElement('div');
                    suggestions.className = 'rxn-search-suggestions-container bg-body border rounded shadow-lg position-absolute w-100 d-none';
                    suggestions.style.maxHeight = '300px';
                    suggestions.style.overflowY = 'auto';
                    suggestions.style.top = '100%';
                    suggestions.style.left = '0';
                    suggestions.style.zIndex = '1050';
                    suggestions.style.marginTop = '2px';
                    
                    wrapper.appendChild(input);
                    wrapper.appendChild(suggestions);
                    
                    selectEl.classList.add('d-none');

                    selectEl._syncSearch = function() {
                        if (selectEl.selectedIndex >= 0) {
                            input.value = selectEl.options[selectEl.selectedIndex].text;
                        } else {
                            input.value = '';
                        }
                    };
                    
                    input.addEventListener('focus', () => renderSuggestions(''));
                    input.addEventListener('input', (e) => renderSuggestions(e.target.value));

                    let currentFocus = -1;

                    input.addEventListener('keydown', function(e) {
                        const items = suggestions.querySelectorAll('button.dropdown-item');
                        if (items.length === 0) return;

                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            currentFocus++;
                            if (currentFocus >= items.length) currentFocus = 0;
                            addActive(items);
                        } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            currentFocus--;
                            if (currentFocus < 0) currentFocus = items.length - 1;
                            addActive(items);
                        } else if (e.key === 'Enter') {
                            if (currentFocus > -1) {
                                e.preventDefault();
                                items[currentFocus].dispatchEvent(new MouseEvent('mousedown'));
                            } else {
                                // Evitar falso submit si apreta enter con el input vacio o escribiendo
                                e.preventDefault();
                            }
                        }
                    });

                    function addActive(items) {
                        items.forEach(item => item.classList.remove('active', 'bg-primary', 'text-white'));
                        if (currentFocus >= 0 && currentFocus < items.length) {
                            items[currentFocus].classList.add('active', 'bg-primary', 'text-white');
                            items[currentFocus].scrollIntoView({ block: "nearest" });
                        }
                    }
                    
                    let isMousedown = false;
                    suggestions.addEventListener('mousedown', () => isMousedown = true);
                    input.addEventListener('blur', () => {
                        if(!isMousedown) {
                            setTimeout(() => {
                                suggestions.classList.add('d-none');
                                selectEl._syncSearch();
                            }, 150);
                        }
                        isMousedown = false;
                    });

                    // Evento click extra para asegurar despliegue completo
                    input.addEventListener('click', (e) => {
                        renderSuggestions(e.target.value);
                    });

                    function renderSuggestions(term) {
                        suggestions.innerHTML = '';
                        currentFocus = -1;
                        term = term.toLowerCase().trim();
                        let count = 0;
                        Array.from(selectEl.options).forEach((opt, idx) => {
                            if (!opt.value && term) return;
                            if (opt.text.toLowerCase().includes(term) || term === '') {
                                count++;
                                const item = document.createElement('button');
                                item.type = 'button';
                                item.className = 'dropdown-item p-2 border-bottom text-wrap';
                                if (opt.selected) item.classList.add('bg-light', 'fw-bold', 'text-primary');
                                item.textContent = opt.text;
                                item.addEventListener('mousedown', (e) => {
                                    e.preventDefault();
                                    selectEl.selectedIndex = idx;
                                    selectEl._syncSearch();
                                    suggestions.classList.add('d-none');
                                    selectEl.dispatchEvent(new Event('change'));
                                });
                                suggestions.appendChild(item);
                            }
                        });
                        if (count > 0) suggestions.classList.remove('d-none');
                        else suggestions.classList.add('d-none');
                    }
                }
                
                // Si el selector ya estaba searchified, solo sincronizar el input
                if (selectEl._syncSearch) {
                    selectEl._syncSearch();
                }
            }

        });
    </script>
    <script src="/rxnTiendasIA/public/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
