<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ayuda Operativa - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
    <style>
        .help-search-box {
            position: sticky;
            top: 1rem;
            z-index: 5;
            backdrop-filter: blur(10px);
        }

        .help-kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            background: rgba(25, 75, 165, 0.08);
            color: #194ba5;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .help-card + .help-card {
            margin-top: 1.25rem;
        }

        .help-anchor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 0.85rem;
        }

        .help-anchor {
            display: block;
            padding: 0.95rem 1rem;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 14px;
            text-decoration: none;
            color: inherit;
            background: rgba(248, 249, 250, 0.75);
        }

        .help-anchor:hover {
            border-color: rgba(25, 75, 165, 0.25);
            background: rgba(25, 75, 165, 0.06);
        }

        .help-list-tight li + li {
            margin-top: 0.45rem;
        }

        .help-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            background: rgba(108, 117, 125, 0.12);
            color: #495057;
        }

        .help-highlight {
            border-left: 4px solid rgba(25, 75, 165, 0.35);
            background: rgba(25, 75, 165, 0.04);
            border-radius: 12px;
            padding: 1rem 1rem 1rem 1.1rem;
        }

        .help-empty {
            display: none;
        }

        .help-empty.is-visible {
            display: block;
        }
    </style>
</head>
<body class="rxn-page-shell">
    <?php
    $dashboardPath = $dashboardPath ?? '/rxnTiendasIA/public/mi-empresa/dashboard';
    $environmentLabel = $environmentLabel ?? 'Entorno Operativo';
    $area = $area ?? 'tiendas';
    $isCrm = $area === 'crm';
    ?>
    <div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell" style="max-width: 1160px;">
        <div class="rxn-module-header mb-4">
            <div>
                <span class="help-kicker mb-3"><i class="bi bi-life-preserver"></i> Ayuda para humanos</span>
                <h1 class="fw-bold mb-1">Centro de Ayuda del Entorno Operativo</h1>
                <p class="text-muted mb-0">Explicaciones largas, simples y directas para que cualquier administrador entienda que hace cada parte del sistema. Contexto actual: <?= htmlspecialchars((string) $environmentLabel) ?>.</p>
            </div>
            <div class="rxn-module-actions">
                <?php require BASE_PATH . '/app/shared/views/components/user_action_menu.php'; ?>
                <span class="text-muted small">Hola, <?= htmlspecialchars((string) $userName) ?></span>
                <a href="<?= htmlspecialchars($dashboardPath) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver al Entorno</a>
            </div>
        </div>

        <?php
        $moduleNotesKey = 'ayuda_operativa';
        $moduleNotesLabel = 'Ayuda Operativa';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <div class="card rxn-form-card help-search-box mb-4">
            <div class="card-body p-4">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-9">
                        <label for="help-search" class="form-label fw-semibold">Buscar dentro de la ayuda</label>
                        <input type="search" id="help-search" class="form-control form-control-lg" placeholder="Ej: pedidos, sincronizacion, clientes, buscar, usuario, smtp..." autocomplete="off">
                        <div class="form-text">Busca palabras o ideas. La pagina va ocultando lo que no coincide para que encuentres rapido una explicacion util.</div>
                    </div>
                    <div class="col-12 col-lg-3">
                        <button type="button" id="help-clear" class="btn btn-outline-secondary w-100">Limpiar busqueda</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card rxn-form-card help-card" data-help-item data-help-text="inicio primeros pasos entorno operativo modulos ayuda buscar panel administracion empresa usuario">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Por donde arrancar si es tu primera vez</h2>
                <div class="row g-4">
                    <div class="col-12 col-lg-4">
                        <div class="help-highlight h-100">
                            <div class="help-chip mb-2">Paso 1</div>
                            <p class="mb-0">Entra al <strong>Entorno Operativo</strong> y ubica las tarjetas principales. Cada tarjeta te lleva a un modulo distinto del trabajo diario.</p>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="help-highlight h-100">
                            <div class="help-chip mb-2">Paso 2</div>
                            <p class="mb-0">Abre el modulo que necesitas y usa el buscador para encontrar registros. El sistema no filtra solo mientras escribis: primero escribis, despues confirmas.</p>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="help-highlight h-100">
                            <div class="help-chip mb-2">Paso 3</div>
                            <p class="mb-0">Cuando termines una accion importante, revisa si aparece un mensaje de confirmacion, exito o error. Ese mensaje te dice si el sistema guardo, rechazo o dejo pendiente algo.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card rxn-form-card help-card" data-help-item data-help-text="indice secciones catalogo clientes pedidos pedidos servicio usuarios configuracion perfil buscador sincronizacion ayuda rapida orden tarjetas">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-4">Ir directo a un tema</h2>
                <div class="help-anchor-grid">
                    <a href="#modulo-articulos" class="help-anchor"><strong><?= $isCrm ? 'Articulos CRM' : 'Catalogo de Articulos' ?></strong><br><small class="text-muted"><?= $isCrm ? 'Articulos internos, precios, stock, imagenes y sync CRM.' : 'Productos, precios, stock, imagenes y sync.' ?></small></a>
                    <?php if ($isCrm): ?>
                        <a href="#modulo-pedidos-servicio" class="help-anchor"><strong>Pedidos de Servicio</strong><br><small class="text-muted">Alta, tiempos, diagnostico, motivo de descuento y cierre.</small></a>
                    <?php else: ?>
                        <a href="#modulo-clientes" class="help-anchor"><strong>Clientes Web</strong><br><small class="text-muted">Clientes, datos, vinculo con Tango.</small></a>
                        <a href="#modulo-pedidos" class="help-anchor"><strong>Pedidos Web</strong><br><small class="text-muted">Seguimiento, estados y reproceso.</small></a>
                    <?php endif; ?>
                    <a href="#modulo-usuarios" class="help-anchor"><strong>Administrar Cuentas</strong><br><small class="text-muted">Altas, permisos y accesos internos.</small></a>
                    <a href="#modulo-configuracion" class="help-anchor"><strong>Configuracion</strong><br><small class="text-muted"><?= $isCrm ? 'Parametros operativos propios de CRM, SMTP y Tango.' : 'Slug, branding, SMTP y Tango.' ?></small></a>
                    <a href="#modulo-orden-tarjetas" class="help-anchor"><strong>Orden de Tarjetas</strong><br><small class="text-muted">Como acomodar el menu sin afectar otros entornos.</small></a>
                    <a href="#modulo-buscadores" class="help-anchor"><strong>Buscadores</strong><br><small class="text-muted">Como buscar bien sin perder tiempo.</small></a>
                </div>
            </div>
        </div>

        <div class="card rxn-form-card help-card" id="modulo-articulos" data-help-item data-help-text="articulos catalogo productos precio stock imagenes sync sincronizacion sku descripcion editar eliminar purgar crm">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3"><?= $isCrm ? 'Articulos CRM' : 'Catalogo de Articulos' ?></h2>
                <p class="text-muted"><?= $isCrm ? 'Este modulo sirve para la base interna de articulos de CRM. Mantiene el mismo circuito de botones de sincronizacion para que el operador no cambie de habito entre entornos.' : 'Este modulo sirve para revisar lo que la tienda muestra y vende. Si pensas en productos, fotos, precios y stock, pensas en este modulo.' ?></p>
                <ul class="help-list-tight mb-4">
                    <li><strong>Que ves en el listado:</strong> codigo del articulo, nombre, descripcion, precios, stock, estado activo/inactivo y fecha de ultima sincronizacion.</li>
                    <li><strong>Para que sirve:</strong> <?= $isCrm ? 'controlar el maestro operativo del CRM antes de usarlo en pedidos de servicio o futuras integraciones.' : 'controlar si el catalogo local esta coherente antes de vender online.' ?></li>
                    <li><strong>Cuando entrar:</strong> si un producto no aparece, tiene precio raro, imagen incorrecta o stock desactualizado.</li>
                </ul>
                <h3 class="h6 fw-bold mb-2">Botones que suelen generar dudas</h3>
                <ul class="help-list-tight mb-0">
                    <li><strong>Sync Articulos:</strong> trae o actualiza el maestro de articulos.</li>
                    <li><strong>Sync Precios:</strong> actualiza precios desde el sistema comercial.</li>
                    <li><strong>Sync Stock:</strong> refresca existencias locales.</li>
                    <li><strong>Sync Total:</strong> ejecuta una cadena mas completa de actualizaciones. Puede tardar mas.</li>
                    <li><strong>Purgar Todo:</strong> borra el catalogo local. Solo usar si realmente queres reconstruirlo desde cero.</li>
                </ul>
            </div>
        </div>

        <?php if ($isCrm): ?>
            <div class="card rxn-form-card help-card" id="modulo-pedidos-servicio" data-help-item data-help-text="crm pedidos servicio servicio tecnico diagnostico motivo descuento finalizado clasificacion cliente articulo tiempo neto bruto">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h4 fw-bold mb-3">Pedidos de Servicio</h2>
                    <p class="text-muted">Este modulo sirve para registrar trabajo tecnico o comercial en formato operativo. Piensalo como la ficha de cada intervencion.</p>
                    <ul class="help-list-tight mb-4">
                        <li><strong>Inicio y Finalizado:</strong> marcan el rango horario real del trabajo.</li>
                        <li><strong>Descuento:</strong> es tiempo que no debe computarse. Se escribe en formato <code>HH:MM:SS</code>.</li>
                        <li><strong>Tiempo bruto:</strong> fin menos inicio.</li>
                        <li><strong>Tiempo neto:</strong> bruto menos descuento.</li>
                        <li><strong>Diagnostico y Motivo Descuento:</strong> son los textos principales del trabajo y conviene completarlos con lenguaje claro.</li>
                    </ul>
                    <div class="help-highlight">
                        <strong>Dato importante:</strong> el pedido guarda snapshot de cliente y articulo. Eso significa que el historico sigue legible aunque mas adelante cambie el origen de datos.
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$isCrm): ?>
        <div class="card rxn-form-card help-card" id="modulo-clientes" data-help-item data-help-text="clientes web cliente email documento razon social tango validacion contacto editar comercial vinculo codigo tango">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Clientes Web</h2>
                <p class="text-muted">Aca ves las personas o empresas que interactuan con la tienda. No es solo un listado: tambien es el lugar donde se resuelve la parte comercial si un pedido necesita asociarse correctamente.</p>
                <ul class="help-list-tight mb-4">
                    <li><strong>Que podes corregir:</strong> nombre, apellido, email, telefono, documento, razon social y direccion.</li>
                    <li><strong>Que significa vincular con Tango:</strong> relacionar el cliente web con el cliente comercial correcto para que los pedidos puedan entrar bien al ERP.</li>
                    <li><strong>Cuando usar validar:</strong> cuando el cliente ya tiene codigo Tango o cuando queres comprobar que la relacion comercial quedo bien.</li>
                </ul>
                <div class="help-highlight">
                    <strong>Dato importante:</strong> si un pedido no logra entrar correctamente al circuito comercial, muchas veces el problema real no esta en el pedido sino en el cliente asociado.
                </div>
            </div>
        </div>

        <div class="card rxn-form-card help-card" id="modulo-pedidos" data-help-item data-help-text="pedidos web pedido estado pendiente enviado error reprocesar cliente tango integracion detalle pedido numero email">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Pedidos Web</h2>
                <p class="text-muted">Este modulo muestra lo que entro desde la tienda y en que situacion esta cada pedido.</p>
                <ul class="help-list-tight mb-4">
                    <li><strong>Pendiente:</strong> el pedido existe localmente pero todavia no se completo su envio correcto al ERP.</li>
                    <li><strong>Enviado OK:</strong> el pedido ya paso al sistema comercial.</li>
                    <li><strong>Con Error:</strong> hubo un rechazo o una falla técnica, y conviene revisar cliente, articulos o configuracion.</li>
                    <li><strong>Reprocesar:</strong> intenta enviar de nuevo el pedido despues de corregir la causa del problema.</li>
                </ul>
                <p class="mb-0">Si un pedido falla o se rechaza, no siempre hay que tocar el pedido mismo. A veces el error esta en el cliente, en el articulo o en una configuracion faltante.</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="card rxn-form-card help-card" id="modulo-orden-tarjetas" data-help-item data-help-text="orden tarjetas menu launcher dashboard mover arrastrar drag drop tiendas crm perfil">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Orden de Tarjetas del Menu</h2>
                <p class="text-muted">Puedes arrastrar las tarjetas del entorno para acomodarlas. El orden se guarda por separado para cada entorno operativo.</p>
                <ul class="help-list-tight mb-0">
                    <li><strong>Tiendas y CRM no comparten orden.</strong> Mover tarjetas en un entorno no debe desordenar el otro.</li>
                    <li><strong>Como reordenar:</strong> mantén presionada una tarjeta, arrastrala y soltala en la nueva posicion.</li>
                    <li><strong>Cuando conviene usarlo:</strong> para dejar primero los modulos que mas usa cada operador.</li>
                </ul>
            </div>
        </div>

        <div class="card rxn-form-card help-card" id="modulo-usuarios" data-help-item data-help-text="usuarios cuentas permisos administrador activo password mail acceso empresa entorno operativo">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Administrar Cuentas</h2>
                <p class="text-muted">Este modulo sirve para manejar quienes pueden entrar al entorno operativo y que permisos tienen.</p>
                <ul class="help-list-tight mb-0">
                    <li><strong>Usuario activo:</strong> puede ingresar y operar.</li>
                    <li><strong>Usuario inactivo:</strong> la cuenta queda frenada, aunque el registro no se borra.</li>
                    <li><strong>Administrador:</strong> tiene acceso ampliado dentro del tenant.</li>
                    <li><strong>Cuando cambiar contraseña:</strong> si la persona pierde acceso, cambia de rol o por seguridad.</li>
                </ul>
            </div>
        </div>

        <div class="card rxn-form-card help-card" id="modulo-configuracion" data-help-item data-help-text="configuracion empresa slug branding smtp tango connect mail tienda url publica identidad logo colores">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Configuracion de la Empresa</h2>
                <p class="text-muted"><?= $isCrm ? 'Esta pantalla controla parametros propios del entorno CRM. Aunque muchos campos se parecen a Tiendas, CRM guarda su configuracion en un origen separado.' : 'Esta pantalla controla la identidad visible y varias conexiones importantes del entorno.' ?></p>
                <ul class="help-list-tight mb-4">
                    <?php if (!$isCrm): ?><li><strong>Slug:</strong> es la parte de la URL publica que identifica a la empresa dentro de la tienda.</li><?php endif; ?>
                    <li><strong>Branding:</strong> <?= $isCrm ? 'en CRM aplica al fallback visual y recursos internos del entorno.' : 'logo, colores, textos o referencias visibles para clientes.' ?></li>
                    <li><strong>SMTP:</strong> define como sale el correo desde la plataforma.</li>
                    <li><strong>Tango Connect:</strong> conecta el entorno con el ERP o sistema comercial.</li>
                </ul>
                <div class="help-highlight">
                    <strong>Consejo practico:</strong> si algo falla en correos o integraciones, este suele ser el primer lugar que conviene revisar. En CRM, la configuracion queda separada de Tiendas aunque haya sido clonada al inicio para no arrancar vacio.
                </div>
            </div>
        </div>

        <div class="card rxn-form-card help-card" data-help-item data-help-text="perfil preferencias visuales tema fuente panel personal usuario apariencia entorno">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Mi Perfil</h2>
                <p class="text-muted mb-0">Es tu espacio personal. No cambia la tienda ni la empresa: solo ajusta como ves vos el panel, por ejemplo tema o tamaño visual.</p>
            </div>
        </div>

        <div class="card rxn-form-card help-card" id="modulo-buscadores" data-help-item data-help-text="buscar buscadores filtros sugerencias enter aplicar limpiar resultados listado coincidencias campo">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Como funcionan los buscadores</h2>
                <p class="text-muted">Este comportamiento es igual en los modulos principales para que la experiencia sea predecible.</p>
                <ul class="help-list-tight mb-4">
                    <li><strong>No filtra solo mientras escribis.</strong> Esto evita movimientos raros del listado y ayuda a trabajar mejor cuando hay muchos registros.</li>
                    <li><strong>Puede mostrar sugerencias.</strong> Mientras escribis, aparecen hasta tres coincidencias utiles.</li>
                    <li><strong>Elegir sugerencia no aplica el filtro.</strong> Solo completa el texto del input.</li>
                    <li><strong>Para filtrar de verdad:</strong> Enter o boton Buscar / Aplicar.</li>
                    <li><strong>Limpiar filtros:</strong> vuelve al listado general.</li>
                </ul>
                <h3 class="h6 fw-bold mb-2">Que significa “Buscar por”</h3>
                <p class="mb-0">Te deja decirle al sistema donde queres buscar: por ejemplo por ID, nombre, email, SKU, documento o todos los campos disponibles segun el modulo.</p>
            </div>
        </div>

        <div class="card rxn-form-card help-card" data-help-item data-help-text="mensajes exito error alerta guardar cambios confirmacion demora sincronizacion correo tango">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Mensajes, alertas y tiempos de espera</h2>
                <ul class="help-list-tight mb-0">
                    <li><strong>Mensaje verde o de exito:</strong> el sistema acepto y guardo la accion.</li>
                    <li><strong>Mensaje rojo o de error:</strong> algo no pudo completarse y conviene leer el texto exacto.</li>
                    <li><strong>Demora normal:</strong> acciones con Tango, correo o sincronizacion pueden tardar mas que una simple edicion.</li>
                    <li><strong>Si una accion parece congelada:</strong> revisa si hay confirmaciones del navegador, mensajes ocultos o procesos externos involucrados.</li>
                </ul>
            </div>
        </div>

        <div class="card rxn-form-card help-card" data-help-item data-help-text="preguntas frecuentes faq duda comun no encuentro producto cliente pedido correo url tienda">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Preguntas frecuentes</h2>
                <div class="accordion" id="helpFaq">
                    <div class="accordion-item">
                        <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">No encuentro un producto. Que reviso primero?</button></h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#helpFaq"><div class="accordion-body">Revisa si el articulo fue sincronizado, si esta activo y si tiene datos minimos cargados. Si hace falta, ejecuta la sincronizacion correspondiente.</div></div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">Un pedido tiene error. Donde miro?</button></h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#helpFaq"><div class="accordion-body">Empieza por el detalle del pedido. Despues revisa si el cliente esta bien resuelto en Tango y si los articulos involucrados existen correctamente.</div></div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">Cambie algo y no se reflejo. Que puede haber pasado?</button></h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#helpFaq"><div class="accordion-body">Puede que no hayas guardado con el boton principal, que el cambio dependa de una sincronizacion posterior o que el dato venga sobrescrito desde una integracion externa.</div></div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">Para que sirve el slug?</button></h2>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#helpFaq"><div class="accordion-body">Es la parte identificadora de la direccion publica de la tienda. Cada empresa debe tener uno unico.</div></div>
                    </div>
                </div>
            </div>
        </div>

        <div id="help-empty" class="alert alert-secondary mt-4 help-empty">
            No encontré coincidencias en la ayuda con ese texto. Probá con palabras más simples como <code>pedido</code>, <code>cliente</code>, <code>buscar</code>, <code>smtp</code> o <code>tango</code>.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            var input = document.getElementById('help-search');
            var clearButton = document.getElementById('help-clear');
            var items = Array.prototype.slice.call(document.querySelectorAll('[data-help-item]'));
            var emptyState = document.getElementById('help-empty');

            if (!input || !clearButton || !items.length || !emptyState) {
                return;
            }

            function normalize(value) {
                return (value || '').toLowerCase().trim();
            }

            function applyFilter() {
                var term = normalize(input.value);
                var visibleCount = 0;

                items.forEach(function (item) {
                    var text = normalize(item.getAttribute('data-help-text') + ' ' + item.textContent);
                    var show = term === '' || text.indexOf(term) !== -1;
                    item.style.display = show ? '' : 'none';
                    if (show) {
                        visibleCount += 1;
                    }
                });

                emptyState.classList.toggle('is-visible', visibleCount === 0);
            }

            input.addEventListener('input', applyFilter);
            clearButton.addEventListener('click', function () {
                input.value = '';
                applyFilter();
                input.focus();
            });
        }());
    </script>
</body>
</html>
