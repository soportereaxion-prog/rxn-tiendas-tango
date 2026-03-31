# Corrección de Sincronización de Clientes en CRM

## Qué se hizo
Se modificó el método `ensureSchema` en `CrmClienteRepository` para eliminar el índice único `uq_emp_email_crm_clientes` de la tabla `crm_clientes`.
Se agregó el método privado `dropIndexIfExists` para que se encargue de eliminar dicho índice en caso de que esté presente en la base de datos de las instalaciones existentes.

## Por qué
Durante la sincronización de clientes con el sistema ERP (Tango), ocurría un error crítico `1062 Duplicate entry` cuando varios clientes de Tango, bajo la misma empresa, compartían un mismo correo electrónico (situación común en el escenario B2B donde distintas sucursales o franquicias comparten la administración o contacto).
El diseño inicial imponía una restricción de unicidad para empresa+email (`uq_emp_email_crm_clientes`) que bloqueaba el insert y frenaba la sincronización total para los demás clientes en la cola.

## Impacto
- La sincronización de clientes de Tango ya no aborta por correos duplicados entre distintos clientes bajo una misma empresa.
- Mejor tolerancia a fallas de integración en escenarios de datos imperfectos provenientes del ERP.

## Decisiones tomadas
- Remover la restricción de email único en la tabla `crm_clientes`.
- Asegurar de forma automatizada (mediante `ensureSchema`) que cualquier instalación antigua se repare a sí misma quitando el índice en el primer inicio o intento de acceso a la DB.
