# 1.2.6 - RXN Live: Resolución Definitiva del Parpadeo Infinito y Botón Actualizar

**Responsable:** Lumi / Charly
**Fecha:** 2026-04-06 21:40
**Entorno:** RXN Suite (CRM / Tiendas)

## 1. Qué se hizo
- **Sincronización `saveVolatileState`:** Se modificó la arquitectura de persistencia volátil del módulo para inyectar los parámetros URL de manera síncrona en el `sessionStorage` justo antes del disparo de la navegación (`window.location.search = ...`).
- **Remoción del Desfasaje de Recarga:** Se eliminó la obligación estricta de "forzar redirección cuando la URL difiere del Volátil". Esto mitigó un "race condition" semántico donde la página, al cargar sin registrar los nuevos parámetros por lentitud sincrónica, veía diferencias entre su memoria y la URL actual y entraba en un bucle infinito de sobreescritura de History.
- **Validación Limpia en Intercambio de Vistas:** Ahora, al permutar entre dos "Vistas Guardadas" del Dropdown, la lógica reescribe limpiamente la URL con los parámetros registrados en el JSON de la base de datos y detona de manera elegante la consulta al backend.
- **Inclusión Botón "Actualizar":** Se sumó un componente estético de UI en el *topbar* bajo el título `Actualizar [Alt+A]` que invoca un simple `window.location.reload()`.

## 2. Por qué
Porque al utilizar filtros entre fechas (u otros del motor BD), la actualización en caché no llegaba a tiempo antes del `DOMContentLoaded`, propiciando que la recarga intentara devolver a la plataforma al estado inmediatamente anterior. En iteraciones sin `page` o en configuraciones "volátiles puras", terminaba ejecutando infinitos `window.location.href`.

## 3. Impacto Operativo
- Se acabó la maldición del parpadeo para los operadores de Backend (Tiendas/CRM).
- Las vistas guardadas aplican consistentemente filtros en duro antes del renderizado (vía URL).

## 4. Archivos modificados
- `app/modules/RxnLive/views/dataset.php`
