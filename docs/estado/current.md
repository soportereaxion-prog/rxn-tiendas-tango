# ESTADO ACTUAL

## módulos tocados

* módulo: auth (Nuevo: Fase 1 Contexto Multiempresa - Sesiones y Usuarios)
* módulo: core (Actualización radical de `App.php` y `Context.php`)
* módulo: dashboard (Inyectada bifurcación de UI basada en Sesiones)

## decisiones

* Se creó la tabla `usuarios` (relación a `empresas`) con la semilla default segura `admin@empresa.test`.
* Se inyectó globalmente el manejo de estados instalando `session_start()` al inicio de `App::run()`.
* **Fase 1 Auth Multiempresa**: Se modificó `Context.php` prohibiendo la simulación por `$_GET` a menos que se fuerce el flag de Dev. Ahora todo recae 100% en `$_SESSION['empresa_id']`, lo cual es inyectado por el nuevo servicio central `AuthService`.
* `EmpresaConfigController` fue bloqueado tras un Guard liviano (`AuthService::requireLogin()`), expulsando accesos directos al `/login`.

## riesgos

* Los usuarios se generan globalmente e insertan `empresa_id` estática. Esta asignación en una futura fase de panel requerirá listbox / selector de administrador RXN.
* La contraseña de semilla se definió de manera robusta (`RxnTest2026!`). Se insta a no deployar la DB en producción sin haber eliminado la semilla o refrescado la clave.

## próximo paso

* El sistema operativo está listo y seguro para empezar a albergar funcionalidades Multi-Empresa reales (como Gestión de Productos por Empresa o Configuración de Sucursales).
