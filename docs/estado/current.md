# ESTADO ACTUAL

## módulos tocados

* módulo: core (Nombres de Autoloader alterados)
* módulo: **tango** (¡NUEVO E INAUGURADO!)
* módulo: infra (Capa nueva HTTP)

## decisiones

* Se escindió la lógica de Redes fuera de la lógica de Dominios. 
* El orquestador responsable de mezclar Contexto de Empresa con variables de Peticiones REST recaerá puramente en el `Service` de la entidad correspondiente (`TangoService`), nunca en el Controlador, logrando controladores livianos.

## riesgos

* **PELIGRO DEPLOY LINUX**: Composer ya advirtió un Classmap conflictivo por incompatibilidad de Case-Sensitive en las carpetas base de módulos (Auth != auth). Debe ser solventado vía `git mv` temporal antes del pase final a productivo Unix. (Ver Log Refactor 02-11 para detalles técnicos).

## próximo paso

* Consolidar el refactoring de carpetas / Rename en GIT para estandarizado PSR-4 cross-platform.
* Comenzar a levantar módulos reales con sus propios Controladores utilizando los nuevos Servicios Base de la Infraestructura.
