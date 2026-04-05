<?php
$pageTitle = 'RXN Suite';
ob_start();
?>
<?php
    $dashboardPath = $dashboardPath ?? '/mi-empresa/dashboard';
    $environmentLabel = $environmentLabel ?? 'Entorno Operativo';
    $area = $area ?? 'tiendas';
    $isCrm = $area === 'crm';
    ?>
    <div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell" style="max-width: 1160px;">
        <div class="rxn-module-header mb-4">
            <div>
                <span class="help-kicker mb-3"><i class="bi bi-life-preserver"></i> Ayuda para humanos</span>
                <h1 class="fw-bold mb-1">Centro de Ayuda del Entorno Operativo</h1>
                
            </div>
            <div class="rxn-module-actions">

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
                    <a href="#modulo-notas" class="help-anchor"><strong>Notas (Bitácora)</strong><br><small class="text-muted">Cuaderno operativo de seguimiento interno.</small></a>
                    <a href="#modulo-anura" class="help-anchor"><strong>Telefonía (Anura)</strong><br><small class="text-muted">Historial y llamadas entrantes automáticas.</small></a>
                    <a href="#modulo-configuracion" class="help-anchor"><strong>Configuracion</strong><br><small class="text-muted"><?= $isCrm ? 'Parametros operativos propios de CRM, SMTP y Tango.' : 'Slug, branding, SMTP y Tango.' ?></small></a>
                    <a href="#modulo-orden-tarjetas" class="help-anchor"><strong>Orden de Tarjetas</strong><br><small class="text-muted">Como acomodar el menu sin afectar otros entornos.</small></a>
                    <a href="#modulo-buscadores" class="help-anchor"><strong>Buscadores y Atajos</strong><br><small class="text-muted">Como buscar bien sin perder tiempo usando el teclado.</small></a>
                    <a href="#novedades" class="help-anchor"><strong>Novedades</strong><br><small class="text-muted">Multi-empresa, seguridad, zoom e impresión.</small></a>
                </div>
            </div>
        </div>

        <div class="card rxn-form-card help-card" id="novedades" data-help-item data-help-text="lo nuevo novedades actualizacion mejoras atajo teclado multiempresa multi-empresa zoom printforms csrf token expirado seguridad ingresar">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Novedades (¡Lo último!)</h2>
                <p class="text-muted">Un resumen súper fácil de las nuevas funcionalidades agregadas para ahorrarte clics y protegerte mejor.</p>
                <ul class="help-list-tight mb-0">
                    <li><strong>Operación Multi-Empresa rápida:</strong> Si administras más de una, en tu listado de empresas ahora hay un botón "Ingresar". Al hacerlo, el sistema se pone el sombrero de esa compañía para que operes todo directamente ahí sin tener que desloguearte.</li>
                    <li><strong>Zoom en impresión:</strong> Para los que sufren diseñando formularios (PrintForms), agregamos controles de Zoom (acercar, alejar, ajustar ventana). Ya no hay que pelear para ver la hoja A4 completa.</li>
                    <li><strong>Seguridad vigilando (Token Expirado):</strong> Hay nuevos guardias ciegos cuidando tu sesión. Si vas a buscarte un café, volves a la hora e intentas modificar un pedido y te sale "Token Expirado", es normal, ¡no rompiste nada! Sólo apretá `F5` para recargar la página e intentar de nuevo.</li>
                </ul>
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
                        <li><strong>Tiempo bruto / neto:</strong> el sistema los calcula automáticamente.</li>
                        <li><strong>Diagnostico y Motivo:</strong> escribí con claridad técnica acá.</li>
                        <li><strong>Atajo rápido:</strong> Apretá <code>ALT + P</code> mientras estés editando para enviarlo directamente a Tango.</li>
                        <li><strong>Seguridad de enviado:</strong> Cuando se envía a Tango exitosamente, asume el Número Oficial y se <strong>bloquea</strong> (ya nadie lo puede modificar, para cuidar la consistencia). Si hay rechazo dirá <code>ERROR API</code> y sí te dejará reintentar.</li>
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

        <div class="card rxn-form-card help-card" id="modulo-notas" data-help-item data-help-text="notas bitacora anotador interno conversacion seguimiento registro interno comercial crm">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Notas Internas (Bitácora)</h2>
                <p class="text-muted">Es un cuaderno digital interno para la empresa. Útil para dejar constancia de seguimientos o temas administrativos rápidos.</p>
                <ul class="help-list-tight mb-0">
                    <li><strong>Para qué sirve:</strong> Para registrar un "llamó el cliente consultando", "hay que revisar el stock", etc.</li>
                    <li><strong>Privacidad:</strong> Todo lo que escribís en las Notas es puramente <strong>interno</strong>. El cliente nunca verá esto en la parte pública.</li>
                    <li><strong>Búsqueda combinada:</strong> Cuenta con un buscador súper rápido para no perder de vista ninguna anotación histórica importante.</li>
                </ul>
            </div>
        </div>

        <div class="card rxn-form-card help-card" id="modulo-anura" data-help-item data-help-text="anura telefono llamadas entrantes registro historial atencion central telefonica call webhook">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Llamadas (Central Anura)</h2>
                <p class="text-muted">Muestra de forma automática el historial de las llamadas que llegan a tus internos telefónicos.</p>
                <ul class="help-list-tight mb-0">
                    <li><strong>Todo es automático:</strong> No tenés que cargar nada a mano. Si entra una llamada en tu central Anura, se impacta sola.</li>
                    <li><strong>Multi-Empresa:</strong> Si gestionás varias empresas, las llamadas se dividen solas según cómo apuntaste el enlace de sistema en la central.</li>
                    <li><strong>¿Por qué puede no aparecer una llamada?:</strong> Si alguien llamó pero no figura en tu listado, lo más lógico es que falte configurar o activar el "Webhook" en el portal propio del Anura.</li>
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
                <h3 class="h6 fw-bold mb-2">Atajos de Teclado (¡Chau Mouse!)</h3>
                <ul class="help-list-tight mb-4">
                    <li><strong>Buscar rápido:</strong> En cualquier pantalla, tocá la tecla `F3` o la barra `/` y vas directo a la caja de búsqueda.</li>
                    <li><strong>Moverse en el Dashboard:</strong> Cuando buscás un módulo en las tarjetas de inicio, usa las <strong>flechas para arriba/abajo o los lados</strong> para saltar entre las opciones coloreadas y tocá `Enter` para entrar directo al que elegiste.</li>
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

    
<?php
$content = ob_get_clean();
ob_start();
?>
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
    <script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
