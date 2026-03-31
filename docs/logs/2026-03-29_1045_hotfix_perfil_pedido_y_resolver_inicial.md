# Hotfix selector Perfil de pedido Tango + resolver inicial de payloads

## Que se hizo
- Se corrigio `app/modules/Tango/TangoApiClient.php` para que el catalogo de perfiles de pedido consuma `process=20020` con `view=Habilitados`, interprete correctamente los campos `ID_PERFIL`, `COD_PERFIL`, `DESC_PERFIL`, `HABILITADO` y soporte la forma real de respuesta del listado.
- Se agrego la lectura de detalle por perfil (`GetById` de `process=20020`) y un resolver compartido `app/modules/Tango/Services/TangoOrderHeaderResolver.php` para derivar cabeceras comerciales desde el perfil elegido.
- Se reengancharon ambos flujos transaccionales (`PedidoServicioTangoService` y `PedidoWebController`) para enviar a Tango `ID_PERFIL_PEDIDO`, `ID_GVA43_TALON_PED`, `ID_GVA01`, `ID_GVA23`, `ID_STA22`, `ID_GVA10`, `ID_GVA24` e `ID_MONEDA` a traves del resolver compartido.
- `TangoOrderMapper` deja de fijar esos valores por defecto cuando el caller ya entrega cabeceras resueltas; conserva solo un fallback legacy encapsulado para no romper instalaciones viejas fuera de estos dos circuitos.

## Por que
- El selector nuevo habia quedado roto: despues de validar o cambiar empresa Connect no cargaba perfiles utilizables porque el consumo del catalogo no estaba alineado con la respuesta real de Axoft.
- El perfil seleccionado ya tenia que empezar a gobernar la cabecera del pedido; si no, la UI nueva quedaba desacoplada del payload real y seguian mandandose IDs comerciales hardcodeados.

## Impacto
- Configuracion vuelve a listar y buscar perfiles habilitados reales de la empresa Connect seleccionada.
- Pedidos Web y PDS comparten una misma estrategia para resolver cabeceras comerciales desde el perfil Tango con fallbacks de compatibilidad.
- El talonario PDS legacy y el deposito configurado quedan solo como respaldo operativo si el perfil no devuelve esos IDs o no puede consultarse.

## Deuda / pendiente explicitada
- `ID_MONEDA` sigue con fallback compatible (`1`). No se implemento ningun mapeo desde `MONEDA_HABITUAL` porque hoy no existe evidencia verificada en este repo para hacerlo sin inventar semantica.
- No se interpretaron aun los flags `COMPORTAMIENTO_*`; por ahora solo se consumen IDs explicitos presentes en el detalle del perfil.
