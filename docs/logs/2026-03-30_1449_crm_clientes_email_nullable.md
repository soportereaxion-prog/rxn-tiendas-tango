# Modificación de Columna Email en CRM Clientes y Ajuste Visual de Formulario

**Fecha:** 2026-03-30
**Componentes afectados:** `CrmClienteRepository.php`, `CrmClientes/views/form.php`

## Qué se hizo
1. Se forzó la columna `email` de la tabla `crm_clientes` para que permita valores NULOS (`NULL`), mediante una alteración silenciosa en la inicialización conectiva del esquema (`ensureSchema`).
2. Se reestructuró y unificó la estética del formulario local de actualización del CRM (`CrmClientes/views/form.php`) para igualar las clases visuales y estructura (tipo "sábana") que venía usando el módulo de `Usuarios`.

## Por qué
- La sincronización principal que baja los datos desde Tango Connect al caché local (`CrmClienteSyncService`) interceptaba registros de clientes carentes de correo electrónico, mapeándolos a `null`.
- Al inyectar el lote en MySQL, el motor bloqueaba la operación arrojando `SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'email' cannot be null`, lo cual reventabla el flujo e impedía consolidar la caché local.
- Adicionalmente el cliente requería emparejar las tarjetas de edición para mantener homogeneidad en el sistema, adaptándolo al marco CSS limpio provisto en `rxn-theming.css`.

## Impacto
- **Operativo**: La sincronización de clientes con Tango Connect volverá a funcionar perfectamente mitigando bloqueos transaccionales por campos de contacto no esenciales.
- **Visual**: Interfaz más limpia en la edición con cabeceras `border-0 shadow-sm`, despegada del panel base.
- **Técnico**: No demanda intervenciones manuales en la consola de la BD, dado que el repositorio garantiza que esa columna sea flexible desde códgigo al inicializarse y cada vez que el usuario entre al CRM.

## Decisiones tomadas
- Se prefirió un `ALTER TABLE` dinámico dentro de un bloque `try/catch` alojado en `ensureSchema()`, en lugar de proveer una migración en formato SQL duro. Esto asegura que la mitigación del error no dependa del conocimiento de herramientas como PhpMyAdmin o acceso al servidor remoto por parte del administrador (auto-reparación).
- Se reutilizaron al pie de la letra los envoltorios CSS "sábana" provistos en `Usuarios`, re-aplicando el concepto de "RXN Form Shell" para una experiencia ininterrumpida de bajada de listados.
