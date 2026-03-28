# [UI] - Correccion de layout en login y auth compacto

## Que se hizo
- Se corrigio el contenedor reusable `rxn-auth-shell` para que apile sus bloques en columna y no distribuya titulo, alertas y tarjeta en fila.

## Por que
- El login habia quedado visualmente roto porque el shell de autenticacion estaba usando `display:flex` en direccion horizontal por defecto.

## Impacto
- Login, recuperacion, reset y reenvio de verificacion vuelven a mostrarse centrados, compactos y consistentes.

## Decisiones tomadas
- Se resolvio en la capa global para corregir todas las vistas auth que reutilizan el mismo shell, sin tocar logica ni markup adicional.
