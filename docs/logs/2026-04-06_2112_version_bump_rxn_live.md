# 1.2.5 - RXN Live: Compatibilidad Nativa de Fechas y Nueva Política de BBDD

**Responsable:** Lumi / Charly
**Fecha:** 2026-04-06 21:12
**Entorno:** RXN Suite (CRM / Tiendas)

## 1. Qué se hizo
- **Normalización Visual Nativa para Fechas:** Se corrigieron los filtros avanzados (`Entre`) dentro del módulo Analytics RXN Live, forzando inputs `<input type="date">` y `<input type="datetime-local">`.
- **Estandarización Persistente del Motor:** El navegador ahora es el único responsable de la visualización estética según el Locale de su Sistema Operativo de origen, pero siempre empuja un string ISO (`YYYY-MM-DD`) impoluto hacia la query de MySQL evitando fallos de compatibilidad en bases de datos con collations distintos.
- **Workflow Manual Estricto (Migraciones):** Se redactó, documentó y fijó como política inviolable dictada por la jefatura operativa (`AGENTS.md`) que de ahora en más, todo script de migración DDL/DML, aunque exista como resguardo para uso exclusivo y manual en modo producción, deberá en modo desarrollador inyectarse siempre de manera cruda y viva (instantánea) directamente contra la base de datos de desarollo, saltándose los procesos automatizados intermedios locales.

## 2. Por qué
Porque los inputs nativos previenen las inyecciones extrañas de fechas. Las quejas de fallos de visualización al querer forzar un formato local, derivaban del intento fallido de reescribir un componente nativo del OS y, la falta de "crashes" técnicos, confirmaban un modelo mental erróneo a la hora de abordar el formateo del input.
Por el lado DB, el Rey ha exigido la manualidad, inmediatez y autoridad arquitectónica irrestrictas.

## 3. Impacto Operativo
- Ninguno sobre Tiendas Front-End.
- CRM y Datasets ganan fidelidad para extracciones filtradas mediante calendario visual.
- El ciclo de desarrollo para persistencia salteará bucles de testeo intermedios que ensuciaban el stack.

## 4. Archivos modificados
- `app/config/version.php`
- `AGENTS.md`
- `app/modules/RxnLive/views/dataset.php` (registrado del anterior push)
