# ESTADO ACTUAL

## módulos tocados

* módulo: core (Nombres de Autoloader alterados)
* módulo: **tango** (¡NUEVO E INAUGURADO!)
* módulo: infra (Capa nueva HTTP)

## decisiones

* Se escindió la lógica de Redes fuera de la lógica de Dominios. 
* El orquestador responsable de mezclar Contexto de Empresa con variables de Peticiones REST recaerá puramente en el `Service` de la entidad correspondiente (`TangoService`), nunca en el Controlador, logrando controladores livianos.
* Se instaló política de composición en lugar de Herencia para el HTTP Client. Las reglas de infra emplearán excepciones semánticas (ConfigurationException, ConnectionException, etc.).

## riesgos

* **PELIGRO DEPLOY LINUX**: Composer ya advirtió un Classmap conflictivo por incompatibilidad de Case-Sensitive en las carpetas base de módulos (Auth != auth). Debe ser solventado vía `git mv` temporal antes del pase final a productivo Unix. (Ver Log Refactor 02-11 para detalles técnicos).

## próximo paso

* Comenzar a levantar módulos reales y Modelos de Entidades (Productos/Pedidos) valiéndose de este canal de conexión con Axeft.
