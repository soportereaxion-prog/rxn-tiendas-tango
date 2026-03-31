# Refactor de Catálogo Clasificaciones PDS hacia API Live Tango

- **Fecha:** 2026-03-30 08:58
- **Objetivo:** Terminar de una vez por todas con el catálogo manual estático y consumir `process=326` empalmando ID para pedidos.

## Qué se hizo
1. **Repository/Schema**: Se incorporó la columna `clasificacion_id_tango` (INT NULL) a `crm_pedidos_servicio`. Se integró esta columna en las funciones de guardado (`create`, `update`).
2. **TangoApiClient**: Se agregó `getClasificacionesPds()` que consume con `fetchRichCatalog` el `process=326` devolviendo keys maestras y códigos.
3. **Controller**: Se transformó `classificationSuggestions` para usar dicho método levantando el token configurado, e incluyendo `extraId` en el JSON de salida HTTP. Se modificó `validateRequest` para ignorar la verificación estricta local y guardar el `id_tango`.
4. **UI Core (JS)**: Modificado `rxn-ui.js` / `crm-pedidos-servicio-form.js` `setupPicker` para permitir la existencia de `data-picker-extra-hidden` extrayendo el ID en silencio mientras el `codigo` se guarda en el principal, asegurando compatibilidad visual actual.
5. **View HTML**: En la vista de PDS (`form.php`) se insertó un input hidden recibiendo `data-picker-extra-hidden` enganchado a `clasificacion_id_tango`. En la Config. de la Empresa, se bloqueó la carga manual.
6. **Send to Tango**: En `PedidoServicioTangoService`, el Payload Builder inyecta `ID_GVA81 => $pedido['clasificacion_id_tango']` a los `resolvedHeaders` del Mapper.

## Por qué
El catálogo PDS manual presentaba problemas semánticos y de sincronización, obligando al usuario a copiar y pegar datos duros que fácilmente podían corromperse. Reutilizando la función y configuración de Tango Connect, la integración del PDS ahora envía el identificador de dominio original garantizando integridad transaccional al lado del ERP.

## Impacto
* Los selectores viejos ignoran la novedad ya que es un atributo extra (Backwards-compatibility safe).
* Los pedidos sin UUID persistirán usando fallback visual mapping (el CRM viejo y Tango hacían macheo por código/descripción si faltaba el ID).
* El componente requerirá URL y token válidos siempre que ingrese al picker.
