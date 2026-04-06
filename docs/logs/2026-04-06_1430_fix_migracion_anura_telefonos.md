# Creación de Tabla Intermedia de Teléfonos Anura para el CRM

## Qué se hizo
1. Se detectó en los logs de producción un error 500 originado en el webhook de Anura dictando `Base table or view not found: 1146 Table 'crm_telefonos_clientes' doesn't exist`.
2. Se verificó el esquema de la migración existente (`2026_04_04_create_crm_llamadas.php`) y no contemplaba la vinculación entre llamadas y los IDs cruzados a Tango CRM (tabla `crm_telefonos_clientes` ni la columna `cliente_id` insertada desde webhooks).
3. Se creó una nueva migración correctiva: `2026_04_06_1430_create_crm_telefonos_y_clientes_fk.php` que crea dicha tabla y modifica la tabla de `crm_llamadas`.

## Por qué
Para permitir vincular las llamadas entrantes (a partir del Phone/Caller ID de Anura) directamente con el archivo de clientes, pudiendo recuperar el tracking histórico y armar los Pedidos de Servicio vinculados unívocamente.

## Impacto
Se estabiliza el Webhook de Anura para que los POST de finalización de llamadas o los test locales puedan persistirse exitosamente evitando fatales por base de datos y quedando lista para subirse en un nuevo paquete OTA que garantice las relaciones en Plesk.
