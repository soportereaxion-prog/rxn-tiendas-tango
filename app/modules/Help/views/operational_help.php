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
    <div class="container-fluid mt-2 mb-5 rxn-responsive-container rxn-form-shell">
        <div class="rxn-module-header mb-4">
            <div>
                <span class="help-kicker mb-3"><i class="bi bi-life-preserver"></i> Ayuda para humanos</span>
                <h1 class="fw-bold mb-1">Centro de Ayuda del Entorno Operativo</h1>
                
            </div>
            <div class="rxn-module-actions">

                <span class="text-muted small">Hola, <?= htmlspecialchars((string) $userName) ?></span>
                <a href="<?= htmlspecialchars($dashboardPath) ?>" class="btn btn-outline-secondary btn-sm" title="Volver al Entorno"><i class="bi bi-arrow-left"></i> Volver</a>
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
                        <a href="#modulo-presupuestos" class="help-anchor"><strong>Presupuestos</strong><br><small class="text-muted">Armado, carga rápida por teclado y Sync Catálogos.</small></a>
                    <?php else: ?>
                        <a href="#modulo-clientes" class="help-anchor"><strong>Clientes Web</strong><br><small class="text-muted">Clientes, datos, vinculo con Tango.</small></a>
                        <a href="#modulo-pedidos" class="help-anchor"><strong>Pedidos Web</strong><br><small class="text-muted">Seguimiento, estados y reproceso.</small></a>
                    <?php endif; ?>
                    <a href="#modulo-usuarios" class="help-anchor"><strong>Administrar Cuentas</strong><br><small class="text-muted">Altas, permisos y accesos internos.</small></a>
                    <a href="#modulo-notas" class="help-anchor"><strong>Notas (Bitácora)</strong><br><small class="text-muted">Cuaderno operativo de seguimiento interno.</small></a>
                    <a href="#modulo-anura" class="help-anchor"><strong>Telefonía (Anura)</strong><br><small class="text-muted">Historial y llamadas entrantes automáticas.</small></a>
                    <?php if ($isCrm): ?>
                        <a href="#modulo-tratativas" class="help-anchor"><strong>Tratativas</strong><br><small class="text-muted">Oportunidades comerciales, estados, cliente y vínculo con PDS/Presupuestos.</small></a>
                        <a href="#modulo-agenda" class="help-anchor"><strong>Agenda CRM</strong><br><small class="text-muted">Calendario unificado, filtros, fullscreen, Google Calendar y colores.</small></a>
                        <a href="#modulo-horas" class="help-anchor"><strong>Horas (Turnero)</strong><br><small class="text-muted">Registro de tiempo trabajado, mobile-first, carga diferida y vínculo a tratativas.</small></a>
                        <a href="#modulo-mail-masivos" class="help-anchor"><strong>Mail Masivos</strong><br><small class="text-muted">Plantillas, reportes, envíos y bloque de novedades.</small></a>
                    <?php endif; ?>
                    <a href="#modulo-notificaciones" class="help-anchor"><strong>Notificaciones</strong><br><small class="text-muted">Campanita, inbox, marcar como leídas y filtros.</small></a>
                    <a href="#modulo-mobile" class="help-anchor"><strong>Uso desde el celular</strong><br><small class="text-muted">Menú hamburguesa, navegación y tips mobile.</small></a>
                    <a href="#modulo-rxnlive" class="help-anchor"><strong>RXN Live (Analítica)</strong><br><small class="text-muted">Tableros, vistas guardadas, pivot, chart y export.</small></a>
                    <a href="#modulo-mi-perfil" class="help-anchor"><strong>Mi Perfil</strong><br><small class="text-muted">Tema claro/oscuro, color de calendario y preferencias.</small></a>
                    <a href="#modulo-configuracion" class="help-anchor"><strong>Configuracion</strong><br><small class="text-muted"><?= $isCrm ? 'Parametros operativos propios de CRM, SMTP, Tango y Google Calendar.' : 'Slug, branding, SMTP y Tango.' ?></small></a>
                    <a href="#modulo-orden-tarjetas" class="help-anchor"><strong>Orden de Tarjetas</strong><br><small class="text-muted">Como acomodar el menu sin afectar otros entornos.</small></a>
                    <a href="#modulo-buscadores" class="help-anchor"><strong>Buscadores y Atajos</strong><br><small class="text-muted">Como buscar bien sin perder tiempo usando el teclado.</small></a>
                    <a href="#novedades" class="help-anchor"><strong>Novedades</strong><br><small class="text-muted">Lo último: split view, adjuntos, RxnLive, tema claro y más.</small></a>
                </div>
            </div>
        </div>

        <div class="card rxn-form-card help-card" id="novedades" data-help-item data-help-text="lo nuevo novedades actualizacion mejoras atajo teclado multiempresa multi-empresa zoom printforms csrf token expirado seguridad ingresar correo email outlook copiar duplicar pds dropddown selectores split view notas explorer adjuntos archivos rxnlive analitica vistas compartidas tema claro oscuro escape volver unificado fullwidth ancho">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Novedades (¡Lo último!)</h2>
                <p class="text-muted">Un resumen súper fácil de las funcionalidades agregadas en las últimas releases para ahorrarte clics, ordenar la operación y mejorar la lectura.</p>
                <ul class="help-list-tight mb-0">
                    <li><strong>Turnero mobile-first (Horas CRM):</strong> Módulo nuevo para que los operadores registren tiempo de trabajo desde el celular: botón grande <em>Iniciar/Cerrar</em>, contador en vivo del día, geolocalización opcional, vínculo a tratativa y reflejo automático en la Agenda CRM (color teal). También permite carga diferida (post-facto) para turnos olvidados. Accesible desde el menú hamburguesa del topbar.</li>
                    <li><strong>Notificaciones in-app (campanita 🔔):</strong> Sistema nuevo de avisos internos. Aparece en el topbar con badge rojo cuando hay no leídas. Click → dropdown con las últimas 8. "Ver todas" te lleva al inbox completo en <code>/notifications</code> con filtros <em>Todas / No leídas / Leídas</em>, marcar como leídas en bloque y soft-delete. Anti-duplicado de 24hs — nada de spam.</li>
                    <li><strong>Notas estilo Explorer (Split View):</strong> El listado de Notas pasó a un modo master-detail: a la izquierda buscás y elegís, a la derecha se abre el contenido al toque, sin recargar la página. Te movés con las flechas <kbd>↑</kbd> <kbd>↓</kbd> (o <kbd>j</kbd>/<kbd>k</kbd>), abrís a editar con <kbd>Enter</kbd> y la nota activa queda recordada al volver al listado.</li>
                    <li><strong>Adjuntos en Notas y Presupuestos:</strong> Ya podés subir archivos (hasta 10 por registro, 100 MB c/u) por arrastrar y soltar. Las imágenes tienen vista previa con un click 👁. El sistema rechaza archivos peligrosos (.exe, .php, etc.) y guarda todo aislado por empresa.</li>
                    <li><strong>RXN Live (Analítica) más prolijo:</strong> El tablero ya no se desborda del viewport — el footer de paginación queda siempre pegado abajo. Las vistas guardadas ahora se ven entre todos los usuarios de la misma empresa (cada uno ve quién es el dueño y solo el dueño puede pisar/borrar). Los números muestran 4 decimales por defecto.</li>
                    <li><strong>Tema claro/oscuro por usuario:</strong> Desde <em>Mi Perfil</em> elegís tu tema preferido. Se sincroniza al toque entre pestañas abiertas y queda guardado en tu cuenta — no en el navegador.</li>
                    <li><strong>Botón Volver unificado y Escape contextual:</strong> En toda la suite el botón <em>Volver</em> está en el mismo lugar (arriba a la derecha) y con el mismo estilo. Apretar <kbd>Esc</kbd> hace lo mismo que el Volver — si tenés cambios sin guardar te pregunta antes para que no pierdas nada.</li>
                    <li><strong>Flujo Tratativa → PDS/Presupuesto/Nota:</strong> Cuando creás un documento desde una tratativa, el botón <em>Guardar</em> te deja en el documento (para que sigas trabajando) y el <em>Volver</em> te lleva al detalle de la tratativa. Cero saltos accidentales.</li>
                    <li><strong>Mail Masivos con bloque de novedades:</strong> En el form de "Nuevo envío" hay un paso opcional <em>"Bloque de contenido"</em> que mete las novedades del producto como cards prolijas en el cuerpo del mail. Compatible con Outlook, Gmail y Apple Mail.</li>
                    <li><strong>Vistas a ancho completo:</strong> Liberamos el cap de 1100/1400 px que tenían los formularios y listados. Ahora aprovechás toda la pantalla en monitores grandes.</li>
                    <li><strong>Clonación Automática de PDS:</strong> Olvidate de reescribir todo cuando vayas de la tienda matriz de un cliente a sus sucursales. Podés "Copiar" un pedido de servicio finalizado y crear uno idéntico con un clic.</li>
                    <li><strong>Selectores rápidos con teclado:</strong> Los menús desplegables (Dropdowns) para buscar clientes o artículos se dejan domar con las flechas y <kbd>Enter</kbd>.</li>
                    <li><strong>Seguridad vigilando (Token Expirado):</strong> Si volvés del café y te sale "Token Expirado" al modificar un pedido, no rompiste nada — apretá <kbd>F5</kbd> para recargar e intentar de nuevo.</li>
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
                <ul class="help-list-tight mb-3">
                    <li><strong>Sync Articulos:</strong> trae o actualiza el maestro de articulos.</li>
                    <li><strong>Sync Precios:</strong> actualiza precios desde el sistema comercial.</li>
                    <li><strong>Sync Stock:</strong> refresca existencias locales.</li>
                    <li><strong>Sync Total:</strong> ejecuta una cadena mas completa de actualizaciones. Puede tardar mas.</li>
                    <li><strong>Auditoría RXN Sync:</strong> abre la consola central de sincronización (clientes, artículos, pedidos) con auditoría bidireccional contra Tango.</li>
                </ul>

                <h3 class="h6 fw-bold mb-2">Push y Pull desde la edición individual</h3>
                <ul class="help-list-tight mb-0">
                    <li><strong>Botón Push (subir a Tango):</strong> primero <em>guarda los cambios del formulario</em> en la base local y después intenta actualizar el registro en Tango. Sirve para corregir un nombre/descripción y mandarlo de un toque.</li>
                    <li><strong>Botón Pull (bajar de Tango):</strong> trae el registro actualizado desde Tango y reemplaza la copia local. Se usa cuando alguien tocó el dato directo en el ERP y querés reflejarlo acá.</li>
                    <li><strong>Botón Guardar modificaciones:</strong> persiste solo localmente, sin tocar Tango. Útil si Connect está caído o si querés guardar a medias.</li>
                    <li><strong>Cómo lee Tango el Push:</strong> el sistema arranca del registro completo que tiene Tango y solo sobreescribe los campos que el operador puede editar (nombre/descripción para artículos; razón social, CUIT, email, teléfono y dirección para clientes). Los IDs internos, códigos y campos comerciales del ERP nunca se modifican desde acá.</li>
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
                        <li><strong>Navegación por teclado en selectores:</strong> Cliente, Artículo y Clasificación se abren con <kbd>Enter</kbd> mostrando los primeros resultados sin necesidad de escribir. Navegás con las flechas ↑ ↓, confirmás con <kbd>Enter</kbd> y <kbd>Tab</kbd> te lleva limpio al siguiente campo del formulario.</li>
                        <li><strong>Salida segura con Escape:</strong> Al apretar <kbd>Esc</kbd> dentro del pedido, el sistema te pide confirmación antes de abandonar sin guardar — evita salidas accidentales que te hagan perder los cambios.</li>
                        <li><strong>Seguridad de enviado:</strong> Cuando se envía a Tango exitosamente, asume el Número Oficial y se <strong>bloquea</strong> (ya nadie lo puede modificar, para cuidar la consistencia). Si hay rechazo dirá <code>ERROR API</code> y sí te dejará reintentar.</li>
                    </ul>
                    <div class="help-highlight">
                        <strong>Dato importante:</strong> el pedido guarda snapshot de cliente y articulo. Eso significa que el historico sigue legible aunque mas adelante cambie el origen de datos.
                    </div>
                </div>
            </div>

            <div class="card rxn-form-card help-card" id="modulo-presupuestos" data-help-item data-help-text="crm presupuestos presupuesto cotizacion articulo teclado carga rapida sync catalogos sincronizar listas precios vendedores condicion venta adjuntos archivos arrastrar imagenes preview tratativa volver guardar">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h4 fw-bold mb-3">Presupuestos</h2>
                    <p class="text-muted">Este módulo te permite armar propuestas comerciales ágiles para tus clientes y prospectos.</p>
                    <ul class="help-list-tight mb-4">
                        <li><strong>Carga ágil por teclado:</strong> Puedes cargar renglones enteros casi sin usar el ratón. En el buscador escribes el material, confirmas con <kbd>Enter</kbd> (o seleccionas de la lista), luego usas <kbd>TAB</kbd> para pasear por Cantidad, Precio y Bonificación, y presionas de nuevo <kbd>Enter</kbd> para agregar el renglón al cuerpo.</li>
                        <li><strong>Apertura rápida de selectores con Enter:</strong> Al apretar <kbd>Enter</kbd> sobre los buscadores de Cliente o Artículo aparecen directamente los primeros resultados sin necesidad de escribir. Navegás con ↑ ↓ y confirmás con <kbd>Enter</kbd>.</li>
                        <li><strong>Botón Sync Catálogos:</strong> Está en las opciones del presupuesto. Úsalo para actualizar rápido de Tango las Listas de Precios, Vendedores, Condiciones de Venta y Transporte. El sistema asume tu última elección.</li>
                        <li><strong>Resolución de precio:</strong> Si el artículo que buscaste tiene precio en la lista de precios seleccionada, la barra de carga te lo autocompleta al instante. Si no, queda en "0" para edición manual.</li>
                        <li><strong>Salida segura con Escape:</strong> Apretar <kbd>Esc</kbd> dentro del presupuesto te lleva a Volver. Si tenés cambios sin guardar, el sistema te pregunta antes de salir.</li>
                        <li><strong>Si venís desde una Tratativa:</strong> El botón <em>Guardar</em> te deja en el presupuesto (para que sigas trabajando) y el <em>Volver</em> te lleva al detalle de la tratativa.</li>
                    </ul>

                    <h3 class="h6 fw-bold mb-2">Adjuntos</h3>
                    <ul class="help-list-tight mb-0">
                        <li><strong>Cómo subir:</strong> Arrastrá los archivos al panel de adjuntos al final del formulario, o clickeá para abrir el explorador.</li>
                        <li><strong>Límites:</strong> Hasta 10 archivos por presupuesto, 100 MB por archivo, 100 MB acumulados.</li>
                        <li><strong>Imágenes con preview:</strong> Las imágenes tienen botón 👁 para verlas en grande sin descargar.</li>
                        <li><strong>Seguridad:</strong> Archivos ejecutables o sospechosos (.exe, .php, .html, .svg, etc.) son rechazados automáticamente.</li>
                        <li><strong>Uso interno por ahora:</strong> Los adjuntos quedan dentro del CRM (no se mandan automáticamente por mail al cliente). Si querés enviarlos, descargalos y adjuntalos manualmente al correo.</li>
                    </ul>
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

        <div class="card rxn-form-card help-card" id="modulo-notas" data-help-item data-help-text="notas bitacora anotador interno conversacion seguimiento registro interno comercial crm split view master detail explorer flechas teclado adjuntos archivos arrastrar imagenes preview vista previa papelera importar exportar xlsx tratativa cliente tags etiquetas">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Notas Internas (Bitácora)</h2>
                <p class="text-muted">Es un cuaderno digital interno para la empresa. Útil para dejar constancia de seguimientos, conversaciones con clientes o temas administrativos.</p>

                <h3 class="h6 fw-bold mt-3 mb-2">Para qué sirve</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Bitácora interna:</strong> Registrar un "llamó el cliente consultando", "hay que revisar el stock", "quedó pendiente de respuesta", etc.</li>
                    <li><strong>Privacidad:</strong> Todo lo que escribís en las Notas es <strong>puramente interno</strong>. El cliente nunca lo ve en la parte pública.</li>
                    <li><strong>Vinculación opcional:</strong> Una nota puede ir asociada a un cliente, a una tratativa o quedar suelta. Se le pueden poner etiquetas (tags) para clasificar.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Vista Split (master-detail estilo Explorer)</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Cómo se ve:</strong> A la izquierda la lista de notas con su buscador, tabs Activos/Papelera y paginación. A la derecha el detalle de la nota seleccionada, en vivo, sin recargar.</li>
                    <li><strong>Click en una nota:</strong> Se abre en el panel derecho al instante.</li>
                    <li><strong>Búsqueda en vivo:</strong> Mientras escribís, la lista filtra sola con un pequeño retraso para no marear. Apretás <kbd>Enter</kbd> y saltás directo a la primera coincidencia.</li>
                    <li><strong>Persistencia:</strong> Al volver al listado desde otro lado, la última nota que estabas mirando queda seleccionada.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Atajos de teclado</h3>
                <ul class="help-list-tight mb-3">
                    <li><kbd>↓</kbd> / <kbd>j</kbd>: ir a la nota siguiente.</li>
                    <li><kbd>↑</kbd> / <kbd>k</kbd>: ir a la nota anterior.</li>
                    <li><kbd>Enter</kbd> (con foco fuera del buscador): editar la nota activa.</li>
                    <li><kbd>Enter</kbd> en el buscador: aplicar filtro y bajar el foco a la lista.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Adjuntos</h3>
                <ul class="help-list-tight mb-0">
                    <li><strong>Arrastrar y soltar:</strong> Soltá los archivos sobre el panel de adjuntos del detalle de la nota. También podés clickear el área para abrir el explorador.</li>
                    <li><strong>Límites:</strong> Hasta 10 archivos por nota, 100 MB por archivo, 100 MB acumulados.</li>
                    <li><strong>Vista previa:</strong> Las imágenes tienen botón 👁 para verlas en grande sin descargar. El resto se descarga directo.</li>
                    <li><strong>Seguridad:</strong> Los archivos peligrosos (.exe, .php, .bat, .html, .svg, etc.) son rechazados automáticamente, aunque les cambien la extensión.</li>
                    <li><strong>Borrado:</strong> Mover una nota a la papelera <strong>NO</strong> borra los adjuntos. Sólo el borrado definitivo desde la papelera elimina los archivos físicamente.</li>
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

        <div class="card rxn-form-card help-card" id="modulo-rxnlive" data-help-item data-help-text="rxn live analitica analytics tablero dashboard powerbi datos pivot tabla dinamica chart grafico vista guardada compartida empresa duenio export excel xlsx columnas filtros ventas pedidos servicio clientes integracion tango decimales viewport pantalla completa">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">RXN Live (Analítica)</h2>
                <p class="text-muted">RXN Live es el módulo analítico de la suite — pensalo como el "PowerBI interno" para mirar datos de la operación: ventas históricas, pedidos de servicio, clientes, integración con Tango y más. Cada dataset se ve en tabla plana o tabla dinámica (pivot), con chart al costado.</p>

                <h3 class="h6 fw-bold mt-3 mb-2">Datasets disponibles</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Ventas Histórico:</strong> Análisis de ventas por período, cliente, vendedor o artículo.</li>
                    <li><strong>Pedidos de Servicio (PDS):</strong> Tiempos, técnicos, estados y descuentos.</li>
                    <li><strong>Análisis de Clientes:</strong> Segmentación por compras, frecuencia y comportamiento.</li>
                    <li><strong>Integración Tango:</strong> Estado del intercambio de datos con el ERP.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Vista Plana y Tabla Dinámica</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Vista Plana:</strong> Las filas tal cual están en la base, con orden, filtros y totales en el pie.</li>
                    <li><strong>Tabla Dinámica (Pivot):</strong> Cruzás dimensiones (filas/columnas) y aplicás operaciones (suma, promedio, count). Tipo Excel.</li>
                    <li><strong>Chart al costado:</strong> Gráfico interactivo que se actualiza con la pivot. Podés mostrar/ocultar la tabla o el chart con los toggles.</li>
                    <li><strong>Decimales:</strong> Por defecto se muestran 4 decimales para no perder precisión (excepto la operación COUNT, que va en enteros).</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Vistas guardadas (compartidas por empresa)</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Qué es una vista:</strong> Una combinación de filtros, columnas visibles, anchos, orden y configuración de pivot/chart, guardada con un nombre.</li>
                    <li><strong>Compartidas:</strong> Todos los usuarios de la misma empresa ven las mismas vistas guardadas. El dueño aparece al costado del nombre (ej: <em>Mis PDS — Gaby</em>).</li>
                    <li><strong>Quién puede pisar/borrar:</strong> Sólo el dueño. Si querés modificar una vista ajena, usá <em>Nueva Vista</em> para duplicarla y trabajar sobre tu propia copia.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Exportar a Excel</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Botón Exportar:</strong> Genera un <code>.xlsx</code> con las columnas visibles, en el orden que tenés configurado, respetando los anchos de columna que ajustaste.</li>
                    <li><strong>Filtros aplicados:</strong> Lo que exportás es exactamente lo que ves filtrado — no el dataset completo.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Encuadre del viewport</h3>
                <ul class="help-list-tight mb-0">
                    <li>El tablero se ajusta automáticamente para que el footer de paginación quede pegado al borde inferior de la ventana, sin scroll del cuerpo. Funciona igual con tablas chicas (Ventas Histórico) o densas (PDS con muchas filas).</li>
                    <li>Si redimensionás la ventana o cambiás entre tabla y pivot, el encuadre se recalcula solo.</li>
                </ul>
            </div>
        </div>

        <?php if ($isCrm): ?>
        <div class="card rxn-form-card help-card" id="modulo-tratativas" data-help-item data-help-text="tratativas tratativa oportunidad deal negociacion caso comercial cliente pds presupuesto nota vincular estado ganada perdida pausada probabilidad valor cierre flujo contextual guardar volver escape">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Tratativas (Oportunidades Comerciales)</h2>
                <p class="text-muted">Una tratativa agrupa bajo un mismo caso comercial los PDS, Presupuestos y Notas que hacen a una negociacion con un cliente.</p>
                <ul class="help-list-tight mb-3">
                    <li><strong>Crear tratativa:</strong> Desde el dashboard CRM, click en <em>Tratativas</em> y luego <em>Nueva Tratativa</em>. Titulo obligatorio, cliente opcional.</li>
                    <li><strong>Vincular PDS, Presupuestos o Notas:</strong> Desde el <em>detalle</em> de una tratativa, usa los botones <em>Nuevo PDS</em>, <em>Nuevo Presupuesto</em> o <em>Nueva Nota</em>. Se crean ya vinculados.</li>
                    <li><strong>Estados:</strong> Nueva, En curso, Ganada, Perdida, Pausada. Si cerras como <em>ganada</em> o <em>perdida</em>, el sistema te pide un motivo de cierre obligatorio.</li>
                    <li><strong>Probabilidad y valor:</strong> Campos opcionales que te ayudan a priorizar. La probabilidad va de 0 a 100%.</li>
                    <li><strong>Papelera:</strong> Al eliminar una tratativa definitivamente, los PDS, Presupuestos y Notas vinculados NO se borran, solo se desvinculan (quedan sueltos).</li>
                    <li><strong>Buscar cliente:</strong> En el formulario de tratativa, presiona <kbd>Enter</kbd>, <kbd>F3</kbd> o hace doble click en el campo Cliente para abrir el buscador Spotlight.</li>
                    <li><strong>Filtros:</strong> El listado tiene tabs por estado (Nueva, En curso, etc.) y papelera, ademas del buscador F3 universal. Los filtros persisten al navegar.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Flujo contextual desde una tratativa</h3>
                <p class="text-muted mb-2">Cuando creás un PDS, Presupuesto o Nota desde el detalle de una tratativa, el sistema entiende que estás trabajando dentro de ese caso:</p>
                <ul class="help-list-tight mb-0">
                    <li><strong>Botón Guardar:</strong> Te deja en el documento (PDS / Presupuesto / Nota) para que sigas editando. No te saca al toque.</li>
                    <li><strong>Botón Volver:</strong> Te lleva al detalle de la tratativa de origen. Es el camino explícito para regresar.</li>
                    <li><strong>Tecla Escape:</strong> Hace lo mismo que Volver. Si tenés cambios sin guardar, te pregunta antes de salir para que no pierdas trabajo.</li>
                </ul>
            </div>
        </div>

        <div class="card rxn-form-card help-card" id="modulo-agenda" data-help-item data-help-text="agenda calendario fullcalendar eventos google calendar oauth sync sincronizar color usuario operador filtro pds presupuesto tratativa manual rescan historico pantalla completa fullscreen alt a">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Agenda CRM</h2>
                <p class="text-muted">Calendario visual que muestra todos los eventos del CRM en un solo lugar, con sync push a Google Calendar.</p>

                <h3 class="h6 fw-bold mt-4 mb-2">Uso basico</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Ver eventos:</strong> El calendario muestra automaticamente los PDS (azul), Presupuestos (verde), Tratativas (amarillo) y eventos manuales (gris). Cada uno se proyecta al crear o editar desde su modulo.</li>
                    <li><strong>Click en un evento:</strong> Si es PDS, Presupuesto o Tratativa, te lleva al modulo de origen. Si es manual, te lleva a editarlo.</li>
                    <li><strong>Click en un dia vacio:</strong> Abre el formulario de evento manual con la fecha precargada.</li>
                    <li><strong>Nuevo Evento:</strong> Boton en la esquina superior derecha para crear un evento manual con titulo, descripcion, ubicacion, color y rango de fechas.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Filtros</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Por tipo de origen:</strong> Las pills de colores (PDS, Presupuestos, Tratativas, Llamadas, Manuales) filtran que tipos de eventos se ven.</li>
                    <li><strong>Por operador:</strong> Las pills con nombre y color de cada usuario te permiten ver solo la agenda de un vendedor o equipo especifico.</li>
                    <li><strong>Persistencia:</strong> Los filtros se guardan en tu navegador (localStorage) y se restauran al volver a la agenda.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Colores de eventos</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Fondo del evento:</strong> El color del operador asignado (configurable desde <em>Mi Perfil</em> > <em>Color de calendario</em>).</li>
                    <li><strong>Borde izquierdo:</strong> El color del tipo de origen (azul=PDS, verde=Presupuesto, amarillo=Tratativa, violeta=Llamada, gris=Manual).</li>
                    <li><strong>Para elegir tu color:</strong> Ve a <em>Mi Perfil</em> desde cualquier entorno y busca la seccion <em>Agenda CRM</em>.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Atajos de teclado</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Alt + A:</strong> Activa/desactiva la vista en pantalla completa del calendario.</li>
                    <li><strong>Escape:</strong> Sale de pantalla completa si esta activa.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Rescan historico</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Boton "Rescan historico":</strong> Escanea todos los PDS, Presupuestos y Tratativas existentes y los proyecta como eventos en la agenda. Util al activar la agenda por primera vez en una empresa que ya operaba.</li>
                    <li>Es idempotente: podes darlo varias veces sin duplicar eventos.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Google Calendar</h3>
                <ul class="help-list-tight mb-0">
                    <li><strong>Configurar credenciales:</strong> Desde <em>Configuracion CRM</em> o desde el panel de Google Calendar en la Agenda (icono de engranaje). Necesitas un <em>Client ID</em>, <em>Client Secret</em> y <em>Redirect URI</em> de <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a>.</li>
                    <li><strong>Pasos en Google Cloud Console:</strong>
                        <ol class="mt-1 mb-1">
                            <li>Crear un proyecto (o reusar uno existente).</li>
                            <li>Menu <em>APIs & Services</em> > <em>Library</em> > buscar <em>Google Calendar API</em> > <em>Enable</em>.</li>
                            <li>Menu <em>APIs & Services</em> > <em>OAuth consent screen</em> > configurar nombre de app, email de soporte, y agregar scope <code>calendar.events</code>.</li>
                            <li>Menu <em>APIs & Services</em> > <em>Credentials</em> > <em>Create Credentials</em> > <em>OAuth 2.0 Client ID</em> > tipo <em>Web application</em>.</li>
                            <li>En <em>Authorized redirect URIs</em> agregar exactamente: <code>https://tudominio.com/mi-empresa/crm/agenda/google/callback</code> (reemplaza <code>tudominio.com</code> por tu dominio real). Para desarrollo local: <code>http://localhost:9021/mi-empresa/crm/agenda/google/callback</code>.</li>
                            <li>Google te muestra el <em>Client ID</em> (formato <code>xxxxx.apps.googleusercontent.com</code>) y el <em>Client Secret</em> (formato <code>GOCSPX-xxxx</code>). Copialos al formulario de configuracion en rxn_suite.</li>
                        </ol>
                    </li>
                    <li><strong>Conectar:</strong> Una vez configuradas las credenciales, click en <em>Conectar con Google</em>. Google te pide autorizacion y al confirmar tu cuenta queda vinculada.</li>
                    <li><strong>Modos de sync:</strong>
                        <ul class="mt-1">
                            <li><em>Por usuario</em>: cada operador conecta su propia cuenta Google y ve sus eventos en su calendario personal.</li>
                            <li><em>Por empresa</em>: una sola cuenta Google compartida por toda la empresa.</li>
                            <li><em>Ambos</em>: los eventos se replican al calendario personal del operador Y al calendario corporativo simultaneamente.</li>
                        </ul>
                    </li>
                    <li><strong>Sync push-only:</strong> Los eventos se envian del CRM a Google Calendar cuando se crean o editan. Los cambios que hagas directamente en Google NO vuelven al CRM (sincronizacion unidireccional).</li>
                    <li><strong>Categorias en Google:</strong> Cada tipo de evento aparece con un color distinto en Google Calendar (PDS azul, Presupuesto verde, Tratativa amarillo, etc.) para diferenciarlos visualmente.</li>
                    <li><strong>Desconectar:</strong> En cualquier momento podes desconectar tu cuenta de Google desde la Agenda. Los eventos ya sincronizados en Google NO se borran.</li>
                </ul>
            </div>
        </div>

        <div class="card rxn-form-card help-card" id="modulo-mail-masivos" data-help-item data-help-text="mail masivos email broadcast envio masivo plantilla template reporte destinatarios bloque contenido novedades cliente final smtp variables placeholder bloque html outlook gmail apple mail">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Mail Masivos (CRM)</h2>
                <p class="text-muted">Módulo para diseñar y disparar envíos masivos de correo a tus clientes o prospectos. Trabaja en tres piezas: <em>Reportes</em> (de quién a quién), <em>Plantillas</em> (qué y cómo se ve) y <em>Envíos</em> (cuándo se manda y desde qué SMTP).</p>

                <h3 class="h6 fw-bold mt-3 mb-2">Reportes</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Reporte de destinatarios:</strong> Define a quiénes se les manda. El editor visual te deja elegir entidad raíz (clientes, contactos, etc.) y filtros para acotar el universo.</li>
                    <li><strong>Reporte de contenido (broadcast):</strong> Trae las filas que se renderizan como bloque dentro del cuerpo del mail. El más usado es <em>Novedades del producto</em> (tabla <code>customer_notes</code>).</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Plantillas</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Editor visual:</strong> Diseñás el HTML del mail con variables del destinatario (ej: <code>{{nombre}}</code>, <code>{{email}}</code>) que el sistema reemplaza por fila al disparar.</li>
                    <li><strong>Placeholder de bloque de contenido:</strong> Si insertás <code>{{Bloque.html}}</code> en el cuerpo, el sistema reemplaza eso una sola vez con las cards renderizadas del reporte de contenido elegido.</li>
                    <li><strong>Compatible con Outlook/Gmail/Apple Mail:</strong> Las cards de novedades usan tablas inline y colores por categoría (feature, mejora, seguridad, performance, fix visible) — todo email-safe.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Crear un envío</h3>
                <ol class="help-list-tight mb-3">
                    <li><strong>Paso 1 — Datos básicos:</strong> Nombre del envío, asunto del mail, plantilla.</li>
                    <li><strong>Paso 2 — Destinatarios:</strong> Reporte que define a quiénes se manda.</li>
                    <li><strong>Paso 3 — Bloque de contenido (opcional):</strong> Reporte broadcast que se inyecta en <code>{{Bloque.html}}</code>. Si no elegís ninguno, el placeholder queda vacío.</li>
                    <li><strong>Paso 4 — SMTP:</strong> Servidor desde el cual se mandan los mails (configurado en <em>Configuración CRM</em>).</li>
                    <li><strong>Paso 5 — Confirmar y disparar.</strong></li>
                </ol>

                <h3 class="h6 fw-bold mt-4 mb-2">Monitor y reportes</h3>
                <ul class="help-list-tight mb-0">
                    <li><strong>Estado del envío:</strong> En el monitor ves cuántos mails se mandaron, cuántos quedaron pendientes y cuántos fallaron, en vivo.</li>
                    <li><strong>Historial:</strong> Todos los envíos quedan registrados con fecha, plantilla usada y estadísticas de entrega.</li>
                </ul>
            </div>
        </div>

        <div class="card rxn-form-card help-card" id="modulo-horas" data-help-item data-help-text="horas turnero turno tiempo trabajado registro operador vivo en vivo iniciar cerrar contador geolocalizacion geo ubicacion diferido diferida post facto concepto tratativa vinculo agenda teal mobile celular celu movil anular listado inconsistencia">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Horas (Turnero CRM)</h2>
                <p class="text-muted">Módulo mobile-first para que los operadores registren el tiempo que laburan. Pensado para abrir desde el celular en la calle: botón grande, contador en vivo y geolocalización opcional.</p>

                <h3 class="h6 fw-bold mt-3 mb-2">Para qué sirve</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Registro vivo:</strong> Tocás <em>Iniciar turno</em>, el sistema guarda la hora y arranca un contador "Hoy llevás X:XX:XX". Cuando terminás, tocás <em>Cerrar turno</em> y confirmás.</li>
                    <li><strong>Un solo turno abierto a la vez:</strong> Si ya tenés uno abierto y tratás de abrir otro, el sistema te frena — evita el típico "me olvidé que tenía uno corriendo".</li>
                    <li><strong>Concepto opcional:</strong> Al iniciar podés describir qué estás haciendo (ej: <em>"Visita técnica — Cliente X"</em>). Se muestra en el listado del día y en la agenda.</li>
                    <li><strong>Vincular a tratativa (opcional):</strong> Si el turno es parte de una negociación en curso, elegís la tratativa del dropdown. Después aparece consolidado en el detalle de la tratativa (Fase 4).</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Geolocalización (opcional)</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Consentimiento del browser:</strong> La primera vez el celu te pregunta si permitís compartir ubicación. Si decís que sí, el sistema guarda lat/lng al iniciar y al cerrar.</li>
                    <li><strong>Si negás permiso:</strong> El turno se guarda igual, solo sin coordenadas. No bloquea la operación.</li>
                    <li><strong>Indicador visible:</strong> Abajo del botón principal te muestra el estado ("Pidiendo ubicación…" / "Ubicación lista" / "Sin ubicación").</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Cargar turno diferido (post-facto)</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Cuándo:</strong> Si trabajaste un turno y te olvidaste de registrarlo en vivo. Desde el turnero entrás a <em>Cargar turno diferido</em>.</li>
                    <li><strong>Qué carga:</strong> Inicio y fin manuales (datetime), concepto y tratativa opcional. El sistema valida que no se solape con otros turnos ya cargados.</li>
                    <li><strong>Geo inconsistente:</strong> Si cargás diferido más de 24hs después del turno real, se marca la fila con un flag <em>inconsistencia_geo</em> para que admin revise. Aparece como aviso amarillo en el listado.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Cruce de medianoche</h3>
                <p class="mb-3">Si empezás antes de las 00:00 y cerrás después, el turno se guarda como uno solo. El contador del día siguiente arranca en cero — no suma el resto de la jornada anterior.</p>

                <h3 class="h6 fw-bold mt-4 mb-2">Reflejo automático en la Agenda CRM</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Color teal:</strong> Cada turno cerrado se proyecta en la agenda como evento de tipo <em>hora</em> (teal <code>#20c997</code>), al lado de PDS, Presupuestos y Tratativas.</li>
                    <li><strong>Google Calendar:</strong> Si tenés Google Calendar conectado, el turno también sube a tu calendario personal o corporativo (según el modo configurado).</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Listado admin</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Filtros:</strong> Desde / Hasta / Operador (ID). Por defecto muestra el mes en curso.</li>
                    <li><strong>Columnas:</strong> ID, operador, inicio, fin, duración, modo (vivo/diferido), estado (abierto/cerrado/anulado) y concepto.</li>
                    <li><strong>Operador ve lo suyo, admin ve todo:</strong> Un operador normal solo ve sus propios turnos. Admin y RXN Admin ven los de toda la empresa.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Anular un turno</h3>
                <ul class="help-list-tight mb-0">
                    <li><strong>Motivo obligatorio:</strong> Al anular se pide motivo. Queda en la tabla de auditoría (<code>crm_horas_audit</code>) para trazabilidad.</li>
                    <li><strong>No se borra:</strong> La fila queda visible en el listado en gris con badge <em>ANULADO</em>. Nunca desaparece.</li>
                    <li><strong>Sin edición por operador:</strong> Después de cerrar un turno, solo admin puede ajustarlo. Si te equivocaste, avisá al admin.</li>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <div class="card rxn-form-card help-card" id="modulo-notificaciones" data-help-item data-help-text="notificaciones campanita campana bell badge rojo inbox aviso aviso interno alerta dropdown leer marcar leida leidas no leidas todas filtros eliminar borrar soft delete olvido turno turno abierto abiertos dedupe anti spam polling">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Notificaciones</h2>
                <p class="text-muted">Sistema de avisos internos de la suite. Cada vez que el sistema detecta algo que te conviene saber (un turno abierto desde ayer, un recordatorio, etc.) te lo manda al inbox personal.</p>

                <h3 class="h6 fw-bold mt-3 mb-2">Campanita en el topbar</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Dónde está:</strong> En la barra superior, al lado de tu nombre y el toggle de tema. Ícono de campana 🔔.</li>
                    <li><strong>Badge rojo:</strong> Si tenés notificaciones sin leer aparece un círculo rojo con el número. Si todas están leídas, el badge desaparece.</li>
                    <li><strong>Click → dropdown:</strong> Se abre un panel con las últimas 8 notificaciones. Las no leídas tienen un puntito amarillo y fondo suavemente resaltado.</li>
                    <li><strong>No polea:</strong> La campanita carga al entrar a cualquier página y al abrir el dropdown. No hace polling en background — si querés refrescar, recargá o abrí/cerrá el panel.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Qué hacés desde el dropdown</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Click en una notif:</strong> Si tiene link asociado (ej: "te lleva al turno olvidado"), navega al lugar correspondiente y la marca como leída.</li>
                    <li><strong>Marcar todas como leídas:</strong> Botón al pie del dropdown. Limpia el badge de un saque.</li>
                    <li><strong>Ver todas:</strong> Link arriba a la derecha — te lleva a la página <code>/notifications</code> con el listado completo.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Página de Notificaciones (inbox)</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Filtros:</strong> Pills arriba para alternar <em>Todas</em> / <em>No leídas</em> / <em>Leídas</em>.</li>
                    <li><strong>Por fila:</strong> Tres botones a la derecha — <em>Abrir</em> (va al link), <em>Marcar leída</em> (✓ verde) y <em>Eliminar</em> (✗ rojo, soft-delete).</li>
                    <li><strong>Eliminar ≠ borrar definitivo:</strong> "Eliminar" es soft-delete (queda oculto para vos). No hay purga automática; el sistema no borra notificaciones por TTL.</li>
                    <li><strong>Paginación:</strong> 30 por página. Si acumulás mucho, usá los filtros para achicar.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Tipos de notificaciones (hoy)</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Turno olvidado:</strong> Si tenías un turno abierto ayer y no lo cerraste, al volver te lo avisa con link para cerrarlo o anularlo.</li>
                    <li><strong>Ajuste de admin:</strong> Si admin te modifica un turno, te llega la notificación con el detalle del cambio.</li>
                    <li><strong>Próximos tipos (roadmap):</strong> Tratativas próximas a vencer, recordatorios de agenda, mensajes del sistema.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Anti-duplicados</h3>
                <p class="mb-0">El sistema usa una clave de deduplicación por 24hs. Si un hook intenta mandarte la misma notificación varias veces en un día, la ves una sola vez. No hay spam ni repeticiones molestas.</p>
            </div>
        </div>

        <div class="card rxn-form-card help-card" id="modulo-mobile" data-help-item data-help-text="mobile celular celu movil responsive tablet smartphone hamburguesa hamburguer menu offcanvas navegacion touch tactil pulgar viewport iphone android chrome safari campanita tema claro oscuro turnero">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Uso desde el celular (mobile)</h2>
                <p class="text-muted">La suite está pensada para trabajar cómoda desde PC pero también desde el celular. Estos son los detalles clave para el uso mobile.</p>

                <h3 class="h6 fw-bold mt-3 mb-2">Menú hamburguesa</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Dónde:</strong> Arriba a la izquierda, el ícono de tres rayitas ☰. Aparece solo en pantallas chicas (menos de ~992px de ancho).</li>
                    <li><strong>Qué tiene:</strong> Panel lateral con todas las secciones — CRM (Clientes, Presupuestos, PDS, Tratativas, Agenda, Horas, Notas, Llamadas), Tiendas, Administración (si sos admin) y Cuenta.</li>
                    <li><strong>Entrar al turnero:</strong> Tap en ☰ → <em>Horas (turnero)</em>. Es el camino más rápido desde cualquier pantalla del CRM.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Turnero optimizado para celular</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Botones grandes:</strong> 56px mínimo, pulgar-friendly. No hace falta hacer zoom para iniciar o cerrar.</li>
                    <li><strong>Layout one-column:</strong> Todo apilado verticalmente. Sin menús desplegables extra ni contenido que se desborde.</li>
                    <li><strong>Contador siempre visible:</strong> Arriba, en grande, con el tiempo del día sumado y corriendo en vivo.</li>
                    <li><strong>Geo pide permiso automáticamente:</strong> El browser te pregunta una vez y guarda la decisión.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Tips generales para mobile</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Listados largos:</strong> Algunas tablas (ej: listado de Horas admin, Notas) tienen scroll horizontal. Deslizá con el dedo para ver columnas de la derecha.</li>
                    <li><strong>Formularios:</strong> Los campos se apilan en una sola columna en mobile (row g-3 / col-12). En tablet en landscape se acomodan en dos columnas automáticamente.</li>
                    <li><strong>Tema claro/oscuro:</strong> El toggle está en el topbar (☀️ / 🌙). Se sincroniza con tu cuenta, así que si cambiás en el celu, también se cambia en la PC.</li>
                    <li><strong>Notificaciones:</strong> La campanita del topbar funciona igual en celu. El dropdown se abre al tap, al lado izquierdo (para no taparse con el borde de la pantalla).</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Qué módulos están optimizados mobile-first</h3>
                <ul class="help-list-tight mb-0">
                    <li><strong>Horas (Turnero):</strong> Diseñado desde cero para usar en la calle desde el celular.</li>
                    <li><strong>Notificaciones:</strong> Campanita + inbox responsive.</li>
                    <li><strong>Resto de la suite:</strong> Funciona en mobile, pero está pensada principalmente para escritorio o tablet en landscape. Listados grandes y editores densos (RxnLive, Presupuestos con muchos renglones) conviene hacerlos desde la PC.</li>
                </ul>
            </div>
        </div>

        <div class="card rxn-form-card help-card" id="modulo-configuracion" data-help-item data-help-text="configuracion empresa slug branding smtp tango connect mail tienda url publica identidad logo colores google calendar oauth">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Configuracion de la Empresa</h2>
                <p class="text-muted"><?= $isCrm ? 'Esta pantalla controla parametros propios del entorno CRM. Aunque muchos campos se parecen a Tiendas, CRM guarda su configuracion en un origen separado.' : 'Esta pantalla controla la identidad visible y varias conexiones importantes del entorno.' ?></p>
                <ul class="help-list-tight mb-4">
                    <?php if (!$isCrm): ?><li><strong>Slug:</strong> es la parte de la URL publica que identifica a la empresa dentro de la tienda.</li><?php endif; ?>
                    <li><strong>Branding:</strong> <?= $isCrm ? 'en CRM aplica al fallback visual y recursos internos del entorno.' : 'logo, colores, textos o referencias visibles para clientes.' ?></li>
                    <li><strong>SMTP:</strong> define como sale el correo desde la plataforma.</li>
                    <li><strong>Tango Connect:</strong> conecta el entorno con el ERP o sistema comercial.</li>
                </ul>

                <h3 class="h6 fw-bold mt-3 mb-2">Alta de una empresa Connect (paso a paso)</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>1. Cargar credenciales:</strong> URL base de Connect, Llave/Client Key y Token. Validá que estén bien antes de seguir.</li>
                    <li><strong>2. Tocar "Validar y cargar metadata":</strong> el sistema consulta el maestro de empresas (process 1418) y llena el dropdown <em>ID de Empresa (Connect)</em> con todas las empresas disponibles para esa llave. Listas, depósitos y perfiles todavía NO se cargan en este paso — eso es esperado.</li>
                    <li><strong>3. Elegir la empresa Connect del dropdown:</strong> apenas seleccionás, el sistema dispara automáticamente la carga de listas de precio, depósitos y perfiles de pedido para esa empresa puntual.</li>
                    <li><strong>4. Guardar:</strong> recién con la empresa elegida y los catálogos resueltos podés guardar la configuración.</li>
                </ul>
                <div class="help-highlight">
                    <strong>¿Por qué este orden?</strong> Las listas, depósitos y perfiles dependen de la empresa Connect — sin empresa elegida, Tango no puede resolverlos. Si abrís el panel de "Diagnóstico Connect" antes del paso 3 y ves marcado solo Empresas, está bien. Si después del paso 3 algún catálogo aparece en VACÍO o ERROR, ahí sí hay un problema real (credenciales sin permisos, perfil mal configurado en Axoft, etc.) y el detalle del banner te indica qué pasó.
                </div>

                <div class="help-highlight">
                    <strong>Consejo practico:</strong> si algo falla en correos o integraciones, este suele ser el primer lugar que conviene revisar. En CRM, la configuracion queda separada de Tiendas aunque haya sido clonada al inicio para no arrancar vacio.
                </div>
            </div>
        </div>

        <div class="card rxn-form-card help-card" id="modulo-mi-perfil" data-help-item data-help-text="perfil mi perfil preferencias visuales tema claro oscuro dark light fuente panel personal usuario apariencia entorno color calendario agenda contraseña password datos">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-3">Mi Perfil</h2>
                <p class="text-muted">Pantalla personal del operador. Acá ajustás tus datos, tu apariencia y tus preferencias visuales. Lo que cambies acá viaja con tu cuenta — no depende del navegador ni de la PC desde la que entres.</p>

                <h3 class="h6 fw-bold mt-3 mb-2">Datos personales</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Nombre y email:</strong> Visibles para el resto del equipo en listados y filtros por operador.</li>
                    <li><strong>Cambiar contraseña:</strong> Te pide la actual y la nueva dos veces como confirmación.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Tema claro / oscuro</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Toggle por usuario:</strong> Elegís claro u oscuro y queda guardado en tu cuenta.</li>
                    <li><strong>Sincronización entre pestañas:</strong> Si tenés la app abierta en varias pestañas, al cambiar el tema se actualiza todo al instante, sin necesidad de recargar.</li>
                    <li><strong>Coherencia visual:</strong> Todos los módulos respetan tu elección — incluyendo Notas, Agenda CRM, Tratativas, Llamadas y RXN Live, que originalmente eran solo oscuros.</li>
                </ul>

                <?php if ($isCrm): ?>
                <h3 class="h6 fw-bold mt-4 mb-2">Color de calendario (Agenda CRM)</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Para qué sirve:</strong> Es el color con el que aparecen tus eventos en la <em>Agenda CRM</em>. Si en tu equipo cada uno tiene un color, identificás de un vistazo qué carga es de quién.</li>
                    <li><strong>Cómo elegirlo:</strong> En la sección <em>Agenda CRM</em> de Mi Perfil, abrís el selector de color y guardás. Se aplica al toque sobre los eventos nuevos y existentes.</li>
                    <li><strong>Combinación con el origen:</strong> El fondo del evento es tu color (operador) y el borde izquierdo es el color del tipo (PDS azul, Presupuesto verde, etc.). Así ves operador + tipo en una sola mirada.</li>
                </ul>

                <h3 class="h6 fw-bold mt-4 mb-2">Horario laboral (alimenta avisos del Turnero)</h3>
                <ul class="help-list-tight mb-3">
                    <li><strong>Es orientativo:</strong> Lo que cargás acá no te bloquea — es la base para que el sistema te avise si te olvidaste de iniciar o cerrar un turno.</li>
                    <li><strong>Bloques por día:</strong> De lunes a domingo podés cargar uno o varios bloques (ej: lunes 09:00 → 13:00 + 14:00 → 18:00). Click en <em>+ Bloque</em> para sumar uno.</li>
                    <li><strong>Desde el celular:</strong> En pantalla chica cada día se muestra como una card apilada con sus bloques y el botón <em>+ Bloque</em> abajo. Sin scroll horizontal molesto.</li>
                    <li><strong>Avisar si no abrí turno:</strong> Switch opcional que te pincha una notificación cuando arranca un bloque y no abriste turno del turnero.</li>
                    <li><strong>Tolerancia para "olvidaste cerrar":</strong> Minutos que el sistema espera después del fin de un bloque antes de avisarte que quedó abierto. Default 30 min; rango 5–240.</li>
                </ul>
                <?php endif; ?>

                <h3 class="h6 fw-bold mt-4 mb-2">SMTP personal para Mail Masivos (solo admins)</h3>
                <ul class="help-list-tight mb-0">
                    <li><strong>Quién lo ve:</strong> Esta sección aparece solo si tu cuenta tiene privilegios de administrador — es el SMTP que usa el módulo <em>Mail Masivos</em> para disparar envíos desde tu identidad.</li>
                    <li><strong>Por qué está oculta para operadores:</strong> Los operadores no necesitan configurar SMTP; mostrarlo generaba ruido y exponía campos que no tenían que tocar (especialmente desde el celular).</li>
                    <li><strong>Cómo probarla:</strong> Una vez cargados host, puerto, usuario y password, el botón <em>Probar conexión</em> hace un handshake real con el server y te devuelve OK o el error exacto. Queda registrado el último test.</li>
                </ul>
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
                    <li><strong>Buscar rápido:</strong> En cualquier pantalla, tocá la tecla <kbd>F3</kbd> o la barra <kbd>/</kbd> y vas directo a la caja de búsqueda.</li>
                    <li><strong>Moverse en el Dashboard:</strong> Cuando buscás un módulo en las tarjetas de inicio, usa las <strong>flechas ↑ ↓ ← →</strong> para saltar entre las opciones coloreadas y tocá <kbd>Enter</kbd> para entrar directo al que elegiste.</li>
                    <li><strong>Volver con Escape:</strong> En cualquier formulario, apretar <kbd>Esc</kbd> hace lo mismo que el botón <em>Volver</em>. Si tenés cambios sin guardar, te pregunta antes de salir para que no pierdas trabajo.</li>
                    <li><strong>Ver todos los atajos disponibles:</strong> Apretá <kbd>Shift</kbd> + <kbd>?</kbd> en cualquier pantalla y se abre un panel con la lista completa de atajos activos en ese contexto, agrupados por módulo. Súper útil para descubrir las hotkeys del módulo en el que estás.</li>
                    <li><strong>Copiar fila en listados:</strong> En tablas con copia rápida (PDS, Presupuestos, Notas), pasá el mouse sobre una fila y apretá <kbd>Alt</kbd> + <kbd>O</kbd> para clonar el registro sin abrir el form.</li>
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
