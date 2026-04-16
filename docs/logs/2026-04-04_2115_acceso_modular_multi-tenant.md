# Gestión de Acceso Modular Multi-Tenant y Reordenamiento UI

**Fecha:** 2026-04-04
**Build:** 20260404.10 / 1.1.57

## Qué se hizo
1. Se establecieron permisos modulares dentro de la base de datos de empresas introduciendo columnas lógicas para controlar accesos a submódulos (`crm_modulo_llamadas`, `crm_modulo_monitoreo`, y `modulo_rxn_live`).
2. Se sincronizó la interfaz de `crear.php` y `editar.php` en Empresa para que `Llamadas CRM`, `Monitoreo de Usuarios` correspondan jerárquicamente a `CRM`.
3. Se integró `RXN Live` como un módulo espejo y con sincronismo DOM puro a través de JS para existir visualmente tanto dentro de la familia `Tiendas` como `CRM`, respetando las condicionales combinadas de activación. 
4. Se conectaron las tarjetas del launcher de entornos operativos (`tenant_dashboard.php` y `crm_dashboard.php`) con los estados actuales del Tenant por medio de `EmpresaAccessService` para inyectar y retirar visualmente componentes a los cuales no se tenga acceso.
5. Se reparó el `EmpresaController` para no expulsar a los administradores a la vista general index tras ejecutar `update()` en el panel de configuración de empresas.

## Por qué
* Porque mantener los submódulos huérfanos sin control granular de base de datos abría riesgos de visualización de información a empresas o suscripciones no habilitadas.
* Porque la UI requería estandarización visual y de flujos atornillando los submódulos a su entidad padre (apagando un padre se apagan sus hijos).
* Porque la experiencia de usuario (UX) sufría "falsos positivos", donde elementos desactivados permanecían visibles en el dash.

## Impacto
* Todas las validaciones y lógicas aplican exclusivamente al panel de administrador rxnMaster y no tienen impacto directo o degradable sobre la sesión de las sucursales, a excepción de limpiar la pantalla quitando funcionalidades invisibles, elevando el valor percibido del UX.

## Decisiones Técnicas y de Seguridad
* Se decidió utilizar espejamiento unificado de atributo `name` nativo para resolver el caso "RXN LIVE". En lugar de registrar o mapear un input a un intermediador en Backend o bifurcar su lógica de base, el frontend se ocupa del espejeo visual y PHP recoge eficientemente el estado gracias al uso del `isset()`, resolviendo elegantemente el reto de los "interruptores combinados multifrecuencia".
* En base a la Política de Seguridad Base, se asegura el uso constante del `Context::getEmpresaId()` resguardado por las guardias granulares inyectadas sobre `routes.php`. Al no existir mutaciones de estado adicionales mediante peticiones GET se mantiene en check con los protocolos actuales y todo escape de salida de UI es seguro debido al uso de `htmlspecialchars()`. Todos estos cambios están aislados en el marco de la UI del Admin (`auth->requireRxnAdmin()`).
