# [CRM/PDS] - Envio a Tango, tiempo decimal y compactacion operativa

## Que se hizo
- Se ajusto la vista `app/modules/CrmPedidosServicio/views/form.php` para compactar espacios, eliminar acciones inferiores y subir la accion principal al header.
- Se movio `Detalle tecnico` por encima de la franja de resumen para reducir aire muerto y mejorar lectura en 1080p.
- Se agrego el calculo persistido de `tiempo_decimal` en `crm_pedidos_servicio`, derivado de `duracion_neta_segundos / 3600` con precision de 4 decimales.
- Se incorporo el boton `Enviar a Tango` para PDS, reusando el circuito de pedidos de Tiendas y guardando `nro_pedido` con el numero externo devuelto por el endpoint.
- El envio genera un pedido comercial con un solo renglon: el `articulo` elegido viaja como articulo Tango y la `cantidad` viaja como tiempo decimal.
- Se sumaron metadatos de integracion en el PDS (`estado_tango`, `intentos_envio_tango`, payload, respuesta y mensaje de error).

## Por que
- El formulario todavia tenia demasiado aire visual para una pantalla operativa y dejaba la accion de guardar demasiado abajo.
- El flujo esperado del negocio es: guardar PDS local, revisar, y luego enviarlo a Tango como pedido comercial.
- Para representar horas de servicio en un renglon comercial hacia Tango era necesario convertir tiempo real base 60 a valor decimal.

## Impacto
- Un PDS ya puede registrarse localmente y despues enviarse manualmente a Tango desde el mismo formulario.
- El campo `Pedido Tango` deja de ser carga manual y pasa a reflejar el numero externo retornado por la integracion.
- El articulo del PDS toma snapshot de precio local y lo usa como precio unitario al construir el pedido externo.
- La ayuda operativa ahora explica el concepto de `valor decimales` y el envio de PDS a Tango en lenguaje dummy-friendly.

## Decisiones tomadas
- Se mantuvo `numero` como correlativo interno del PDS y se reutilizo `nro_pedido` para el identificador devuelto por Tango.
- El tiempo decimal se guarda con 4 decimales para evitar errores tipicos de redondeo en servicios horarios.
- El envio se apoya en la configuracion propia de CRM (`empresa_config_crm`) y no en la de Tiendas.
