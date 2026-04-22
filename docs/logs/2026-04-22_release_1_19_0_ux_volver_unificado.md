# Release 1.19.0 — UX transversal: botón Volver unificado + retorno contextual PDS/Tratativas

**Fecha**: 2026-04-22
**Build**: 20260422.1
**Tipo**: iteración UX + fix de bug de versionado

## Qué se hizo

### 1. PDS contextual a Tratativa

- **Botón Volver** del form PDS (`CrmPedidosServicio/views/form.php`): si el PDS tiene `tratativa_id`, el Volver lleva al detalle de la tratativa; sino, al listado de PDS. Se calcula con `$pdsBackHref` y `$pdsBackTitle` antes de los `ob_start()` para que esté disponible tanto en el header como si hiciera falta en otro slot.
- **Botón Guardar**: se revirtió el cambio previo que mandaba a la tratativa al guardar. Ahora `PedidoServicioController::resolveReturnPath` siempre vuelve al `/editar` del PDS. Motivo: el usuario va componiendo el PDS y guarda varias veces mientras trabaja — no queremos sacarlo del form cada vez.
- **Alert-info**: el mensaje del banner ahora dice "Usá ← Volver para regresar a la tratativa" en lugar de mentir sobre el Guardar.

### 2. Estándar único de botón Volver — barrido transversal

Se fijó el siguiente patrón único para todas las vistas de backoffice:

```html
<a href="<?= htmlspecialchars($dest) ?>"
   class="btn btn-outline-secondary btn-sm"
   title="Volver al <destino específico>">
    <i class="bi bi-arrow-left"></i> Volver
</a>
```

- **Clase única**: `btn btn-outline-secondary btn-sm`. Sin `rounded-pill`, sin colores alternativos, sin `shadow-sm` salvo cuando la vista lo exige (Mantenimiento, EmpresaConfig por consistencia propia).
- **Icono**: `bi bi-arrow-left` (nada de `←` string).
- **Texto**: `"Volver"` a secas. La variación específica (al Panel / al CRM / al listado / al Launcher / etc) se mueve al `title` como tooltip. Esto elimina la inconsistencia histórica entre ~8 textos distintos.

### 3. Vistas tocadas (barrido completo)

Se unificaron **~30 vistas** del backoffice:

- **Dashboard**: `tenant_dashboard.php`, `admin_dashboard.php`.
- **RxnSync**: `index.php`.
- **RxnLive**: `dataset.php` (el link `text-muted` se reemplazó por botón estándar, el header pasó a `flex-md-nowrap` para que todas las acciones entren en una fila en desktop; el botón quedó a la derecha del todo, siguiendo la convención del resto del sistema).
- **CrmPedidosServicio**: `form.php` (Volver+Ayuda movidos del topbar al header de página), `index.php`.
- **CrmPresupuestos**: `form.php` (título "Presupuesto #N" + badge de estado agregados al header), `index.php`.
- **CrmTratativas**: `index.php`, `form.php`.
- **CrmAgenda**: `index.php`, `form.php`.
- **CrmLlamadas**: `index.php`.
- **CrmNotas**: `form.php`, `show.php`, `import.php`.
- **CrmMailMasivos**: `reportes/form.php`, `envios/crear.php`, `envios/monitor.php`.
- **CrmClientes**: `index.php`, `form.php`.
- **Articulos**: `index.php`, `form.php`.
- **Pedidos** (Tiendas): `index.php`, `show.php`.
- **ClientesWeb**: `index.php`.
- **Categorias**: `index.php`, `crear.php`, `editar.php`.
- **Empresas**: `index.php`, `editar.php`, `crear.php`.
- **Usuarios**: `mi_perfil.php`, `index.php`, `editar.php`.
- **PrintForms**: `index.php`, `editor.php`.
- **Admin**: `mantenimiento.php`, `module_notes_index.php`, `smtp_global.php`, `rxn_live_vistas.php`.
- **Help**: `operational_help.php`.
- **EmpresaConfig**: `index.php`.

### 4. `page_header.php` como fuente de verdad

El partial `app/shared/views/partials/page_header.php` recibió el mismo estándar aplicado al botón de Volver que renderiza. A partir de ahora:

- Cualquier vista que declare `$usePageHeader = true` hereda el patrón sin tocar código.
- El `$backLabel` que pase la vista se transforma automáticamente en el `title` del botón, y el texto visible queda en `"Volver"` a secas.
- Esto cubre automáticamente: `RxnLive/index.php`, `Dashboard/crm_dashboard.php`, `RxnGeoTracking/config.php`, `Usuarios/crear.php` y cualquier módulo futuro que use el partial.

### 5. Convención: topbar de documento vs header de página

Se fijó la siguiente regla (visible en `CrmPedidosServicio/views/form.php`):

- **Topbar del admin_layout** (cuando un form lo usa): sólo acciones que **modifican el documento** — Enviar a Tango, Copiar, Enviar Mail, Imprimir, Formulario, Eliminar, **Guardar**.
- **Header de página**: título del documento + acciones de página — **Volver** y **Ayuda**.

El PDS estaba mezclando las dos cosas en el topbar y rompía la inercia del usuario. Ahora está separado.

### 6. OTA/Mantenimiento vuelve al Backoffice

`app/modules/Admin/views/mantenimiento.php`: el botón Volver apuntaba a `/mi-empresa/configuracion` (Tiendas) independientemente del contexto de origen. Eso fallaba para empresas que sólo tienen CRM (mostraba "Acceso denegado: El tenant actual no tiene habilitado Entorno Operativo de Tiendas"). Se cambió a `/admin/dashboard` (Backoffice), que es el destino natural para un módulo estrictamente admin.

> **Pendiente futuro**: hacer esto genuinamente contextual (detectar área de origen por referer o query param) — por ahora Backoffice fijo es suficiente.

### 7. Fix: "Versión App: Desconocida" en Mantenimiento

`app/modules/Admin/Controllers/MantenimientoController.php::index`: leía `$versionData['version']` y `$versionData['build']`, pero las claves reales del array son `current_version` y `current_build` (el bloque `history` es el changelog). El check `isset($versionData['build'])` fallaba siempre y el panel caía al fallback "Desconocida". Ahora lee las claves correctas y muestra `1.19.0 (Build 20260422.1)`.

## Por qué

Charly reportó 5 ítems de correcciones menores (#29 de la tanda histórica):

1. PDS desde tratativa: al guardar iba a la tratativa; debería quedarse en el PDS. El botón Volver es el que lleva a la tratativa.
2. RxnLive: el botón Volver no estaba en el lugar estándar del resto del sistema.
3. OTA vuelve a Tiendas: debería volver a Backoffice.
4. Version App Desconocida: bug histórico nunca arreglado.
5. Asignar PDS/Presupuesto/Nota existente a tratativa → guardado al backlog en Engram, se ataca en una iteración futura.

Al analizar el #2 se detectó que había ~8 textos distintos para "Volver al X" y 3-4 clases CSS distintas, así que en lugar de tocar sólo RxnLive se barrió todo el backoffice de una (Charly pidió "todo junto").

## Impacto

- **Usuario final**: navegación más predecible entre módulos. El botón Volver está siempre en el mismo lugar (derecha del título de página) y con el mismo estilo. Evita la fatiga cognitiva de re-identificar el botón en cada vista.
- **Developer**: próximas vistas deben usar el estándar fijado. `page_header.php` es la opción canónica; si se arma el header a mano, respetar clase + icono + texto.
- **Mantenimiento**: el indicador de versión del panel ahora funciona. Se hace más fácil diagnosticar en prod qué build está corriendo.

## Decisiones tomadas

- **"Volver" a secas + title** en lugar de "Volver al CRM / Panel / Entorno / ...": unifica visualmente y deja la especificidad en el tooltip.
- **`btn-sm` como default**: las toolbars del backoffice manejan muchos botones por fila; `btn-sm` mantiene la densidad sin comprimir demasiado.
- **OTA a Backoffice fijo** (sin contextual por referer): el 99% de las veces Mantenimiento se abre desde Backoffice. Complejizar por el 1% no vale.
- **Guardar del PDS no vuelve a tratativa**: el Guardar es una acción de compose intermedia (el usuario guarda cada tanto mientras trabaja). El Volver es el explícito.

## Validación

- Charly confirmó los 4 fixes tras reload duro (Ctrl+Shift+R):
  - #1: Volver a tratativa OK, Guardar queda en PDS OK.
  - #2: RxnLive con Volver en la fila principal a la derecha — después de 2 iteraciones de alineación.
  - #3: OTA vuelve a Backoffice OK.
  - #4: Versión App muestra valor real.

## Pendiente

- **Backlog guardado en Engram**: "Asignar PDS/Presupuesto/Nota existente a Tratativa" (#5 de la tanda — se ataca en próxima iteración de tratativas).
- **Retorno contextual por área en OTA**: por ahora Backoffice fijo.
- **Siguiente iteración**: módulo Presupuestos + cosas pesadas de Artículos y Clientes (flagged por Charly como "muy pesada").
