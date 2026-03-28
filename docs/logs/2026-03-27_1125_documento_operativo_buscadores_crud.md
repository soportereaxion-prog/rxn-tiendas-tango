# [Docs] - Documento operativo para buscadores CRUD

## Que se hizo
- Se creo un documento operativo dedicado para estandarizar buscadores CRUD y autosuggest del backoffice.
- Se dejo el criterio listo para extender a futuros modulos, incluido CRM.
- Se vinculo el estandar resumido existente con la nueva guia ampliada.

## Por que
- El patron ya se empezo a aplicar en `empresas` y necesitaba una bajada operativa clara para replicarlo sin improvisacion en cada modulo.

## Impacto
- El equipo ya tiene una referencia concreta para implementar buscadores consistentes en modulos actuales y futuros.
- Se reduce el riesgo de mezclar autofiltrado agresivo con sugerencias o de romper la experiencia entre pantallas.

## Decisiones tomadas
- Se mantuvo la estrategia simple del proyecto: CRUD server-rendered + endpoint minimo de sugerencias + whitelist de campos operativos.
