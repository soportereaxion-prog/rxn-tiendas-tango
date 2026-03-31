# [PRINT/CANVAS] - Base persistente y editor visual A4

## Que se hizo
- Se implemento el nuevo modulo `Formularios de Impresion` dentro del entorno CRM con listado y editor visual propio.
- Se agrego persistencia local para definiciones, versiones y assets mediante las tablas `print_form_definitions`, `print_form_versions` y `print_form_assets`, creadas automaticamente desde el repositorio del modulo.
- Se incorporo un primer editor visual A4 con fondo configurable, objetos `text`, `variable`, `line` y `rect`, seleccion, drag, propiedades basicas y versionado por guardado.
- Se creo un registro inicial de documentos y variables para `crm_presupuesto`, dejandolo como primer consumidor real del motor.

## Por que
- El usuario pidio dejar de discutir el concepto y avanzar ya hacia el canvas que definira la impresion de la plataforma.
- Hacia falta materializar una base tecnica real para que el sistema no dependa de CrystalReports ni de soluciones ad-hoc por modulo.
- Tambien era necesario traducir la idea de "canvas" a una implementacion estable y printable, apoyada en hoja DOM versionable y no en un bitmap puro.

## Impacto
- CRM ahora expone un acceso real a `Formularios de Impresion` desde dashboard y desde `Presupuestos`.
- El sistema ya puede guardar versiones de un formulario imprimible A4 con imagen de fondo, fuentes controladas y objetos posicionados.
- `Presupuesto CRM` deja de tener solo una promesa documental y pasa a tener un motor base sobre el que luego podran montarse variables nuevas, repetidores y renderer final.

## Decisiones tomadas
- El motor nace como infraestructura transversal, aunque por ahora el editor visible se publique en CRM.
- Se mantuvo el criterio funcional de `canvas`, pero la implementacion base se resuelve como hoja HTML/DOM editable y persistida en JSON estructurado.
- El v1 del editor prioriza fondo, texto, variables y dibujo controlado; se posterga `table_repeater`, PDF y formulas libres para una etapa posterior.
- `crm_presupuesto` queda como primer documento registrado en `PrintFormRegistry`, evitando inventar un sistema aparte dentro del modulo de presupuestos.
