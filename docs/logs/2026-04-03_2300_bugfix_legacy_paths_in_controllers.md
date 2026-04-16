# Depuración de Rutas Hardcodeadas Legacy

**Fecha**: 2026-04-03
**Módulos afectados**: CRM (Pedidos de Servicio, Presupuestos) y PrintForms

## Qué se hizo
1. Se depuraron instancias de `/rxnTiendasIA/public` ubicadas en los controladores `PedidoServicioController.php` y `PresupuestoController.php`.
2. Se corrigió un `str_replace` erróneo en guardado de adjuntos que intentaba inferir directorios inyectando la cadena legacy.
3. Se sanearon etiquetas HTML (`<title>`) y scripts estáticos en `PrintForms\views\document_render.php` que aún declaraban la antigua Base URL.

## Por qué
Durante la reestructuración de la plataforma hacia `rxn_suite`, todos los ruteadores y subdirectorios de despliegue principal en Plesk/Apache pasaron a apuntar a la raíz nativa (`/`). Sin embargo, los redirects nativos y construcciones de array context de controladores en el CRM quedaron congelados enviando explícitamente a `/rxnTiendasIA/public/...`.
Al accionar eventos tipo POST (ej: botón de Guardar PDS), el controlador procesaba y redirigía al path legacy, ocasionando un error 404 instantáneo, en vez de mantener al usuario operando en su local o servidor nativo `/mi-empresa/...`

## Impacto
El sistema recobra su agilidad de estado sin roturas de navegación pos-guardado, confirmando compatibilidad con la nueva topografía del Document Root.
