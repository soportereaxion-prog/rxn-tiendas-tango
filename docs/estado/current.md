# ESTADO ACTUAL

## módulos tocados

* módulo: Theming Engine B2B/B2C (`UIHelper`, `rxn-theming.css`)
* módulo: Admin / dashboard (`home.php`, inyecciones `<html>` en todo el backoffice)
* módulo: Store (`layout.php`, LocalStorage toggle)
* módulo: Categorías (`Categorias`, `articulo_categoria_map`, filtros públicos)
* módulo: CRM / Pedidos de Servicio (`CrmPedidosServicio`, `crm_pedidos_servicio`)
* módulo: Versionado interno (`app/config/version.php`, `VersionService`)
* módulo: Bitácora interna de módulos (`ModuleNoteService`, `ModuleNotesController`, `module_notes_panel.php`)
* módulo: EmpresaConfig (Configuración Backend Tenant)
* módulo: Configuración CRM (`empresa_config_crm`, separación operativa de Tiendas)
* módulo: Usuarios (Nuevo sub-layout `mi-perfil`)
* módulo: DB Schema (`empresas`, `usuarios`)
* módulo: Empresas (orden server-rendered y paginación del CRUD)

## decisiones

* **Capa Theming B2B:** Se rechaza el themer centralizado de Boostrap clásico en pos de variables CSS puras inyectadas mediante un Helper dinámico (`UIHelper`) en runtime, evaluando la BDD o Sesión (Light/Dark Mode; Fuentes `sm/md/lg`).
* **Branding Tenant (Store):** El `layout.php` público extrae parámetros corporativos (Logo, Colores hexadecimales primario/secundario y metadatos sociales de Footer) hidratando de forma autónoma cada `/tienda` generada en tiempo real.
* **Separación de pre-conceptos de DB:** Para los Themes B2C (Colors/Logo) el `EmpresasRepository` afecta directamente las columnas nativas de la tabla en vez de un string JSON. Esto garantiza búsquedas rápidas si el motor debe evolucionar, reduciendo carga lógica.
* Las configuraciones del Dark Mode B2C se manejan en `LocalStorage` por lado cliente; no se registra en BDD, garantizando la velocidad sin queries redundantes para "usuarios invitados".
* **Arquitectura B2B de Nivel 1 & 2:** La raíz (`/`) actúa como un Launcher Modular (*Nivel 1*) bifurcando "RXN Backoffice" y "Entorno Operativo". Toda transacción se delegó a dos super-subdashboards interconectados transversalmente (`/admin/dashboard` y `/mi-empresa/dashboard`).
* **Split Operativo por Módulo:** El launcher principal ahora separa `Entorno Operativo de Tiendas` y `Entorno Operativo de CRM`, gobernados por los flags reales del tenant en `empresas`.
* **Orden de Tarjetas por Entorno:** El reordenamiento drag & drop del dashboard se persiste por area (`tiendas` y `crm`) para que cada menu operativo conserve su propia organizacion sin contaminar al otro.
* **Dashboard Drag & Drop y Cards (B2B):** Migrado y transformado hacia `tenant_dashboard.php` (Nivel 2B). La visual cambió drásticamente adaptando Botones Simples a Tarjetas (Cards) Click-Through superpuestas con transiciones premium. Se le inyectó la variable `$_SESSION['es_admin']` oculta en `AuthService` para restringir la tarjeta "Administrar Cuentas" de manera reactiva en back-end a los usuarios de tenant común. Las modificaciones del SortableJS se emiten vía FETCH logrando consistencia Cero FOUC.
* **Maquetación EmpresaConfig:** El formulario monolítico vertical se refactorizó hacia Grillas de Bootstrap 5 (`.row`, `.col-md-6`) encapsuladas por 5 `Card` semánticas (Datos, Identidad B2C, Visual Corporativa, Tango Connect, SMTP) optimizando la densidad operativa horizontal.
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
* **Configuracion CRM Separada:** El entorno CRM persiste sus parametros operativos en `empresa_config_crm`; al inicio se copia la base de Tiendas solo como semilla para no arrancar vacio, pero luego la edicion queda desacoplada.
* **Pedidos de Servicio CRM:** Se suma un primer modulo operativo real con tabla propia `crm_pedidos_servicio`, correlativo por empresa, snapshots de cliente/articulo local-first desde orígenes propios (`crm_clientes` y `articulos` de momento) y calculo de tiempos.
* **Ayuda Operativa por Contexto:** La ayuda interna ahora adapta contenido para `Tiendas` o `CRM`, incluyendo explicaciones dummy-friendly sobre pedidos de servicio, sync CRM y orden de tarjetas.
* **Busquedas Parciales Reales:** El autosuggest compartido permite `Enter` con texto parcial sin forzar seleccion; si se elige una sugerencia, la seleccion dispara el filtro del listado.
* **Usuarios por Tenant:** El modulo `Administrar Cuentas` queda disponible para usuarios autenticados del tenant, manteniendo el aislamiento por empresa en consulta y persistencia.
* **Escalado de Privilegios Controlado:** Los operadores del tenant pueden administrar usuarios de su propia empresa, pero no ven ni pueden otorgar el flag `es_admin` salvo que ya tengan capacidad para gestionar privilegios.
* **Visual de Slug en EmpresaConfig:** El slug visible del tenant se muestra sin prefijo duro `/rxnTiendasIA/public/`, evitando ruido visual y manteniendo el alcance en solo lectura.
* **Versionado Interno Curado:** La release activa se declara en `app/config/version.php` y se consume via `VersionService`, permitiendo exponer las novedades solo a perfiles administradores del entorno interno y dejando la tienda publica sin ese bloque.
* **Disciplina de Release:** Toda iteracion relevante debe dejar sincronizados `docs/logs`, `docs/estado/current.md` y `app/config/version.php`; la release visible actual queda publicada como `v1.1.3` build `20260328.5`.
* **Bitácora Interna por Módulo:** Se agrega una mecánica admin-only para cargar anotaciones rápidas desde cada módulo, persistidas en JSON local (`app/storage/module_notes.json`), visibles en un widget flotante con arrastre/redimensión manual, minimización tipo dock compacta abajo a la derecha y auditables desde `/admin/notas-modulos` con soporte para varias capturas pegadas o adjuntas, referenciadas en texto como `#imagenN`.
* **Categorias Local-First:** Se incorpora un módulo tenant de categorías y una asignación por `empresa_id + codigo_externo` en `articulo_categoria_map`, evitando depender de `articulos.id` para que la clasificación sobreviva a purgas o resincronizaciones del catálogo.
* **Store por Categorías:** El catálogo público suma bloque visual de categorías, navegación rápida y filtro `?categoria=slug`, sin tocar carrito ni checkout.
* **Configuración Independiente por Entorno:** CRM deja de reutilizar la persistencia de `empresa_config` y pasa a usar `empresa_config_crm` para sus parámetros operativos, manteniendo el branding público exclusivamente del lado Tiendas.
* **Servicios Preparados por Área:** `TangoService`, `TangoSyncService` y `MailService` ya admiten resolución explícita por entorno (`tiendas`/`crm`) sin forzar autodetección global ni alterar el comportamiento actual de Tiendas.

## entorno local de referencia

* Wampserver 3.3.7 x64
* Apache 2.4.62.1
* PHP 8.3.14
* PHP CLI: `D:\RXNAPP\3.3\bin\php\php8.3.14\php.exe`
* MySQL 9.1.0
* MariaDB 11.5.2
* DBMS default: mysql

## riesgos

* El iterador generador inyectó rutas `<link>` en cabeceras dependientes del contexto `/rxnTiendasIA/public/`. Si el servidor cambia de ruta base a la raíz de dominio completa, es crítico ajustar `rxn-theming.css` URI y variables de upload.
* Si el Tenant sube logos `.svg` rotos, el Store frontend podría crashear su ratio visual temporalmente en cabeceras. (Validados por MIME superficial).
* La paginación del CRUD de empresas renderiza todos los números de página. Si el volumen crece mucho, convendrá compactar la navegación sin alterar la estrategia server-rendered.
* Las categorías requieren ejecutar la nueva migración (`database_migrations_categorias.php`) para crear `categorias` y `articulo_categoria_map` antes de operar el módulo.
* La independencia de configuración quedó acotada al MVP operativo: Store, clientes, pedidos y mailing público siguen leyendo `empresa_config` de Tiendas; CRM guarda su propia base pero aún no consume todos esos servicios compartidos.
* La sincronización CRM todavía no está expuesta por rutas/botones propios; el servicio ya quedó preparado, pero el árbol operativo visible sigue concentrando los syncs en Tiendas.

## próximo paso

* Testeo global en servidor DonWeb para asegurar compatibilidad con PHP 8.2 estricto alojado remotamente.
* Avanzar en siguientes características propuestas en la iteración del proyecto (Por ej: refinamientos de flujo transaccional Tango Connect si las hubiere).
* Evaluar un futuro mapeo de rubros desde Tango solo si el origen remoto demuestra estabilidad y no rompe el criterio local-first.
* Si CRM suma integraciones reales con correo o sync, desacoplar consumidores restantes para que también lean `empresa_config_crm` donde corresponda.
* Si se habilita sync desde CRM, evaluar ampliar trazabilidad para distinguir el área dentro de `tango_sync_logs`.
