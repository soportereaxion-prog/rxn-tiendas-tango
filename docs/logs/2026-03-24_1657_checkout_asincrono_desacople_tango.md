# Checkout Asíncrono — Desacople de API Tango

## Contexto
Durante la finalización de un pedido en el Store público, el sistema desencadenaba una sincronización síncrona contra la API externa (Tango Connect) mediante el `TangoOrderMapper`. Esto exponía la transacción de compra a:
1. Lentitud inherente de integraciones de red externas.
2. Posibles Warnings o Fatals si la data de Tango Mapper no estaba completamente poblada para un cliente casual.
3. Mensajes de error de validación visualizados inadvertidamente por el cliente final.

Se tomó la **decisión estratégica de desterrar el ingreso directo a Tango al finalizar la compra**. Todo pedido público quedará persistido localmente en estado "Pendiente" y librado a su gestión asíncrona mediante el panel administrativo.

## Implementación
- Se purgaron el llamado a `$tangoClient->sendOrder($tangoPayload)`, la instanciación de las dependencias (`TangoOrderMapper` y `TangoOrderClient`) y todo el bloque `try-catch` HTTP del core del `CheckoutService.php`.
- Ahora la orden guarda velozmente su estado relacional a nivel DB local e inyecta el ID generado retornándolo instantáneamente validando la compra como Exitosa en el Store Front (`tango_enviado => false`).

## Archivos Afectados
- `[M]` `app/modules/Store/Services/CheckoutService.php`

## Impacto / Riesgos
- **Impacto Positivo:** El funnel de ventas ya no está comprometido ante caídas de Axoft/Tango Connect o errores de formato de datos (ej: el `Warning: Undefined array key "id_sta11_tango"` no volverá a ocurrir en el Frontend).
- **Riesgo Mitigado:** Los pedidos ahora dependen estrictamente de un operador para ser ingresados formalmente en Tango ERP usando el botón "Reenviar a Tango" que armamos previamente en el BackOffice.

## Próximos Pasos
- Consolidar el Dashboard de Pedidos para que el equipo comercial acceda rápido a la bolsa de Pendientes a despachar.
