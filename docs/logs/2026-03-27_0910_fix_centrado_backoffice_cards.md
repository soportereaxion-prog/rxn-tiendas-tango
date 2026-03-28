# [UI] - Centrado de tarjetas en RXN Backoffice

## Que se hizo
- Se ajusto el grid del dashboard `RXN Backoffice` para centrar horizontalmente sus tarjetas cuando no ocupan toda la fila.

## Por que
- El layout habia quedado desalineado hacia la izquierda, a diferencia del `Entorno Operativo`, generando inconsistencia visual entre ambos launchers.

## Impacto
- Las tarjetas del backoffice quedan centradas en escritorio manteniendo el comportamiento responsive de Bootstrap.

## Decisiones tomadas
- Se aplico unicamente `justify-content-center` sobre la fila de tarjetas en `app/modules/dashboard/views/admin_dashboard.php` para resolverlo con el cambio minimo.
