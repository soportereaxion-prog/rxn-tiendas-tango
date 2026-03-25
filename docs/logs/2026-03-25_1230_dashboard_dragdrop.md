# [UI] — Dashboard Drag & Drop Personalizable

## Contexto
El Dashboard de inicio (B2B Admin) presentaba una lista estática de accesos directos hacia los distintos módulos operativos (Pedidos Web, Clientes Web, Catálogo, etc). Para mejorar la ergonomía del usuario administrativo, se solicitó refactorizar la botonera hacia un sistema de grilla/tarjetas reordenables dinámicas, conservando la usabilidad y logrando persistencia por sesión y base de datos de manera aislada al motor Tango.

## Problema
Los botones HTML estaban embebidos duramente en `home.php`. Esto forzaba una lectura descendente inmutable. No existían identificadores únicos (`data-id`) que permitieran relacionarlos posicionalmente, ni una estructura JSON en BDD que respaldara de forma limpia una matriz por cuenta usuaria.

## Decisión
- **Frontend Core:** Emplear la librería *Vanilla JS* `SortableJS` consumida por CDN para interceptar el grid y computar las físicas de arrastre con mínima carga del navegador (y sin invocar JQuery, React ni Angular).
- **Backend Schema:** Aprovechando que la tabla `usuarios` recién había incorporado preferencias B2B, sumamos una columna `dashboard_order` (formato TEXT) para alojar el JSON array proveniente de la UI.
- **Backend AJAX:** Montar un endpoint silencioso `POST /mi-perfil/dashboard-order` consumiendo raw `php://input` para confirmar las reordenaciones drag & drop en un solo paso hacia la BDD y la Sesión nativa.
- **Pre-render PHP:** Al recargar la portada, se invierte la lógica: en vez de forzar a JavaScript a reordenar los nodos DOM visiblemente y generar un parpadeo molestoso al usuario (FOUC), PHP recorre los Módulos del Sistema según el array persistente devolviendo el HTML preensamblado con la posición que determinaste en la sesión pasada.

## Archivos afectados
- `migrate_dashboard_order.php` 
- `app/modules/Auth/Usuario.php` (Binding Model Property)
- `app/modules/Auth/AuthService.php` (Lectura del Array hacia Super global `$_SESSION`)
- `app/config/routes.php` (Nuevo Endpoint AJAX POST)
- `app/modules/Usuarios/UsuarioPerfilController.php` (Receptor `guardarOrdenDashboard()`)
- `app/modules/dashboard/views/home.php` (Loop refactorizado con IDs, Cursor Grab, Script SortableJS onEnd)

## Implementación
1. Modificación de SQL de usuarios `ALTER TABLE usuarios ADD COLUMN dashboard_order...`.
2. Desarrollo del handler JSON para la request `fetch`.
3. Absorción de los Modulos B2B hardcodeados hacia el mapa estructural base `['pedidos_web' => '<a href...>', ...]`. El motor en `home.php` revisa qué prioridades tiene `$_SESSION['dashboard_order']` antes de pintarlos.

## Impacto
Menú de acceso administrativo totalmente líquido según los hábitos de click del Operador. Escalabilidad asegurada mediante un fallback natural inteligente: cualquier `item` nuevo provisto en el futuro sin registro del operador se pinta rutinariamente al final.

## Riesgos
- Ninguno detectable a nivel infraestructura. La abstracción CSS/DOM ha preservado totalmente el funcionamiento original del stack.

## Validación
- Comandos SQL aplicaron exitosamente y en el flujo real Sortable ejecuta el fetch XHR asincrónico tras el drop del bloque, con retornos `200 OK` por Controlador.
