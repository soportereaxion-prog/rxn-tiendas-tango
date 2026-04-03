# Oficialización de Política de Cambios de Base de Datos (Migraciones)

**Fecha:** 2026-04-03 17:23
**Propósito:** Dejar documentado y blindado documentalmente el flujo de vida del sistema DB luego de estabilizar el Módulo de Mantenimiento. Esta regla descarta abordajes empíricos, improvisaciones o uso de "dumping" directo, pasando al formato corporativo y versionado a través de Migraciones atómicas y ejecutables. 

### Decisiones Aprobadas
1. **Flujo Cauteloso:** Queda formalizada como norma operativa suprema que de ahora en adelante todo requerimiento que involucre persistencia (DDL, Data Base Fixing, New Seeds), será resuelto creando una contra-prestación de migración en PHP (ej: `database_migrations_xxx.php`).
2. **Inmutabilidad Documentada:** La regla asimila explícitamente el enfoque "Rollback Forward". Ningún archivo de migración procesado será mutado, todo parche generará un file compensatorio.
3. **Calidad DB / Resguardo Anti-Colapso:** Ante la posibilidad fehaciente de cruzar configuraciones multi-entorno, toda migración (especialmente los DDL de Schema y DMLs crudos) **deben llevar sentencias Idempotentes** (IF NOT EXISTS, ignorar duplicates, chequeos de existencia de campo), reduciendo el riesgo fatal del "Tabla ya existe" parando deploys productivos. 

### Archivos Afectados
Se eligió no diseminar normas arbitrariamente en muchos archivos redundantes. En su lugar se inyectó un "Cuerpo Legislativo de Persistencia" universal en los archivos contextuales del proyecto consumibles por cualquier Agente o Humano:
- `AGENTS.md` (Integración de Regla Obligatoria para LLMs).
- `PROJECT_CONTEXT.md` (Se le inyectó la sección _Política Formal de Persistencia_, listando Tipologías, Criterios de Idempotencia y Workflow de Deploy).
- `docs/logs/2026-04-03_1730_politica_oficial_migraciones.md` (Este documento).

Al fijarse aquí la directriz, el proyecto RXN Suite avanza desde un MVP multi-empresa "envolvente" hacia un software robusto multi-tier, listo para actualizaciones seguras.
