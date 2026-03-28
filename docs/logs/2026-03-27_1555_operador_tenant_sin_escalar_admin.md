# [Usuarios] - Operador tenant sin escalado de privilegios

## Que se hizo
- Se mantuvo el acceso del usuario operador al modulo `Administrar Cuentas` dentro de su tenant.
- Se oculto la opcion `Privilegios de administrador` para quienes no pueden otorgar ese rol.
- En backend se ignora cualquier intento de enviar `es_admin` si el usuario actual no tiene capacidad para gestionar privilegios administrativos.

## Por que
- El operador de una empresa necesita poder crear y editar usuarios de su propio tenant.
- Pero no debe tener capacidad visual ni tecnica para escalar cuentas al rol de administrador.

## Impacto
- El operador puede seguir administrando cuentas de su empresa.
- No puede promover usuarios a admin ni siquiera manipulando el formulario.
- El aislamiento por `empresa_id` sigue intacto.

## Decisiones tomadas
- Se resolvio con doble control: ocultamiento en vistas + validacion server-side en `UsuarioService`.
