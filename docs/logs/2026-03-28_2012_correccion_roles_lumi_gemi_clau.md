# Corrección de roles internos Lumi / Gemi / Clau

## Fecha y tema
2026-03-28 20:12 - Ajuste final de la jerarquía interna para alinearla con la definición operativa real.

## Que se hizo
- Se corrigió `AGENTS.md` raíz para dejar el esquema real: `Lumi` interpreta y delega, `Gemi` valida con Lumi y `Clau` ejecuta todo el código.
- Se corrigieron los `AGENTS.md` modulares que habían quedado con una jerarquía equivocada que desplazaba a Lumi del rol de primera interfaz.
- Se ajustó `docs/estado/current.md` y se corrigió el log previo de jerarquía para evitar que quede información operativa errónea en markdowns activos.

## Por que
- La definición correcta ya estaba clara operativamente, pero la documentación había quedado descajetada después del ajuste anterior.
- Hacía falta que todos los markdowns vigentes reflejen el reparto real de funciones para no inducir a errores en iteraciones futuras.

## Impacto
- Queda asentado que OpenCode opera como `Lumi` en este proyecto.
- `Gemi` deja documentado su rol de validación e intercambio con Lumi.
- `Clau` queda asentada como ejecutora Senior y única responsable del trabajo de código dentro del esquema interno.

## Decisiones tomadas
- Se corrigieron los markdowns activos en lugar de dejar convivir dos jerarquías incompatibles.
- Los logs históricos funcionales no se reescriben salvo cuando contienen una definición operativa incorrecta que pueda contaminar iteraciones futuras.
