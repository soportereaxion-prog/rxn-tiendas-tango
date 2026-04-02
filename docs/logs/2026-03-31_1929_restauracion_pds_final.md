# Restauracion Integral de Pedidos de Servicio (PDS)

## ¿Que se hizo?
Se solventaron multiples fallos criticos y dependencias rotas producto del reseteo del codigo, devolviendole paridad operativa integral al formulario de creacion y edicion del PDS.

1. **Parche a Base de Datos en Update/Create (PDOException)**: Se agrego la vinculacion explicita de `:articulo_precio_unitario` y `:tiempo_decimal` en el metodo maestro de ensamblaje `buildPayload` dentro de `PedidoServicioRepository`.
2. **Supervivencia de imagenes de Diagnostico ante Fallas de Validacion**: Si el operador olvidaba un campo esencial (ej. el cliente), y el PDS recargaba, las capturas de pantalla pegadas se evaporaban porque no estaban atadas localmente en memoria. Se agregaron Hidden Inputs que conservan la data base64 de las imagenes y se muestran en el fallback, evitando repetir el pegado.
3. **Modulo de Correo, Copia e Impresion**: Se añadieron los endpoints, controladores y botones para estas funcionalidades vitales (`sendEmail`, `printPreview`, `copy`). En especifico:
   - Los botones "Copiar" y "Enviar por mail" se reformatearon de etiquetas ancla HTML (`<a>`) a formularios `POST` nativos para respetar el flujo transaccional.
   - El envio de correos ahora procesa y adjunta imagenes de diagnostico locales como recaudo fundamental al destinatario.
4. **Buscador de Articulos CRM**: Se reensamblo el endpoint REST para responder una concatenacion estructurada en `CODIGO - DESCRIPCION / NOMBRE` para resolver con exactitud la busqueda por cliente.
5. **Limpieza visual de redundancias**: Se extrajo la barra flotante de footer residual y se organizo el formulario en diseño estilo "sabana" del ecosistema RXN.

## ¿Por que?
El comando `git reset` habia volado la persistencia local de estas variables, degradando criticamente la usabilidad y provocando crash 500 silenciosos al intentar persistir los modelos de CRM.

## Impacto
El PDS funciona impecablemente en todos sus aspectos base. Recupera consistencia de seguridad por POST actions, robustez en el payload transaccional, fidelidad al guardar capturas adjuntas, y se reactiva la interaccion por correo.

## Archivos Afectados
- `app/modules/CrmPedidosServicio/PedidoServicioRepository.php`
- `app/modules/CrmPedidosServicio/PedidoServicioController.php`
- `app/modules/CrmPedidosServicio/views/form.php`
- `app/config/routes.php`
- `app/config/version.php`
