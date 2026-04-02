# Log de Modificación - Separación de Contexto Operativo en Empresas

## Qué se hizo
- Se agregó el botón explicito "Ingresar" (`bi-box-arrow-in-right`) en la vista del listado general de Gestión de Empresas.
- Se introdujo una nueva ruta POST `/empresas/{id}/ingresar` apuntando a un nuevo método en `EmpresaController`.
- El método `ingresar` actualiza explícitamente el `$_SESSION['empresa_id']` tras validar el permiso del `BackofficeAdmin`.

## Por qué
Los administradores del sistema (como Tenant 1 / Master Admin) tienen permiso global para ver el módulo de `Gestión de Empresas` y editar los metadatos de cualquier empresa en la base de datos a través de la ruta `/empresas/{id}/editar`. Sin embargo, esto causaba confusión operativa porque los repositorios internos (como Usuarios, Artículos, etc.) siempre filtran mediante la protección multi-tenant de `$_SESSION['empresa_id']`. Al editar el "cascarón" (la metadata principal), el entorno operativo no cambiaba de tenant principal a tenant destino, y el admin continuaba operando bajo el contexto de `suite reaxion` u otra empresa inicial.

## Impacto
- El Administrador Global / Backoffice tiene ahora dos flujos separados y claros.
- Lápiz (clic en la fila, Gold Standard): Edita el ABM general (metadata) de la empresa y NADA MÁS.
- Ingresar (botón principal explícito): Cambia la sesión actual inyectando al admin global en el contexto de un tenant elegido.
- No se introdujo nuevo manejo de JWTs o RBAC complejo, se mantuvo la simplicidad escalable.

## Decisiones tomadas
- Se mantuvo `data-row-link` ligado al Lápiz ("Editar") de forma intacta para preservar el standard funcional de todos los CRUDs del sistema ("al hacer clic en la fila se va a la edición superficial").
- Se requirió confirmación manual JS `data-msg` sobre este nuevo botón para evitar el switcheo no intencional por misclic de administrador que pudiera desembocar en que agregue stock o cambie un usuario en la empresa vecina.
