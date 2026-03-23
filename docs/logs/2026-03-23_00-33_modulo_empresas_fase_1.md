# Modulo Empresas — Fase 1 (Alta y Listado)

## Contexto
El sistema nace multiempresa desde el inicio. La entidad raíz del modelo será EMPRESAS y sobre ella colgarán futuras entidades controladas por `empresa_id`. En esta iteración se requiere crear la primera base funcional usando MariaDB local (puerto 3307).

## Problema
No existía la DB local `rxn_tiendas_core` ni el módulo de código correspondiente a "empresas", ni un entry-point limpio para navegarlos.

## Decisión
* Se creó la DB `rxn_tiendas_core` y la tabla `empresas` en la instancia MariaDB local 127.0.0.1:3307.
* Se crearon los archivos nativos respetando MVC simplificado exigido por `AGENTS.md`: Entity, Repository, Service, y Controller.
* Se inyectaron rutas simples con array estático `[EmpresaController::class, 'index']`.

## Archivos afectados
- `test_db.php` y `test_module.php` (tests temporales creados localmente para asegurar tests CLI reales).
- `app/config/routes.php` (añadidas rutas `/empresas`, `/empresas/crear` y POST).
- `app/modules/dashboard/views/home.php` (Menú mínimo de landing page).
- `app/modules/empresas/Empresa.php`
- `app/modules/empresas/EmpresaRepository.php`
- `app/modules/empresas/EmpresaService.php`
- `app/modules/empresas/EmpresaController.php`
- `app/modules/empresas/views/index.php`
- `app/modules/empresas/views/crear.php`

## Implementación
Alta conectada a la base de datos de manera directa y Listado renderizando una vista de Bootstrap.
Flujo validado: Un GET a crear retorna vista, el submit a un POST graba en DB, y redirecciona al Listado donde el nuevo código impacta visualmente en la tabla.

## Impacto
El sistema ya cuenta con el módulo base principal operando funcionalmente. Se puede generar tantas empresas como se solicite desde el navegador o servicios programáticos.

## Riesgos
- El ruteo por URI depende de que `BASE_PATH` en Request mantenga `'/rxnTiendasIA/public'` estático en todos los entornos locales.
- No contiene edición, ni desactivación todavía.

## Validación
- Se corrió test de conexión MariaDB local (XAMPP/RXNAPP).
- Se corrió test de crear entidad a nivel código mediante `$service->create(...)`.
- Salida final registrada y exitosa.

## Notas
No olvidar remover los scripts `.php` de validación temporal (`test_db.php`, `test_module.php`) si se decide limpiar el repo, aunque dado el historial no interfieren.
