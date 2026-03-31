# Fix cascada empresa Connect en Configuracion

## Que se hizo
- Se ajusto `app/modules/EmpresaConfig/views/index.php` para que al cambiar `ID de Empresa (Connect)` se recarguen automaticamente `Lista de Precio 1`, `Lista de Precio 2` y `Deposito` con la metadata de esa empresa.
- Se agrego una marca oculta `tango_metadata_company_id` para dejar trazado desde que empresa Connect se resolvieron realmente los selectores antes del guardado.
- Se reforzo `app/modules/EmpresaConfig/EmpresaConfigController.php` para bloquear guardados cuando la empresa Connect cambia pero los selectores dependientes no fueron recalculados o contienen valores ajenos a esa empresa.
- Se reutilizo el mismo ajuste para Tiendas y CRM, ya que ambos entornos comparten el modulo `EmpresaConfig`.

## Por que
- Existia una brecha operativa: el selector de empresa podia cambiar, pero las listas y el deposito quedaban con valores viejos o sin refrescar.
- Eso permitia guardar combinaciones inconsistentes entre empresa Connect y parametros comerciales dependientes.

## Impacto
- La UI ahora refresca en cascada los selectores dependientes apenas cambia la empresa Connect.
- Si el operador intenta guardar una empresa distinta sin haber refrescado esos selectores, el backend rechaza el guardado con un mensaje claro.
- Tiendas y CRM quedan alineados porque usan la misma vista, rutas y controlador base para configuracion.

## Decisiones tomadas
- Se mantuvo el enfoque simple sobre el modulo existente, sin crear endpoints nuevos ni duplicar logica para CRM.
- La validacion estricta backend solo se activa cuando cambia la empresa Connect, evitando endurecer guardados comunes de configuracion no relacionados con este flujo.
