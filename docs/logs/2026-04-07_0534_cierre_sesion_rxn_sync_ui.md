# Cierre de Sesión RXN Sync - UI y Base de Persistencia

**Fecha:** 2026-04-07
**Agente:** Lumi (Antigravity/Agent Teams)

## 1. Qué se hizo
- Se analizó la estructura real de Tango Connect para Clientes (`ID_GVA14`) y Artículos (`ID_STA11`) mediante la herramienta de auditoría de Payload.
- Se definió un modelo estricto de **Whitelist** para Clientes (Razón Social, CUIT, Domicilio, Localidad, CP, Teléfonos, Email) y Artículos (Descripción, Código de Barra, Observaciones) para evitar sobreescribir dependencias de Tango (como Unidades de Medida o direcciones anidadas).
- Se ejecutó la migración `2026_04_07_crear_rxn_sync_status.php` directo en base de desarrollo local para crear la tabla pivote de centralización `rxn_sync_status`.
- Se creó `RxnSyncController` y `RxnSyncService` para manejar la UI por pestañas y la lógica de "Match Suave".
- Se refactorizaron las vistas CRUD legacy (Clientes y Artículos) eliminando los multíples botones invasivos de sync, y centralizándolos en un único acceso "Auditoría RXN Sync".

## 2. Por qué
- La persistencia de Tango-Match (pivote) aísla el acoplamiento directo y nos permite manejar estados (`pendiente`, `conflicto`, `vinculado`, `error`).
- La UI centralizada permite ver en qué estado está cada entidad respecto de su "Shadow Copy" en el CRM, preparando el terreno para el proceso automático a futuro sin sobrecargar las vistas transaccionales.

## 3. Impacto Operativo
- Las Vistas CRUD de Clientes y Artículos ya no cuentan con "Sync Precios", "Sync Artículos", "Sync Total". Todo flujo cruzado o de revisión profunda de identidad pasa por el módulo `rxn-sync`.

## 4. Decisiones de Arquitectura Tomadas
- Endpoint único `/mi-empresa/crm/rxn-sync/push` mockeado temporalmente para preparar la Fase 1.3/2 (Escritura Real mediante el Whitelist propuesto).
- Persistencia asíncrona (Fetch tabs).

## 5. Medidas de Seguridad Base (AGENTS.md)
- Tablas pivote cruzan estrictamente por `empresa_id` con validación PDO en Backend (Crm y Tiendas).
- Endpoint RXN Sync blindado bajo la directiva Middleware `$requireCrm`.
- Migración versionada registrada siguiendo reglas de Multi-Agent Development.
