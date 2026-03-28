# [Empresas/Auth/UX] - Flags modulares, buscadores y acceso a backoffice

## Que se hizo
- En `Editar Empresa` se dejaron `Razón Social` y `CUIT` en solo lectura, preservando sus valores para una futura reactivacion funcional.
- Se agregaron los flags `Tiendas` y `CRM` en empresas, dependientes del estado `Empresa activa`.
- Se corrigio el autosuggest para que `Enter` permita filtrar con texto parcial sin obligar a seleccionar una sugerencia.
- Se ajusto el acceso al backoffice para que usuarios con privilegios de administrador tambien puedan entrar al circuito administrativo.

## Por que
- Hacia falta preparar la entidad empresa para modulos futuros sin romper el flujo actual.
- El comportamiento del buscador estaba forzando una seleccion que cortaba la operacion natural.
- El rol administrador no estaba reflejando correctamente el acceso esperado al backoffice.

## Impacto
- Las empresas ya pueden dejar preparados flags de disponibilidad para `Tiendas` y `CRM`.
- Las busquedas quedan mas naturales en todos los modulos que usan el componente comun.
- Un usuario administrador ahora puede abrir el backoffice y sus pantallas relacionadas.

## Decisiones tomadas
- Se agregaron columnas `modulo_tiendas` y `modulo_crm` en la tabla `empresas` con default `0`.
- Se centralizo el permiso en `AuthService::requireBackofficeAdmin()` para reutilizarlo en dashboard, empresas y SMTP global.
