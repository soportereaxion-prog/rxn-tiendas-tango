# Implementación del Módulo CRM Monitoreo de Usuarios

**Fecha:** 2026-04-03 23:35
**Autor:** Antigravity (Lumi)

## Qué se hizo
- Se creó el controlador `CrmMonitoreoUsuariosController` en el namespace `App\Modules\CrmMonitoreoUsuarios`.
- Se creó la vista index en `app/modules/CrmMonitoreoUsuarios/views/index.php`.
- Se implementó un diseño de "Grilla de Usuarios" con tarjetas (Cards), avatares dinámicos basados en iniciales, buscador de clientes vía JS (`rxn-shortcuts.js`) y un indicador de estado.
- Se agregó el acceso a `Monitoreo de Usuarios` en el listado `$defaultCards` de `app/modules/Dashboard/views/crm_dashboard.php` usando el ícono `bi-activity`.
- Se incluyó la ruta `/mi-empresa/crm/monitoreo-usuarios` en `app/config/routes.php`.

## Por qué
- Para proveer al equipo administrativo y coordinadores del módulo CRM un pantallazo veloz del estado, identidad corporativa (Interno Anura, Perfil Tango) y nivel de actividad básica de los usuarios registrados del tenant.

## Impacto
- Nuevo módulo disponible exclusivamente para usuarios con acceso al CRM (`requireCrm`).
- No altera tablas de base de datos en esta primera etapa, consume la info ya existente vía `UsuarioService` y `UsuarioRepository`.
- Se mantiene el estándar visual usando renderizado de servidor con Bootstrap 5 y Javascript vanilla para búsquedas instantáneas en la vista.

## Decisiones tomadas
- Se evito crear una migración de persistencia (`last_activity_at`) provisoriamente, posponiendo este scope para una futura iteración dedicada.
- El filtrado de la empresa respeta el scope actual (`empresa_id`); en el caso que lo visualice el RXN Global Admin, se muestran todos los del sistema para facilitar auditorías, lo cual ya estaba resuelto en arquitectura anterior.
- En lugar de AJAX/Polling background en tiempo real, se diseñó con un botón manual de `Actualizar` para mayor simplicidad y menor overhead, a menos que en un futuro se refactorice con Websockets o SSE.

## Medidas de Seguridad
- Aislamiento tenant (Multiempresa) soportado. Si no se es `es_rxn_admin`, se filtran los usuarios devolviendo unicamente los correspondientes al `empresa_id` en curso.
- Route protegida vía Guard `requireCrm`, evaluado a la entrada del framework.
