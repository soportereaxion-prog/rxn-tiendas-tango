# ESTADO ACTUAL

## módulos tocados

* módulo: Theming Engine B2B/B2C (`UIHelper`, `rxn-theming.css`)
* módulo: Admin / dashboard (`home.php`, inyecciones `<html>` en todo el backoffice)
* módulo: Store (`layout.php`, LocalStorage toggle)
* módulo: Categorías (`Categorias`, `articulo_categoria_map`, filtros públicos)
* módulo: Ofertas Store (`articulo_store_flags`, toggle frontal, vuelta contextual)
* módulo: CRM / Pedidos de Servicio (`CrmPedidosServicio`, `crm_pedidos_servicio`)
* módulo: Pedidos Web (`Pedidos`, `pedidos_web`, reenvío Tango)
* módulo: CRM / Presupuestos (`CrmPresupuestos`, `crm_presupuestos`, `crm_presupuesto_items`)
* módulo: Formularios de impresión (`canvas` / `print forms`, estándar documental transversal)
* módulo: Versionado interno (`app/config/version.php`, `VersionService`)
* módulo: Mantenimiento OTA (`MigrationRunner`)
* módulo: Bitácora interna de módulos (`ModuleNoteService`, `ModuleNotesController`, `module_notes_panel.php`)
* módulo: EmpresaConfig (Configuración Backend Tenant)
* módulo: Configuración CRM (`empresa_config_crm`, separación operativa de Tiendas)
* módulo: Clientes CRM (`CrmClientes`, `crm_clientes`, sync Tango 2117)
* módulo: Usuarios (Nuevo sub-layout `mi-perfil`)
* módulo: DB Schema (`empresas`, `usuarios`)
* módulo: Empresas (orden server-rendered y paginación del CRUD)

## decisiones

* **Estandarización Visual Transversal (Dark/Light):** Se purgó la plataforma de clases estáticas y fondos hardcodeados (como `bg-white` o `text-dark`), migrando a un sistema puro de CSS Variables sobre `:root` y `[data-theme="dark"]`. Las cards, tablas e inputs reaccionan asíncronamente heredando colores lógicos, erradicando textareas blancos ciegos en contextos oscuros.
* **Capa Theming B2B:** Se rechaza el themer centralizado de Boostrap clásico en pos de variables CSS puras inyectadas mediante un Helper dinámico (`UIHelper`) en runtime, evaluando la BDD o Sesión (Light/Dark Mode; Fuentes `sm/md/lg`).
* **Branding Tenant (Store):** El `layout.php` público extrae parámetros corporativos (Logo, Colores hexadecimales primario/secundario y metadatos sociales de Footer) hidratando de forma autónoma cada `/tienda` generada en tiempo real.
* **Separación de pre-conceptos de DB:** Para los Themes B2C (Colors/Logo) el `EmpresasRepository` afecta directamente las columnas nativas de la tabla en vez de un string JSON. Esto garantiza búsquedas rápidas si el motor debe evolucionar, reduciendo carga lógica.
* Las configuraciones del Dark Mode B2C se manejan en `LocalStorage` por lado cliente; no se registra en BDD, garantizando la velocidad sin queries redundantes para "usuarios invitados".
* **Arquitectura B2B de Nivel 1 & 2:** La raíz (`/`) intercepta accesos no autenticados derivando directamente a `/login`. Para sesiones válidas actúa como un Launcher Modular (*Nivel 1*) bifurcando "RXN Backoffice" y "Entorno Operativo". Toda transacción se delegó a dos super-subdashboards interconectados transversalmente (`/admin/dashboard` y `/mi-empresa/dashboard`).
* **Split Operativo por Módulo:** El launcher principal ahora separa `Entorno Operativo de Tiendas` y `Entorno Operativo de CRM`, gobernados por los flags reales del tenant en `empresas`.
* **Orden de Tarjetas por Entorno:** El reordenamiento drag & drop del dashboard se persiste por area (`tiendas` y `crm`) para que cada menu operativo conserve su propia organizacion sin contaminar al otro.
* **Dashboard Drag & Drop y Cards (B2B):** Migrado y transformado hacia `tenant_dashboard.php` (Nivel 2B). La visual cambió drásticamente adaptando Botones Simples a Tarjetas (Cards) Click-Through superpuestas con transiciones premium. Se le inyectó la variable `$_SESSION['es_admin']` oculta en `AuthService` para restringir la tarjeta "Administrar Cuentas" de manera reactiva en back-end a los usuarios de tenant común. Las modificaciones del SortableJS se emiten vía FETCH logrando consistencia Cero FOUC.
* **Maquetación EmpresaConfig:** El formulario monolítico vertical se refactorizó hacia Grillas de Bootstrap 5 (`.row`, `.col-md-6`) encapsuladas por 5 `Card` semánticas (Datos, Identidad B2C, Visual Corporativa, Tango Connect, SMTP) optimizando la densidad operativa horizontal.
* **Perfil de pedido Tango unificado en Configuracion:** `EmpresaConfig` reemplaza los selectores operatorios de `lista_precio_1`, `lista_precio_2`, `deposito_codigo` y el input visible de `tango_pds_talonario_id` por un unico selector `Perfil de pedido Tango`, compartido entre Tiendas y CRM y persistido por area con snapshot local de codigo/descripcion.
* **Buscador de Empresa Connect:** El primer selector de empresa dentro de `Configuracion` ahora usa una entrada con sugerencias locales sobre el maestro Connect ya validado, sin boton de busqueda y manteniendo el select real oculto para persistencia.
* **Feedback visual de Empresa Connect:** El buscador visible de empresa en `Configuracion` suma icono, placeholder guiado y una pill de estado que deja clara la empresa Connect actualmente activa antes de cargar catalogos o guardar.
* **Hotfix FormData en Configuracion:** Las acciones AJAX de `Configuracion` ya no serializan por error el formulario del widget de notas; ahora toman explicitamente `#empresa-config-form`, permitiendo que `tango_connect_company_id` viaje y alimente listas/deposito como corresponde.
* **Metadata Connect simplificada para Configuracion:** La pantalla de `Configuracion` ya no consume catalogos legacy de listas/deposito en este flujo; al validar o cambiar la empresa Connect solo trae el maestro de empresas y el catalogo de perfiles de pedido (`process=20020`).
* **Resolver compartido de cabecera Tango:** Los pedidos de Tiendas y CRM ya empiezan a resolver `ID_PERFIL_PEDIDO`, talonario, condicion, vendedor, deposito, lista y transporte desde el perfil Tango seleccionado (`process=20020` detalle), con fallbacks de compatibilidad acotados a configuracion legacy.
* **Snapshot local del perfil Tango:** Cada vez que se guarda Configuración se cachea el detalle completo del perfil (IDs comerciales + metadata) en `empresa_config`/`empresa_config_crm`, de modo que los envíos posteriores no dependen de consultas adicionales a Connect.
* **Heurística temporal para `ID_MONEDA`:** Hasta que dispongamos del catálogo oficial de Tango, se asume que `MONEDA_HABITUAL = 'C'` representa pesos (`ID_MONEDA = 1` o `2` según la empresa, hoy se forza `2` para company 351) y cualquier otro flag deriva en `ID_MONEDA = 0`, documentado como heurística bimonetaria con override puntual.
* **Normalización de URL Connect en el resolver:** El consumidor interno que trae el detalle del perfil ahora fuerza el sufijo `/Api` (o lo arma desde `tango_connect_key`) igual que la validación visual, evitando que el resolver quede sin datos cuando la empresa configuró solo `https://{key}.connect.axoft.com`.
* **Listado de Empresas:** El CRUD de `empresas` ahora resuelve búsqueda, orden por columnas y paginación server-rendered vía GET (`search`, `field`, `sort`, `dir`, `page`) con whitelist cerrada y límite fijo de 10 registros por página.
* **Estandar de Busquedas CRUD:** Se adopta un patron comun para buscadores server-rendered con sugerencias parciales desacopladas del filtro efectivo del listado. El operador escribe, recibe hasta 3 sugerencias y solo filtra al confirmar con `Enter` o boton.
* **Listado de Usuarios:** El módulo `Usuarios` incorpora búsqueda por `nombre`/`email`, orden por columnas y paginación server-rendered vía GET (`search`, `sort`, `dir`, `page`) respetando `empresa_id` en contexto tenant y vista global para RXN admin.
* **Despliegue transversal de buscadores:** El patron de sugerencias sin autofiltro ya se replica en `empresas`, `usuarios`, `articulos`, `clientes` y `pedidos`, con JS/CSS reutilizable y experiencias visuales alineadas.
* **Pedidos Web:** El detalle y listado muestran el conteo de intentos de envío a Tango y se agregaron acciones masivas para reenviar pedidos seleccionados o todos los pendientes.
* **Feedback Visual Estándar:** Se incorporó un sistema liviano de confirmación con modal Bootstrap y mensajes semaforizados para reemplazar diálogos nativos y mejorar la consistencia visual.
* **Confirmaciones Reutilizables:** El modal de confirmación quedó centralizado y reutilizable en JS/CSS propio, con aplicación inicial en pedidos, artículos y acciones puntuales de clientes.
* **Backoffice por Privilegios:** El acceso al circuito `/admin/*` ya no depende solo de `es_rxn_admin`; ahora un usuario con privilegios de administrador tambien puede ingresar al backoffice operativo.
* **Flags Modulares en Empresas:** La entidad `empresas` incorpora `modulo_tiendas` y `modulo_crm` como switches preparatorios para futuras habilitaciones, dependientes del estado activo del tenant.
* **Guardas Reales por Entorno:** Las rutas internas y la tienda pública ya no dependen solo del login; `Tiendas` y `CRM` se habilitan o bloquean según `activa`, `modulo_tiendas` y `modulo_crm`.
* **CRM Inicial con Datos Separados:** El entorno CRM nace con dashboard propio, acceso a configuración reutilizada y módulos de `Articulos CRM` y `Clientes CRM` persistidos en tablas dedicadas (`crm_articulos`, `crm_clientes`) sin mezclar con Tiendas.
* **Configuracion CRM Separada:** El entorno CRM persiste sus parametros operativos en `empresa_config_crm`; al inicio se copia la base de Tiendas solo como semilla para no arrancar vacio, pero luego la edicion queda desacoplada. Ahora tambien guarda el catalogo local `clasificaciones_pds_raw` para alimentar el selector del PDS.
* **Pedidos de Servicio CRM:** Se suma un primer modulo operativo real con tabla propia `crm_pedidos_servicio`, correlativo por empresa, snapshots de cliente/articulo/clasificacion local-first desde orígenes propios (`crm_clientes`, `crm_articulos` y configuracion CRM), calculo de tiempos, valor decimal horario persistido, adjuntos de diagnostico con referencias `#imagenN`, miniaturas compactas debajo del textarea, checkbox de hora actual para `Inicio` y `Finalizado` y monitor de tiempos mas visible durante la carga.
* **PDS → Tango:** El PDS puede enviarse manualmente a Tango desde CRM; el pedido externo usa el `articulo` del PDS como renglon comercial y la `cantidad` viaja como tiempo decimal calculado desde el tiempo neto, con lookup defensivo de `ID_STA11`, rutas `/Api/*` consistentes y validacion real de `succeeded=false` en Connect.
* **Pedidos transaccionales alineados al Perfil Tango:** Tanto `Pedidos Web` como `Pedidos de Servicio CRM` ya usan el perfil seleccionado para poblar la cabecera comercial del payload, y `Pedidos Web` corrige ademas el uso de claves internas `id_gva01_tango`, `id_gva10_tango`, `id_gva23_tango`, `id_gva24_tango` en lugar de los aliases legacy equivocados.
* **Ayuda Operativa por Contexto:** La ayuda interna ahora adapta contenido para `Tiendas` o `CRM`, incluyendo explicaciones dummy-friendly sobre pedidos de servicio, sync CRM y orden de tarjetas.
* **Busquedas Parciales Reales:** El autosuggest compartido permite `Enter` con texto parcial sin forzar seleccion; si se elige una sugerencia, la seleccion dispara el filtro del listado.
* **Payload de PDS dinámico:** Envío a Tango inyecta Vendedor (`ID_GVA23`) y Talonario (`ID_GVA43`) del perfil configurado al usuario operador, consumiéndose desde la API "al vuelo" directamente.
* **Finalizado PDS Obligatorio:** El campo `fecha_finalizado` ahora es estrictamente requerido para guardar en el CRM, previniendo el envío de tiempo nulo ($0) a Tango.
* **Manejo F10 Estricto:** Se corrigió el shortcut global `F10 / Ctrl+Enter` para que priorice el guardado principal del formulario (`button[form]`) en lugar de otros submits previos del DOM (como `Enviar a Tango`), aplicable a PDS y Presupuestos.
* **Tango API Order Number Extracción:** Dado que la API de Tango Connect (proceso 19845) NO retorna el número de pedido formateado, se agregó la captura mandatoria del valor `savedId` desde el payload de éxito, permitiendo que el sistema guarde y referencie internamente la confirmación de Tango en el campo `nro_pedido`.
* **Usuarios por Tenant:** El modulo `Administrar Cuentas` queda disponible para usuarios autenticados del tenant, manteniendo el aislamiento por empresa en consulta y persistencia.
* **Escalado de Privilegios Controlado:** Los operadores del tenant pueden administrar usuarios de su propia empresa, pero no ven ni pueden otorgar el flag `es_admin` salvo que ya tengan capacidad para gestionar privilegios.
* **Visual de Slug en EmpresaConfig:** El slug visible del tenant se muestra sin prefijo duro `/rxn_suite/public/`, evitando ruido visual y manteniendo el alcance en solo lectura.
* **Versionado Interno Curado:** La release activa se declara en `app/config/version.php` y se consume via `VersionService`, permitiendo exponer las novedades solo a perfiles administradores del entorno interno y dejando la tienda publica sin ese bloque.
* **Títulos de Pestaña Dinámicos:** La clase `HeaderController` o las inyecciones en `layout.php`/`admin_layout.php` ahora evalúan de forma unificada si existe un `titulo_pestana` definido a nivel configuración para mostrar el branding de cada tenant en la tab del navegador.
* **Gestión de Permisos Granulares:** Las tablas de la empresa ahora soportan switches nativos para `crm_modulo_llamadas`, `crm_modulo_monitoreo` y `modulo_rxn_live`.
* **Sincronismo Espejo de Módulos (RXN Live):** Debido a que `RXN Live` habilita dashboards analíticos tanto de tiendas como de CRM, su configuración se dividió estructuralmente en Base de Datos usando `tiendas_modulo_rxn_live` y `crm_modulo_rxn_live`, permitiendo que el administrador de la empresa encienda y apague la misma funcionalidad operativa (el Dashboard RXN Live) de forma totalmente independiente desde cada árbol operativo (Tiendas vs CRM), validándose en sus respectivos dashboards mediante chequeos separados en `EmpresaAccessService`.
* **Seguridad Reactiva en Dashboard:** Las tarjetas de menú de `tenant_dashboard.php` y `crm_dashboard.php` evalúan en tiempo real los flags de acceso a través de las autorizaciones definidas en `EmpresaAccessService` para desaparecer en lugar de provocar errores de ruteo.
* **Disciplina de Release Reforzada:** Toda iteración operativa o funcional relevante *tiene* que dejar sincronizados `docs/logs`, `docs/estado/current.md` y obligatoriamente `app/config/version.php`. Cada vuelta que le peguemos a algo importante tiene que aparecer allí; es una regla inquebrantable del flujo de trabajo. La release visible actual queda publicada como `v1.1.57` build `20260404.10`.
* **Estrategia de Deploy B (Oficial):** El subdominio `suite.reaxionsoluciones.com.ar` apunta con Document Root a `rxn_suite/public/`. Las URLs son absolutas desde `/` sin subfijo de proyecto. El `.htaccess` de `public/` usa `RewriteBase /`. `tools/deploy_prep.php` incluye post-proceso de limpieza de referencias legacy y verificación de htaccess. Ver log `2026-04-02_1945_correccion_deploy_produccion_plesk.md`.
* **Jerarquía Operativa Actualizada:** OpenCode opera como `Gemi`, que es la interfaz principal, interpreta y valida; y `Clau` ejecuta todo el código sin excepción como ejecutora Senior.
* **Bitácora Interna por Módulo:** Se agrega una mecánica admin-only para cargar anotaciones rápidas desde cada módulo, persistidas en JSON local (`app/storage/module_notes.json`), visibles en un widget flotante con arrastre/redimensión manual, minimización tipo dock compacta abajo a la derecha y auditables desde `/admin/notas-modulos` con soporte para varias capturas pegadas o adjuntas, referenciadas en texto como `#imagenN`.
* **Categorias Local-First:** Se incorpora un módulo tenant de categorías y una asignación por `empresa_id + codigo_externo` en `articulo_categoria_map`, evitando depender de `articulos.id` para que la clasificación sobreviva a purgas o resincronizaciones del catálogo.
* **Store por Categorías:** El catálogo público suma bloque visual de categorías, navegación rápida y filtro `?categoria=slug`, sin tocar carrito ni checkout.
* **Store con Vista Alternable:** El frente publico puede alternar entre `?vista=categorias` y `?vista=catalogo`, preservando filtros, busqueda y paginacion sin cambiar rutas base.
* **Ofertas Comerciales por SKU:** Tiendas agrega `articulo_store_flags` para decidir por `empresa_id + articulo_codigo_externo` si un producto se publica en oferta, usando `precio_lista_1` como base y `precio_lista_2` como promocion activa.
* **Retorno Contextual de Producto:** El detalle del Store conserva el contexto del listado (`vista`, `search`, `categoria`, `page`) y expone un boton de vuelta util para no cortar el flujo comercial.
* **Configuración Independiente por Entorno:** CRM deja de reutilizar la persistencia de `empresa_config` y pasa a usar `empresa_config_crm` para sus parámetros operativos, manteniendo el branding público exclusivamente del lado Tiendas.
* **Servicios Preparados por Área:** `TangoService`, `TangoSyncService` y `MailService` ya admiten resolución explícita por entorno (`tiendas`/`crm`) sin forzar autodetección global ni alterar el comportamiento actual de Tiendas.
* **Clientes CRM local-first reales:** `Clientes CRM` deja de reutilizar el ABM manual de `ClientesWeb` y pasa a operar como `Articulos CRM`: listado server-rendered, ficha local simple y sync propio desde Tango/Connect `process=2117` hacia `crm_clientes`.
* **PDS sobre cache CRM:** `Pedidos de Servicio CRM` ya no sugiere clientes desde el flujo equivocado de `clientes_web`; ahora consume `crm_clientes` sincronizados, preservando `cliente_fuente = crm_clientes` y los IDs Tango necesarios para envio.
* **Presupuestos CRM v1 usable:** CRM suma una pantalla operativa de presupuestos con cabecera comercial, defaults por cliente, renglones acumulables, totales recalculados en backend y snapshots locales de cabecera/detalle para no romper historicos. Se restableció el acceso en el dashboard del CRM.
* **Configuración de Formularios Impresos CRM:** Se amplió `empresa_config_crm` para permitir la asignación de diseños en formato PDF creados en PrintForms (`pds_email_pdf_canvas_id`, `presupuesto_email_pdf_canvas_id`) a PDS y Presupuestos, sentando la base para envío de reportes exportables.
* **Catalogos Comerciales CRM Cacheados:** `Presupuestos CRM` agrega `crm_catalogo_comercial_items` para cachear depositos, condiciones de venta, listas, vendedores y transportes por empresa, evitando consultas remotas interactivas desde el navegador.
* **Canvas de Impresión Estandarizado:** La plataforma define una sola mecánica de `Definicion de formularios de impresion`, tratada funcionalmente como canvas sobre hoja A4 pero resuelta tecnicamente como hoja DOM versionable, con fondo, fuentes, objetos posicionados y registro controlado de variables por documento.
* **Editor Visual A4 v1:** CRM incorpora el primer modulo operativo de `Formularios de Impresion`, con hoja A4 editable, fondo configurable, objetos `text`/`variable`/`line`/`rect`/`table_repeater`, fuentes desde whitelist, drag visual y versionado persistente por documento.
* **Presupuestos Imprimibles:** El modulo de presupuestos se engancha al motor documental inyectando un `ContextBuilder` plano y sumando un boton `Imprimir` que despacha el documento final HTML listo para PDF.
* **Envíos Automatizados (PDS y Presupuestos):** CRM ahora integra un `DocumentMailerService` que despacha correos utilizando la configuración SMTP tenant, procesando el cuerpo de la plantilla HTML definida y agregando el PDF adjunto (`DomPDF`) derivado del canvas (`PrintForms`) on the fly, previa validación visual de una opción "Enviar por Correo".
* **Contexto visible en Backoffice:** Las cabeceras del backoffice ahora muestran `Hola, {usuario}` y, si la empresa en sesion tiene Tiendas habilitado con slug valido, un acceso rapido a su URL publica.
* **Dashboard Estandarizado y Navegación:** Se unificaron visualmente todos los dashboards operativos empleando clases compartidas (`.rxn-module-card`, transparentes) y se habilitó un botón en la barra superior (Backend) para alternar rápidamente entre modo claro y oscuro, actualizando asíncronamente (AJAX POST `/mi-perfil/toggle-theme`) la preferencia en BD. Además, se introdujo un componente local transversal de búsqueda de módulos que se activa presionando `/` o `F3`.
* **Solución de Falso Positivo en Migraciones (PDO):** El runner de migraciones (`MigrationRunner`) ahora captura y tolera la excepción nativa "There is no active transaction" durante el `commit` / `rollBack`. Esto soluciona los reportes falsos de error que ocurrían en MySQL tras una consulta DDL (`CREATE TABLE`, `ALTER TABLE`), ya que MySQL ejecuta un auto-commit implícito que cerraba la transacción iniciada por PHP sin aviso previo.

## entorno local de referencia

* Wampserver 3.3.7 x64
* Apache 2.4.62.1
* PHP 8.3.14
* PHP CLI: `D:\RXNAPP\3.3\bin\php\php8.3.14\php.exe`
* MySQL 9.1.0
* MariaDB 11.5.2
* DBMS default: mysql

## riesgos

* **[RESUELTO 2026-04-02]** ~~El iterador generador inyectó rutas en cabeceras dependientes del contexto `/rxn_suite/public/`.~~ La Estrategia B (Document Root en `public/`) fue implementada como única estrategia oficial. Ver log `2026-04-02_1945_correccion_deploy_produccion_plesk.md`.
* Si el Tenant sube logos `.svg` rotos, el Store frontend podría crashear su ratio visual temporalmente en cabeceras. (Validados por MIME superficial).
* La paginación del CRUD de empresas renderiza todos los números de página. Si el volumen crece mucho, convendrá compactar la navegación sin alterar la estrategia server-rendered.
* Las categorías requieren ejecutar la nueva migración (`database_migrations_categorias.php`) para crear `categorias` y `articulo_categoria_map` antes de operar el módulo.
* Las ofertas comerciales del Store requieren ejecutar `database_migrations_store_flags.php` para crear `articulo_store_flags` en ambientes existentes.
* La independencia de configuración quedó acotada al MVP operativo: Store, clientes, pedidos y mailing público siguen leyendo `empresa_config` de Tiendas; CRM guarda su propia base pero aún no consume todos esos servicios compartidos.
* Si `crm_clientes` ya existe desde la iteración equivocada como copia de `clientes_web`, el bootstrap actual convive con columnas viejas; conviene monitorear duplicados históricos antes de endurecer más constraints o limpieza definitiva.
* La resolucion del `Perfil de pedido Tango` depende de que la empresa elegida exista y responda bien con `process=20020`; si Axoft devuelve `succeeded=false` incluso con empresa valida, convendra revisar el ambiente remoto y no la UI local.
* Store, sincronizacion y otros consumidores no transaccionales siguen dependiendo de `lista_precio_1`, `lista_precio_2`, `deposito_codigo` y/o `tango_pds_talonario_id`; Pedidos Web y PDS ya migraron su cabecera, pero la moneda comercial aun usa una heuristica bimonetaria documentada como temporal.
* `Presupuestos CRM` hoy puede operar con precio manual si todavia no existe una fuente local consistente de precios por lista; el relevamiento fino de pricing CRM sigue siendo critico antes de automatizar completamente el valor del renglon.

## próximo paso

* **[DEPLOY INMEDIATO]** Ejecutar `php tools/deploy_prep.php`, verificar que el post-proceso reporta `[OK]`, subir `/build` a Plesk con Document Root en `public/`, ejecutar `composer dump-autoload -o` en servidor.
* Verificar funcionamiento en `https://suite.reaxionsoluciones.com.ar/login` sin loops.
* Testeo global en servidor Linux para asegurar compatibilidad con PHP 8.2 estricto alojado remotamente.
* Avanzar en siguientes características propuestas en la iteración del proyecto (Por ej: refinamientos de flujo transaccional Tango Connect si las hubiere).
* Evaluar un futuro mapeo de rubros desde Tango solo si el origen remoto demuestra estabilidad y no rompe el criterio local-first.
* Si CRM suma integraciones reales con correo o sync, desacoplar consumidores restantes para que también lean `empresa_config_crm` donde corresponda.
* Validar en datos reales de Connect si el `process=2117` devuelve siempre los mismos nombres de campos opcionales (`MAIL`, `TELEFONO_1`, etc.) para evitar huecos silenciosos en email/teléfono/dirección.
* Completar `Presupuestos CRM` con pricing por lista real y versionado comercial una vez estabilizada la pantalla base.
* Reemplazar la heurística `MONEDA_HABITUAL` → `ID_MONEDA` por datos oficiales de Tango ni bien logremos consumir el catálogo correcto (process 16660 u otro que Axoft habilite).
* Opcionalmente ampliar el `Canvas de impresion` con mas herramientas graficas (imagenes libres, codigo de barras/QR) si surge la necesidad.
