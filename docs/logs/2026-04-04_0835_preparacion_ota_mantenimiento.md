# Preparación de OTA: Migraciones RXN_LIVE y consolidación

**Fecha:** 2026-04-04 08:35  
**Autor:** Agente IA (Lumi)

## Objetivo
Atendiendo la instrucción de armar el OTA del día para desplegar los cambios masivos de ayer en el módulo `RXN_LIVE` y correcciones del PDS, se consolidaron las modificaciones en scripts de base de datos para asegurar el despliegue automático por el nuevo **Módulo de Mantenimiento / OTA**.

## ¿Qué se hizo?
1. **Auditoría de artefactos SQL**: Durante el desarrollo de `RXN_LIVE`, se armó un script pelado de SQL (`database/rxn_live_views.sql`) para crear las vistas `RXN_LIVE_VW_CLIENTES`, `RXN_LIVE_VW_VENTAS` y `RXN_LIVE_VW_PEDIDOS_SERVICIO`. Las vistas puras en SQL no se corren automáticamente en el flujo de migraciones, por tanto, en caso de deploy, iban a fallar los gráficos correspondientes en el remoto.
2. **Conversión a Migración**: Se creó la migración nativa PHP `database/migrations/2026_04_04_create_rxn_live_views.php` con las llamadas a `PDO->exec()` que crean (o reemplazan) dichas vistas, garantizando idempotencia (`CREATE OR REPLACE VIEW`).
3. **Ejecución del empaquetador**: Se generó el paquete `/build` y `/deploy_db` mediante `tools/deploy_prep.php`, capturando todas las modificaciones recientes (los controllers de RXN_LIVE, Webhooks, Fix del TangoService de PDS, Modales, Canvas editor y demás).

## Impacto
El sistema en remote, al subir el OTA (que se consolida en `/build` y el consecuente paquete zip), podrá ejecutar desde `/admin/mantenimiento` las nuevas migraciones:
- `2026_04_04_create_rxn_live_vistas_table.php`
- `2026_04_04_create_rxn_live_views.php`

Las vistas en DB se crearán con permisos adecuados y el usuario podrá iniciar a explotar sus métricas de Negocios y de Taller (PDS) sin requerir comandos de base de datos manuales.

## Criterios adoptados
- Idempotencia: `CREATE OR REPLACE` impide que haya fallos de repetición si por algún motivo la migración se vuelve a correr luego de un desajuste.
- Alineación estricta con la política de seguridad y mantenimiento definida en las *Core Rules* (`AGENTS.md`) de no realizar DDL manual en producción.
