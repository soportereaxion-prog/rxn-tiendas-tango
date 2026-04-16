# Política y Seguridad de Sesiones Actualizada

**Fecha:** 2026-04-02 13:06
**Objetivo:** Implementación de política de duración y recolección de sesión para operador (Backoffice).

## Resumen
Se revisó el arranque global de sesión en `app/core/App.php` y en las funciones de autenticación del Backoffice (`app/modules/auth/AuthService.php`). Se insertó de forma controlada y quirúrgica una lógica de tracking de tiempo y limpieza exclusiva para los administradores que NO interfiere con el entorno público/clientes web ni requiere forzar SSL innecesario (`secure=true` sólo se lanza si se detecta HTTPS).

## Archivos modificados
- `app/core/App.php`: Agregada la validación de expiración relativa y absoluta.
- `app/modules/auth/AuthService.php`: Agregado `session_regenerate_id(true)` tras el login exitoso (mitigación de fixation) y setup inicial de los timestamp.

## Configuración Existente y Retenida
- `session.cookie_lifetime`: 86400 (24hs). Se conservó para asegurar que el control de expiración caiga enteramente en nuestra lógica restrictiva y no de forma silenciosa por el browser o el GC nativo.
- `session_set_cookie_params`: Correctamente preparado con `httponly=true`, `samesite=Lax` y el condicional estricto para `secure` basado en protocolo. Todo esto previene robos de cookie.
- Las sesiones de Clientes (`ClienteWebContext`) NO fueron alteradas, aislando los scopes de backoffice de la tienda pública. 

## Reglas aplicadas al Operador
- **Idle Timeout (Inactividad):** 6 horas limit (21600 segundos). El timer de inactividad se renueva globalmente cada vez que pasa por `App.php` si la sesión sigue viva.
- **Absolute Timeout (Caduco Múltiple):** 12 horas limit (43200 segundos). Si pasan 12 hs desde que inició sesión, se cierran independientemente de su actividad.
- **Expiración selectiva:** Cuando se constata la expiración, NO se hace un `session_destroy()`, sino que se `unset(...)` de todas las llaves exclusivas de backoffice. Esto garantiza que si el usuario estaba en simultáneo navegando algo del lado local público, esa sub-sesión no se muere.

## Compatibilidad Local
Puesto que no se modifica el dominio, las validaciones de PHP no rompen puentes (ej. localhost vs Local IP). El uso condicional de `isset($_SERVER['HTTPS'])` asegura compatibilidad 100% con un entorno XAMPP típico.
