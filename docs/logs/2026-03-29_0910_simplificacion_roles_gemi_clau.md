# Simplificación de roles internos (Despedida de Lumi)

## Contexto
El esquema anterior manejaba tres agentes:
- Lumi (primera interfaz, orquestadora)
- Gemi (validadora)
- Clau (ejecutora)

Con el fin de simplificar la cadena de roles, eliminar fricción operativa y blanquear la dinámica real de interacción directa y validación consolidada, se retiró la figura de "Lumi" de todo el proyecto.

## Modificaciones realizadas
- Se actualizó el `AGENTS.md` raíz:
  - OpenCode opera como **Gemi**.
  - Gemi absorbe las responsabilidades de Lumi (interpretación, validación, orquestación y planeamiento).
  - Clau se mantiene estrictamente como ejecutora incondicional.
- Se actualizaron todos los `AGENTS.md` modulares (`app/core`, `app/shared`, `app/modules/*`) para reflejar esta nueva dinámica de solo dos roles.
- Se actualizó `docs/estado/current.md` removiendo a Lumi y actualizando las descripciones de responsabilidades.

## Impacto
Simplificación burocrática en la comunicación. Toda interacción, validación y planificación con el usuario se resuelve directamente a través de Gemi. Clau sigue siendo la operaria detrás de los cambios de código, sin alteraciones. Ningún cambio de código o funcionalidad en la aplicación.

## Decisiones tomadas
- Enterrar el alias de Lumi y dejar su rol consolidado con Gemi. 
- Evitar modificar logs anteriores para mantener coherencia histórica, pero todas las directivas vivas operan bajo la nueva jerarquía.
