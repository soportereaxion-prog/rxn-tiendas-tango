# [Ayuda] - Centro de ayuda operativa inicial

## Que se hizo
- Se incorporo una vista de ayuda para el entorno operativo accesible en nueva pestaña.
- Se agrego un enlace visual discreto de `Ayuda` en el dashboard operativo y en los modulos principales de uso diario.
- Se creo documentacion base en lenguaje simple para explicar que hace cada modulo y como funcionan las busquedas.

## Por que
- El entorno operativo necesitaba una referencia clara para usuarios administradores sin depender de explicaciones tecnicas o asistencia externa cada vez.
- La documentacion tambien queda preparada para crecer cuando se sumen modulos nuevos.

## Impacto
- Los administradores de empresa ahora pueden consultar una guia simple desde el propio sistema.
- Se establece una base documental para futuras ayudas del CRM u otros modulos operativos.

## Decisiones tomadas
- Se limito esta primera version solo al entorno operativo.
- Se mantuvo una implementacion liviana: una vista server-rendered + un archivo documental fuente en `docs/`.
