# [API/UI] — Sanitización GVA21 Logs por Rol

## Problema Detectado
El módulo de "Pedidos Web" generaba un log en crudo (una tarjeta con pestañas para "Payload Enviado" y "Respuesta API") en donde se vertía el volcado literal del Request JSON hacia Axoft Tango Connect y la Response JSON devuelta desde la API. Esto era crucial para RXN Configuración Base (Master RXN) en la fase de homologación de las Tiendas. Contradecía, no obstante, las directrices UI/UX para un cajero/operador, exeponiéndolo a arrays en jerga técnica (`data -> errors -> propertyName -> descriptions`). 

## Solución Aplicada
Se refactorizó el modelo visual B2B apoyándonos en la jerarquía del rol del usuario conectado:
1. **Backend (Controller):** El controlador `PedidoWebController.php` absorbe la respuesta SQL `respuesta_tango`. Detecta de forma estricta si el usuario **NO** tiene credenciales `es_rxn_admin`. De ser así, somete el payload JSON a una travesía recursiva:
   - Apunta contra arrays anidadas (`data['messages']`, secundario asumiendo `$body[0]['description']`).
   - Extrae únicamente el Value String de la llave `description` y lo consolida en la variable `$cleanMessage`.
   - Ante errores críticos de red descarta el JSON y establece un flag literal `"Respuesta de red rechazada... "`.
2. **Frontend (View):** `show.php` aisla enteramente las pestañas interactivas de crudos (Payload / Response tabs) al uso exclusivo `$isGlobalAdmin`. En su lugar, construye una pastilla aséptica que procesa simplemente ❌ `Operación Rechazada. [Mensaje Saneado]` bajo la atenta mirada semántica de Bootstrap. Adicionalmente, sanea la cápsula perimetral `mensaje_error` roja tradicional.

## Impacto
Pérdida de exposición estructural del payload JSON de Tango Connect hacia operarios/managers del inquilino. Simplificación radical de la experiencia de validación de Errores de Facturación online para B2C e integración nativa y mantenida del sandbox de Debug para el equipo Master IT.
