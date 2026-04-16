# Bitácora de Desarrollo - 2026-04-07 21:51

## Título: Estabilización de Modal UX, Mapeo Tango y Clonación de Presupuestos (v1.3.1)

## Qué se hizo
- **Sincronización a Tango (`ID_GVA81`)**: Se modificó el builder de carga útil (`Payload`) de Presupuestos CRM insertando la lógica para capturar y enviar el identificador Tango `ID_GVA81` en la cabecera del documento usando la propiedad `clasificacion_id_tango`.
- **Intercepción de Eventos Escape**: Se forzó en los modales y campos asíncronos (`Spotlight` y `Clasificación / Artículos`) el uso explícito de `event.preventDefault()` y `event.stopPropagation()` durante el uso de la tecla `Escape`.
- **Fidelidad de Clonación**: Se rehidrataron por medio del controlador (`PresupuestoController`) las métricas tipo *Snapshot* (`_nombre_snapshot`) extraídas de la consulta en Base de Datos original que no eran pasadas al arreglo general de hidratación, asegurando que el comando "Copiar Presupuesto" entregue el estado intacto visual y de texto hacia la vista de clonación en el Front-End.

## Por qué
- **Pérdida de Sincronización API**: La integración con Tango Connect estaba rebotando cargas operativas (presupuestos procesados) arrojando falsos errores por falta del código "clasificación" obligatoria (`ID_GVA81`).
- **Problema de Enfoque Teclado (Ghost Modal)**: La ventana de salida global del sistema interceptaba cualquier `Escape` libre perdiendo el flujo e instando al operador a aceptar/rechazar salida general cada vez que querían deshacer una elección de búsqueda parcial asíncrona.
- **Detrimento Operativo**: Administradores que duplicaban presupuestos para uso temporal reportaron perder la consistencia descriptiva ("no me lleva todos los campos" -> perdía las descripciones estáticas base relativas a IDs internos al recargar la vista generada con `BuildFormState`).

## Impacto
Estos aportes impactan fundamentalmente en la Experiencia de Usuario sin generar un quiebre o alteración estructural en bases de datos más allá del aprovechamiento óptimo de `Payload`. 
La funcionalidad de clonar retoma la confiabilidad comercial total, unificando Presupuestos con la política operativa pre-existente en Pedidos de Servicio (PDS) de Tango (el mapeo de `ID_GVA81` responde 1:1 al pipeline de PDS).

## Decisiones Tomadas
- Se prefirió retener la arquitectura base y agregar las excepciones de interrupción de eventos directamente a los inputs en JS para proteger los escuchas globales del documento, sin refactorizar por completo el `rxn-shortcuts.js`.
- Los fallbacks de valores temporales (e.g., `_nombre_snapshot`) en la vista de carga pasan directamente por extracción tolerada (`?? ''`) durante la inyección en array, evadiendo una carga superflua iterativa en BBDD.
- Las adiciones se registran en `app/config/version.php` como **v1.3.1**.
