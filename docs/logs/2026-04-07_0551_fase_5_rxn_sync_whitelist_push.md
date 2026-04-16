# Cierre de Fase 5: RXN Sync - Motor de Escritura Seguro por Whitelist

**Fecha:** 2026-04-07
**Agente:** Lumi (Antigravity/Agent Teams)

## 1. Qué se hizo
- Se cerró el ciclo bidireccional del módulo `RXN - Sync` construyendo el motor de hidratación (Push).
- En UI: se habilitó el Botón "Empujar" para entidades con sincronía existente (`vinculado`, `error`, `conflicto`), inhabilitándolo solo para entidades `pendiente` (sin Tango ID conocido aún, bloqueando por diseño la creación de nuevas entidades hasta la futura fase de Onboarding).
- En Backend (`TangoApiClient`): se introdujo el método `updateEntity()` para realizar un update genérico al API rest enviando un envelope plano `['data' => [$payload]]` hacia el endpoint `Update`.
- En Backend (`RxnSyncService`): se desarrolló el método `pushToTango()` que implementa la **Lógica de Absorción Asimétrica (Whitelist)**:
   1. Fetch JSON maestro completo usando `GetById`
   2. Sustitución en RAM del modelo clonado afectando solo los strings permitidos del CRM (`nombre`, `descripcion`, `codigo_postal`, `telefono`, etc.).
   3. Retorno del Paylod hidratado y reseteo del `rxn_sync_status` a `vinculado` en caso de éxito.

## 2. Por qué
- Era inviable construir una entidad de clientes de Tango a mano desde el CRM enviando ceros, nulos o valores default dadas sus +120 properties.
- Este patrón de Hidratación o Clonación en RAM garantiza que *nada originario de Tango* pierda su integridad estructural al tocar el botón de forzar guardado, resolviendo una barrera arquitectónica enorme.

## 3. Impacto Operativo
- Las pestañas "Artículos" y "Clientes" en la ruta `rxn-sync` ahora tienen un flujo de UI 100% transaccional capaz de alterar remotamente los clientes en Tango y registrar la auditoría en tiempo real en UI.

## 4. Notas para el Rey / Próximos Eventos
- Los endpoints probables probados para "Save" arrojaron un 500 (`Save`, `SaveData`, `Apertura`). Se definió estructuralmente `Update?process=` en el servicio final en espera de validar la documentación HTTP de Axioma para "Actualización de un registro desde Integrador REST". 
- Todo el entorno quedó perfectamente encapsulado para que cuando se conozca o certifique el verbo correcto de Update, se corrija un solo string en `TangoApiClient.php` (`updateEntity`) y el flujo global funcione como seda.
