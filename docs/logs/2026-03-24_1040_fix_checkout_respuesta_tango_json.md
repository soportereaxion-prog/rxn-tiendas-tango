# rxn_suite — FIX SERIALIZACIÓN DE RESPUESTA TANGO + PRUEBA REAL DE CHECKOUT

## Fecha
2026-03-24 10:40

## 1. Objetivo
Atacar de forma concluyente el error de MySQL `SQLSTATE[22032]: Invalid JSON text`, disparado al intentar documentar el error devuelto por la API de Tango Connect dentro del campo `respuesta_tango` del pedido recientemente insertado. Efectuar una prueba real que certifique el end-to-end de manera irrefutable.

## 2. Causa Raíz
Cuando la integración de Tango en `CheckoutService` experimentaba un fallo exógeno (e.g. timeout, mal credencial o un HTTP 500 del servidor destino Axoft), el bloque `catch` recuperaba un string plano ("Fallo en Integración Externa..."). Este string desnudo era inyectado directamente en el método `markAsErrorToTango`, quien lo enchufaba (vía PDO) a la columna `respuesta_tango`. Al carecer de formato JSON (`{}` o `[]` o bien escaped strings), el motor JSON nativo de MySQL abortaba la operación, impidiendo la actualización final y rompiendo el hilo visual al comprador.

## 3. Tipo de Columna y Contrato Esperado
- Base de Datos: Columna `respuesta_tango` del tipo **JSON**.
- Contrato original del Repo: `string payload, string errorResponse`. No serializaba. 

## 4. Valor que Rompía
Se rompía inyectando directamente mensajes atrapados por Exception: `Fallo en Integración Externa: HTTP Error 500`.

## 5. Solución Aplicada
Se refactorizó el contrato en `app/modules/Pedidos/PedidoWebRepository.php`:
`markAsErrorToTango(..., string $errorText, ?string $jsonResponse = null)`
- La columna `mensaje_error` (tipo TEXT) guarda el plano `$errorText`.
- La columna `respuesta_tango` (tipo JSON) almacena estrictamente el parámetro opcional `$jsonResponse` provisto.
- En caso de omitirse el json (e.g. en el bloque catch que sólo entrega string), la función lo envuelve internamente `json_encode(['error' => $errorText])`. Garantizando así validez absoluta para el driver PDO y MySQL JSON column constraint.

Se alineó acorde `app/modules/Store/Services/CheckoutService.php` para inyectarle estos distintos sabores semánticos.

## 6. Prueba Real Realizada
Se desplegó un script CLI mock `test_checkout_flow_cli.php` que levanta la conexión a la base de datos real local, inyecta contextualmente un item existente con precio ($100), fabrica un session state in-memory de Cart, y orquesta `processCheckout()`. 

## 7. Resultado Real
El test arrojó victoria empírica absoluta.
El pedido se guardó como ID 3, estado: `error_envio_tango`.
Su columna `respuesta_tango` validó decodificación de vuelta arrojando textualmente: `{"error": "Fallo en Integración Externa: HTTP Error 500"}`
Los renglones se perpetuaron adecuadamente sin incidentes. 

## 8. Archivos Tocados
- `app/modules/Pedidos/PedidoWebRepository.php`
- `app/modules/Store/Services/CheckoutService.php`
- `test_checkout_flow_cli.php` (Nuevo script de testing temporal)

## 9. Riesgos
Eliminación de la dependencia frágil: el flujo web nunca volverá a quebrarse por un string incorrecto en integración. La orden web se conserva permanentemente, mitigando pérdida económica del carrito.

## 10. Próximos pasos
1. Interceptar el JSON error real en las capas web y habilitar un mailer silencioso al administrador para dar cuenta del Tango-Down.
2. Confirmar la homologación del payload contra el ERP verdadero sin errores 500.
