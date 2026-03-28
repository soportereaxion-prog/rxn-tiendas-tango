# [UX] - Estandar CRUD con sugerencias sin autofiltro

## Que se hizo
- Se elimino el autosubmit del buscador en `empresas`.
- Se incorporo un endpoint liviano de sugerencias parciales y un dropdown de ayuda que no modifica el CRUD hasta confirmar la busqueda.
- Se documento el patron en `docs/modules.md` para reutilizarlo en futuros listados.

## Por que
- El autofiltro por escritura puede escalar mal y vuelve menos predecible la operacion cuando hay volumen de registros.
- El objetivo es asistir al operador con coincidencias parciales sin disparar recargas ni filtrar el listado antes de tiempo.

## Impacto
- El buscador de empresas ahora sugiere hasta tres coincidencias mientras se escribe.
- El listado solo se filtra al presionar `Enter` o `Aplicar`.
- Queda definido un estandar reutilizable para CRUDs del sistema.
- Se separa el valor del input editable del valor confirmado que realmente filtra el CRUD.

## Decisiones tomadas
- Se mantuvo el enfoque simple: server-rendered para el listado y endpoint minimo JSON solo para sugerencias.
- Se dejo el patron desacoplado para poder replicarlo luego en usuarios, articulos, clientes u otros modulos.
