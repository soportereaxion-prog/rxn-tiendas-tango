# [CONFIGURACIÓN] — Selectores Dinámicos Tango Connect (Listas y Depósitos)

## Contexto y Problema
La cónsola de configuración de Empresa permitía la edición manual y "a ciegas" de atributos cruciales para el mapeo Tango (Lista Precio 1, Lista Precio 2, y Código de Depósito). Esta práctica elevaba dramáticamente la tasa de fallos de integración por errores tipográficos y desincronización de Identificadores Base (Ej: Tipear '00' analógico cuando la API pide ID Relacional '1'). La Jefatura de Proyecto determinó que la UI debía validarse lógicamente contra el servidor de Axoft antes de permitir la selección inteligente.

## Arquitectura de Solución
1. **API Endpoints (Backend)**: Se abrieron dos compuertas AJAX seguras en `EmpresaConfigController.php`:
   - `/mi-empresa/configuracion/test-tango`: Someta una prueba estructural de credenciales usando una petición ligera al endpoint real `process=87&top=1`. Retorna un OK limpio.
   - `/mi-empresa/configuracion/tango-metadata`: Si el test fue genuino, instancia un cliente interno que excava empíricamente las bases de Stock `process=17668` y Precios `process=20091` para mapear los identificadores de Depósitos (`ID_STA22 => DESCRIPCION_DEPOSITO`) y Listas de Precios.
2. **Interactividad UI (Frontend)**:
   - Los campos de Lista de Precio 1, 2 y Depósito fueron forzadamente mutados a barreras `<select>`.
   - Inicialmente **Cargan inactivos/bloqueados**. Si la B.D. poseía un valor histórico, carga una única opción ineludible `[Cód Actual Guardado: ID]`. Si no posee data, carga vacío. Esto **es intencionalmente renderizado sin atributo `disabled="disabled"`** para sortear los purgados `$_POST` predeterminados por el DOM. El usuario, efectivamente, NO puede seleccionar otra cosa.
   - Botón `Validar Conexión`: Ejecuta la prueba AJAX. Si Axoft reporta 200, el botón muta a diseño VERDE ✅ y levanta `tango-metadata`, inyectando cientos de `<option value="x">` dentro de los selectores, permitiendo ahora sí la libertad de configuración asistida.

## Impacto
Descartada estructuralmente la posibilidad de forjar IDs irreales. Interrumpido el volcado de dependencias rotas hacia el motor MySQL. Se protege el estado validado forzando un handshake real de AuthHeaders contra Axoft antes de renderizar la matriz.
