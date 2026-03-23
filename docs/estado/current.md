# ESTADO ACTUAL

## módulos tocados

* módulo: usuarios (NUEVO: Fase 1 ABM Operativo)
* módulo: auth (Repositorio ampliado)
* módulo: dashboard

## decisiones

* Se creó un ABM de usuarios en un módulo segregado (`App\Modules\Usuarios`) para preservar Single Responsibility en `Auth`.
* Todas las consultas de lectura y escritura (`UsuarioService`) se acoplaron de forma obligatoria de manera indisoluble al `Context::getEmpresaId()`.
* Se estableció la regla de **Email Único Global** aprovechando el esquema `UNIQUE` originario de MariaDB. Esto impide duplicidad de correos a lo largo de todo el ecosistema RXN.
* La edición de roles (`es_admin` / `activo`) y Contraseña quedan unificados en el ABM general.

## riesgos

* Hasta que no haya "Roles avanzados" refinados, todo Operador interno con `es_admin=1` tiene poder de edición y bloqueos ilimitados sobre el resto de su empresa.
* El Backoffice Central RXN por ahora carece de capacidad técnica y visual para gestionar usuarios de sub-empresas.

## próximo paso

* Elaborar un esquema de Control de Acceso por Módulo/Role específico, o iniciar entidades comerciales núcleo del entorno operativo (Stock, Ventas, etc).
