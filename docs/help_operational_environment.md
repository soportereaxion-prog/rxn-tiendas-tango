# Ayuda Operativa - Entorno Operativo

## Objetivo

Explicar en lenguaje simple que hace cada modulo del entorno operativo, que significan sus acciones mas comunes y como resolver dudas tipicas sin necesidad de interpretar lenguaje tecnico.

Este archivo es la base documental viva del entorno operativo. Cada vez que se agregue un modulo nuevo, una accion nueva o un cambio funcional relevante, debe ampliarse aqui antes de cerrar la iteracion.

## Vision general

El Entorno Operativo es el panel de trabajo diario de la empresa. Desde ahi se gestionan productos, clientes, pedidos, cuentas internas, configuraciones e integraciones.

Segun los flags activos de la empresa, el launcher puede mostrar mas de un entorno:

- `Entorno Operativo de Tiendas`: circuito comercial web y operativo tradicional;
- `Entorno Operativo de CRM`: base comercial separada para crecer sin mezclar datos con Tiendas.

La regla general es simple:

1. entras a un modulo;
2. buscas el registro o situacion que te interesa;
3. revisas el dato;
4. actuas;
5. confirmas que el sistema haya mostrado mensaje de exito o error.

## Preguntas que esta ayuda tiene que responder siempre

Cada modulo nuevo debe quedar explicado de manera que un usuario no tecnico pueda responder estas preguntas:

- para que sirve este modulo;
- cuando conviene usarlo;
- que datos veo en pantalla;
- que hace cada boton importante;
- que significa cada estado visible;
- que errores comunes pueden aparecer;
- que revisar primero antes de pedir soporte.

## Modulos actuales

### Entorno Operativo de CRM
- Arranca con una base corta para no sobrecargar el proyecto antes de tiempo.
- Hoy expone `Configuracion` y `Articulos CRM`.
- La configuracion reutiliza temporalmente la consola general del tenant.
- Los articulos CRM se guardan en tablas separadas de Tiendas.

#### Dudas comunes
- si no ves la tarjeta CRM en el launcher, revisar que el tenant este activo y que el flag `CRM` este encendido;
- si el listado de articulos CRM aparece vacio, no significa error: esta base empieza limpia y no comparte datos con Tiendas.

### Catalogo de Articulos
- Administra el catalogo visible y vendible de la tienda.
- Permite controlar nombre, descripcion, precios, stock e imagenes.
- Incluye sincronizaciones con fuentes externas.

#### Botones importantes
- `Sync Articulos`: trae o actualiza el maestro de productos.
- `Sync Precios`: actualiza importes comerciales.
- `Sync Stock`: refresca existencias.
- `Sync Total`: ejecuta una cadena mas amplia de sincronizacion.
- `Purgar Todo`: borra el catalogo local; debe explicarse siempre como accion delicada.

#### Dudas comunes
- si un articulo no aparece, revisar sincronizacion, estado activo y datos minimos;
- si el precio no coincide, revisar sincronizacion de precios;
- si el stock parece raro, revisar sincronizacion de stock.

### Clientes Web
- Reune clientes registrados o utilizados en compras.
- Permite editar datos de contacto y revisar el vinculo comercial.

#### Dudas comunes
- si un pedido falla, revisar si el cliente esta bien vinculado;
- si falta informacion comercial, revisar documento, email y codigo Tango;
- si el cliente existe pero no opera bien, validar nuevamente su relacion comercial.

### Pedidos Web
- Permite revisar pedidos ingresados desde la tienda.
- Muestra si estan pendientes, enviados o con error.

#### Estados minimos a explicar
- `pendiente`: existe localmente pero aun no termino correctamente el circuito;
- `enviado`: fue aceptado en el sistema comercial;
- `error`: hubo rechazo o problema y conviene revisar el motivo.

#### Dudas comunes
- un error no siempre implica problema en el pedido: puede venir por cliente, articulo o configuracion;
- reprocesar sirve despues de corregir la causa del problema.

### Administrar Cuentas
- Gestiona accesos internos al entorno operativo.
- Permite crear, editar, activar o desactivar usuarios.

#### Conceptos basicos
- `activo`: puede ingresar;
- `inactivo`: la cuenta existe pero queda frenada;
- `administrador`: tiene mas permisos dentro del tenant.

### Notas Internas (Bitácora)
- Funciona como un cuaderno de operador para la empresa, transversal al sistema.
- Sirve para dejar registros en texto plano, novedades, seguimientos o aclaraciones comerciales de CRM.
- Cuenta con buscador propio y sincronización.

#### Dudas comunes
- Útil cuando tenés que registrar que un cliente "llamó y pidió que lo contactemos más tarde". 
- Todo lo anotado es interno, tu cliente no lo va a ver.

### Telefónica Anura (Llamadas)
- Registro directo y automático de todas las llamadas entrantes que llegan a los internos de tu empresa vía central Anura.
- No hace falta cargar nada a mano: las llamadas caen solas y se dividen automáticamente para la empresa correcta (Multi-Tenant).

#### Dudas comunes
- **Si no ves una llamada:** puede que la llamada no haya ingresado al interno correcto, o que falte configurar el Webhook en tu portal de Anura.
- **Utilidad:** ideal para buscar quién nos llamó a una hora específica si no llegamos a atender.

### Configuracion
- Centraliza slug publico, branding, SMTP y Tango Connect.
- Es el punto de ajuste de identidad e integraciones de la empresa.

#### Dudas comunes
- si falla correo, revisar SMTP;
- si falla integracion, revisar Tango;
- si hay dudas con la direccion publica, revisar slug;
- si la tienda no refleja imagen o look esperado, revisar branding.

### Mi Perfil
- Ajusta preferencias personales de visualizacion del panel.
- No modifica la operacion comercial general de la empresa.

## Regla de busqueda en listados y atajos nuevos

### Buscador rápido y Atajos
- el usuario escribe primero;
- el sistema puede sugerir hasta tres coincidencias;
- elegir sugerencia completa el texto pero no filtra aun;
- el filtro real se aplica con `Enter` o `Buscar` / `Aplicar`;
- `Limpiar filtros` vuelve al listado general.
- **Atajo global F3 o Barra ( / ):** En cualquier listado, presionar `F3` o `/` salta directo al buscador sin usar el mouse.
- **Navegar con teclado:** En el panel de control (Dashboard), al buscar un módulo, podés usar las **flechas del teclado** para moverte entre las tarjetas y apretar `Enter` para ingresar.

## Novedades Operativas Recientes (¡Para aprovechar!)

### 1. Operación Multi-Empresa rápida
Si administras más de una empresa, en tu panel principal ahora vas a ver el botón **"Ingresar"** en cada tarjeta de empresa. Si le haces clic, el sistema "se pone el sombrero" de esa empresa: todo lo que hagas a partir de ahí (pedidos, configuración, clientes) será de esa empresa, sin tener que salir y volver a iniciar sesión.

### 2. Pedidos de Servicio más seguros
- **Nuevo atajo `ALT + P`:** Sirve para enviar rápidamente el pedido a Tango.
- **Bloqueo de seguridad:** Cuando el pedido llega bien a Tango (y obtiene su número oficial), se bloquea. **Ya no se puede modificar** para evitar diferencias entre el sistema web y el comercial.
- **Error API:** Si Tango rechaza el pedido, verás el estado `ERROR API` y vas a tener a mano el botón para intentar reenviarlo luego de arreglar el problema.

### 3. Seguridad de Sesión (Importante)
Todo el sistema tiene nuevas reglas de seguridad. Si dejaste la pantalla abierta mucho tiempo, fuiste a comer y al volver querés guardar algo, puede que te de un error (tipo "Token expirado" o "Seguridad vencida"). Esto es normal y protege tus datos. **Solución:** Apretá `F5` para recargar la página y continuá tu trabajo.

### 4. Zoom en Formularios de Impresión
Para quienes arman los PDFs o reportes en el módulo de impresiones (PrintForms), el diseñador visual ahora tiene botones de **Zoom**, igual que un mapa: Acercar, Alejar y Ajustar Pantalla. Ya no hay que pelear más con barras de desplazamiento para ver cómo queda la hoja A4 completa.

## Regla de tono para ayudas futuras

La ayuda debe escribirse:

- en castellano simple;
- sin jerga innecesaria;
- explicando botones y estados como si el usuario no conociera el flujo interno;
- con ejemplos concretos cuando una accion suele prestarse a confusion;
- con foco en “que pasa si aprieto esto” y “que reviso si algo sale mal”.

## Regla de mantenimiento

Si se incorpora un modulo nuevo en el entorno operativo:
- agregar enlace a ayuda si corresponde;
- sumar descripcion funcional en este archivo;
- detallar botones, estados y dudas comunes;
- actualizar la vista de ayuda operativa visible desde el sistema;
- registrar el cambio en `docs/logs`.
