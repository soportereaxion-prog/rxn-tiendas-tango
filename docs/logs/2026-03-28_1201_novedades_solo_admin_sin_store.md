# Novedades solo para administradores y fuera del store

## Que se hizo
- Se agrego `AuthService::hasAdminPrivileges()` para centralizar la deteccion de administradores tenant o RXN.
- Se restringio el bloque `Novedades` en `app/modules/dashboard/views/home.php`, `app/modules/dashboard/views/admin_dashboard.php` y `app/modules/dashboard/views/tenant_dashboard.php` para que solo renderice cuando el usuario tenga privilegios administrativos.
- Se elimino completamente el bloque `Novedades` de `app/modules/Store/views/layout.php`, dejando el footer publico sin referencia de releases internas.
- Se actualizo `docs/estado/current.md` para reflejar que el versionado visible ya no se expone en la tienda publica.

## Por que
- Las novedades internas son contexto operativo y no deben quedar visibles para perfiles comunes ni para clientes del frente de tienda.
- La regla pedida fue clara: administradores si, store no.

## Impacto
- Los administradores siguen viendo la release activa en launcher y dashboards internos.
- Los usuarios no administradores dejan de ver el bloque `Novedades` aunque ingresen al launcher o al entorno operativo.
- El sitio publico vuelve a quedar limpio de referencias internas de versionado.

## Decisiones tomadas
- Se reutilizo una validacion de privilegios centralizada en Auth en lugar de repetir condicionales de sesion dispersos.
- No se altero `app/config/version.php`; solo se ajusto la visibilidad de consumo.
