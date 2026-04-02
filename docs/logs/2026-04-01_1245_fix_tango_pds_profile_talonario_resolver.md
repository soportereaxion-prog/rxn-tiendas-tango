# Fix: Resolución de ID_GVA43_TALONARIO_PEDIDO y Fallback 6 en Tango PDS Integrator

**Fecha:** 2026-04-01 12:45
**Módulo:** Tango Integración (Usuarios / Pedidos Web)
**Descripción:** Se blindó el parser del endpoint `GetById` (process 20020) para manejar respuestas de Perfiles enviadas como arrays, corrigiendo la falla que provocaba que el snapshot de usuario guardara `null` para el talonario del pedido en la IU de edición. Se removió el fallback hardcodeado `6` y `1` del mapper, delegando la responsabilidad de omitir atributos de perfiles dinámicos al Tango Connect.

### Problema
Debido a una desalineación con la API OData de Tango Connect `GetById`, la respuesta del proceso `20020` envolvía el objeto de Perfil del Usuario en un Array dentro de `"value": [ { ... } ]`. Al hacer un destructuring manual buscando `$detail['ID_GVA43_TALONARIO_PEDIDO']`, se devolvía un nulo, porque se estaba consultando a un array anidado.
Al propagarse este nulo hasta `TangoOrderMapper.php`, saltaba un fallback `?? 6` en el mapeador, provocando que TODOS los pedidos pasaran como Talonario PDS `6` causando error 400 por validación de sucursal contra Tango.

### Solución
1. **`TangoApiClient.php`:** Corregido el parseo de OData de `value` del Process 20020 para garantizar que desempaquete el array `[0]` de un List object si la API responde iterando un elemento único.
2. **`TangoProfileSnapshotService.php`:** Se robusteció el mapeo haciéndolo case-insensitive `[strtolower($key)]` en caso de variaciones con los conectores on-premise.
3. **`TangoOrderMapper.php`:** Eliminada la mutación fatal de `?? 6` para Talonarios y `?? 1` para Depósitos. Pasan como referenciados en Snapshot y si el snapshot no indica (null), se quitan via `array_filter` delegando al perfil subyacente de Tango de forma limpia. 

### Impacto
El Selector de Edición de Usuario ahora informa en vivo a la etiqueta: `Talonario PDS resuelto (ID_GVA43): 6, 8, etc.` y el Payload PDS final respeta dicho Talonario ignorando el 6 estático histórico.
