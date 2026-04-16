# Filtro Avanzado: Columna Grabación en CrmLlamadas

**Fecha:** 2026-04-09  
**Módulo:** CrmLlamadas  
**Tipo de cambio:** Feature — agregar embudo de filtro avanzado a columna Grabación  
**Agente ejecutor:** clau-direct (fallback por bloqueo de gemi-direct)

---

## Descripción del cambio

Se incorporó el filtro avanzado (icono de embudo `rxn-filter-col`) a la columna **Grabación** del listado de llamadas (`CrmLlamadas`).

### Criterio funcional para el campo filtrable

La columna Grabación no mapea a un campo único de base de datos. En la vista combina tres estados:
- **Con audio**: cuando `l.mp3` es no nulo y no vacío (la llamada tiene archivo de audio)
- **Evento (ej: HANGUP)**: cuando `l.evento_link` es no nulo y no vacío (sin audio pero con evento de telefonía)
- **Sin audio**: cuando ninguno de los anteriores aplica

Se creó una expresión CASE virtual (`grabacion_estado`) para el `filterMap`:
```sql
CASE 
  WHEN l.mp3 IS NOT NULL AND l.mp3 != '' THEN 'Con audio' 
  WHEN l.evento_link IS NOT NULL AND l.evento_link != '' THEN l.evento_link 
  ELSE 'Sin audio' 
END
```

Esto permite al usuario filtrar por texto usando los operadores estándar del sistema (`contiene`, `igual`, `no_contiene`, etc.).

### Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `app/modules/CrmLlamadas/views/index.php` | TH de Grabación → `class="rxn-filter-col" data-filter-field="grabacion_estado"` |
| `app/modules/CrmLlamadas/CrmLlamadaRepository.php` | Se agregó `grabacion_estado` al `$filterMap` en `findAllWithSearch()` y `countAll()` |

---

## Control de Seguridad (Política Base)

### 1. Aislamiento multiempresa (`Context::getEmpresaId()`)
✅ **Cumple.** Ambas queries (`findAllWithSearch`, `countAll`) ya filtran por `l.empresa_id = :empresa_id`. El filtro avanzado añadido opera dentro de ese scope existente. No se introduce ningún vector de bypass multiempresa.

### 2. Permisos strictos en backend
✅ **Cumple.** El filtro avanzado se procesa en el backend a través de `AdvancedQueryFilter::build()`, que usa un whitelist (`filterMap`). Solo los campos explícitamente listados son procesables. El nuevo campo `grabacion_estado` se suma al whitelist de forma controlada.

### 3. Separación RXN admin (sistema) vs admin tenant
✅ **No aplica.** Este cambio no altera permisos ni roles. Opera sobre la misma vista que ya es accesible para el tenant.

### 4. No mutación de estado por GET
✅ **Cumple.** Los filtros avanzados se transmiten vía `$_GET['f']` como parámetros de lectura. No hay mutación de datos. Solo se filtra el SELECT.

### 5. Validación fuerte server-side
✅ **Cumple.** `AdvancedQueryFilter::build()` valida que el campo esté en el `$columnMap` (whitelist). Los valores se bindean con PDO prepared statements. No hay interpolación directa de valores del usuario en el SQL.

### 6. Escape seguro en salida (XSS)
✅ **No aplica directamente.** El filtro opera en backend. Los valores filtrados ya se renderizan con `htmlspecialchars()` en la vista (líneas 175, 178 del index.php).

### 7. Impacto sobre acceso local del sistema
✅ **Sin impacto.** No se modifican rutas, controladores ni middleware. El cambio es puramente de filtrado de datos ya accesibles.

### 8. Necesidad de token CSRF
✅ **No necesario.** Los filtros avanzados operan sobre requests GET de solo lectura. No hay formularios POST involucrados en este cambio.

---

## Observaciones adicionales

- **Bug preexistente detectado (no corregido):** La columna `Duración` tiene `rxn-filter-col` y `data-filter-field="duracion"` en la vista, pero `duracion` **no está en el `filterMap`** del repositorio. El icono de embudo aparece pero el filtro no aplica. Esto queda fuera del alcance de este cambio.
- La expresión CASE usada es compatible con MySQL 5.7+ y MariaDB 10.x+.
- No se requiere migración de base de datos (el campo virtual se calcula al vuelo).
