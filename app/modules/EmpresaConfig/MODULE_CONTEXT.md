# MODULE_CONTEXT — EmpresaConfig

## Nivel de criticidad
ALTO. Este módulo guarda la configuración operativa de cada empresa (tenant) para las áreas **Tiendas** y **CRM**: credenciales Tango Connect, SMTP, identidad visual, numeración, plantillas de impresión, Google OAuth, etc. Un error acá deja a la empresa sin poder sincronizar con Tango ni emitir correos.

## Propósito
Centralizar la configuración por tenant. El módulo es **dual**: cada empresa tiene una fila en `empresa_config` (Tiendas) y otra en `empresa_config_crm` (CRM), desacopladas. La semilla inicial de CRM se copia de Tiendas al crear la empresa, pero después la edición queda independiente.

## Alcance

**QUÉ HACE:**
- Form de configuración con 5 secciones: Datos generales, Identidad de marca, Identidad visual corporativa, Integración Tango Connect, SMTP/Correo. El área CRM suma: Numeración interna, Formularios de impresión, Google Calendar OAuth.
- Validación live de la conexión Tango Connect (botón "Validar Conexión" + botón "Diagnóstico crudo" agregado en release 1.12.2).
- Auto-llenado del textarea de Clasificaciones PDS desde Tango (process 326) — release 1.12.3, solo si el textarea está vacío.
- AJAX endpoints atómicos que alimentan los selectores dinámicos del form: `tango-empresas`, `tango-listas`, `tango-depositos`, `tango-perfiles`, `tango-clasificaciones`, `tango-diagnose`.
- Uploads de logo, favicon, header/footer de impresión, imagen fallback — quedan en `/uploads/empresas/{empresaId}/branding/`.

**QUÉ NO HACE:**
- No sincroniza catálogos comerciales (eso lo hace **RxnSync** desde release 1.12.5 — ver `App\Modules\RxnSync\Services\CommercialCatalogSyncService`).
- No ejecuta syncs masivos de clientes/artículos/precios/stock — eso es responsabilidad de `RxnSync` y `TangoSyncController`.
- No administra usuarios (eso es `Usuarios`) ni empresas en el sentido del ABM (eso es `Empresas`). Este módulo solo edita la config de la empresa activa en el contexto.

## Bifurcación CRM vs Tiendas

El form es compartido entre áreas pero con diferencias semánticas importantes. El área se detecta por URI en `resolveArea()`: `/mi-empresa/crm/` → `crm`, si no → `tiendas`.

### Campos que aplican a ambas áreas
- `nombre_fantasia`, `email_contacto`, `telefono`
- Credenciales Tango Connect: `tango_api_url`, `tango_connect_key`, `tango_connect_company_id`, `tango_connect_token`
- `cantidad_articulos_sync` (batch de Sync Artículos)
- `clasificaciones_pds_raw` (catálogo local de clasificaciones PDS, usado por el PDS)
- `tango_perfil_snapshot_json` (snapshot interno del perfil de pedidos)
- SMTP: `smtp_host`, `smtp_port`, `smtp_user`, `smtp_pass`, `smtp_secure`, `smtp_from_email`, `smtp_from_name`, `usa_smtp_propio`

### Campos **Tiendas-only** (release 1.12.5)
- `lista_precio_1`, `lista_precio_2` — son las 2 listas fuente de verdad para el Sync Precios de Tiendas (que actualiza columnas planas `precio_lista_1` / `precio_lista_2` en `crm_articulos`).
- `deposito_codigo` — el depósito de referencia para Sync Stock de Tiendas (actualiza `stock_actual` plano en `crm_articulos`).

En el área CRM estos 3 campos **se ocultan en el form** y se muestra un banner informativo: *"Listas y Depósitos en CRM se sincronizan todos automáticamente desde Sync Catálogos en RxnSync."* Se mantienen físicamente en la tabla `empresa_config_crm` por backward compat, pero no se usan en el flujo CRM.

### Campos **CRM-only**
- `pds_numero_base`, `presupuesto_numero_base`
- `pds_email_pdf_canvas_id`, `presupuesto_email_pdf_canvas_id`
- `pds_email_body_canvas_id`, `presupuesto_email_body_canvas_id`
- `pds_email_asunto`, `presupuesto_email_asunto`
- `impresion_header_url`, `impresion_footer_url`
- `google_oauth_client_id`, `google_oauth_client_secret`, `google_oauth_redirect_uri`, `agenda_google_auth_mode`

### Identidad B2C (`$showStoreBranding`)
- `logo_url`, `favicon_url`, `color_primary`, `color_secondary`, `footer_text`, `footer_address`, `footer_phone`, `footer_socials` — solo se renderiza si `$showStoreBranding = true`, que por default es Tiendas. En CRM solo se permite cambiar el `favicon_url` (para la pestaña del navegador del CRM).

## Piezas principales

- **Controlador**: `EmpresaConfigController.php`
  - `index()` — renderiza el form.
  - `store()` — persiste el POST.
  - `testConnection()` — AJAX para probar SMTP.
  - `testConnectTango()` — AJAX handshake Tango Connect.
  - `getTangoEmpresas()` / `getTangoListas()` / `getTangoDepositos()` / `getTangoPerfiles()` / `getTangoClasificaciones()` — endpoints atómicos que alimentan los selectores dinámicos del form (release 1.12.2 + 1.12.3).
  - `diagnoseTangoConnect()` — dump crudo de process=1418 para diagnóstico (release 1.12.2).

- **Service**: `EmpresaConfigService.php` — bifurca `forCrm()` vs constructor default según área.
- **Repository**: `EmpresaConfigRepository.php` — también bifurca con `forCrm()` / `forArea($area)`. Opera sobre `empresa_config` (Tiendas) o `empresa_config_crm` (CRM).
- **Modelo**: `EmpresaConfig.php` — DTO con todos los campos (no bifurca — la bifurcación se aplica en qué campos leen/escriben los repos y cuáles renderiza la vista).

## Vista

`views/index.php` es una vista ÚNICA compartida entre áreas. Usa la variable `$area` para renderizar condicionalmente los bloques específicos de CRM/Tiendas. Los títulos y subtítulos vienen como variables (`$tangoSectionTitle`, `$smtpSectionTitle`, etc.) que el Controller arma en `buildViewContext($area)`.

**Widgets destacados**:
- Selectores asistidos (Empresa Connect, Listas, Depósito) con patrón `applyLocalSearchPattern()` — un `<select>` oculto + input visible con sugerencias locales. El wrapper sube a `z-index: 1060` al focus para que el dropdown no quede tapado por vecinos (release 1.12.3).
- Botón "Diagnóstico crudo" (release 1.12.2) → dump de process=1418 con Company: -1.
- Panel de diagnóstico `#tango-diagnostic-panel` que se pinta cuando algún catálogo viene vacío o con error, con detalle de id_keys vs first_item_keys para detectar cambios de shape en Axoft.

## Persistencia

- Tabla `empresa_config` (Tiendas). Columnas operativas: `tango_*`, `lista_precio_*`, `deposito_codigo`, `smtp_*`, `clasificaciones_pds_raw`.
- Tabla `empresa_config_crm` (CRM). Mismas columnas + los específicos CRM (pds_*, presupuesto_*, google_oauth_*, impresion_*). `lista_precio_*` y `deposito_codigo` existen físicamente en esta tabla por compatibilidad pero están ocultas en el form y no se usan.

## Seguridad

- **Aislamiento multiempresa**: todas las queries filtran por `empresa_id` del `Context`. No hay lectura cruzada entre tenants.
- **Guards**: `AuthService::requireLogin()` en todos los endpoints.
- **Tokens sensibles**: el `tango_connect_token` y el `smtp_pass` se almacenan en DB. En los endpoints de diagnóstico, el token NUNCA viaja al frontend — se redacta a `***redacted***` con `scrubSensitiveHeaders()` antes de emitir cualquier debug info JSON.
- **Google OAuth secret**: encriptado en DB (placeholder "Guardado. Dejá vacío para mantener." si ya hay valor).
- **CSRF**: actualmente sin validación de token CSRF en los endpoints AJAX. Deuda de seguridad activa compartida con RxnSync.

## Reglas operativas

- **JS scope**: `configBase`, `tangoDiagnostics` y `recordTangoDiagnostic` viven en el scope del `DOMContentLoaded` (no adentro de `loadTangoMetadata`) — regresión fixeada en release 1.12.2 hotfix. No mover a inner scope.
- **Fuente de verdad del form**: el JS AJAX usa `tangoBtn.closest('form')` para serializar, no `document.querySelector('form')`. Eso protege del widget de notas compartido que inserta otro `<form>` antes del principal (release 1.12.2 hotfix).
- **Shape del textarea Clasificaciones PDS**: es líneas planas `CODIGO descripcion`, NO JSON. El servicio `ClasificacionCatalogService::parseRaw()` usa regex split. El auto-llenado de `populateClasificacionesPds()` respeta el shape y solo sobrescribe si el textarea está vacío.
- **Diagnóstico persistente > DevTools**: regla del proyecto. Si algún catálogo Connect viene vacío o con error, el banner visible explica por qué (HTTP code, id_keys esperadas vs first_item_keys recibidas, raw_sample). No depender de que el operador abra DevTools.

## Rutas y Pantallas
- `/mi-empresa/configuracion` (Tiendas) y `/mi-empresa/crm/configuracion` (CRM): GET renderiza el form, POST persiste.
- `/mi-empresa/[crm/]configuracion/test-smtp`: POST AJAX, handshake SMTP.
- `/mi-empresa/[crm/]configuracion/test-tango`: POST AJAX, handshake Tango.
- `/mi-empresa/[crm/]configuracion/tango-empresas|listas|depositos|perfiles|clasificaciones`: POST AJAX, endpoints atómicos del form.
- `/mi-empresa/[crm/]configuracion/tango-diagnose`: POST AJAX, dump crudo para diagnóstico.

## Dependencias directas

- `TangoApiClient` — para handshake y fetch de catálogos Connect.
- `App\Core\Services\MailService` — para testear SMTP.
- `Empresas\EmpresaRepository` — para persistir branding (logo, favicon, colores, footer).
- `OperationalAreaService` — para resolver área operativa y paths.

## Dependencias indirectas / impacto lateral

- **RxnSync** consume las credenciales Tango para hacer sync masivo.
- **TangoSyncService** depende de `lista_precio_1/2` y `deposito_codigo` en Tiendas (si no están configurados, syncPrecios/syncStock tiran excepción con mensaje claro). En CRM NO dependen de esos campos.
- **CrmPedidosServicio**, **CrmPresupuestos**, **CrmAgenda** consumen los campos CRM-only (numeración, plantillas, OAuth).

## Tipo de cambios permitidos

- Agregar campos nuevos al form (con migración correspondiente).
- Ajustes visuales del form.
- Nuevos endpoints de diagnóstico/validación AJAX.

## Tipo de cambios sensibles

- Cambiar el shape de `clasificaciones_pds_raw` (rompe `ClasificacionCatalogService`).
- Cambiar la lógica de bifurcación CRM/Tiendas (romper la visibilidad condicional confunde operadores y puede romper sync de Precios/Stock si los campos Tiendas-only se empiezan a usar en CRM o viceversa).
- Exponer el token Tango o el SMTP pass en respuestas JSON o logs.
- Tocar `EmpresaConfigRepository::forArea()` — afecta a TODOS los consumers (EmpresaConfig, RxnSync, TangoSyncService, CommercialCatalogSyncService, UsuarioController, etc.).

## Riesgos conocidos

- **Bug dormido de shape** en `UsuarioController::fetchTangoProfile():137`: guarda `json_encode($items)` al textarea `clasificaciones_pds_raw` — shape incompatible con `parseRaw()`. Documentado como tech debt en release 1.12.3 (no atacado porque es caller externo). Workaround: desde release 1.12.3 el operador puede vaciar el textarea y dejar que `populateClasificacionesPds` lo escriba bien al próximo Validar Conexión.

## Checklist post-cambio

- [ ] Form se guarda correctamente en Tiendas y CRM (dos tenants distintos no se pisan).
- [ ] "Validar Conexión" trae empresas, listas, depósitos, perfiles, clasificaciones — los 5 fetches paralelos.
- [ ] Si algún catálogo viene vacío o con error, el banner de diagnóstico aparece con info útil.
- [ ] En área CRM NO se renderizan `lista_precio_1/2` ni `deposito_codigo`; en su lugar aparece el banner "Sincronizados desde RxnSync".
- [ ] Token Tango no aparece en ningún JSON response.
- [ ] Los selectores dinámicos (Empresa, Listas, Depósito) tienen z-index correcto — el dropdown activo queda arriba de los vecinos.

## Documentación relacionada

- `docs/architecture.md` (Patrón Local-First)
- `app/modules/RxnSync/MODULE_CONTEXT.md` (consumidor principal de la config Tango)
- `app/modules/CrmPresupuestos/MODULE_CONTEXT.md` (consume catálogos poblados por RxnSync)
- `app/modules/Tango/MODULE_CONTEXT.md` (API cliente que este módulo instancia)
