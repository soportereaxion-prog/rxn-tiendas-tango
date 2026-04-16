# Control de Cambio Documental: MODULE_CONTEXT Batch (Core & Admin)
**Fecha:** 2026-04-09 16:05

## Resumen de la Intervención
Se generaron de manera estandarizada los documentos `MODULE_CONTEXT.md` para el bloque de módulos administrativos y de control base del sistema, garantizando que cada uno exponga claramente su alcance, piezas, dependencias, aislamientos y reglas operativas y de seguridad.

### Módulos Cubiertos
1. **Admin:** Operaciones globales, mantenimiento, RXN Admin. (Alto riesgo de alcance de BD).
2. **Dashboard:** Entradas y paneles principales por rol. (Riesgo visual y aislamiento estadístico).
3. **EmpresaConfig:** Configuraciones y comportamientos del negocio Tenant-side. (Riesgo lógico y escape de secretos/claves).
4. **Empresas:** Entidades dueñas de licencias y tenants. Exclusivo de superadmins.
5. **Help:** Centro de ayuda operativo. (Riesgo XSS dinámico).
6. **Usuarios:** ABM de personal y autogestión de credenciales. (Riesgo crítico de IDOR y escalada de privilegios).

## Políticas de Seguridad Explicitadas en la Documentación
Para cada uno de estos módulos, los `MODULE_CONTEXT.md` blindan (documentalmente) las directivas de la Política Base de Seguridad del Repositorio:

- **Aislamiento Multiempresa (`Context::getEmpresaId()`):** Crítico en `Usuarios`, `EmpresaConfig` y `Dashboard`. Menos relevante (pero aplicable por exclusión) en `Admin` y `Empresas` que son inter-tenants.
- **Permisos y Guards:** Control cruzado explícito. Se ha indicado cuándo usar `AuthService::requireRxnAdmin()` (ej: `Admin`, `Empresas`) versus `AuthService::requireLogin()` junto con control interno (ej: `Usuarios`, `Help`).
- **Prevención de Elevación de Privilegios:** Especial énfasis en `Usuarios` sobre las banderas de administración (`es_admin` y `es_rxn_admin`).
- **No Mutación por GET:** Normativa asentada como obligatoria en todas las directrices, prohibiendo alterar estados mediante la carga en la barra de direcciones.
- **Escape Seguro (XSS):** Obligatoriedad del uso preventivo de `htmlspecialchars` en la impresión de nombres, configuraciones o logs.
- **Protección CSRF y Server-side Validation:** Requiriendo blindaje activo frente a inyección SQL (uso de PDO y control de DDL), además de control sobre peticiones POST.

## Riesgos y Pendientes
- **Acatamiento Real:** El documento ahora establece la ley; sin embargo, no se auditaron en profundidad todos los controladores para certificar si el 100% de estas leyes de `MODULE_CONTEXT.md` se están cumpliendo sin fisuras en código (particularmente validaciones de IDOR en `UsuarioService`). Esto se deja asentado como riesgo técnico residual.
- **Usuarios:** La auditoría posterior de Lumi confirmó que `UsuarioController::requireAdmin()` hoy sólo delega a `AuthService::requireLogin()`. La frontera real de privilegios vive en `UsuarioService` y en las banderas de UI; por eso el `MODULE_CONTEXT` fue afinado para no sobredeclarar guards inexistentes.
