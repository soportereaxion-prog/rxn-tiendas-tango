# Versionado interno y novedades visibles

## Que se hizo
- Se creo `app/config/version.php` como fuente unica de verdad para la release actual y sus novedades.
- Se agrego `app/shared/services/VersionService.php` para leer, normalizar y formatear version, build, fecha y highlights sin meter logica dispersa en las vistas.
- Se actualizo `app/modules/dashboard/views/home.php`, `app/modules/dashboard/views/admin_dashboard.php` y `app/modules/dashboard/views/tenant_dashboard.php` para mostrar un bloque `Novedades` con la misma release activa.
- Se amplio `app/modules/Store/views/layout.php` con una columna `Novedades` en el footer publico para exponer la version vigente y los cambios destacados de forma visible en el sitio.
- Se actualizo `docs/estado/current.md` para dejar reflejada la nueva pieza transversal de versionado.

## Por que
- El proyecto ya venia creciendo con muchos cambios trazados en `docs/logs`, pero no tenia una referencia corta y consistente de version activa para operadores ni para el frente publico.
- La necesidad era resolverlo sin base de datos nueva ni sobrearquitectura: una configuracion central + un servicio liviano bastan en esta etapa.

## Impacto
- La version activa queda centralizada y lista para evolucionar release a release editando un solo archivo.
- Backoffice y tienda publica muestran la misma narrativa de cambios, reduciendo desalineaciones entre lo interno y lo visible en el sitio.
- Futuras iteraciones pueden sumar historial agregando nuevas entradas en `app/config/version.php` y su log correspondiente.

## Decisiones tomadas
- Se uso un archivo de configuracion PHP en vez de BD para mantener simplicidad y despliegue inmediato.
- Se expone al sitio solo un resumen curado de novedades, dejando el detalle tecnico completo en `docs/logs`.
- La UI elegida fue un bloque fijo `Novedades`: en dashboards como tarjeta de contexto y en el store como seccion del footer para no interrumpir el flujo comercial.
