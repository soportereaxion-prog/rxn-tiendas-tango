# [B2B/ADMIN] — Relación Explícita Usuario ↔ Empresa

## Problema Detectado
El sistema RXN Tiendas IA poseía la arquitectura lógica para separar a los usuarios por inquilino (`empresa_id`), sin embargo, carecía de una interfaz gráfica que permitiese vincular explícitamente a un usuario con una empresa en específico desde el panel. Adicionalmente, el Backend (UsuarioService) encerraba forzosamente la asignación de cuentas nuevas al ID del usuario creador, imposibilitando que un RXN Master (Global Admin) creara u operara usuarios de otros inquilinos.

## Solución Aplicada
1. **Repository Upgrade:** Generados métodos `findAll()` y `findById(int $id)` en `UsuarioRepository` asimilando el aislamiento requerido para el Administrador Global.
2. **Controller Bridge:** `UsuarioController` detecta al Master Admin, instancia el Repositorio de Empresas de forma paralela y envía el listado de `$empresas` a la capa gráfica V.
3. **Servicio Bypasseable (Logic):** `UsuarioService` incorpora cortocircuitos formales:
   - Si la flag máster `es_rxn_admin` es verdadera, el `POST` parameter `empresa_id` es leído y el usuario se inyecta en ese ID objetivo, sobrepasando el contexto heredado.
   - Si la flag es falsa (Tenant Local Admin), la API destruye automáticamente cualquier inyección fraudulenta del `<select>` y acopla la creación de forma ciega a la Empresa local.
4. **Front-End Dropdown:** Inserción del `<select name="empresa_id">` iterando la colección `$empresas` en los archivos `crear.php` y `editar.php`. Condicionado estrictamente a `$isGlobalAdmin === true`.

## Estructura de Relación
La tabla matricial `usuarios` ahora es manipulable transversalmente vía `empresa_id = YYYY`. 

## Impacto en Seguridad
La modificación no compromete la hermética del Multitenancy a nivel cliente. Un Inquilino estándar (empleado, admin de tenant) jamás verá el desplegable `Assigned Company` (ni en DOM ni en código fuente). En caso de forja de petición HTTP (Postman/cURL) inyectando `empresa_id`, el `UsuarioService` ignora paramétricamente esa llave aislando la información persistida. Las capacidades Cross-Tenant recaen **única y exclusivamente** bajo llave C-Level `es_rxn_admin`.
