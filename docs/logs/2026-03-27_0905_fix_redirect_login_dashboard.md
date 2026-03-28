# [Auth] - Ajuste de redirección post login al launcher operativo

## Que se hizo
- Se corrigió la redirección del módulo de autenticación para que, al detectar sesión activa o completar login válido, envíe al usuario a `/mi-empresa/dashboard`.

## Por que
- El flujo visual vigente define que el ingreso operativo debe aterrizar primero en el launcher del entorno operativo y no directamente en la configuración de empresa.

## Impacto
- Los usuarios autenticados vuelven a entrar al panel operativo esperado.
- La pantalla de configuración sigue disponible como módulo interno desde el dashboard.

## Decisiones tomadas
- Se aplicó el cambio solo en `app/modules/Auth/AuthController.php` para mantener la solución mínima y consistente con la navegación ya implementada.
