# MODULE_CONTEXT — Clientes

---

## Nivel de criticidad

**BAJO (stub)**

Este módulo existe como placeholder estructural. No contiene lógica operativa funcional. La gestión real de clientes del backoffice está implementada en el módulo `ClientesWeb`.

---

## Propósito

Placeholder para un futuro módulo de gestión de clientes internos del sistema. Según el `AGENTS.md` local, su rol previsto es: alta, baja, modificación de clientes con listados, filtros, búsqueda y validación de datos.

---

## Alcance

### Qué hace
- Nada operativo. Los archivos de controlador, modelo y vista están vacíos (0 líneas de código).

### Qué NO hace
- No gestiona clientes. Toda la gestión de clientes (web y CRM) está actualmente en `ClientesWeb`.
- No tiene rutas funcionales.
- No tiene persistencia propia.

---

## Piezas principales

### Controlador
- `ClientesController.php` — 3 líneas — Solo declaración de namespace, sin clase ni métodos.

### Modelo
- `ClientesModel.php` — 3 líneas — Vacío, sin clase.

### Vistas
- `views/index.php` — 0 líneas — Archivo vacío.

### Documentación local
- `AGENTS.md` — Define intención futura: CRUD de clientes, restricción por `empresa_id`, base para pedidos y facturación.

---

## Rutas / Pantallas

No hay rutas funcionales registradas para este módulo.

---

## Tablas / Persistencia

No hay tablas asociadas directamente. El `AGENTS.md` local sugiere que las queries deberían filtrar por `empresa_id` e indexar por documento/nombre, pero ninguna tabla está definida ni consumida.

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

1. **Es un stub**: no debe confundirse con el módulo activo `ClientesWeb`.
2. Si se implementa en el futuro, debe seguir las directrices del `AGENTS.md` local (filtro por `empresa_id`, soft delete, sanitización de inputs).

---

## Tipo de cambios permitidos (bajo riesgo)

- Cualquier implementación desde cero, ya que no hay código que romper.

---

## Tipo de cambios sensibles (requieren análisis previo)

- **Implementar lógica que duplique funcionalidad de `ClientesWeb`**: antes de activar este módulo, debe definirse claramente la frontera entre "clientes internos" y "clientes web/B2C".

---

## No romper

Nada que romper actualmente. Si se activa, considerar:
1. No duplicar la tabla `clientes_web` sin una estrategia clara de separación.
2. No crear rutas que colisionen con `/mi-empresa/clientes` (actualmente ruteadas a `ClientesWeb`).

---

## Riesgos conocidos

1. **Confusión de nomenclatura**: el directorio `Clientes` existe pero no opera. La gestión real está en `ClientesWeb`. Puede confundir a desarrolladores nuevos.
2. **Rutas potencialmente solapadas**: si se activa, las rutas `/mi-empresa/clientes` ya podrían estar mapeadas al módulo `ClientesWeb`.

---

## Checklist post-cambio

- [ ] Si se implementa: verificar que no colisiona con `ClientesWeb` en rutas ni tablas
- [ ] Si se implementa: respetar aislamiento multiempresa con `empresa_id`

---

## Regla de mantenimiento

Este archivo debe actualizarse si:
- Se implementa funcionalidad real en este módulo
- Se decide fusionar o eliminar en favor de `ClientesWeb`
