# Iteración Corta: Fix de Dependencias Prematuras en Enrutador

## Fecha y Tema
2026-03-24 04:35 - Reparación arquitectónica de instancias (Lazy Loading) en el Router de la aplicación.

## 1. El Problema (Crash en el Front)
Se detectó un `"Fatal error: Uncaught ConfigurationException: Contexto multiempresa cerrado. No se puede instanciar el tunel Tango."` al intentar acceder a la vista pública de una tienda (ej: `/test-php`).
Al analizar la traza, se comprobó que el error era provocado durante la **declaración de las rutas** y no durante su **ejecución**.

En `app/config/routes.php`, los controladores operaban así:
`$router->get('/ruta', [new Controlador(), 'metodo']);`

Esto provocaba que **TODOS** los controladores y todos sus constructores internos (ej. repositorios y Singletons) se instanciaran de forma monolítica al cargar la configuración, consumiendo memoria y fallando si algún servicio inyectado requería un ID de sesión (como el Sync).

## 2. Solución (Lazy Loading)
- **`app/core/Router.php`:** Se modificó `get()` y `post()` para que acepten `array|callable`. Se inyectó el Closure interno `$resolveHandler` en el método `dispatch()`. Este Closure analiza si el handler es un array referencial (ej: `[Controller::class, 'method']`), y usa reflection/new operator sobre la marcha (Lazy Load) EXCLUSIVAMENTE sobre la URL requerida tras hacer match.
- **`app/config/routes.php`:** Se reemplazaron todas las sintaxis de `new Controlador()` por `Controlador::class`.

## 3. Impacto Técnico
- **No más crashes cruzados:** El `TangoService` jamás se encenderá a menos que efectivamente se emita una petición a un Endpoint que lo llame.
- **Micro-optimización pasiva:** Aumenta severamente la velocidad de procesamiento del FrontController, bajando el I/O por no tener que cargar N clases en la memoria del runtime web a la espera del Dispatch.

## 4. Próxima Etapa
Las operaciones básicas comerciales están firmes, no hay más filtraciones en las capas del FrontController. Procedimiento a CartCheckout disponible.
