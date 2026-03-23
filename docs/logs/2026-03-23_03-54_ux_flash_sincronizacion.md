# [User Experience] — [Implementación de Notificaciones Flash en Sincronización]

## Contexto
La funcionalidad Core de "Forzar Sync Connect" derivaba orgánicamente al usuario a una pantalla de inspección de Código JSON crudo emanado desde `TangoSyncController`. Esta arquitectura violaba los principios básicos de Experiencia de Usuario, requiriendo un botón "Back" nativo del navegador para regresar a la aplicación, lo que interrumpía abruptamente el flujo operativo regular. Jefatura ordenó abolir la entrega directa de JSON e instaurar un circuito de redirecciones enriquecidas con métricas operativas presentables.

## Solución Global: App\Core\Flash
* Se instrumentó un componente puro `Flash` dentro de `App\Core`, valiéndose del contenedor Superglobal `$_SESSION`.
* Permite almacenar diccionarios transitorios (Key: `flash_messages`) compuestos por `$type` (ej. `success`, `danger`), `$message` y el arreglo de `$stats`.
* Al ejecutar su método `get()`, auto-destruye permanentemente la variable en Sesión para garantizar un consumo de "Un Solo Uso" o Toast Alert behavior.

## Refactorización del Endpoint
* `TangoSyncController::syncArticulos()` fue re-enrutado abandonando los `echo json_encode(...)`.
* Alimenta el Session Container enviando `$stats` por intermedio de `Flash::set()`.
* Expulsa al Emisor con `header('Location: /mi-empresa/articulos')`.
* Tolerante a Faults: Un error 302/500 de la Nube será capturado y derivado como `Flash::set('danger'...)`.

## Integración Visual en el Catálogo
* El Formulario de `/mi-empresa/articulos/views/index.php` fue complementado con una capa superior que invoca estáticamente a `Flash::get()`.
* De existir un Mensaje Volátil, renderiza una caja flotante de alerta Bootstrap (Dismissible) evidenciando "Nuevos Localmente", "Omitidos", "Actualizados" o "Recibidos en Capa Red", ofreciendo certezas reales y depuradas sobre la ingesta HTTP.

## Pruebas y Riesgos
* Test de Extracción Vacía: Si el motor de la nube expulsa un array esteril, la Vista no estalla; simplemente alerta 0 items localmente ingresados.
* Acoplamiento de Vistas: Quedó fundado un Helper oficial para futuros cruds como Edición de Precios o Alta Manual de Listas, permitiendo estandarizar la forma en que los Controladores "hablan" con sus Clientes Front-End.
