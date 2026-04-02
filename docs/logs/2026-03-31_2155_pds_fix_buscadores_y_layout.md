# 2026-03-31 21:55 - Reparacion Buscadores y Layout PDS

## Qué se hizo
1. Se reparó el buscador de Clientes y Artículos en el formulario PDS (Pedido de Servicio).
2. Se reestructuró la capa visual del formulario PDS usando una cuadrícula nativa `.crm-col-` limitando a 16 columnas.
3. Se aplicaron estilos agnósticos a los chipsets del módulo para resistir Dark Mode nativo `.bg-body-tertiary` sin perder impacto estético.
4. Se agregó la Action Bar completa superior (`Copiar`, `Enviar por Mail`, `Imprimir`, `Formulario`).

## Por qué
El reset de las ramas había destruido la lógica de vinculación de variables (el motor de bases de datos de PDO crasheaba al reutilizar la variable `:term` más de una vez sin `PDO::ATTR_EMULATE_PREPARES` en los métodos de `PedidoServicioRepository`).
El usuario exigió el estilo visual oscuro exacto desde una screenshot provista para los bloques del formulario, por lo que el esquema se refactorizó para alojarse debajo de Diagnóstico en base al reporte de requisitos.

## Impacto
El formulario PDS ya puede resolver búsquedas AJAX correctamente para la relación Cliente-Artículo, además sus controles (Guardar, Descuento, etc) vuelven a gozar de un diseño estructurado por áreas en su panel oscuro con feedback directo en tiempo real. 

## Decisiones tomadas
No usar `background: linear-gradient(bright)` para las calculadoras/chipsets, usar background adaptativo al Dark Mode que asimila el diseño provisto por el usuario en la imagen. En lugar de bindings manuales para sugerencias asíncronas de base, optamos por usar bindings `:t1`, `:t2`, etc. para evadir la falla `Exception 2031` en el driver nativo de PDO local sin forzar reconfiguraciones en la base de datos instalada del cliente. 
