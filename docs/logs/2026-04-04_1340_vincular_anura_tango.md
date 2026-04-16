# Registro de Modificación: Integración Híbrida Anura Webhook y Tango

**Concepto y Problema Resuelto:**
Hasta la fecha, al ingresar una llamada por Anura, estas caían huérfanas en el reporte. Cuando el usuario necesitaba realizar un Pedido de Servicio, debía seleccionar manualmente su cliente de Tango desde la interfaz desde cero. 
Con esta corrección arquitectónica se posibilitó crear un **Directorio Automático Inteligente**. Cada vez que un usuario asocia un número telefónico nuevo con un cliente de Tango, el sistema hace 2 cosas:
1. Marca esa llamada histórica con ese Cliente ID.
2. Anida el par (Número, Cliente) en la nueva tabla `crm_telefonos_clientes`.

La próxima vez que un webhook reciba un ring de ese mismo número, se unirá automáticamente a ese cliente, por lo tanto "Llamadas de Central" empezará a mostrar nombres en lugar de números. Al generar el PDS, este viaja con la URL y el formulario recibe al cliente. 

**Cambios Físicos:**
- **BD Migración:** Creada en `deploy_db/database_migrations_llamadas_tango.php`. Agrega `crm_llamadas.cliente_id` y crea la tabla `crm_telefonos_clientes`.
- **`CrmLlamada.php` y `CrmLlamadaRepository.php`**: Mapeo y Upsert de base de datos (`vincularClienteLlamada()`).
- **`WebhookController.php`**: Inyección pre-recepción para escanear `crm_telefonos_clientes`.
- **`CrmLlamadasController.php`**: Nuevo micro-endpoint JSON para el Upsert de la UI (`vincularClienteApi`).
- **`CrmLlamadas/views/index.php`**: Formulario Modal, badge decorativo y AJAX Autocomplete portado desde ERP a Llamadas.
- **`CrmPedidosServicio/PedidoServicioController.php`**: Ajuste del initial state para capturar `$_GET['cliente_id']`.

**Implementación Segura:**
- Respetado el aislamiento al solicitar `empresa_id` con query parameter bindings.
- **Update Posterior (14:00hs)**: Se implementó **Matufia/Retroactividad** al momento de buscar vínculos. Al vincular o desvincular un número de un Cliente, el sistema aplica la lógica no sólo a `crm_telefonos_clientes` sino a *todo el historial anterior* para ese `numero_origen`. Se agregó un botón de anclaje rápido en la matriz para separar la relación si hay errores y limpiar el historial retroactivamente.
