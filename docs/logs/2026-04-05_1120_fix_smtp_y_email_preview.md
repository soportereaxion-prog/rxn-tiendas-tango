# Fix bugs SMTP y Preview de Email (CSS Bleeding)

## Qué se hizo
1. Se corrigió un error en el renderizado del cuerpo alternativo de correos enviados por la plataforma (AltBody), eliminando por completo todo contenido alojado dentro de etiquetas `<style>` y `<script>`.
2. Se arreglaron los validadores AJAX de las credenciales de SMTP para que siempre capturen la información del formulario donde están interactuando los botones.

## Por qué
1. El cliente de correo del usuario (Thunderbird) extraía el código CSS renderizado para imprimir como PDF (`:root { color ... }`), y lo previsualizaba crudo en la bandeja de entrada o dejaba basura residual. Esto sucedía porque `strip_tags()` dejaba vivo el contenido *entre* las etiquetas eliminadas (el propio CSS).
2. Con la implementación del buscador unificado de Dashboard, la invocación de `document.querySelector('form')` pasaba a capturar por omisión un `<form>` antes del de configuración en la cabecera. Eso causaba que el endpoint SMTP asuma "Host vacío" o deniegue la prueba a pesar de tener los datos cargados en pantalla.

## Impacto
- Mejora en la visualización e interacciones de Correos de PDS, evitando previews técnicos poco profesionales para el cliente.
- Vuelta a operación del botoncito "Probar conexión", "Probar SMTP" y "Validar Conexión" de Connect.

## Decisiones tomadas
- Se sustituyó `document.querySelector('form')` por el escalamiento desde el target `btn.closest('form')` que se usa más habitualmente al lidiar con vistas que tienen overlays paralelos.
- Se pre-procesó con regex el contenido HTML en la función `buildMailer` -> `$mail->AltBody` del core.
