# Ajuste de Lógica de Copiado PDS y Metadata Tango

**Fecha**: 2026-04-01
**Módulos**: CrmPedidosServicio, Tango Connect
**Tipo**: Fix / Refactor

## Qué se hizo
1.  **Refactor Action Copiar en Pedidos (PDS)**: Se actualizó `PedidoServicioController->copy()` para utilizar un `null` estricto en el campo `fecha_finalizado` en lugar de una cadena vacía `''`. Se añadieron al purge de propiedades las llaves operativas de `tango_sync` (`nro_pedido`, `_status`, etc.).
2.  **Mapeo Múltiple y Case-Insensitive en TangoOrderHeaderResolver**: Se reescribió la función interna `$getProp` de la clase `TangoOrderHeaderResolver` para recorrer los keys variados provenientes de Tango Connect. Ahora toma argumentos infinitos que verifica en orden sin sensibilidad a mayúsculas/minúsculas.
3.  **Inclusión Explícita de Metadata Secundaria (ID_GVA43, ID_STA22)**: Se garantizó que el sistema evalúe el JSON del perfil buscando nombres de campos habituales como `$getProp('ID_GVA43_TALONARIO_PEDIDO', 'ID_GVA43')` y no descartando `ID_STA22` por casing.

## Por qué
- El string vacío provocaba una `PDOException` (código `1292` por `Incorrect datetime value`) bajo el `strict_mode` de las instancias modernas de MySQL. Esto causaba un aborto silencioso de la inserción, retornando un `Flash::danger()` que el usuario presuponía erróneamente que era un pop-up de fallo de validación del formulario HTTP.
- En la consulta a `TangoProfileSnapshotService`, ciertos metadatos operativos llegaban en formatos variados (ejemplo: llave nativa pura u OData re-mapeado). Si el fallback por hardcode Legacy no debía usarse más, la lectura del JSON resultante debía flexibilizarse para capturar las variaciones nominativas.

## Impacto
* Ahora cuando el usuario selecciona **Copiar**, el registro se duplica exitosamente bajo la nueva estampa temporal y sin acarrear el estado de sincronización fallido/exitoso de Tango del viejo pedido de servicio. Ya no interrumpe el flujo visual.
* Los atributos como `ID_STA22` viajan incondicionalmente en la cabecera del payload de **Tango Connect (19845)**.
