# Corrección de Estado "En línea" en Monitoreo de CRM

## Qué se hizo
- Se corrigió la lógica visual del indicador de estado (el punto verde parpadeante) en las tarjetas de usuarios del dashboard de "Monitoreo de Operadores".
- En `CrmMonitoreoUsuariosController.php` se inyectó la propiedad `is_current_user` comprobando el ID de sesión.
- En la vista `index.php` del módulo, se reservó la clase CSS `active` (pulso verde de "En línea") **únicamente** para el usuario actualmente logueado.

## Por qué
- La vista original estaba utilizando la propiedad de la base de datos `activo` (que indica si la cuenta está habilitada o suspendida en el sistema ABM) para renderizar el pulso verde.
- Esto provocaba una pésima experiencia de usuario, porque los clientes interpretaban el punto verde como "El usuario está online en este momento", dando la impresión errónea de que toda la nómina de operadores estaba trabajando y conectada simultáneamente.

## Impacto
- Al no contar aún con una arquitectura de *Presence* o control de sesiones concurrentes a nivel sistema, la solución óptima es marcar como verde exclusivamente al usuario actual (que sabemos fehacientemente que está online).
- El resto de los usuarios con cuentas habilitadas retienen un punto gris oscuro (`inactive`), y las cuentas suspendidas asumen una opacidad reducida para no destacar.
