# MODULE_CONTEXT: Dashboard

## Propósito
Proveer las vistas principales, resúmenes estadísticos y accesos rápidos (landing pages) que un usuario visualiza al ingresar al sistema, condicionado por su rol (`RXN Admin`, `Tenant Admin`, Usuario CRM, etc.).

## Alcance
- Vistas de bienvenida y portales de entrada.
- Diferenciación visual y operativa de paneles (`Admin` vs `Tenant` vs `CRM`).
- Integración de componentes transversales (como el buscador unificado `dashboard_search.php`).

## Piezas Principales
- **Controladores:**
  - `DashboardController` (o su equivalente lógico encargado de enrutar la *home*).
- **Vistas (`views/`):**
  - `admin_dashboard.php`: Panel de super administradores.
  - `tenant_dashboard.php`: Panel de administración de una empresa/cliente específico.
  - `crm_dashboard.php`: Panel para operadores o comerciales.
  - `home.php` / `index.php`: Vistas de testeo y entrada por defecto.

## Rutas y Pantallas
- Generalmente accesible desde `/`, `/home` o `/dashboard`.
- Renderiza condicionalmente las diferentes vistas según el rol del usuario autenticado.

## Persistencia
- Típicamente no tiene persistencia propia (solo lectura). Extrae y consolida datos de otros módulos (Usuarios, Empresas, CRM) para mostrar resúmenes.

## Dependencias e Integraciones
- Módulos núcleo para mostrar resúmenes estadísticos.
- `AuthService` para derivar al panel correcto.
- Componente `dashboard_search.php` (Buscador unificado) que debe estar presente en paneles basados en tarjetas según política de diseño.

## Reglas Operativas y Seguridad (Política Base)
- **Aislamiento Multiempresa:** El dashboard de un *Tenant* debe mostrar estrictamente datos y conteos pertenecientes a su `empresa_id`.
- **Permisos (Guards):** Redirigir correctamente o bloquear si el rol no coincide con el panel solicitado (ej. un usuario normal intentando ver `admin_dashboard`). No puede asumirse que ocultar un botón basta.
- **Mutación y GET:** Al ser un panel resumen, es de naturaleza GET. No deben ejecutarse cambios de estado desde la carga del dashboard.
- **Validación y Escape:** Todo dato resumido (nombres de empresas, notas, alertas) debe ser escapado preventivamente (XSS) mediante `htmlspecialchars`.

## Riesgos y Sensibilidad
- Mostrar accidentalmente la tarjeta o conteos de otra empresa debido a un mal filtrado del `empresa_id`.
- Riesgo visual: saturar el dashboard de "leyendas" o bloques de texto fijos (va contra las Reglas de Diseño).

## Checklist Post-Cambio
1. Validar que la vista de Dashboard no filtre datos entre empresas (comprobar en base de datos si la query considera `Context::getEmpresaId()`).
2. Comprobar que no haya leyendas fijas agregadas recientemente.
3. Asegurar que los componentes interactivos respondan correctamente a XSS y tengan escape adecuado.
4. Confirmar funcionamiento de los atajos y el buscador unificado (`F3` / `/`).
