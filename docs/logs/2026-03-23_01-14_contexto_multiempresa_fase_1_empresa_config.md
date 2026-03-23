# Multiempresa Contexto — Fase 1 (Empresa Config)

## Contexto
El sistema cuenta con un generador base de contexto (`App\Core\Context`) inyectado al inicio. El objetivo de la iteración es materializar ese contexto armando una entidad hija de la empresa (dependiente), demostrando que se puede leer/escribir información aislada usando únicamente el helper de contexto en la capa profunda, sin interferir con el Backoffice superior.

## Problema
Carecíamos de una arquitectura de prueba de aislamiento y una primera pantalla tipo "Mi Entorno". Las empresas operaban pero no guardaban datos propios dependientes de ellas, ni existía un flujo que no requiera mandar el `empresa_id` por `POST`.

## Solución Propuesta y Diseño
Se construyó `empresa_config` con una columna estricta `UNIQUE empresa_id`.
La lógica MVC `EmpresaConfig` lee estrictamente desde `$this->getContextId()` que emite `Context::getEmpresaId()`. Si el ID es nulo, arroja una Execpción impidiendo tocar base de datos.
El método `save()` interroga iterándose. Si el Repository detecta un registro de ese `$empresaId`, emite un SQL `UPDATE`. Si no existe, invoca inyección nativa del SQL `INSERT`.
Esto asegura que la URI `POST /mi-empresa/configuracion` funcione para todo el ciclo de vida sin necesitar parámetros.

## Archivos afectados
- `app/modules/EmpresaConfig/EmpresaConfig.php`
- `app/modules/EmpresaConfig/EmpresaConfigRepository.php`
- `app/modules/EmpresaConfig/EmpresaConfigService.php`
- `app/modules/EmpresaConfig/EmpresaConfigController.php`
- `app/modules/EmpresaConfig/views/index.php`
- `app/config/routes.php`
- `app/modules/dashboard/views/home.php`

## Validaciones
* Test CLI puro: Modificamos el Contexto A y escribimos datos. Cambiamos el puntero global hacia el Contexto B y leímos (llegó NULO/limpio como se esperaba). Insertamos datos en B, volvio a resurgir como objeto local. Isolation supervivida sin sangrado.

## Riesgos y Consideraciones
* El Autoloader de PSR-4 es estricto en Linux y requiere ser ordenado en Windows también, debiendo crear el path `App/Modules/EmpresaConfig` con **CamelCase** preciso. Dejar `empresa_config` como minusculas rompería el deploy final.
* Resta definir quién inyectará de manera definitiva en un futuro el `empresa_id` a la variable de Contexto Global, asumiendo será el Middleware de Login.
