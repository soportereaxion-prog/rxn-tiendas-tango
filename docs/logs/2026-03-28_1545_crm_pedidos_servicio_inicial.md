# [CRM/PEDIDOS DE SERVICIO] - Modulo inicial con calculo de tiempos

## Que se hizo
- Se agrego el nuevo modulo `Pedidos de Servicio` dentro del entorno CRM con rutas propias, tarjeta en dashboard y vistas de listado/alta/edicion.
- Se incorporo persistencia local en la tabla `crm_pedidos_servicio`, creada automaticamente con correlativo por empresa, snapshots de cliente/articulo y tiempos calculados.
- Se implementaron calculos de `duracion_bruta` y `duracion_neta` a partir de `fecha_inicio`, `fecha_finalizado` y `descuento` en formato `HH:MM:SS`.
- Se sumaron endpoints livianos de sugerencias para cliente, articulo y clasificacion, reutilizando la base hoy disponible sin bloquear la evolucion futura del CRM.

## Por que
- El entorno CRM ya tenia base separada y necesitaba su primer modulo operativo real mas alla de configuracion y articulos.
- La operatoria actual requiere registrar servicios con tiempos exactos, cierres parciales y texto tecnico, por lo que hacia falta resolver calculo y trazabilidad desde la primera iteracion.
- Se eligio una implementacion simple y evolutiva: persistencia propia, sin framework extra ni dependencia dura con un futuro modulo CRM de clientes todavia no definido.

## Impacto
- Un tenant con CRM activo ya puede crear, listar y editar pedidos de servicio desde `/mi-empresa/crm/pedidos-servicio`.
- Cada registro conserva snapshot de cliente y articulo para no romper historicos si el origen cambia mas adelante.
- El formulario muestra el resultado de tiempos en vivo y el backend valida que el descuento no supere la duracion total.

## Decisiones tomadas
- `numero` se genera automaticamente por `empresa_id` y no se deja editable para mantener consistencia operativa.
- `articulo` se resuelve desde `articulos` del entorno Tiendas, como se pidio, pero el pedido guarda tambien `articulo_codigo` y `articulo_nombre` locales.
- `cliente` se vincula por ahora contra la base disponible `clientes_web`, guardando snapshot local para desacoplar el historico del modulo futuro de clientes CRM.
- `clasificacion` queda local-first con sugerencias internas y valores historicos guardados, preparada para migrar luego a endpoint externo sin refactor fuerte.
