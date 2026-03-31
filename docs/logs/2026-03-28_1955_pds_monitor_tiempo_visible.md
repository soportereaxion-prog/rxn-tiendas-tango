# [CRM/PDS] - Monitor de tiempos mas visible durante la carga

## Que se hizo
- Se compactaron aun mas paddings, gaps y helpers del encabezado del PDS para reducir aire muerto entre inputs.
- Se redujo la altura visible de `Diagnostico` y `Falla` para que entren mas datos operativos en una pantalla 1080p.
- El bloque de tiempos (`bruto`, `descuento`, `neto`, `estado`, `snapshot`, `valor decimales`) se movio dentro del detalle tecnico y ahora queda mas visible/pegado al flujo de carga.
- El monitor de tiempos se hizo sticky en desktop para poder seguir controlandolo mientras se completa el PDS.

## Por que
- La operatoria del PDS necesita mirar el calculo de tiempos constantemente mientras se redacta el detalle tecnico.
- La vista todavia dejaba demasiado aire entre inputs y empujaba el monitor de tiempos fuera de pantalla.

## Impacto
- El formulario entra mejor en escritorio y los tiempos quedan mas a mano durante la carga del servicio.
