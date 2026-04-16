# Control de Cambios: Fix AdvancedQueryFilter y Errores de SQL

**Fecha:** 2026-04-05
**Módulo:** Core, CRM Llamadas, CRM Notas

## 📌 Qué se hizo
Se corrigió un error crítico introducido en la implementación de filtros avanzados donde se producía un `Array to string conversion` y excepciones PDO de tipo `Invalid parameter number`.

## ❓ Por qué
- En `CrmNotaRepository` y `CrmLlamadaRepository`, no se estaba extrayendo (_destructuring_) el array devuelto por `AdvancedQueryFilter::build()`, lo que provocaba que se concatenara un `Array` como parte del string SQL en crudo.
- El objeto `AdvancedQueryFilter` generaba variables _bind_ posicionales (`?`) las cuales, al ser combinadas por `array_merge` con arrays de parámetros asociativos/nombrados (`:empresa_id` o `:search`), corrompían las declaraciones de PDO generando excepciones en PHP 8.

## ✅ Impacto
- **Core:** Se refactorizó `AdvancedQueryFilter::build()` para utilizar siempre parámetros nombrados de forma incremental (`:adv_0`, `:adv_1`, etc.). Además, se saneó para que devuelva la sentencia limpia (sin el `AND` inicial fijado), delegando al repositorio su correcta inyección.
- **Repositorios:** Se ajustó la asignación `[$advFilterSql, $advParams]` en los módulos de Notas y Llamadas.
- **Estabilidad Global:** Este fix de `AdvancedQueryFilter` previene instantáneamente cualquier error de binding mixto en todos los otros módulos (Empresas, Usuarios, Artículos, etc) donde el filtro ya estaba activo.
