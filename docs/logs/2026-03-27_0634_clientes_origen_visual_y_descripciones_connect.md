# [CLIENTES + EMPRESA CONFIG] - Origen visual y descripciones legibles

## Que se hizo
- Se mejoro `app/modules/ClientesWeb/views/edit.php` para mostrar por cada relacion comercial si hoy coincide con Tango (`Heredado de Tango`) o si fue corregida desde la web (`Override web`).
- El panel lateral de `Clientes Web` ahora puede mostrar no solo el codigo guardado sino tambien la descripcion legible de condicion de venta, lista, vendedor y transporte cuando la metadata fue resuelta.
- Se amplio `ClienteWebController::obtenerMetadataTango()` para aceptar un `cliente_id_gva14` opcional y devolver tambien los defaults vivos del cliente en Tango, permitiendo comparar contra lo guardado localmente sin tocar schema.
- Se mejoro `app/modules/EmpresaConfig/views/index.php` para resolver automaticamente las descripciones de empresa, listas y deposito al abrir la pantalla, evitando que queden solo textos tipo `codigo actual guardado`.
- Los selects de EmpresaConfig ahora conservan fallback legacy si el valor guardado ya no existe en Connect, mostrando igual una opcion de referencia para no perder el dato al siguiente guardado.

## Por que
- El operador necesitaba contexto visual real: no alcanza con ver `1` o `2` si no se sabe que representan ni si vienen del maestro Tango o de una correccion manual.
- En Parametros de Empresa el comportamiento era confuso porque la descripcion solo aparecia al validar manualmente la conexion, aunque ya habia datos guardados.

## Impacto
- La edicion de Clientes Web ahora queda mucho mas explicita: se entiende que parte del encabezado del pedido fue heredada y que parte fue ajustada localmente.
- EmpresaConfig reduce friccion operativa al mostrar descripciones legibles apenas entra la pantalla, sin obligar al operador a repetir una validacion solo para interpretar valores guardados.
- No se agregaron columnas nuevas: la lectura de origen en Clientes Web se resuelve comparando contra el estado actual del cliente Tango vivo.

## Decisiones tomadas
- Se evito tocar esquema SQL para marcar origen por campo; en esta iteracion el estado visual se infiere comparando lo guardado contra los defaults actuales del cliente en Tango.
- En EmpresaConfig se privilegio una auto-hidratacion liviana por frontend reutilizando el endpoint existente, evitando acoplar el primer render server-side a Connect.
- Cuando un valor guardado no existe mas en los catalogos remotos, se conserva una opcion fallback `Valor guardado sin descripcion (...)` para no romper compatibilidad ni perder trazabilidad visual.
