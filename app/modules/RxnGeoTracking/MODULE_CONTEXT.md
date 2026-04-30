# MODULE_CONTEXT — RxnGeoTracking

> **ESTADO: IMPLEMENTADO (desde release 1.10.0 – 1.12.0, 2026-04-16).** Infraestructura + consentimiento + login + dashboard admin + integración en Presupuestos/Tratativas/PDS + job de purga. Módulo completo según las 6 fases del plan original.

## Nivel de criticidad
ALTO. Aunque no es transaccional de negocio (no emite comprobantes ni toca Tango), maneja **datos personales sensibles** (geolocalización + IP + user-agent correlacionados con identidad de usuario), lo cual lo ubica bajo la Ley 25.326 de Protección de Datos Personales (Argentina). Un error en este módulo no rompe facturación, pero puede generar responsabilidad legal y pérdida de confianza del cliente final.

## Propósito
Registrar de forma uniforme y auditable **la ubicación aproximada y la IP** desde la cual un usuario logueado ejecuta acciones críticas del CRM (login, creación de Presupuestos, creación de Tratativas, creación de Pedidos de Servicio), exponer esos eventos a los administradores de la empresa tenant mediante un dashboard con mapa, y hacerlo bajo un marco de consentimiento explícito y retención configurable por tenant.

## Alcance

**QUÉ HACE:**
- Provee un servicio central (`GeoTrackingService`) que otros módulos invocan con una sola línea para registrar un evento tipado.
- Captura **IP-based geolocation** siempre (fallback barato, zero-friction) vía resolución server-side con API externa (candidatos: `ip-api.com`, `ipinfo.io`, `MaxMind GeoLite2` self-hosted).
- Captura **Browser Geolocation API** cuando el usuario consintió y el navegador devuelve posición (GPS en mobile, WiFi triangulation en desktop).
- Persiste eventos en `rxn_geo_eventos` scopeados estrictamente por `empresa_id`.
- Gestiona un **banner de consentimiento** reusable con versión trazable (`consent_version`) para cumplimiento legal.
- Aplica **retención configurable por empresa** mediante job periódico que purga eventos más viejos que `retention_days` (default 90).
- Renderiza un **dashboard admin** con mapa Google Maps, markers por evento, filtros (usuario, rango de fechas, tipo de evento, entidad) y export CSV.

**QUÉ NO HACE:**
- **No bloquea** la acción del usuario si la geolocalización falla, si denegó permisos, o si el servicio de resolución de IP está caído. La captura es **fire-and-forget**; el flujo transaccional del módulo llamante no puede depender del éxito del tracking.
- No trackea ediciones, lecturas, aprobaciones ni cambios de estado. Solo **creación** de las entidades citadas y login.
- No hace inferencia de patrones, alertas por comportamiento anómalo, ni detección de fraude. Es un log auditable, no un motor de heurísticas.
- No expone los datos a usuarios no administradores de la empresa, ni entre tenants distintos.
- No persiste dirección exacta ni stream continuo de posiciones (no es una app de fleet tracking).

## Piezas principales

- **Controladores:**
  - `RxnGeoTrackingController` — dashboard admin (`index`), detalle de evento (`show`), export CSV (`export`), endpoint AJAX de posiciones para Google Maps (`mapPoints`).
  - `RxnGeoTrackingConsentController` — endpoint POST para que el frontend grabe la respuesta del banner (`accept`, `deny`, `later`).
  - `RxnGeoTrackingConfigController` — ABM de configuración por empresa (retention_days, habilitado, consent_version vigente).
- **Servicios:**
  - `GeoTrackingService` — API pública que otros módulos consumen. Métodos: `registrar(string $eventType, ?int $entidadId = null, ?string $entidadTipo = null)`, `tieneConsentimientoVigente(int $userId): bool`, `purgarEventosExpirados(int $empresaId): int`.
  - `IpGeolocationResolver` — abstrae la API externa. Interface con método `resolver(string $ip): ?GeoLocation` y al menos una implementación concreta (ej. `MaxMindLocalResolver` si vamos self-hosted, o `IpApiResolver` si usamos servicio externo).
  - `GeoEventRepository` — persistencia de eventos.
  - `GeoTrackingConfigRepository` — configuración por tenant.
- **Vistas:**
  - `views/dashboard.php` — mapa + listado + filtros.
  - `views/config.php` — configuración del módulo por empresa.
  - `views/_consent_banner.php` — partial incluido desde `admin_layout.php`.
- **Rutas/Pantallas:**
  - `/mi-empresa/geo-tracking` — dashboard (solo admin empresa).
  - `/mi-empresa/geo-tracking/config` — configuración (solo admin empresa).
  - `/geo-tracking/consent` (POST) — grabación de respuesta del banner (cualquier usuario autenticado).
  - `/geo-tracking/report` (POST, XHR) — endpoint que recibe la posición capturada por el navegador después de una acción (lat/lng/accuracy).
- **Tablas/Persistencia:**
  - `rxn_geo_eventos` (ver Schema propuesto abajo).
  - `rxn_geo_config` (ver Schema propuesto abajo).
  - `rxn_geo_consent` — registro por usuario del consentimiento otorgado.
- **Assets frontend:**
  - `public/js/rxn-geo-tracking.js` — helper cliente que pide `navigator.geolocation.getCurrentPosition()` después de una acción exitosa y hace POST a `/geo-tracking/report` con el `evento_id` devuelto por el servidor.
  - `public/js/rxn-geo-consent.js` — lógica del banner de consentimiento.
  - `public/js/rxn-geo-tracking-dashboard.js` — inicializa Google Maps en el dashboard admin, pobla markers con color-coding por event_type, popup al click.
- **Scripts CLI:**
  - `app/modules/RxnGeoTracking/tools/purge_geo_events.php` — job de purga diaria. Itera las empresas con eventos, lee `retention_days` de cada una, borra eventos más viejos. Soporta `--dry-run` y `--verbose`. Agendar via cron diario del server (sugerido 3 AM). El script se empaqueta en el OTA automáticamente por estar bajo `app/` (whitelist del ReleaseBuilder).

## Schema propuesto

### `rxn_geo_eventos`
| Columna             | Tipo           | Notas                                                          |
|---------------------|----------------|----------------------------------------------------------------|
| `id`                | BIGINT PK      |                                                                |
| `empresa_id`        | INT NOT NULL   | FK → `empresas.id`. **Multi-tenant obligatorio**.              |
| `user_id`           | INT NOT NULL   | FK → `usuarios.id`.                                            |
| `event_type`        | VARCHAR(64)    | `login` \| `presupuesto.created` \| `tratativa.created` \| `pds.created` |
| `entidad_tipo`      | VARCHAR(32)    | NULL en login. `presupuesto` \| `tratativa` \| `pds`.          |
| `entidad_id`        | BIGINT         | NULL en login. ID de la entidad recién creada.                 |
| `ip_address`        | VARCHAR(45)    | IPv4 o IPv6.                                                   |
| `lat`               | DECIMAL(10,7)  | NULL si no se obtuvo.                                          |
| `lng`               | DECIMAL(10,7)  | NULL si no se obtuvo.                                          |
| `accuracy_meters`   | INT            | Devuelto por Geolocation API. NULL si no aplica.               |
| `accuracy_source`   | VARCHAR(16)    | `ip` \| `gps` \| `wifi` \| `denied` \| `error`                 |
| `resolved_city`     | VARCHAR(128)   | NULL si no se pudo resolver.                                   |
| `resolved_country`  | CHAR(2)        | ISO 3166-1 alpha-2. NULL si no se pudo resolver.               |
| `user_agent`        | TEXT           | Hash o truncado a 512 chars para no inflar la tabla.           |
| `consent_version`   | VARCHAR(16)    | Versión del consentimiento vigente al momento del evento.      |
| `created_at`        | DATETIME       | Timestamp del evento en hora del servidor.                     |

Índices: `(empresa_id, created_at)`, `(empresa_id, user_id, created_at)`, `(event_type)`, `(created_at)` para purga.

### `rxn_geo_config`
| Columna                   | Tipo        | Notas                                                   |
|---------------------------|-------------|---------------------------------------------------------|
| `empresa_id`              | INT PK      | 1:1 con empresa.                                        |
| `habilitado`              | TINYINT(1)  | Default 1. Permite desactivar el módulo por tenant.     |
| `retention_days`          | INT         | Default 90. Rango sugerido 30–730.                      |
| `requires_gps`            | TINYINT(1)  | Default 0. Si 1, el banner no ofrece opción "denegar".  |
| `consent_version_current` | VARCHAR(16) | Versión vigente. Bumpear cuando cambia la política.     |
| `updated_at`              | DATETIME    |                                                         |

### `rxn_geo_consent`
| Columna               | Tipo        | Notas                                                       |
|-----------------------|-------------|-------------------------------------------------------------|
| `id`                  | BIGINT PK   |                                                             |
| `user_id`             | INT         | FK → `usuarios.id`.                                         |
| `empresa_id`          | INT         | FK → `empresas.id`. Trazabilidad cross-tenant.              |
| `consent_version`     | VARCHAR(16) | Versión que aceptó/rechazó.                                 |
| `decision`            | VARCHAR(16) | `accepted` \| `denied` \| `later`                           |
| `ip_address`          | VARCHAR(45) | IP al momento de la decisión (prueba legal).                |
| `user_agent`          | TEXT        | User-agent al momento de la decisión.                       |
| `created_at`          | DATETIME    |                                                             |

Índice único: `(user_id, empresa_id, consent_version)` para evitar duplicados pero permitir histórico cuando sube la versión.

## Seguridad Base (Política de Implementación)
- **Aislamiento Multiempresa**: OBLIGATORIO Y ESTRICTO. Toda consulta al dashboard, exports y endpoints filtra por `Context::getEmpresaId()`. Un admin de empresa A **nunca** puede ver eventos de empresa B.
- **Permisos / Guards**: El dashboard y la configuración requieren `AuthService::requireLogin()` + validación `es_admin = 1` de la empresa tenant. El endpoint `/geo-tracking/report` solo requiere login (cualquier usuario autenticado reporta su propia posición sobre su propio evento).
- **Mutación**: Persistencia de eventos se ejecuta server-side al finalizar la acción del módulo llamante. El endpoint `/geo-tracking/report` POST agrega la posición al evento ya creado, validando que `user_id` del evento === `user_id` de la sesión.
- **Validación Server-Side**: Lat/lng validados como decimales en rango (-90..90, -180..180). `accuracy_meters` > 0. `event_type` contra whitelist estricta. `consent_version` contra la versión vigente en `rxn_geo_config`.
- **Escape Seguro (XSS)**: User-agent, nombres de ciudad resueltos, y cualquier input se escapa al renderizar en dashboard.
- **Protección CSRF**: El endpoint `/geo-tracking/report` usa el token CSRF estándar del framework.
- **Rate limiting**: Máximo 1 report por evento; el servidor rechaza reports duplicados por `evento_id`.
- **Acceso Local**: Dashboard sujeto al tenant de la sesión.

## Consentimiento legal (Ley 25.326)
- **Banner obligatorio**: aparece en la primera sesión del usuario bajo cada `consent_version_current`. Si ya respondió esa versión, no vuelve a aparecer hasta que se bumpee.
- **Texto del banner** debe ser claro, explícito y no manipulador. Opciones: `Acepto` / `No acepto` / `Decidir después`.
  - Si `requires_gps = 0`: "No acepto" deja los eventos con `accuracy_source = 'denied'` (solo IP).
  - Si `requires_gps = 1`: el banner no muestra "No acepto" y la decisión binaria es `Acepto` / `Cerrar sesión`.
- **Registro de la decisión** en `rxn_geo_consent` con IP y user-agent como prueba legal.
- **Política de Privacidad de la suite** debe mencionar explícitamente qué se captura, por qué, cuánto se retiene, quién lo ve, y cómo ejercer el derecho ARCO (acceso, rectificación, cancelación, oposición). Esto es responsabilidad de la empresa operadora de la suite y del tenant que la usa con sus empleados — **no del módulo en sí**, pero el módulo no debe activarse en un tenant hasta que ese texto exista.
- **Bumpear `consent_version`** cada vez que cambia materialmente lo que se trackea o cómo se usa. Cambios menores (bugfixes, perf) no bumpean.

## Dependencias directas
- `App\Core\Context` para tenant y session.
- `App\Modules\Auth\AuthService::requireLogin()` para guards.
- `App\Modules\Empresas\EmpresaRepository` para validación de configuración.
- `App\Modules\Usuarios\UsuarioRepository` para lookups en dashboard.
- Provider de IP geolocation: librería o servicio externo (ver Dependencias indirectas).

## Dependencias indirectas / impacto lateral
- **API externa de IP geolocation**: si elegimos servicio cloud (ip-api, ipinfo), caídas del servicio o cambios de pricing impactan. Self-hosted con MaxMind GeoLite2 evita eso a costa de mantener el binario/DB actualizado mensualmente.
- **Google Maps JS API**: el dashboard depende del SDK de Maps; requiere API Key válida por empresa o global del suite. Tocar su cupo o revocarla rompe el mapa (el listado/export sigue funcionando).
- **Frontend `admin_layout.php`**: necesita incluir el banner de consentimiento y el helper `rxn-geo-tracking.js`. Un cambio en el layout puede romper ambos.
- **Módulos consumidores** (Auth, CrmPresupuestos, CrmTratativas, CrmPedidosServicio): si cambian la firma de `GeoTrackingService::registrar()` sin actualizar a los cuatro, hay drift.

## Reglas operativas del módulo

- **Invocación desde módulos consumidores**:
  ```php
  // En el controller llamante, después del insert exitoso:
  $eventoId = GeoTrackingService::registrar('presupuesto.created', $presupuestoId, 'presupuesto');
  // Devuelve el ID del evento server-side (con IP ya capturada). El frontend puede
  // después hacer POST a /geo-tracking/report con lat/lng si el user consintió.
  ```
- **Fire-and-forget**: el `registrar()` **nunca** debe lanzar excepción que rompa el flujo del módulo llamante. Captura internamente todo error (API externa caída, DB timeout, etc.), loguea, y retorna `null` si falló. Los tests del módulo consumidor no deben requerir mockear este servicio.
- **Asincronía del frontend**: `rxn-geo-tracking.js` solicita `navigator.geolocation.getCurrentPosition()` con timeout de 5s. Si el user no responde al prompt o denegó anteriormente, se skippea silenciosamente.
- **Purga periódica**: el job de limpieza corre vía cron (o el scheduler interno de la suite si existe) una vez por día. Itera sobre `rxn_geo_config` y para cada empresa borra eventos con `created_at < NOW() - INTERVAL retention_days DAY`. Registra la cantidad borrada.
- **Retention configurable**: el admin de empresa puede cambiar `retention_days` en `/mi-empresa/geo-tracking/config`. Rango permitido 30–730. Valores fuera de rango se rechazan con mensaje claro.
- **Dashboard default**: al entrar muestra los últimos 7 días del tenant. Filtros: usuario, rango fecha, event_type, entidad_tipo. Mapa carga los puntos como marcadores; clic abre popup con detalle del evento.
- **Export CSV**: limitado a 10.000 filas por export para no tumbar el request. Si hay más, forzar filtrado por rango más chico.

## Tipo de cambios permitidos
- Agregar nuevos `event_type` en whitelist para soportar nuevos módulos consumidores (ej. `factura.created` cuando Facturación se sume).
- Mejorar UX del dashboard: clusters en el mapa, heatmap, filtros adicionales.
- Agregar exports adicionales (PDF reporte, JSON).
- Cambiar el provider de IP resolution sin cambiar la interface `IpGeolocationResolver`.

## Tipo de cambios sensibles
- **Cambiar el shape de `rxn_geo_eventos`**: requiere migración cuidadosa, especialmente si modifica campos usados por módulos consumidores. Cualquier cambio en columnas leídas por el dashboard o por queries de auditoría debe preservar compatibilidad o migrar data histórica explícitamente.
- **Modificar `GeoTrackingService::registrar()`**: la firma es consumida por 4+ módulos. Cualquier cambio debe aplicarse consistentemente y preferentemente agregar parámetros opcionales en lugar de cambiar orden.
- **Bajar `retention_days` default o forzar retención más corta**: debe documentarse y comunicarse a los tenants; puede afectar auditorías abiertas.
- **Tocar la lógica de consentimiento o el `consent_version`**: implica implicancias legales. Cualquier cambio debe revisarse contra la política de privacidad vigente.

## Riesgos conocidos

- **VPN/Proxy**: IP-based geolocation devuelve ubicación del exit node del VPN, no del usuario. No hay mitigación técnica; documentar como limitación conocida.
- **Denegación masiva de permisos**: si la mayoría de usuarios rechazan el Geolocation API, el valor del tracking cae a resolución de ciudad por IP. Es aceptable pero hay que setear expectativas.
- **Saturación de la tabla**: 50 usuarios × 20 acciones/día × 365 días = ~365k filas/año/tenant. Con retención de 90 días y 10 tenants, la tabla sostiene ~900k filas activas. Con índices correctos es manejable, pero hay que monitorear.
- **Fuga de datos al provider externo de IP geolocation**: enviamos IPs del usuario final a un tercero. Mitigaciones: self-hosted MaxMind (recomendado), o elegir provider con política de retención cero / EU-based con GDPR compliance.
- **Dependencia de JavaScript en el cliente**: usuarios con JS deshabilitado no reportan lat/lng precisa. Fallback natural: queda solo IP. No bloqueante.
- **Riesgo legal por mal uso del tenant**: si un admin de empresa usa el dashboard para vigilar empleados de forma desproporcionada, la responsabilidad es del empleador. La suite documenta el uso legítimo pero no puede evitar abuso. Mitigación: logs de acceso al dashboard (meta-auditoría).
- **Google Maps API key expuesta en frontend**: la Key del cliente es visible en el bundle. Mitigación estándar: restringir la Key por HTTP referrer al dominio de la suite.

## Checklist post-cambio
- [ ] Login graba evento `login` con IP resuelta correctamente.
- [ ] Crear un Presupuesto graba evento `presupuesto.created` con `entidad_id` del presupuesto creado.
- [ ] Crear una Tratativa graba evento `tratativa.created` con `entidad_id` de la tratativa.
- [ ] Crear un PDS graba evento `pds.created` con `entidad_id` del PDS.
- [ ] El banner de consentimiento aparece para usuarios nuevos o cuando sube `consent_version`.
- [ ] Un usuario que denegó consentimiento sigue generando eventos con `accuracy_source = 'denied'` (solo IP).
- [ ] Si el servicio de IP geolocation falla, la acción del módulo consumidor sigue funcionando sin error visible al usuario.
- [ ] Admin de empresa A no puede ver eventos de empresa B (test multi-tenant).
- [ ] El usuario no-admin no puede acceder al dashboard.
- [ ] Export CSV respeta filtros activos y límite de 10k filas.
- [ ] Job de purga corre y borra eventos expirados sin borrar los vigentes.
- [ ] Cambiar `retention_days` en config refleja en la próxima corrida del job.
- [ ] Mapa Google Maps renderiza con marcadores en las coordenadas correctas.
- [ ] Consent se persiste en `rxn_geo_consent` con IP y user-agent.

## Historial de implementación

Las 6 fases originales del plan quedaron entregadas entre las releases **1.10.0 → 1.12.0** (2026-04-16):

1. **Fase 1 — Infraestructura** ✅ Release 1.10.0. Tablas + service + resolver + repositorios.
2. **Fase 2 — Consentimiento** ✅ Release 1.10.0. Banner + endpoint + tabla consent.
3. **Fase 3 — Integración en Auth** ✅ Release 1.10.0. Login dispara evento.
4. **Fase 4 — Dashboard admin** ✅ Release 1.11.0. Google Maps + listado + export + config.
5. **Fase 5 — Integración en transaccionales** ✅ Release 1.12.0. Presupuestos, Tratativas, PDS.
6. **Fase 6 — Job de purga** ✅ Release 1.12.0. Script CLI `tools/purge_geo_events.php` + doc de cron.

El módulo queda cerrado al MVP del plan. Mejoras futuras posibles (fuera del alcance inicial): clustering en el mapa, heatmap, alertas por eventos anómalos, integración con MaxMind self-hosted.

## Integración con PWA mobile (release 1.38.0)

`App\Modules\RxnPwa` consume este módulo como **requisito core**, no opcional. La PWA tiene un gate global de GPS (`rxnpwa-geo-gate.js`) que bloquea toda interacción con la app si el operador no concede acceso a la ubicación. Es un diferencial central del producto: cada presupuesto emitido en campo se rastrea geográficamente.

### Flujo de captura PWA → server

1. **Cliente PWA** captura GPS al entrar al shell o form (gate bloqueante). `RxnPwaGeoGate.getCurrentGeo()` queda en memoria con `{lat, lng, accuracy, source: 'gps'|'wifi'}`.
2. **Cliente PWA** copia la geo al draft de IndexedDB al guardar.
3. **Cliente PWA** manda `{lat, lng, accuracy, source}` al server en el JSON del sync (`rxnpwa-sync-queue.js::draftToWire`).
4. **Server** `RxnPwaController::syncPresupuesto` llama a `recordGeoEvent()` post-create:
   - `GeoTrackingService::registrar(EVENT_PRESUPUESTO_CREATED, $presupuestoId, 'presupuesto')` crea evento con fallback IP.
   - `GeoTrackingService::reportarPosicionBrowser($eventoId, $lat, $lng, $accuracy, $source)` actualiza con la posición precisa del celu.
5. **Resultado en `rxn_geo_eventos`**: fila con `accuracy_source='gps'/'wifi'/'denied'` y `accuracy_meters` real del celu.

### Diferencia con creación desde web

- **Web**: el evento se crea en `store()` con IP fallback; el JS del browser después llama a `/geo-tracking/report` con la posición del browser. Si el user denegó, queda con `accuracy_source='ip'`.
- **PWA**: el evento se crea con la posición del celu YA presente en el payload. No hace falta el segundo paso `/geo-tracking/report` — `recordGeoEvent` lo hace server-side directo.

### Gate bloqueante en PWA — implicancias

- Un presupuesto creado desde la PWA SIEMPRE tiene geo válida (`source='gps'` o `'wifi'`). Si el operador no concedió GPS, no pudo entrar a la app.
- Casos `accuracy_source='denied'` o `'ip'` en `rxn_geo_eventos` con `entidad_tipo='presupuesto'` → sólo pueden venir del web (donde el banner de consentimiento sigue siendo opt-in).
- Si querés distinguir "creado en campo" (PWA) de "creado en oficina" (web), filtrá `accuracy_source IN ('gps', 'wifi')` — típicamente implica PWA mobile.
