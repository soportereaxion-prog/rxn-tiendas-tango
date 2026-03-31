# 2026-03-30 18:30 - Automatización de Envío de Mails para Documentos (Presupuestos & PDS)

## Qué se hizo
Se completó la implementación del envío automatizado de documentos (Presupuestos y Pedidos de Servicio) por correo electrónico.
1. Se añadió el botón "Enviar por mail" en la vista de edición de `CrmPresupuestos` y `CrmPedidosServicio`.
2. Se implementaron los endpoints `POST /mi-empresa/crm/presupuestos/{id}/enviar-correo` y `POST /mi-empresa/crm/pedidos-servicio/{id}/enviar-correo` en `routes.php`.
3. Se integraron los controladores correspondientes vinculando el `DocumentMailerService` previamente creado con `DomPDF` y motor de plantillas `PrintForms`.
4. El envío extrae la información de contexto de variables, renderiza el cuerpo HTML y el PDF adjunto utilizando `renderToString()`, valida que el cliente posea mail cargado y despacha utilizando la configuración SMTP de Empresa.

## Por qué
Esto cumple con el requerimiento de permitir a los operadores enviar ágilmente un documento a los clientes post-creación, consolidando la descentralización de la suite CRM, aportando autonomía y automatización con las planillas (canvas) estandarizadas diseñadas previamente.

## Impacto
El sistema interactúa de forma activa como despachante de correo electrónico, generando PDFs on-the-fly de uso local en memoria sin persistir los binarios para economizar almacenamiento.

## Decisiones tomadas
- Se valida en el frontend (UI confirmación mediante un sweetalert-like) y back-office la existencia de la cuenta de correo por parte del cliente antes del despacho.
- Las rutas de correo se separaron explícitamente de las rutas genéricas de guardado (`update`) para mantener controladores de responsabilidad única para esta acción.
- El PDF adjunto al correo es generado con DomPDF y adjuntado programáticamente vía `MailService` modificada para soportar content type string sin base64 en memoria (`data: application/pdf`).

## Actualizaciones Posteriores (Refinamiento UI/UX)
- **Registro de Plantillas Email:** Se incluyeron `crm_presupuesto_email` y `crm_pds_email` en el `PrintFormRegistry` para proveer un punto de partida para los cuerpos de correo. Esto resolvió la confusión de los `<select>` de "Configuración de Empresa", aclarando que una plantilla debe ser instanciada primero en el módulo de Formularios (y guardada en DB) para poder ser seleccionada.
- **Enlaces Dinámicos:** Se ajustó el botón "Ir a..." en la grilla de Formularios de Impresión (`views/index.php`) para redirigir dinámicamente al submódulo correspondiente (`presupuestos` o `pedidos-servicio`) según el `document_key`.
- **UX del Canvas de Impresión:** Se implementó una lógica de `redimensionamiento visual (resizer)` inyectada mediante JS (`public/js/print-forms-editor.js`). Ahora los elementos renderizados exponen un manejador en la esquina inferior derecha cuando están seleccionados, permitiendo modificar el Ancho (W) y Alto (H) con el ratón (`pointerdown` / `pointermove`), interactuando de forma nativa con el motor magnético de grilla (snap) existente.
