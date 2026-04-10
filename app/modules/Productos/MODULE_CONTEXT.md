# MODULE_CONTEXT — Productos

---

## Nivel de criticidad

**BAJO (stub)**

Este módulo existe como placeholder estructural. No contiene lógica operativa funcional. La gestión real de artículos/productos está implementada en el módulo `Articulos` (que opera en contextos Tiendas y CRM).

---

## Propósito

Placeholder para un futuro módulo de gestión de productos. Según el `AGENTS.md` local, su rol previsto es: CRUD de productos, manejo de precios, stock básico y preparación para integración con ERP.

---

## Alcance

### Qué hace
- Nada operativo. Los archivos de controlador y modelo están vacíos (0 líneas de código).

### Qué NO hace
- No gestiona productos. Toda la gestión de artículos/productos está en el módulo `Articulos`.
- No tiene rutas funcionales.
- No tiene persistencia propia.

---

## Piezas principales

### Controlador
- `ProductosController.php` — 0 líneas — Archivo vacío.

### Modelo
- `ProductosModel.php` — 0 líneas — Archivo vacío.

### Vistas
- `views/index.php` — Archivo presente (contenido no verificado; posiblemente vacío o placeholder).

### Documentación local
- `AGENTS.md` — Define intención futura: CRUD de productos, precios, stock, integración con ERP, soporte para múltiples listas de precios.

---

## Rutas / Pantallas

No hay rutas funcionales registradas para este módulo.

---

## Tablas / Persistencia

No hay tablas asociadas directamente. La gestión de artículos usa las tablas `articulos` y `crm_articulos` desde el módulo `Articulos`.

---

## Dependencias directas

Ninguna.

---

## Dependencias indirectas / Impacto lateral

Ninguna. Este módulo no es consumido ni consume otros módulos.

---

## Integraciones involucradas

Ninguna.

---

## Seguridad

### Aislamiento multiempresa
- **No aplica**: no hay código operativo.

### Permisos / Guards
- **No aplica**: no hay código operativo.

### Admin sistema (RXN) vs Admin tenant
- **No aplica**.

### No mutación por GET
- **No aplica**.

### Validación server-side
- **No aplica**.

### Escape / XSS
- **No aplica**.

### Impacto sobre acceso local
- Sin impacto.

### CSRF
- **No aplica**.

---

## Reglas operativas del módulo

1. **Es un stub**: no debe confundirse con el módulo activo `Articulos`.
2. Si se implementa en el futuro, debe seguir las directrices del `AGENTS.md` local y evitar duplicar funcionalidad de `Articulos`.

---

## Tipo de cambios permitidos (bajo riesgo)

- Cualquier implementación desde cero, ya que no hay código que romper.

---

## Tipo de cambios sensibles (requieren análisis previo)

- **Implementar lógica que duplique funcionalidad de `Articulos`**: antes de activar este módulo, debe definirse claramente la frontera entre "productos" genéricos y "artículos" (vinculados a Tango, sincronización, catálogo público, etc.).

---

## No romper

Nada que romper actualmente. Si se activa, considerar:
1. No duplicar las tablas `articulos` / `crm_articulos`.
2. No crear rutas que colisionen con `/mi-empresa/articulos` o `/mi-empresa/crm/articulos`.

---

## Riesgos conocidos

1. **Confusión de nomenclatura**: el directorio `Productos` existe pero no opera. La gestión real está en `Articulos`. Puede confundir a desarrolladores nuevos.
2. **Coexistencia con `Articulos`**: si se activa sin una estrategia clara, puede haber solapamiento de responsabilidades.

---

## Checklist post-cambio

- [ ] Si se implementa: verificar que no colisiona con `Articulos` en rutas ni tablas
- [ ] Si se implementa: respetar aislamiento multiempresa con `empresa_id`

---

## Regla de mantenimiento

Este archivo debe actualizarse si:
- Se implementa funcionalidad real en este módulo
- Se decide fusionar o eliminar en favor de `Articulos`
