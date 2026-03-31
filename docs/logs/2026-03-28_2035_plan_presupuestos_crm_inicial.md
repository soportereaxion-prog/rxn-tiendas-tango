# [CRM/PRESUPUESTOS] - Plan funcional inicial del modulo

## Que se hizo
- Se audito la base actual del CRM para identificar que piezas reutilizables ya existen para `Presupuestos`.
- Se documento un plan funcional inicial en `docs/crm_presupuestos_plan.md`.
- Se dejo asentado que la primera vuelta del modulo debe arrancar por estructura operativa y persistencia, no por impresion ni canvas documental.

## Por que
- Antes de construir el configurador tipo Crystal/A4 hacia falta cerrar que es exactamente un presupuesto dentro del CRM web.
- El usuario definio como cabecera minima: `Fecha`, `Cliente`, `Deposito`, `Condicion de venta`, `Transporte`, `Lista de precios` y `Vendedor`.
- Tambien quedo explicitado que el cuerpo se arma iterando renglones por busqueda de articulo (`codigo` o `descripcion`) y acumulando el detalle en pantalla.

## Impacto
- El proyecto ya tiene una definicion funcional base para arrancar `Presupuestos CRM` sin mezclarlo con la logica de Tiendas.
- Queda claro que el autocompletado al elegir cliente debe apoyarse primero en cache/local CRM y solo complementar con catalogos de integracion cuando haga falta.
- Se formaliza que la `Lista de precios` del presupuesto no depende de `precio_lista_1 / precio_lista_2` de Tiendas y requiere su propio relevamiento comercial.

## Decisiones tomadas
- `Presupuestos CRM` arranca como modulo operativo con cabecera + renglones + totales + snapshots.
- El concepto `carrito` queda descartado para este modulo.
- El versionado comercial avanzado y la impresion tipo Crystal quedan para una etapa posterior, una vez estabilizado el modulo base.
- Se recomienda empezar por tablas `crm_presupuestos` y `crm_presupuesto_items`, dejando la capa documental/canvas para despues.
