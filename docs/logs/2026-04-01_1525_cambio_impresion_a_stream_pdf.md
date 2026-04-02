# Cambio de Arquitectura de Impresion a PDF Nativo

- **Qué se hizo**: Se modificó el comportamiento del endpoint `/imprimir` tanto para *Presupuestos* como para *Pedidos de Servicio* (PDS). En lugar de renderizar una vista HTML en el navegador usando el canvas y un toolbar propio (`window.print()`), el controlador ahora genera un documento PDF nativo usando `Dompdf` y lo envía en *stream* directo al navegador.
- **Por qué**: 
  1. El diseño del navegador estaba siendo constantemente corrompido por extensiones de usuario (Dark Reader) o temas globales (UI oscura de Vibe), lo que generaba fricción operativa.
  2. El PDF generado para enviar por correo y la vista previa web no eran idénticos. Unificar la lógica "Lo que imprime es lo que se envía" elimina sorpresas al usuario.
- **Impacto**: El botón "Imprimir" ahora abre (o descarga, dependiendo del navegador) un PDF perfecto, inmune a dark modes, y asegurando fidelidad 1:1 con los documentos adjuntos en los correos electrónicos. 
- **Decisiones tomadas**: En ambos controladores (`PresupuestoController` y `PedidoServicioController`) se consumió la configuración de *Canvas PDF* específica de la empresa (`EmpresaConfigRepository`) logrando que si el cliente configuró un template para emails, este template también prime al apretar "Imprimir".
