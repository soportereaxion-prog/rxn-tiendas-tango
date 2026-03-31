# [PRINT/CANVAS] - Soporte para tablas repetitivas y preview de Presupuestos

## Que se hizo
- Se agrego soporte para el objeto `table_repeater` en el editor visual (`public/js/print-forms-editor.js`), permitiendo posicionar, dimensionar y ajustar estilos de tablas repetitivas iteradas desde una variable array (ej. `items[]`).
- Se configuro el registro `PrintFormRegistry` para incluir las columnas base del presupuesto en el array `default_objects`.
- Se creo el renderizador real del canvas en `app/modules/PrintForms/PrintFormRenderer.php` que transforma las definiciones JSON y el contexto de datos en un layout imprimible de DOM absoluto.
- Se vinculo la impresion en `app/modules/CrmPresupuestos/PresupuestoController.php` creando la accion `printPreview` que despacha el render HTML limpio (en `document_render.php`) ideal para PDF.
- Se implemento el eslabon faltante: `CrmPresupuestoPrintContextBuilder.php`, encargado de convertir el array de BD a variables estandarizadas, manejando los formatos de fecha, dinero y cantidad.
- Se agregaron los botones en la interfaz de Presupuestos para activar la impresion.

## Por que
- El editor de formularios nacio con variables simples, pero los presupuestos (y remitos futuros) necesitan iterar renglones, lo que se vuelve el punto de friccion central de cualquier sistema de impresion.
- Hacia falta cerrar el ciclo completo de la herramienta documental para probarla en vivo y entregarle valor al modulo recien nacido de CRM.

## Impacto
- El editor visual ahora tiene una tabla inyectable y ajustable.
- El usuario puede generar un PDF limpio y estandarizado sin depender de una infraestructura pesada.
- Todos los futuros documentos del sistema pueden reciclar este mismo builder de contexto y renderer.
