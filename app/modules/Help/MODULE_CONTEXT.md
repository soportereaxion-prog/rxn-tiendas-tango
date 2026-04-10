# MODULE_CONTEXT: Help

## Propósito
Proveer un centro de ayuda, documentación de uso y asistencia técnica para el usuario final, contextualizado según el área operativa donde se encuentre trabajando.

## Alcance
- Renderización de la vista dinámica de ayuda.
- Integración contextual con el `OperationalAreaService` para mostrar contenido pertinente al módulo que el usuario está consultando (ej. si está en CRM, mostrar ayuda de CRM).
- Pantalla accesible para todo usuario con cuenta activa (`AuthService::requireLogin()`).

## Piezas Principales
- **Controlador:** `HelpController.php`.
- **Vistas (`views/`):**
  - `operational_help.php`: Contiene todo el texto explicativo de la plataforma, componentes tabulares, de atajos de teclado y funcionamiento del ecosistema.

## Rutas y Pantallas
- `/help/operational`: (GET) Renderiza la interfaz principal del centro de ayuda.

## Persistencia
- **No posee.** El contenido suele ser estático o inyectado vía base de código (`markdown` parseado, o HTML incrustado en PHP).

## Dependencias e Integraciones
- `App\Shared\Services\OperationalAreaService`: Fundamental para determinar el `$area`, el `$dashboardPath` y `$environmentLabel` para adecuar el contenido de la ayuda.
- `AuthService`: Solamente restringe su uso a personal logueado.

## Reglas Operativas y Seguridad (Política Base)
- **Aislamiento Multiempresa:** No es altamente sensible dado que es un módulo estático, sin embargo, **no debe revelar funcionalidades operativas de otros módulos a los que el Tenant o rol no tiene acceso**.
- **Permisos (Guards):** Uso simple de `AuthService::requireLogin()`. No requiere discriminación de roles salvo que la propia vista oculte secciones bajo un `if(isAdmin())`.
- **Mutación y GET:** Módulo puramente de lectura (GET). Bajo ninguna circunstancia este módulo ejecutará acciones o mutaciones de estado en base de datos.
- **Escape Seguro:** Evitar el uso de variables directas desde `$_GET` para imprimir en pantalla (ej. parámetros de búsqueda en la ayuda) sin utilizar `htmlspecialchars()`.

## Riesgos y Sensibilidad
- Modificar textos o ejemplos sin revisar podría derivar en que los usuarios utilicen mal la aplicación, induciendo a errores operativos ("Documentación obsoleta").
- Riesgo de XSS si se inyectan parámetros como `?area=<script>...` que son rebotados a la vista sin escape correcto por el `OperationalAreaService` o la vista misma.

## Checklist Post-Cambio
1. Verificar que no se hayan introducido consultas de base de datos pesadas (el módulo de ayuda debe cargar muy rápido).
2. Asegurar que las variables pasadas a la vista, provenientes de la ruta, estén apropiadamente sanitizadas.
3. Constatar que no existan botones "activos" de pruebas de código dentro del HTML que disparen endpoints del sistema.
4. Validar visualmente el aspecto responsivo y la legibilidad general del documento.
