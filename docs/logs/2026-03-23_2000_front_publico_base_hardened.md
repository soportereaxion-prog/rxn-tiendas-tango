# Front Público Multiempresa y Carrito (Base Hardened)

## Fecha y Tema
2026-03-23 20:00 - Implementación Base del Frente Público, Modelado Multi-tienda por URL (slug) y Carrito de Compras en Backend.

## 1. Contexto y Auditoría Inicial
El panel de control (rutas admin y sistema operativo del ERP) estaban acoplados de tal forma que todo ruteo resolvía de manera genérica.
El proyecto exigía la **preparación del front store comercial** sin destrozar el panel transaccional, exigiendo que las URL dinámicas funcionaran como resolutores de empresas activas de manera encapsulada.
* **BD:** La tabla `empresas` no tenía forma de capturar la URL comercial unívoca.
* **Vistas:** Se requería escapar del molde de Bootstrap Admin tradicional y forjar un cascarón visual.
* **Caché:** Alto riesgo de consumo de IO en el catálogo público si escalaba súbitamente sin una capa ligera de resguardo.
* **Carrito:** Requiere snapshot de precios para congelarlos ante cambios del ERP, y aislamiento total por `empresa_id`.

## 2. Decisiones Arquitectónicas (Base Hardened)
### Resolución por Slug (`StoreResolver.php`)
Desarrollador un middleware `StoreResolver` a inyectar en las primeras líneas del controlador Store (`StoreController` / `CartController`). Valida contra la tabla `empresas` y rechaza tiendas inactivas o links caídos enviándolos a un genérico `error_tienda.php`.
Se adaptaron las Rutas de PHP al FINAL del array del Enrutador. Así la wildcard `/{slug}` funciona como último recurso del Router sin pisar ` /empresas ` ni ` /login `. Empleando esto mantenemos a raya el over-engineering.

### El PublicStoreContext (Objeto Central)
Se definió el Singleton `PublicStoreContext` en Ram para almacenar el ID y config de esa tienda. Evitamos pedirlo a la DB ininterrumpidamente durante todo el Request Flow.

### Caché Json Nivel de Disco
Implementamos un humilde pero ágil `App\Core\FileCache` con persistidor a `app/storage/cache`.
Nuestra `TangoSyncService` fue cableada para disparar `\App\Core\FileCache::clearPrefix("catalogo_empresa_{$empresaId}");` ante cada inserción limpia, previniendo latencias de precios obsoletos.

### Cart y Session Segregada
La sesión se tabicó usando `$_SESSION['cart'][ $empresa_id ]`. Esto garantiza de plano que si un cliente abre "nuestra-empresa" en una pestaña y "empresa-demo" en otra, jamás cruzará los objetos comprados. Se tomaron snapshots firmes de Precios L1 por defecto sobre la bolsa en caché.

## 3. Archivos Afectados (Core)
* [MOD] `app/config/routes.php` (Wildcard slug + routing del controller Store y Carrito)
* [MOD] `app/core/Router.php` y `FileCache.php`
* [MOD] `app/modules/Empresas/Empresa.php` & `EmpresaRepository.php` (Alteración de BD con nuevo `slug`)
* [NEW] `app/modules/Store/*` (Controllers, Resolver, UI Templates, Public Context y Cart Service).
* [MOD] `app/modules/Tango/Services/TangoSyncService.php` (Desacople de purge en caché iterativo).

## 4. Pruebas Realizadas
* Recreación masiva de Slugs por retro-compatibilidad sobre base local.
* Paginación en Catálogo público encriptada bajo MD5 file-cache en disco.
* Adición múltiple al carrito.
* Restricciones de seguridad por sesión empresarial activadas.

## 5. Riesgos Controlados Estrictamente
Se previno:
1. SQL injection en WildCards limpiando el slug estricto.
2. Intersección de empresas durante sesiones en simultaneo aislando la bolsa HTTP.
3. Desbordamiento de la BDD inyectando FileCache json temporal.
4. Desplome de la Admin UI enviando todo public-bound routes al final del dispatch chain.

## 6. Próximo Paso Lógico
Construcción de `CartService::prepareCheckout()` con pasarela de compra (MercadoPago / integraciones), o el Checkout Page Form nativo (pedidos).
