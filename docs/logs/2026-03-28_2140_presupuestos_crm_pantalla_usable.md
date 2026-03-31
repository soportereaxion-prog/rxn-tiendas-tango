# [CRM/PRESUPUESTOS] - Pantalla usable v1

## Que se hizo
- Se implemento el nuevo modulo `Presupuestos CRM` con rutas propias, tarjeta en dashboard y vistas de listado/alta/edicion.
- Se agrego persistencia local para cabecera y renglones mediante `crm_presupuestos` y `crm_presupuesto_items`, creadas automaticamente desde el repositorio del modulo.
- Se sumo cache local de catalogos comerciales por empresa en `crm_catalogo_comercial_items` para depositos, condiciones, listas, vendedores y transportes.
- La pantalla de alta/edicion ahora permite seleccionar cliente CRM, autocompletar defaults comerciales y acumular renglones buscando articulos por codigo o descripcion.
- Los importes y totales se recalculan en backend al guardar, aunque la UI muestre una previsualizacion operativa en vivo.

## Por que
- El usuario pidio dejar de planificar y avanzar hacia una pantalla de presupuestos realmente usable dentro del CRM.
- Hacia falta materializar una primera base operativa antes de atacar impresion tipo Crystal, canvas A4 o versionado documental.
- Tambien era necesario evitar que el navegador dependiera de consultas remotas interactivas para cada selector comercial.

## Impacto
- Un tenant con CRM activo ya puede entrar a `Presupuestos` desde el dashboard CRM y trabajar una cabecera comercial con detalle por renglones.
- Seleccionar un cliente CRM ahora completa condicion, lista, vendedor y transporte apoyandose en cache local y defaults comerciales guardados.
- El modulo conserva snapshots de cliente, cabecera y renglones para proteger historicos aunque cambie la base CRM mas adelante.
- Si no existe pricing fino por lista, el presupuesto sigue siendo usable porque el renglon puede operar con precio manual/fallback.

## Decisiones tomadas
- Se descarto el concepto de `carrito` para este modulo; el cuerpo se resuelve como tabla de renglones editables.
- Se mantuvo el principio `local-first`: el browser habla solo con endpoints internos y la cache comercial se refresca por backend.
- Se dejo `crm_articulo_precios` preparada como tabla soporte para pricing por lista, pero sin bloquear el modulo si todavia no esta relevada ni poblada.
- La impresion, PDF, canvas A4 y `Nueva Version` se postergan hasta estabilizar esta base operativa del presupuesto.
