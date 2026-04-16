# MODULE_CONTEXT — Auth

## Nivel de criticidad
ALTO (Crítico para la Seguridad)

Este módulo impacta directamente en:
- Acceso al sistema y seguridad perimetral de la aplicación.
- Aislamiento multiempresa.
- Manejo de sesiones y credenciales.
- Recuperación de contraseñas y validación de correos.

Cualquier cambio debe considerarse sensible y ser extremadamente conservador.

## Propósito
El módulo **Auth** es el responsable de gestionar la autenticación, la creación y destrucción de sesiones, y el control de acceso inicial. Su función es validar las credenciales de manera segura, regenerar identificadores de sesión para prevenir Session Fixation y poblar el contexto operativo (`$_SESSION`) para que toda la aplicación sepa quién es el usuario y a qué tenant (`empresa_id`) pertenece. Además, gestiona los flujos de "Forgot Password" y "Verificación de email".

## Alcance
- **Sí hace**: Login/Logout, validación de contraseñas hasheadas (`password_verify`), inyección de contexto de tenant (`empresa_id`, roles), recuperación de contraseña y reset seguro, validación de cuenta por correo electrónico.
- **No hace**: No maneja permisos granulares más allá de los roles base (`es_admin`, `es_rxn_admin`). No maneja la lógica de ABM de Usuarios (que corresponde a `backoffice_usuarios`). No almacena contraseñas en texto plano bajo ninguna circunstancia.

## Piezas principales
- **Controladores**: 
  - `AuthController.php` (login y logout)
  - `PasswordResetController.php` (flujo de olvido y reseteo)
  - `VerificationController.php` (verificación de cuentas nuevas)
- **Servicio**: `AuthService.php` (lógica de autenticación, session fixation y helpers estáticos de roles y require).
- **Repositorio y Modelo**: `UsuarioRepository.php` y `Usuario.php` (búsqueda de usuarios para validar).
- **Vistas**: 
  - `views/login.php`
  - `views/forgot.php`, `views/reset.php`, `views/resend.php`
- **Persistencia involucrada**: 
  - Tabla `usuarios` (credenciales backoffice, password_hash, tokens de reseteo y verificación).
  - Tabla `clientes_web` (credenciales B2C/Store, tokens de reseteo y verificación). `PasswordResetController` y `VerificationController` operan sobre **ambas** tablas simultáneamente.

## Dependencias directas
- **Context / Database / View**: Core del framework.
- **MailService**: Para enviar los enlaces de verificación y recuperación de contraseña.
- **PHP Session**: Base de la autenticación de estado.

## Integraciones involucradas
- **Correo transaccional / SMTP**: El módulo dispara enlaces de verificación y recuperación a través de `App\Core\Services\MailService`, por lo que depende indirectamente de la configuración de correo por tenant/sistema.
- **RxnGeoTracking**: Tras un `AuthService::attempt()` exitoso, después del `session_regenerate_id(true)` y la inyección de contexto en `$_SESSION`, se invoca `GeoTrackingService::registrar('login')` para asentar el evento con IP del cliente. La invocación es **fire-and-forget**: nunca debe lanzar excepción ni bloquear el login. Si el módulo `RxnGeoTracking` no está disponible (feature-flag apagado, servicio caído), el login continúa normalmente. El `evento_id` retornado queda en `$_SESSION['rxn_geo_pending_event_id']` para que el próximo render de `admin_layout` lo inyecte como `<meta name="rxn-pending-geo-event">` y el JS del browser reporte la posición GPS/WiFi precisa. Ver `app/modules/RxnGeoTracking/MODULE_CONTEXT.md` para detalles del contrato del servicio.
- **Banner de consentimiento (RxnGeoTracking)**: El `admin_layout.php` incluye un partial del módulo `RxnGeoTracking` que consulta `GeoTrackingService::tieneConsentimientoVigente($_SESSION['user_id'])`. Si el usuario no respondió la `consent_version` vigente, el banner aparece en el primer render post-login. La respuesta se graba en la tabla `rxn_geo_consent` y no vuelve a aparecer hasta que suba la versión del consentimiento. Esto es parte del cumplimiento de la Ley 25.326 y NO es opcional: Auth **no** debe pre-aceptar ni asumir consentimiento; cada usuario lo decide explícitamente.

## Reglas operativas del módulo y Seguridad
- **Aislamiento Multiempresa**: Una vez logueado, se inyecta de forma inmutable `$_SESSION['empresa_id']` del usuario autenticado. Esto es la piedra fundamental para el filtrado multitenant en el resto de la aplicación. Además se inyectan: `user_id`, `user_name`, `es_admin`, `es_rxn_admin`, `anura_interno`, `pref_theme`, `pref_font`, `dashboard_order`, `backoffice_created_at`, `backoffice_last_activity`.
- **Admin Sistema vs Tenant**: Se diferencia claramente a un "Admin Tenant" (`es_admin = 1`, domina sólo dentro de su empresa) de un "Admin RXN/Sistema" (`es_rxn_admin = 1`, domina la licencia y puede auditar el sistema).
- **Validación Server-Side**: Todas las credenciales y tokens se validan del lado del servidor. No se confía nunca en el estado del cliente.
- **Mutaciones por Método**: Login y reset de contraseña mutan estado por `POST`, pero la activación de cuenta hoy se consuma por `GET` tokenizado en `VerificationController::verify()`. Esa excepción existe por el flujo de correo y debe tratarse como superficie sensible: token aleatorio, expiración y anulación inmediata tras uso.
- **Regeneración de Sesión**: Todo login exitoso llama a `session_regenerate_id(true)` para evitar robos de sesión previos a la autenticación.
- **Verificación Requerida**: Todo usuario nuevo debe tener su correo verificado antes de permitirle autenticarse (soft block).

## No romper
- **Session Fixation Prevention**: No eliminar la instrucción `session_regenerate_id(true)` de `AuthService::attempt`.
- **Rutas de Reset / Forgot**: Mantener seguros y efímeros los tokens enviados por correo para recuperar contraseña.
- **Verificación estricta por Email**: Las búsquedas de usuario para login ignoran los borrados lógicos (`deleted_at IS NULL`) y verifican explícitamente el flag `activo = 1`.
- **Orden de operaciones en login**: La llamada a `GeoTrackingService::registrar('login')` debe ocurrir **después** de `session_regenerate_id(true)` y de la inyección de `$_SESSION`, nunca antes. De lo contrario el evento se asocia a una sesión espuria o sin `user_id`. El tracking es observacional, no debe modificar el flujo de autenticación.

## Riesgos conocidos
- *Ataques de Fuerza Bruta*: Actualmente no existe un control automático contra intentos masivos de login (throttle o lockouts temporales).
- *Filtración de existencia de cuentas*: Los mensajes de error genéricos ("Credenciales inválidas o usuario inactivo") son correctos para evitar filtraciones. Las rutas de reset y verificación ahora usan respuestas ciegas ("Si el email está registrado, recibirás un enlace") para ambas tablas (`usuarios` y `clientes_web`), lo cual mitiga el riesgo de enumeración.
- *Activación por GET Tokenizado*: La verificación de cuenta consume un enlace `GET` que muta estado (`email_verificado = 1`). Aunque el token es aleatorio y expira, cualquier relajación futura de ese flujo podría abrir una superficie delicada frente a reenvíos, prefetch agresivo o consumo accidental del enlace.

## Checklist post-cambio
- [ ] Validar inicio de sesión exitoso con una cuenta regular y una cuenta Admin.
- [ ] Verificar que la variable `$_SESSION['empresa_id']` se establezca correctamente al loguear.
- [ ] Comprobar que el acceso a rutas protegidas deniega la entrada cuando no se está logueado.
- [ ] Probar el cierre de sesión (`logout`) y asegurarse de que la cookie de sesión queda inoperativa.
- [ ] Ejecutar el flujo de recuperación de contraseña de punta a punta.
- [ ] Un login exitoso genera una fila en `rxn_geo_eventos` con `event_type='login'`, IP correcta, y `consent_version` vigente. Si el servicio de geo está apagado o falla, el login sigue funcionando normalmente.

## Tipo de cambios permitidos
- Ajustes menores en las vistas (`login.php`, etc).
- Agregar nuevos helpers de validación estáticos.

## Tipo de cambios sensibles
- Modificar el flujo de validación de contraseñas.
- Modificar lo que se inyecta en `$_SESSION` al autenticarse.
- Cambiar la lógica de los tokens de reset y su expiración.
