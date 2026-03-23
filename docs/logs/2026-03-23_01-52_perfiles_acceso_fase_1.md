# [Perfiles Activos] — [Fase 1: Muro entre Backoffice y Operativos]

## Contexto
Teníamos autenticación andando de manera horizontal, pero RXN es un ecosistema de dos capas: El Panel Administrativo Central de Licencias (Módulo Empresas) y el Sistema interno de operación propio de cada empresa (Pedidos, Empleados, Configuración). Teníamos una vulnerabilidad visual en la cual un operador cualquiera veía el acceso al Backoffice absoluto.

## Problema
Carecíamos de una distinción de credenciales. La entidad universal "Usuario" requería de un identificador para separar a un Administrador "In-House" de un Administrador "Master RXN".

## Implementación de Roles Mínimos
- **Migración DB**: Se insertó `es_rxn_admin (TINYINT 1)` a la tabla de `usuarios`.
- **Session Layer**: El `AuthService::attempt()` se encarga de acoplar en la `$_SESSION` dinámica el estado extraído desde SQL (`es_rxn_admin = 0 | 1`).
- **Bloqueo Visual**: La interfaz `app/modules/dashboard/views/home.php` somete el Render (código HTML) de la Card de "Gestión de Módulos (Backoffice)" atándolo al condicional superior de Sesión. Operadores ciegos, RXN Hosts iluminados.
- **Middlewares / Guards**: Se escribió `AuthService::requireRxnAdmin()` combinando la verificación estándar `requireLogin()` adicionando una negativa taxativa HTTP 403 (Forbidden Access) si la bandera de Sesión del Master RXN es inexistente. Esta barrera se enlazó sobre el 100% de los Action-Methods pertenecientes al core controller `App\Modules\Empresas\EmpresaController`.

## Archivos Afectados
- `app/modules/Auth/Usuario.php` (Extendida variable base).
- `app/modules/Auth/AuthService.php` (Attempt expandido + Nuevo Guard).
- `app/modules/Empresas/EmpresaController.php` (Blindaje x5 methods).
- `app/modules/dashboard/views/home.php` (IF Condicional Render UI).
- `Base de Datos` (Alter Table + Update Seeders).

## Pruebas de Stress
* Logueo con Empleado (`operador@empresa.test`): Genera correctamente la sesión `es_rxn_admin = 0`. 
* Logueo con Global (`admin@empresa.test`): Retiene su valor `1` y le son abiertos los Guards sin excepción.

## Impacto a Futuro
* La administración de "Nuevos Usuarios" del panel central debe proveer Checkboxes para activar roles RXN (Omito esto aquí ya que el ABM global no soporta usuarios todavía, si no Licencias/Empresas. Queda documentado para inyectarse cuando el panel RXN permita ver usuarios generales).

## Próximos pasos
1. Completar robustez de edición inter-rol (Administrador de una empresa decidiendo quién es admin subyacente).
2. Crear módulos core operacionales dependientes del flujo multiempresa.
