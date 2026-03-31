# Implementación PDS: Consumo al Vuelo de Perfiles Pedido ("tango_perfil_pedido")

## Qué se hizo
1.  **Resolver el Perfil del Usuario.** Se modificó `app/modules/CrmPedidosServicio/PedidoServicioTangoService.php`. Antes se instanciaba `TangoOrderHeaderResolver` utilizando únicamente la configuración global de la empresa (`$config`). Ahora, se sustituyó la llamada nativa por `resolveForCurrentContext($clientePayload)`, lo cual inyecta automáticamente el objeto temporal de autenticación `AuthService::getCurrentUser()` y recupera el alias o ID del perfil seleccionado por el administrador operando (`tango_perfil_pedido_id`).
2.  **Consumo estricto al vuelo.** Como decisión arquitectónica transitoria consensuada para pruebas contundentes, en `app/modules/Tango/Services/TangoOrderHeaderResolver.php`, se ha omitido (desactivado) el paso que busca en caché el `snapshot_json` (`getCachedProfileDetail()`). Ahora, ante cualquier llamado a resolutor, el conector invoca asíncronamente en API Real-Time el endpoint en Tango (vía `buildApiClient() -> getPerfilPedidoById($profileId)`).

## Por qué
Para independizar la creación de PDS basándose exclusivamente en qué usuario administrador es el emisor:
- El **usuario 1** podrá enviar pedidos que se rotularán en Tango como generados bajo su respectivo vendedor operativo de su perfil (`ID_GVA23 / COD_GVA23`) y encolados en su correlativo de talonario (`ID_GVA43_TALON_PED / TALONARIO_PEDIDO`).
- El **usuario 2** con un perfil `PED` instanciará su propio Vendedor y Talonario.
Esto flexibiliza el uso multi-tenant local con configuraciones a nivel "sucursal/operario".

## Impacto
*   **Payload a Tango:** En la depuración del payload JSON de Tango ahora se van a visualizar dinámicamente campos como `ID_GVA23` e `ID_GVA43_TALON_PED`.
*   **Latencia Web:** Dado que se anuló momentáneamente el caché de snapshot, habrá un hit al endpoint exterior de Tango adicional al construir el Payload de Pedido (PDS).

## Decisiones Tomadas
*   El consumo *al vuelo* queda documentado con el comentario explicativo temporal dentro del bloque desactivado de `fetchProfileDetail` (TangoOrderHeaderResolver.php).
*   Se corrobora de que `ID_GVA23_ENCABEZADO` y `ID_GVA43_TALONARIO_PEDIDO` son los mapeos fieles que proveen los perfiles de Tango con `process=20020`.
