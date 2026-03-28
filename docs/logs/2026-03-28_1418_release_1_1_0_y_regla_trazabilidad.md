# Release 1.1.0 y refuerzo de trazabilidad por iteracion

## Fecha y tema
2026-03-28 14:18 - Actualizacion de release visible y ajuste de reglas de cierre documental.

## Que se hizo
- Se actualizo `app/config/version.php` para publicar la release `1.1.0` build `20260328.2`.
- Se resumieron en la release visible los cambios acumulados mas relevantes: categorias en Store, split Tiendas/CRM, configuracion CRM propia y Pedidos de Servicio CRM.
- Se actualizo `AGENTS.md` para reflejar el estado operativo actual y para exigir sincronizacion entre `docs/logs`, `docs/estado/current.md` y `app/config/version.php` en cada iteracion relevante.

## Por que
- La version visible no se estaba moviendo porque `VersionService` depende exclusivamente de `app/config/version.php` y ese archivo habia quedado clavado en la release inicial.
- La trazabilidad documental ya existia, pero faltaba una regla explicita para cerrar tambien la release publicada cuando el cambio es funcional o visible.

## Impacto
- Los dashboards y el launcher ya pueden mostrar una release alineada con el estado real del sistema.
- Queda formalizado en la guia operativa que cada iteracion relevante debe revisar no solo logs sino tambien estado actual y release visible.

## Decisiones tomadas
- Se eligio bump a `1.1.0` por acumulacion de funcionalidad visible y separacion operativa entre entornos.
- `app/config/version.php` se mantiene como fuente unica de verdad para la release publicada.
- `docs/logs` sigue siendo la traza tecnica detallada y `docs/estado/current.md` el tablero curado del estado del proyecto.
