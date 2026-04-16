# Control de Cambio: Auditoría RxnSync y Creación de MODULE_CONTEXT Auth
Fecha: 2026-04-09

## Resumen
Se auditaron las diferencias entre el código y la documentación del módulo `RxnSync` y se creó el `MODULE_CONTEXT.md` oficial para el módulo `Auth`.

## Modificaciones

### 1. RxnSync
- **Auditoría**: Se detectó una discrepancia en `app/modules/RxnSync/MODULE_CONTEXT.md` respecto a la limitación de paginación del "Match Suave".
- **Realidad de código**: `resolveTangoIdBySku` en `RxnSyncService.php` implementa iteración de `pageIndex` usando un bucle `while(true)` y corta al llegar a `pageIndex >= 10`, lo que deja un techo duro de 11 páginas efectivas. En paralelo, `auditarArticulos()` y `auditarClientes()` siguen trayendo sólo la primera página remota.
- **Cambio**: Se reescribió la sección de riesgos para separar ambos comportamientos: lookup individual con paginación acotada vs auditorías masivas todavía limitadas a primera página.

### 2. Auth
- **Creación**: Se redactó `app/modules/Auth/MODULE_CONTEXT.md`.
- **Estructura**: Documenta el propósito perimetral de seguridad, inyección multiempresa a nivel de sesión (`$_SESSION['empresa_id']`), la separación estricta entre Admin Local vs Admin Global, los riesgos asociados al AuthService y la validación obligatoria server-side de credenciales.
- **Seguridad Base**: Se explícita la regeneración del session ID contra ataques de fixation, el aislamiento tenant mediante `$usuario->empresa_id`, la dependencia de correo transaccional vía `MailService` y la excepción sensible del flujo de verificación por `GET` tokenizado.

## Seguridad
- Multiempresa: Documentado el seteo y aislamiento inmutable de `empresa_id` en Auth.
- Validaciones server-side: Explícitas en el checklist y la política de Auth. No se almacena ninguna clave plana y las validaciones de hash ocurren exclusivamente en backend.
- Prevención Fixation: Confirmado el uso de `session_regenerate_id()`.
- Mutación por GET: El login y reset operan por `POST`, pero la verificación de cuenta vigente muta estado vía enlace tokenizado; quedó documentado como excepción sensible, no como inexistente.
- Ambos cambios son de alcance puramente documental. Ninguna lógica de negocio en PHP fue alterada en este commit, minimizando el riesgo a 0.
