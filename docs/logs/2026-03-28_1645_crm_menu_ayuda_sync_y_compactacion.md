# [CRM/UI/AYUDA] - Compactacion sabana, orden de tarjetas y alineacion operativa

## Que se hizo
- Se compacto la vista `app/modules/CrmPedidosServicio/views/form.php` para reducir aire innecesario, subir la accion principal al header y mejorar el aprovechamiento de escritorio tipo sabana.
- Se corrigio el dashboard CRM para que tambien soporte drag & drop persistente de tarjetas, guardando el orden por area (`tiendas` y `crm` por separado) en `dashboard_order`.
- Se habilitaron en `Articulos CRM` los mismos botones de sincronizacion visibles que en Tiendas, con rutas propias `/mi-empresa/crm/sync/*`.
- Se ajusto `TangoSyncController` para resolver automaticamente el area activa y redirigir al listado correcto despues de cada sync.
- Se mejoro la validacion de Tango Connect en configuracion para aceptar credenciales guardadas localmente y no exigir URL si la llave ya permite construir el endpoint.
- Se amplio la ayuda operativa con contenido dummy-friendly para CRM: `Pedidos de Servicio`, `Articulos CRM`, `Configuracion CRM` y orden de tarjetas.

## Por que
- El formulario de pedidos seguia demasiado alto para una pantalla operativa `1080p` y la accion principal quedaba demasiado abajo.
- CRM no respetaba el ordenamiento visual de tarjetas como Tiendas, rompiendo consistencia del launcher operativo.
- El operador esperaba ver el mismo circuito de sync en `Articulos CRM` y una ayuda clara para usuarios menos tecnicos.
- La validacion de llave podia rechazar casos validos cuando faltaba URL pero ya existia `client key` suficiente para construir la conexion.

## Impacto
- El formulario de pedidos de servicio entra mejor en escritorio sin tocar el tamano util de `Diagnostico` y `Falla`.
- Cada entorno operativo mantiene su propio orden visual de tarjetas sin contaminar al otro.
- `Articulos CRM` ya expone `Sync Total`, `Sync Stock`, `Sync Precios` y `Sync Articulos` con su propia configuracion CRM.
- La ayuda operativa queda mas util para onboarding y soporte cotidiano.

## Decisiones tomadas
- El orden de dashboard se guarda como JSON por area dentro del mismo campo de usuario para evitar una migracion de columnas innecesaria en esta etapa.
- `Configuracion CRM` sigue mostrando campos parecidos a Tiendas, pero persiste en `empresa_config_crm`; los datos iniciales se clonaron solo como punto de partida.
- Se priorizo compactar el encabezado del pedido antes que reducir textareas tecnicas, porque esos dos bloques son el corazon del trabajo operativo.
