# Correcciones de PDS: Buscador de Artículos, Layout y Adjuntos en Correos

**Fecha:** 2026-03-31 22:55

## Modificaciones realizadas

1. **Reparación del Servicio de Envío de Mails (`MailService.php`)**
   - El sistema anterior de envíos llamaba a la interfaz `MailService::send()` con un quinto parámetro `array $attachments` en `DocumentMailerService`... PERO el método `send` interno de `MailService` jamás recibía ni procesaba el array (sólo pedía destino, asunto, cuerpo y empresa).
   - Se modificó la interfaz para aceptar `array $attachments = []`.
   - Se añadió la lógica de PHPMailer `addStringAttachment` (para iterar los PDFs guardados en memoria o `$pdfContent`) y `addAttachment` (para adjuntar las imágenes de Diagnóstico desde disco local mapeando la ruta web a la raíz `/public`).

2. **Resolución de Error Silencioso en Búsqueda de Artículos (`PedidoServicioRepository.php`)**
   - El input **Artículos** del PDS se quedaba vacío esperando respuestas porque el query `findArticleSuggestions` usaba la condición `OR tags LIKE ...`.
   - La tabla `crm_articulos` (ni `articulos` base) posee la columna `tags`. El error silencioso resultaba en cero registros enviados a UI.
   - Eliminamos ese operador de la query y regularizamos el mapeo frontal (`PedidoServicioController::articleSuggestions`) para que renderice los ítems igual que el módulo Artículos.

3. **Layout UI (`form.php`)**
   - Se aplanó el menú de botones del `action-bar` combinando flex con layout en una sola fila (`justify-content-end`, `flex-wrap`).
   - Se comprimió el margen entre el encabezado operativo y detalle técnico a `mb-3`.
   - El checkbox de atajo de hora `Ahora` se modificó encapsulando el texto como tool-tip (atributo `title` nativo y CSS).

## Impacto
PDS es capaz de enviar correos con total funcionalidad (texto rico, PDF adjunto nativo generado on-the-fly, las imágenes del diagnóstico pegadas vía file system y autocompletar la carga inicial de sus artículos sin freno de SQL). 

## Acciones dependientes
Liberación de versión 1.1.44 (App/Config/version.php).
