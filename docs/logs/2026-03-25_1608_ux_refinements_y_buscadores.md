# [UX/DB] — Refinamientos Navigacionales, Iconografía y Bugfix Búsquedas Clientes

## Contexto
Tras implementar la arquitectura de 2 niveles (Launcher -> Entornos), surgieron observaciones menores en el flujo de usuario y un fallo crítico arrastrado desde iteraciones previas en uno de los buscadores de la Base de Datos.

## Problemas
- **(DB) Crash del buscador de Clientes:** Al intentar buscar clientes, la PDO devolvía una excepción bloqueante. Esto ocurría porque la conexión a MySQL instanciada tiene `PDO::ATTR_EMULATE_PREPARES => false`. La query de `ClienteWebRepository` reutilizaba el mismo alias `:s` en múltiples cláusulas `LIKE` (`nombre LIKE :s OR email LIKE :s`), lo cual es violentamente rechazado por los Prepared Statements Nativos de MySQL.
- **(UI) Botones Volver en CRUDs:** Las vistas internas (Artículos, Clientes, Pedidos) intentaban retroceder a la antigua vista monolítica `/` en vez de ir al Entorno Operativo `/mi-empresa/dashboard`.
- **(UI) Iconografía e Interfaz:** Emojis disonantes con el estándar corporativo establecido previamente. Botón heredado de SMTP Master presente en vistas redundantes.

## Implementación
1. **Repository Patch:** Se mutaron las consultas `countAll` y `findAllPaginated` de `ClienteWebRepository.php` hacia el estándar ordenado de indexación PDO (`:s1`, `:s2`, `:s3`, `:s4`, `:s5`).
2. **Bootstrap Icons:** Inyectado el CDN oficial de Bootsrap Icons sobre `home.php`, `admin_dashboard.php` y `tenant_dashboard.php`. Reemplazo semántico exitoso (`bi-buildings`, `bi-gear`, `bi-people`).
3. **Botón Volver:** Refactorizado masivo de etiquetas `<a>` en las cabeceras UI de los módulos operativos, inyectando `<i class="bi bi-arrow-left"></i>` y repuntando los `HREF`.
4. **Remoción de Duplicidad:** Destruido el anchor redundante del SMTP en listado general de Empresas.

## Impacto
El sistema mantiene estabilidad total eliminando Excepciones Fatales en base de datos. Se garantiza el "look and feel" B2B buscado y la retención del usuario dentro del Nivel Operativo 2 evitando "salidas prematuras" a la raíz.
