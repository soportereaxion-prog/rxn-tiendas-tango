# Jerarquía operativa corregida en AGENTS y documentación

## Fecha y tema
2026-03-28 20:03 - Corrección documental de la jerarquía operativa interna usada dentro de OpenCode.

## Que se hizo
- Se actualizó `AGENTS.md` raíz para dejar explícito que OpenCode opera como `Lumi`, quien interpreta y delega.
- Se propagó la nota de jerarquía a los `AGENTS.md` de `core`, `shared`, `dashboard`, `empresas`, `pedidos`, `productos`, `clientes` y `auth`.
- Se completó además `app/shared/AGENTS.md` y `app/modules/dashboard/AGENTS.md`, que estaban vacíos o incompletos.
- Se actualizó `docs/estado/current.md` para dejar asentado el nuevo reparto operativo.

## Por que
- El esquema anterior no representaba la organización real entre Lumi, Gemi y Clau.
- Hacía falta dejarlo explícito en los markdowns de referencia para que futuras iteraciones no vuelvan a asumir un reparto obsoleto.

## Impacto
- La documentación operativa ahora refleja correctamente quién interpreta, quién valida y quién ejecuta.
- Queda asentado que `Gemi` valida con `Lumi` y que `Clau` ejecuta todo el código como ejecutora Senior.

## Decisiones tomadas
- Se preservó la jerarquía raíz en `AGENTS.md` como fuente principal y los AGENTS modulares remiten a esa definición.
- No se tocó `app/config/version.php` porque el cambio es documental/operativo interno y no funcional de producto.
