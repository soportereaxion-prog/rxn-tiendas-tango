# Auditoria bug selectores por empresa Connect

## Que se investigo
- Se audito la documentacion y el flujo de `Configuracion de la Empresa` en Tiendas y CRM para validar el comportamiento esperado cuando cambia `ID de Empresa (Connect)`.
- Se revisaron `app/modules/EmpresaConfig/views/index.php`, `app/modules/EmpresaConfig/EmpresaConfigController.php`, `app/modules/EmpresaConfig/EmpresaConfigService.php` y las rutas de ambos entornos.

## Hallazgo principal
- El entendimiento funcional queda confirmado: al cambiar la empresa Connect, la pantalla deberia volver a poblar `Lista de Precio 1`, `Lista de Precio 2` y `Deposito` antes de permitir un guardado confiable.
- Hoy ese comportamiento no existe en el codigo compartido. La vista solo carga metadata al presionar `Validar Conexion` y despues no escucha cambios del selector `tango_connect_company_id`.
- El guardado persiste directamente lo que venga en `POST`, sin forzar recarga ni revalidacion de esos tres selectores contra la empresa recientemente elegida.

## Evidencia encontrada
- `docs/logs/2026-03-26_1156_connect_selectores.md` documenta que el boton `Validar Conexion` es el disparador que llena los selectores dinamicos.
- `docs/logs/2026-03-26_2059_selector_id_empresa_connect.md` documenta que el selector de empresa se incorporo al mismo flujo, pero no define una cascada obligatoria al cambiar de empresa.
- `docs/architecture.md` refuerza el patron `local-first` y que Connect se consulta por accion explicita, pero tampoco documenta un refresh dependiente al cambiar la empresa.
- En `app/modules/EmpresaConfig/views/index.php` existe logica para poblar selects y marcar valores originales, pero no hay `change` listener sobre `tango_connect_company_id`.
- En `app/modules/EmpresaConfig/EmpresaConfigController.php` y `app/modules/EmpresaConfig/EmpresaConfigService.php` el backend acepta y guarda `tango_connect_company_id`, `lista_precio_1`, `lista_precio_2` y `deposito_codigo` tal como llegan, sin controlar que pertenezcan a la empresa Connect elegida en ese momento.

## Alcance del problema
- Afecta Tiendas y CRM porque ambos entornos reutilizan `EmpresaConfigController`, `EmpresaConfigService` y `app/modules/EmpresaConfig/views/index.php`; solo cambia el contexto/ruta (`/mi-empresa/configuracion` y `/mi-empresa/crm/configuracion`).
- Esto encaja con la sospecha de regresion transversal: no es un bug aislado de CRM ni de Tiendas, sino del modulo compartido de configuracion.

## Estado de documentacion
- El flujo actual de `Validar Conexion -> cargar metadata` si esta documentado.
- El requerimiento operativo mas estricto de `cambiar empresa -> refrescar listas/deposito antes de guardar` no aparece documentado como regla explicita.
- Tampoco se encontro una bitacora previa que deje asentado este comportamiento como bug conocido o regresion abierta.

## Decisiones tomadas
- Se deja documentado el desfasaje entre comportamiento esperado y comportamiento implementado.
- No se cambia logica funcional en esta iteracion; solo se registra la auditoria y el alcance real del bug para su correccion posterior.
