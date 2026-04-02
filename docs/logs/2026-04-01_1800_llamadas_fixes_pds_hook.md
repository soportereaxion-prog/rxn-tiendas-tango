# Integración Anura Call Processing y PDS (Correcciones y Mejoras)

## Qué se hizo
1. **Solución a Búsqueda con PDO:** Se corrigió un error en el repositorio de llamadas (`CrmLlamadaRepository`) donde se utilizaba el mismo `named parameter` múltiple veces (`:search`), lo cual causaba una excepción de MySQL. Se reemplazaron por `:search1`, `:search2`, etc.
2. **Formato UI Duración:** Se formatearon los segundos devueltos por Anura en la vista a un formato `H:i:s` o equivalente más amigable.
3. **Mapeo de Inicio y Fin PDS:** Se agregaron variables GET `inicio` y `fin` al botón de generación de PDS en la vista, tomando el inicio real y calculando el fin basado en la duración en segundos que reporta el webhook. En `PedidoServicioController`, estos datos hidratan por defecto al formulario.
4. **Validación de Usuario-Interno:** Se agregó lógica estricta con modal de error donde el sistema verifica que la llamada que se intenta asociar a PDS pertenezca a la extensión (`anura_interno`) del propio usuario que lo clickea.
5. **Endpoint de Emulación Webhook:** Se creó una ruta de prueba `GET /api/webhooks/anura/test?interno=100` que construye un payload mockeado para inserción de test.

## Por qué
Para afianzar y dejar productiva y segura la lectura de llamadas con posibilidad de derivarlas a Pedidos de Servicio de forma consistente.

## Impacto
Se estabiliza el módulo con una UX correcta sin posibilidad de falsear información cruzando llamadas de otros usuarios.
