# 2026-04-08_1748 - Documentación de contexto (RxnSync)

## Qué se hizo
Se auditó y documentó integralmente el contexto operativo y técnico del módulo `RxnSync` creando y poblando el archivo `app/modules/RxnSync/MODULE_CONTEXT.md` a pedido del usuario.

## Por qué
Para asegurar que futuras iteraciones sobre este módulo -o módulos subyacentes que dependan de él como `Artículos CRM` y `Clientes CRM`- posean una referencia clara de las responsabilidades, acoplamientos, y heurísticas adoptadas evitando así romper piezas críticas.

## Impacto
Se generó el archivo de contexto. No hubo alteración del código productivo en sí.

## Decisiones tomadas
1. Se analizaron directamente `RxnSyncController.php` y `RxnSyncService.php` recabando el patrón de `Shadow Copy`, `Match Suave` en los `Push/Pull` y la lógica de validación whitelist mediante mb_substr.
2. Se documentó la dependencia latente del `Match Suave` (Auditoría e inicialización visual) donde la paginación a Tango Connect actualmente se limita a una página de `pageSize = 500`. Esto quedó expuesto como posible deuda técnica y riesgo de cara a catálogos más extensos.
3. Quedó documentado explícitamente qué se debe hacer obligatoriamente a modo de checklist luego de cada modificación (validación asíncrona robusta y confirmación exhaustiva sobre la estructura JSON del payload Push para no destruir información en Tango).
