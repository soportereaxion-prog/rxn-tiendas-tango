# RXN_LIVE V1 - Fix y Adaptación a Datasets Reales (MariaDB)
Fecha: 2026-04-03 23:44

## Qué se hizo
Se auditó la base arquitectónica previamente implementada del módulo `RXN_LIVE` y se reestructuraron las entidades de pruebas hacia Datasets 100% reales, funcionales y en línea con el modelo de datos productivo MariaDB/MySQL de RXN Suite.

### Entidades y Vistas consolidadas
Se editó completamente el archivo `database/rxn_live_views.sql`:

1. **RXN_LIVE_VW_CLIENTES**:
   - Alimentada directamente desde la tabla `crm_clientes`.
   - Incorpora parseo de `activo` a labels humanos (`Activo`/`Inactivo`).
   - Recupera de forma segura los documentos y contactos, validando omisión de eliminados (`deleted_at IS NULL`).

2. **RXN_LIVE_VW_VENTAS**:
   - Alimentada a través de un `LEFT JOIN` entre `pedidos_web` (`p`) y `clientes_web` (`c`).
   - Recupera Totales limpios y Estados directos desde Tango (`p.estado_tango`).
   - Estandariza la visualización de Nombres de Clientes mediante `Trim(Concat(Nombre, Apelido))` asegurando un front limpio.
   - Aplica filtros de eliminación lógica (`p.activo = 1`).

## Por qué
Para dejar un módulo MVP que no sea una simple maqueta ("Semilla"), sino que aporte valor operativo real y de uso inmediato post-ejecución del script en el servidor, garantizando compatibilidad binaria absoluta con MySQL y evitando cualquier colisión ligada a viejas nociones de SQL Server.

## Impacto
Puesto que se trabaja aislando la información mediante vistas sobre las tablas reales (sin mutaciones), no se penaliza en absoluto la integridad ni la performance transaccional de los módulos CRM o Tiendas. Los endpoints `GET` exportables funcionarán sobre la Data real instantáneamente.
