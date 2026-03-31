# Fix estado Connect y buscadores en catalogos recuperados

## Que se hizo
- Se corrigio `app/modules/EmpresaConfig/views/index.php` para que el boton de Tango no quede clavado en `Resolviendo descripciones...` cuando la recarga posterior sucede sin validacion completa.
- Se agrego el mismo patron de busqueda con sugerencias locales para `Lista de Precio 1`, `Lista de Precio 2` y `Deposito`, manteniendo los `<select>` reales ocultos para el POST.
- Los buscadores nuevos se alimentan con la metadata ya recuperada desde Connect y reflejan el valor activo elegido en cada campo.

## Por que
- Tras la iteracion anterior, el estado visual del boton quedaba sucio al recargar catalogos por cambio de empresa porque se conservaba la clase `btn-success` y nunca se restauraba el HTML original del boton.
- Operativamente, cuando los catalogos traen muchos items, seguir con `<select>` nativos hacia lenta la seleccion aunque la metadata ya estuviera bien cargada.

## Impacto
- El estado visual del boton vuelve a ser coherente luego de cada recarga de metadata.
- Empresa, listas y deposito comparten ahora la misma experiencia de busqueda asistida en Configuracion.

## Decisiones tomadas
- Se mantuvo el enfoque local-first: los buscadores filtran sobre los catalogos ya cargados en la UI y no requieren endpoints nuevos.
