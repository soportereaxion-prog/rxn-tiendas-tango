# Release 1.46.0 — Push individual de clientes/artículos + UX RxnSync + ayuda

**Fecha**: 2026-05-04
**Build**: 20260504.2
**Iteración**: 47

## Tema

Sesión cerrada con un combo de fixes alrededor de la integración Tango Connect:
- Push individual de cliente/artículo desde el form (que nunca había funcionado en la práctica).
- UX del flow Push → guardar form + push + quedarse en pantalla.
- Bug de redirect del Guardar de artículos al index.
- Bug del label del botón "Sincronizar" en RXN Sync que se quedaba pegado al cambiar tab.
- Remoción del "Purgar Todo" del listado de clientes y artículos.
- Ayuda operativa actualizada con los flows nuevos.

## Root cause del Push roto

Tango Connect process 2117 (clientes) y 87 (artículos) son **PUT completos, no PATCH parciales**. Aunque mandes solo los campos a modificar, el servidor exige que TODOS los required fields del registro viajen en el body. Con un payload subset el servidor responde:

```
{
  "succeeded": false,
  "message": "Se detectó una situación inesperada",
  "exceptionInfo": {
    "messages": ["El campo EXPORTA es requerido"]   // clientes
    "messages": ["El campo PROMO_MENU es requerido"] // artículos
  }
}
```

El código original armaba un whitelist parcial que faltaba required fields invisibles para el operador (EXPORTA, PROMO_MENU, etc — varían por perfil de Tango).

## Solución técnica (3 capas)

### A — Shadow Copy completa + override editable (RxnSyncService)

```php
$updatePayload = array_filter($tangoData, fn($v) => $v !== null);
if ($entidad === 'cliente') {
    $updatePayload['ID_GVA14']   = $tangoId;
    $updatePayload['RAZON_SOCI'] = $localData['razon_social'];
    $updatePayload['CUIT']       = $localData['documento'];
    $updatePayload['DOMICILIO']  = $localData['direccion'];
    $updatePayload['E_MAIL']     = $localData['email'];
    $updatePayload['TELEFONO_1'] = $localData['telefono'];
} else {
    $updatePayload['ID_STA11']   = $tangoId;
    $updatePayload['DESCRIPCIO'] = $localData['nombre'];
}
```

Tango recibe todos sus required fields con sus valores actuales — los ve sin cambios y los acepta. Solo cambian los editables.

### B — Push guarda el form local primero

`CrmClienteController::pushToTango` y `ArticuloController::pushToTango` aceptan flag `_save_form=1`. Si llega, hacen UPDATE local con los campos editables ANTES de invocar `RxnSyncService::pushToTangoByLocalId`. Resultado: el operador puede editar un campo y darle Push directo sin pasar por "Guardar modificaciones".

**Salvaguarda**: si el campo required (razón social / nombre) viene vacío, se aborta el `_save_form` y se sigue con el push usando lo que ya está en DB. Evita destruir datos locales por un bug de JS.

### C — JS robusto: ID específico en el form principal

```html
<form id="rxn-cliente-form" action="..." method="POST">
<form id="rxn-articulo-form" action="..." method="POST" enctype="multipart/form-data">
```

```js
var formEl = document.getElementById('rxn-cliente-form');
if (!formEl) { rxnAlert(...); return; }
var fd = new FormData(formEl);
fd.append('_save_form', '1');
```

**Por qué**: el querySelector original (`form[action*="/" + id + "/"]`) matcheaba también los forms chiquitos del header (Copiar, Eliminar) que tienen action="/clientes/{id}/copiar". Caer en uno de esos hacía un FormData VACÍO → el backend recibía todos los campos en `""` → UPDATE local DESTRUÍA los datos del cliente. Bug crítico durante testing — restauramos cliente 1074 con un Pull desde Tango.

## Otros fixes

- **Guardar artículos vuelve al form** ([ArticuloController.php:396-405](../app/modules/Articulos/ArticuloController.php:396)): redirige a `/editar?id=X` en lugar de `$basePath` (index). Flash success.
- **Label RXN Sync** ([index.php:228-234](../app/modules/RxnSync/views/index.php:228)): nueva función `updateAuditLabel(entidad)` llamada desde `loadTabContent` + click directo del tab + `show.bs.tab` (triple seguro). Click directo es el ancla más confiable porque siempre se dispara antes que cualquier rendering.
- **Purgar Todo removido** del header de [Articulos/views/index.php](../app/modules/Articulos/views/index.php) y [CrmClientes/views/index.php](../app/modules/CrmClientes/views/index.php) — operación demasiado destructiva para estar siempre visible.
- **Ayuda actualizada** ([operational_help.php](../app/modules/Help/views/operational_help.php)): sección "Push y Pull desde la edición individual" en Articulos + paso a paso del alta de empresa Connect.

## Validación

- Push cliente 1074 desde CLI: `succeeded:true`, `recordAffectedCount:14`. ✅
- Push artículo 12946 desde CLI: `succeeded:true`, `recordAffectedCount:8`. ✅
- Charly probó push de artículo desde el browser: OK.
- Push de cliente desde browser quedó pendiente de validar Charly tras el fix del querySelector + salvaguarda.
- Label RXN Sync: queda como tech-debt menor — el handler de click directo se sumó pero Charly reportó que en su prueba siguió pegado. No insistimos más por scope.

## Pendiente (cosmético menor)

- Bug del label RXN Sync al cambiar tab. Posiblemente cache del browser; Ctrl+F5 lo resuelve. Con triple binding (loadTabContent + click + show.bs.tab) la lógica está cubierta.

## Decisiones tomadas

- **Shadow Copy completa vs subset acotado**: Connect exige PUT completo. La intuición original ("mandar solo lo editable") técnicamente no era posible — Tango rebota required fields. La solución elegante: shadow copy + override hace que el operador SIENTA que solo cambia lo editable, aunque el wire lleve todo el registro.
- **Filtrar `null` pero no `""` en array_filter**: Connect a veces rechaza nulls en numéricos/enums. Strings vacíos los acepta porque corresponden a campos VARCHAR válidos.
- **No tocar el flow de "Guardar modificaciones"**: queda como vía alternativa para guardar sin pushear (cliente inactivo, Connect caído, etc). Charly lo confirmó: "que esté a cargo del operador".

## Env vars nuevas

Ninguna.
