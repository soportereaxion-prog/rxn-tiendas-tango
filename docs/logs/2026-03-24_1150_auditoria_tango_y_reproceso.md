# Auditoría y Refactor de Integración Tango (Proceso 19845) y Reproceso Manual

## Contexto
Durante el checkout, los pedidos generados localmente no lograban integrarse con el API Connect de Tango ERP bajo el Endpoint `process=19845`. Se diagnosticaron varios errores de validación genéricos de la propia API, en especial bloqueos al enviar el código de cliente "000000" (Ocasional).

## Problema
1. La API rechazaba cualquier JSON anidado (`CABECERA`).
2. La API requiere explícitamente `ES_CLIENTE_HABITUAL`, `ID_GVA43_TALON_PED` y `ID_STA22` en la raíz del Payload.
3. Para clientes Ocasionales, la API exige una estructura de datos estricta (no documentada públicamente), arrojando siempre `"Se deben ingresar los datos del cliente ocasional."` ante cualquier heurística (40 variaciones testeadas en vivo).
4. Forzando un Cliente Habitual (`ID_GVA14 = 1`), la API sortea la validación de cliente y depósito, pero arroja una excepción `.NET` interna: `Value cannot be null. (Parameter 'source')` en el procesamiento de la lista de Artículos (Items/Renglones), evidenciando que la clave o estructura interna de la lista de renglones tampoco matchea el DTO esperado por el backend de Axoft para el tenant actual.

## Decisión
- **Refactorización de Mapper:** Se actualizó `TangoOrderMapper` para retirar el nodo `CABECERA` y aplanar el JSON, inyectando `ID_STA22` y enviando `RENGLONES` e `ITEMS` simultáneamente.
- **Trazabilidad:** Se mapeó un nodo `CLIENTE_OCASIONAL` best-effort para que la información del checkout viaje en el JSON físico en caso de ser inspeccionado, a la espera del manual oficial de conectores del cliente.
- **Habilitación de Reproceso:** Dado que el workflow actual no permite automatización 100% segura por falta de Schema, se construyó el botón "Volver a enviar a Tango" en el backoffice de Pedidos (`show.php`), conectado a un nuevo endpoint `POST /mi-empresa/pedidos/{id}/reprocesar` que reconstruye la llamada utilizando el mapper y registra la respuesta.

## Archivos afectados
- `app/modules/Tango/Mappers/TangoOrderMapper.php`
- `app/modules/Pedidos/Controllers/PedidoWebController.php`
- `app/modules/Pedidos/views/show.php`
- `app/config/routes.php`

## Implementación
1. Integración de variables `process 19845` a nivel raíz.
2. Endpoint interactivo en Controller.
3. Formulario POST c/ validación interactiva JS en vista.
4. (Script temporal `cli_tango_test.php` usado para fuzzing del Schema).

## Impacto
Pese al rechazo de la API por schema estricto, el panel Web de rxn_suite ofrece trazabilidad táctica (payload y respuesta exacta visualizado en tarjeta) y un método funcional para volver a empujar los JSONs una vez regularizada la configuración en Axoft.

## Riesgos
- El payload no se integrará correctamente en automático hasta que se obtenga el *Swagger JSON* o especificación formal de los nodos `Items` y `Cliente Ocasional` por parte del integrador del ERP.

## Validación
- Probado el renderizado en la UI interactiva de error y test de envío con actualización de estados en base de datos.
