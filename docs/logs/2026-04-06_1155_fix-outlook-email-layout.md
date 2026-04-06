# 2026-04-06_1155 — Fix compatibilidad email Outlook: layout tabla en cuerpo de correo

## Qué se hizo

Se corrigió el desfase visual que mostraban los correos en clientes Outlook al recibir emails generados por el módulo PrintForms.

## Por qué

Outlook no renderiza HTML usando un motor de browser sino el motor de Word (WordprocessingML). Esto provoca que propiedades CSS como `position:absolute`, unidades `mm`, `object-fit` y `box-sizing` sean ignoradas completamente, colapsando todos los elementos en un flujo vertical desordenado que no se condice con el diseño del canvas.

## Impacto

- **Correos en Outlook**: el layout ahora usa tabla HTML (el estándar compatible con todos los clientes de correo incluyendo Outlook 2007-2021).
- **PDF adjunto**: **sin cambios**. Dompdf sigue usando `document_render.php` que mantiene el posicionamiento absoluto y funciona perfectamente.
- **Preview en browser**: **sin cambios**. `document_render.php` no fue modificado.
- **Canvas editor**: **sin cambios**.

## Decisiones tomadas

Se eligió la **Opción C** (template de email simplificado con extracción de tipografía del canvas) por sobre:

- Opción A (EmailRenderer completo con tabla posicionada) — más correcta pero mayor scope
- Opción B (imagen como cuerpo) — pérdida de texto seleccionable

La Opción C preserva:
- Tipografía configurada en el canvas (font-family, font-size en pt, color, font-weight, text-align)
- Dimensiones de imagen (w_mm → px a 96 DPI, con cap a 576px por container de 600px)
- Orden visual (objetos ordenados por y_mm)
- Tablas/repeaters del canvas (renderizadas como HTML table compatible con Outlook)
- **Fondo de email**: Inyección robusta de atributos html `bgcolor` y sub-tags `mso` para prevenir la inversión a negro que hacen por defecto los modos oscuros cuando un div es transparente.

## Archivos modificados

### `app/modules/PrintForms/PrintFormRenderer.php`
- Agregado `x_mm`, `y_mm`, `w_mm`, `h_mm` a todos los objetos renderizados (`renderTextLike`, `renderLine`, `renderRect`, `renderImage`, `renderTableRepeater`)
- Retrocompatible: los campos adicionales no afectan el renderer de browser/PDF

### `app/shared/Services/DocumentMailerService.php`
- `sendDocument()`: el body del email ahora llama a `renderCanvasToEmailHtml()` en lugar de `renderCanvasToHtml()`
- Agregado `renderCanvasToEmailHtml()`: fetches template y delega a `buildEmailBodyHtmlString()`
- Agregado `buildEmailBodyHtmlString()`: usa `email_render.php` en lugar de `document_render.php`

### `app/modules/PrintForms/views/email_render.php` [NUEVO]
- Template HTML para cuerpo de email
- DOCTYPE XHTML Transitional (máxima compatibilidad email)
- Layout basado en `<table>` (sin `position:absolute`, sin `mm`, sin `object-fit`)
- Objetos ordenados por `y_mm` para flujo vertical natural
- Tipografía extraída de `inner_style` del renderer (solo propiedades seguras)
- Imágenes con `width` en px respetando configuración del canvas
- Tablas/repeaters renderizados como HTML table con `border-collapse:collapse`
- Condicionales `<!--[if mso]>` para máxima compatibilidad con versiones viejas de Outlook
