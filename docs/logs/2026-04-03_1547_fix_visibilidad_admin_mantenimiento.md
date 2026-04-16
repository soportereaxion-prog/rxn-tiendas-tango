# Corrección de Visibilidad del Módulo de Mantenimiento

**Fecha**: 2026-04-03
**Módulos Afectados**: `AuthService`

## Causa real del problema
Al introducir la tarjeta de "Mantenimiento y Actualizaciones" en la vista de `EmpresaConfig`, se utilizó como blindaje la función `AuthService::isRxnAdmin()`, la cual fue codificada comprobando estrictamente que el valor `$_SESSION['es_rxn_admin']` fuera igual a `1`.

Si bien esto es correcto teóricamente, detectamos al auditar el sistema que el usuario fundamental o principal administrador (típicamente el que realiza el despliegue y maneja la empresa 1) no siempre posee ese flag instanciado en 1 (ya sea por herencia de bases de datos antiguas o porque la propia lógica antigua lo asumía omitiendo el campo extra). 

De hecho, en `UsuarioRepository::deleteById`, la lógica de protección del sistema define al "administrador global del sistema" como aquel usuario donde `es_admin = 1` AND `empresa_id = 1`.

Debido a que este usuario (el instalador/principal) no contaba físicamente con el flag `es_rxn_admin = 1`, reprobaba `isRxnAdmin()` y la tarjeta de despliegue no le aparecía en la consola.

## Ajuste realizado
Se procedió a refactorizar la directiva lógica `isRxnAdmin()` en `/app/modules/Auth/AuthService.php` y a su consumidor `requireRxnAdmin()`.
La aserción fue expandida mediante un fallback que valida correctamente ambos paradigmas del sistema, resultando el siguiente condicional:

```php
$hasFlag = !empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1;
$isPrimaryAdmin = !empty($_SESSION['es_admin']) && $_SESSION['es_admin'] == 1 &&
                  !empty($_SESSION['empresa_id']) && $_SESSION['empresa_id'] == 1;

return $hasFlag || $isPrimaryAdmin;
```

## Criterio Final de Visibilidad
El acceso a mantenimiento continuará estando aislado de los operadores y administradores de inquilinos comunes (`empresa_id > 1`), cumpliendo con la regla de negocio. Ahora responderá correctamente para:
1. Cualquier usuario que tenga explícitamente el flag `es_rxn_admin = 1`.
2. El administrador principal (raíz) propietario del tenant inicial (`empresa_id = 1` y `es_admin = 1`).
