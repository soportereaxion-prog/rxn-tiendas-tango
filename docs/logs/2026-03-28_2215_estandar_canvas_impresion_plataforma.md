# [PRINT/CANVAS] - Estandar transversal de formularios de impresion

## Que se hizo
- Se definio un estandar funcional para la mecanica de `Canvas de impresion` en `docs/print_forms_canvas_standard.md`.
- Se documento el diseno tecnico base del motor en `docs/print_forms_canvas_tecnico.md`.
- Se enlazo esta definicion con la hoja de ruta de `Presupuestos CRM`, dejandolo como primer consumidor del sistema, pero no como dueño del motor.

## Por que
- El usuario pidio dejar estandarizada la mecanica de impresion de la plataforma antes de seguir agregando variables o documentos concretos.
- La necesidad ya no es solo imprimir presupuestos: se busca una base unica, reusable y evolutiva para cualquier formulario futuro.
- Tambien hacia falta alinear el lenguaje: para producto es un `canvas`, pero tecnicamente conviene resolverlo como una hoja DOM/HTML versionable y no como bitmap puro.

## Impacto
- El proyecto ya tiene una definicion oficial de como deben construirse los formularios de impresion en toda la plataforma.
- Queda claro que la futura experiencia A4 editable con fondo, fuentes y dibujo se apoyara en objetos posicionados, assets versionados y registro controlado de variables.
- `Presupuestos CRM` ya puede crecer hacia impresion sin inventar un sistema aparte ni mezclar concerns del modulo con la infraestructura documental.

## Decisiones tomadas
- La plataforma adopta `Definicion de formularios de impresion` como sistema transversal y no como detalle exclusivo de Presupuestos.
- Funcionalmente se lo llama `canvas`, pero el motor recomendado se implementa sobre DOM/HTML imprimible, no sobre un `<canvas>` raster como unica verdad.
- El v1 del editor debe arrancar con hoja A4, fondo, texto fijo, variables, imagen, linea, rectangulo, fuentes desde whitelist y versionado.
- El primer consumidor sera `crm_presupuesto`, pero la mecanica nace para servir tambien a futuros remitos, recibos, ordenes y comprobantes.
