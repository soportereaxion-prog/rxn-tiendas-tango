# Clientes Web y Resolución Comercial Tango para Pedidos

## Contexto
Teníamos un bloqueo enviando pedidos a Tango. Estábamos iterando ciegamente sobre la estructura de "Cliente Ocasional" cuando el verdadero problema radicaba en la falta del identificador comercial principal (`ID_GVA14`) y demás metadatos necesarios para el ERP. Se adoptó una nueva estrategia: implementar una pantalla de administración integral de clientes donde se pueda buscar y enlazar explícitamente el registro de Tango, y recién entonces permitir procesar los pedidos web.

## Problema
Los pedidos se enviaban asumiendo un "cliente ocasional" sin un link fuerte con la base de datos de Tango. Tango esperaba un ID_GVA14 de "Cliente Habitual", sin lo que el pedido era sistemáticamente rechazado y se retornaba el error evasivo *"Se deben ingresar los datos del cliente ocasional."*.

## Decisión
Se audita la API de `process=2117` descubriendo que el código de cliente que maneja habitualmente el operador (e.g., `CLAUS`) debe ser convertido al identificador base de datos (`ID_GVA14`) explícito a través de una nueva funcionalidad lookup API.
Se implementa un módulo administrativo "Clientes Web" para forzar intencionalmente al operador a proveer el **Código Tango**. El backend consume el web service y rescata los campos vitales, salvaguardando el pedido.

## Archivos afectados
- `database_migrations_checkout.php`: Agrega las columnas de persistencia Tango a la tabla `clientes_web`.
- `app/modules/ClientesWeb/Services/ClienteTangoLookupService.php`: Nueva clase `curl` a process `2117`.
- `app/modules/ClientesWeb/ClienteWebRepository.php`: Incorpora `findAllPaginated`, y el método para salvar los ids descubiertos.
- `app/modules/ClientesWeb/Controllers/ClienteWebController.php`: Rutas backend del index, update y validar_tango.
- `app/config/routes.php`: Registro de endpoints `/mi-empresa/clientes`
- `app/modules/pedidos/Controllers/PedidoWebController.php`: Validación estricta que aborta la llamada a Tango si `$cliente['id_gva14_tango']` es nulo.
- `app/modules/Tango/Mappers/TangoOrderMapper.php`: Reorganizado para inyectar explícitamente `ID_GVA14`, `ES_CLIENTE_HABITUAL`, vendedor, transporte y lista de precios desde la BD en vez de heurística en duro.

## Implementación
1. **Auditoría API**: Se creó y usó un tester on-demand para entender el JSON de respuesta.
2. **Schema Upgrade**: Se añadieron `id_gva14_tango`, `id_gva01_condicion_venta`, `id_gva10_lista_precios`, `id_gva23_vendedor`, `id_gva24_transporte`.
3. **ABM Clientes**: Vistas `index.php` listando clientes, `edit.php` incorporando form de datos y el bloque clave `Vínculo Comercial Tango`.
4. **Validación Tango**: Botón que toma el valor de `codigo_tango`, lo pasa al lookup service, recupera el JSON y salva las constantes.
5. **Restricción de Flujo**: Dentro de `PedidoWebController::reprocesar()` y de la vista de pedido, el sistema bloquea visualmente y a nivel backend cualquier intento de inyección a la plataforma si el código no ha sido transformado en su ID_GVA14 subyacente.

## Impacto
El funnel de envíos de Orders ya no dependerá de heurísticas para resolver al comprador local con el mundo contable ERP. Elimina la falla fatal al garantizar la base comercial del registro antes de iniciar la solicitud asincrónica hacia el API transaccional de Axoft.

## Riesgos
- Configuración errónea del `codigo_tango` ingresado por el operador. Mitigado al requerir que responda `200 OK` y el array JSON venga no vacío por medio de la API `GetByFilter`.
- Demasiados llamados on-demand. Una vez validado, los ID's quedan persistidos, bajando re-consultas.

## Validación
- Comprobar accesos a `/mi-empresa/clientes` habiendo un pedido existente de la etapa previa.
- Colocar un mock `CLAUS` en su input. Validar en Tango.
- Revisar que se pobló `id_gva14_tango: 7` en la BD MySQL Local .
- Visitar la Orden, pulsar "Enviar a Tango" y confirmar que el Mapper usó la bandera `ES_CLIENTE_HABITUAL` en True sumado a los 5 identificadores correspondientes.

## Notas
El objetivo "Enviar pedidos pendientes a Tango masivo desde el ABM de Cliente" fue omitido en la versión actual priorizando en su reemplazo un bloqueo más estricto a las operaciones individuales desde el detail de la Order. Esto previene reintentos ineficientes. Queda propuesto para futuras iteraciones optimizadas.
