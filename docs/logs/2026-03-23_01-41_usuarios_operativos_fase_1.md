# [Usuarios Operativos] — [Fase 1 Multiempresa ABM]

## Contexto
El entorno ya reconocía qué empresa estaba activa en sesión. Era momento de dotar a las empresas de la capacidad de sumar empleados a su propio entorno operativo.

## Problema
Se requería un sistema de ABM (Alta, Baja, Modificación) donde los operadores de una empresa no logren de ninguna manera interactuar o visualizar datos de otras licencias en la plataforma multitenant.

## Auditoría y Decisión
- **Reutilización**: Decidimos operar sobre la tabla original `usuarios` y el repositorio del módulo `Auth` pero segregando el Control a la carpeta `App\Modules\Usuarios\` para separar permisos de Logins vs Administración.
- **Unicidad**: El email será único a nivel GLOBAL en todo el sistema. Requisito pactado para escalar sin conflicto de duplicación si a futuro RXN implementa federación o logins cruzados.
- **Seguridad Cruzada**: Todo impacto que apunte a `ID` inyecta en el repositorio en forma de Query `$id AND empresa_id = $context`. Si no matchea, se tumba el Request con un Error 403 vía Controller catch.

## Archivos Afectados
- `app/modules/Usuarios/UsuarioService.php` (Nuevo)
- `app/modules/Usuarios/UsuarioController.php` (Nuevo)
- `app/modules/Usuarios/views/` (index, crear, editar)
- `app/modules/Auth/UsuarioRepository.php` (Extendiendo `findByEmail`, `findAllByEmpresaId` y Editores de Write dependientes de Empresa).
- `app/config/routes.php` (Las 5 rutas GET/POST del ABM).
- `app/modules/dashboard/views/home.php` (Shortcut).

## Pruebas
Se crearon en base de datos 2 empresas. La "Empresa 2" se dotó con una víctima. Utilizando el ID de la víctima mediante una sesión local simulada para la "Empresa 1", se constató en consola que un update a ese ID es anulado forzosamente debido al chequeo interno antes del query SQL. Idéntica validación para la lectura global.

## Resultado
Sistema ABM desplegado. Listo para uso con front UI.

## Riesgos detectados
La contraseña se exige en "Crear", no se exige en "Editar" (salvo tipeo). Pero no se contempló límite de auto-edición (Un admin puede apagar su propio switch de activo si no presta atención).
