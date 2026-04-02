# Validaciones de Interno Anura en Usuarios

## Qué se hizo
1. **Unicidad de Interno (Tenant-based)**: Se agregó la regla y la comprobación para asegurar que el `anura_interno` de un usuario no puede repetirse dentro de la misma `empresa_id`.
2. **Propagación en Sesión Activa**: Al crear o actualizar un usuario, si es el perfil del operador actual editándolo, inyectamos `$usuario->anura_interno` también dentro de `$_SESSION['anura_interno']` para evitar que quede desfasado. Lo mismo ocurre desde el inicio de sesión.
3. **PDS de Llamadas - Validación Real**: El JS de `CrmLlamadas/views/index.php` ahora evalúa `anura_interno` utilizando una lectura fresca (`AuthService::getCurrentUser()`) en lugar de depender de la matriz inexistente `$_SESSION['usuario']` (que originó el fallo reportado cuando la sesión contenía un caché vacío en la vista de llamadas).

## Por qué
Para afianzar la arquitectura multi-tenancy evitando que dos operadores reclamen colateralmente las mismas llamadas de Anura. Se reportó y resolvió que el control inicial de JS estaba comparando el interno agente con un valor nulo debido a una definición errónea previa de persistencia de sesión.

## Impacto
El sistema recobra la robustez evitando derivar llamadas inter-usuarios al tiempo que expone un error semántico apropiado `RuntimeException` desde `validateAnuraInterno()` si el administrador intenta duplicar una asignación de teléfonos, blindando la integridad de asignación de extensiones comerciales.
