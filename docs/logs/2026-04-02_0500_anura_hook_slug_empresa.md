# Modificación a Webhook de Anura para Registro Universal por Slug

## Qué se hizo
Se actualizó el mecanismo del endpoint del Webhook de Anura para que ya no descarte las llamadas no reclamadas, alineándose con el requerimiento de resguardar todos los registros telefónicos independientemente de la asignación del interno.

- La ruta del webhook cambió de `/api/webhooks/anura` a `/api/webhooks/anura/{slug}`.
- La ruta de test cambió de `/api/webhooks/anura/test` a `/api/webhooks/anura/{slug}/test`.
- En `WebhookController->handleAnura($slug)` ahora se lee la URL para deducir el `empresa_id` resolviendo el `$slug`.
- Si Anura manda un `terminal` extraño, no registrado o directamente sin terminal, la llamada no se ignora, sino que se inyecta en `crm_llamadas` de la empresa deducida usando `usuario_id = NULL`.

## Por qué
Para garantizar el concepto de "caja negra/registro duro" de PBX, donde ninguna llamada de la central se pierde por una falta local de mapeo de usuario o por errores de tipeo de extensiones. El uso del `slug` en el Webhook unifica las llamadas de Anura dentro de arquitecturas multi-tenant sin que el payload nativo del proveedor necesite saber el ID físico del tenant.

## Impacto
Cualquier llamada queda logeada. Si no tiene interno o si no hay usuario que lo reclame, la llamada le aparecerá en el dashboard general de esa empresa y dirá "Unknown/Desconocido" en la columna "Atendió". Desde allí podrán al menos conocer qué tráfico o duración tuvo el cliente.
