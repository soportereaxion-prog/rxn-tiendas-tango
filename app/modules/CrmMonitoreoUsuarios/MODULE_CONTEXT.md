# MODULE_CONTEXT — CrmMonitoreoUsuarios

## Nivel de criticidad
BAJO. Es un módulo visual y de dashboard para supervisión de estado del equipo CRM. No posee flujos transaccionales o de persistencia propios.

## Propósito
Proveer una vista general del equipo de operadores, vendedores y administradores (usuarios del CRM) de la empresa, mostrando sus avatares, roles, perfiles de Tango vinculados y extensiones telefónicas (Anura).

## Alcance
**QUÉ HACE:**
- Muestra el listado completo de usuarios vinculados a la empresa sin paginación, presentándolos en formato tarjeta/dashboard.
- Calcula y renderiza avatares con colores y letras iniciales basados en el nombre.
- Diferencia roles visualmente (Soporte RXN, Administrador de Empresa, Operador/Vendedor).
- Resalta al usuario activo actualmente (`is_current_user`).

**QUÉ NO HACE:**
- No permite crear, editar o borrar usuarios. La gestión de usuarios ocurre en el módulo base `Usuarios`.
- No muta el estado de vinculación de perfiles de Tango ni extensiones de telefonía.

## Piezas principales
- **Controladores:** `CrmMonitoreoUsuariosController`.
- **Servicios/Repositorios inyectados:** `App\Modules\Usuarios\UsuarioService`, `App\Modules\Auth\UsuarioRepository`.
- **Vistas:** `views/index.php`.
- **Rutas/Pantallas:** `/mi-empresa/crm/monitoreo-usuarios`.
- **Tablas/Persistencia:** Consulta directa a tablas globales de usuarios y relaciones de empresa. (No tiene tabla propia).

## Seguridad Base (Política de Implementación)
- **Aislamiento Multiempresa**: OBLIGATORIO. El repositorio filtra mediante `findAllByEmpresaId(Context::getEmpresaId())` a menos que sea un superadmin del sistema (`es_rxn_admin`).
- **Permisos / Guards**: Validado mediante `AuthService::requireLogin()`. 
- **Admin Sistema vs Tenant**: El controlador reconoce el flag `es_rxn_admin == 1`. Si un superadmin ingresa, ve a todos los usuarios globalmente; si no, está restringido al tenant actual.
- **Mutación**: No aplica. Es un módulo de lectura estricta (Solo peticiones GET).
- **Validación Server-Side**: Acceso a variables de sesión seguro.
- **Escape Seguro (XSS)**: Los nombres y perfiles listados en las tarjetas deben imprimirse escapados para evitar inyección.
- **Acceso Local**: Sujeto a la sesión del CRM local.

## Dependencias directas
- El módulo de Autenticación (`AuthService`, `UsuarioRepository`).
- El módulo global de Usuarios (`UsuarioService`).

## Dependencias indirectas / impacto lateral
- Un cambio en la estructura de `usuarios` o en cómo se determinan los perfiles de Tango y Anura impactará esta pantalla.

## Reglas operativas del módulo
- El listado busca mostrar la totalidad del equipo, por lo tanto omite intencionalmente la paginación estándar que usa `UsuarioService`, invocando en su lugar al repositorio o limitando en un límite alto (`1000`).
- **Búsqueda client-side, sin persistencia global**: el input de búsqueda de este módulo (`data-search-input` + `rxn-searchable-item`) filtra las tarjetas en el DOM vía JS local (`data-search-content`). NO submite ni mueve query string, por lo que la persistencia global de filtros (`rxn-filter-persistence.js`) no aplica acá — y está bien que no aplique: el estado "qué operador busco" es efímero y se reinicia en cada visita.

## Tipo de cambios permitidos
- Agregar métricas adicionales en vivo (si está online, total de presupuestos cargados en el día).
- Mejorar el diseño visual de las tarjetas o agregar filtros de búsqueda locales vía JS.

## Tipo de cambios sensibles
- Introducir mutaciones de usuarios en esta vista, rompiendo su responsabilidad de solo-lectura y pisando la funcionalidad del módulo nativo de Usuarios.
- Remover el control `es_rxn_admin` que permite diagnosticar instancias desde la visión de RXN.

## Riesgos conocidos
- Carga visual excesiva si la empresa crece a cientos de usuarios, debido al límite hardcodeado para anular la paginación. En ese escenario, requerirá una refactorización de frontend.

## Checklist post-cambio
- [ ] La pantalla de monitoreo levanta sin errores.
- [ ] Muestra únicamente a los miembros de la empresa (a menos que el superadmin esté logueado).
- [ ] Los identificadores de Anura y TangoPerfil renderizan correctamente sin romper la maqueta.
