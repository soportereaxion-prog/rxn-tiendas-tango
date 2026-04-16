# 2026-04-08 18:15 — MODULE_CONTEXT Articulos (CrmArticulos)

## Qué se hizo

Se auditó el módulo `app/modules/Articulos` y se generó `MODULE_CONTEXT.md`.

El módulo no existe con el nombre `CrmArticulos`; ese es el nombre operativo del catálogo en el entorno CRM. El código físico vive en `app/modules/Articulos` y sirve **ambos entornos** (Tiendas y CRM) mediante resolución de área por URI.

## Por qué

Documentación técnica de referencia para evitar regresos accidentales al tocar el módulo o sus consumidores. Especialmente necesaria dado que el módulo es consumido en SQL directo por `PresupuestoRepository` y `PedidoServicioRepository`, lo que hace que cambios de esquema sean difíciles de detectar.

## Impacto

- Sin cambios en código.
- Se crea `app/modules/Articulos/MODULE_CONTEXT.md`.
- Este log documenta la auditoría.

## Decisiones tomadas

- El archivo se ubica en `app/modules/Articulos/MODULE_CONTEXT.md` (no en un directorio `CrmArticulos` inexistente).
- Se eligió no crear un directorio `CrmArticulos` separado; el módulo es uno solo con comportamiento dual.
- Se documentó el patrón de bootstrap on-the-fly como riesgo conocido (DDL por request).
- Se marcó la falta de CSRF y la falta de validación de tamaño de imagen como deuda de seguridad activa.

## Hallazgos destacados

1. `ensureSchema()` y `ensureSoftDeleteSchema()` ejecutan DDL en cada request que instancie `ArticuloRepository::forCrm()`.
2. `PresupuestoRepository` y `PedidoServicioRepository` consultan `crm_articulos` con SQL directo (no via repositorio), lo que hace que los cambios de esquema sean invisibles para el compilador.
3. El método `copy()` está registrado en rutas y tiene botón en UI, pero siempre retorna error. No crítico, pero confuso.
4. Las categorías CRM se crean en bootstrap (`crm_articulo_categoria_map`) pero no se usan operativamente.
5. El endpoint `/sugerencias` es contrato vivo consumido por PDS y Presupuestos.
