# ESTADO ACTUAL

## módulos tocados

* módulo: empresa_config (Nuevo: Fase 1 Contexto Multiempresa - Configuración activa)
* módulo: dashboard (Inyección de menú secundario)

## decisiones

* Se creó la tabla `empresa_config` (relación 1-1 con empresas a nivel conceptual, estricta UNIQUE SQL) para alojar settings del entorno.
* Se agregó el módulo `App\Modules\EmpresaConfig` incluyendo `EmpresaConfigService` que delega la recolección del ID hacia `App\Core\Context::getEmpresaId()`.
* **Fase 1 Contexto Operativo**: El servicio deniega operación si el Contexto no responde con un ID. Garantiza un guardado como método `UPSERT` por detrás, aislando configuraciones por empresa.
* El Menú Home ahora presenta ramas de ruteo visible: `Administración RXN` por un lado y `Entorno Operativo` por otro.

## riesgos

* Las futuras entidades (productos, pedidos) deben ahora diseñarse copiando el patrón de `EmpresaConfig`.
* Al crearse `empresa_config` bajo un MVC tan rígido, el método `store()` de los Controladores deberá manejar siempre un "Upsert" a través del Service si la entidad es de rango general único (configuración).

## próximo paso

* Avanzar a módulos operativos robustos dependientes de multitenancy (ej: Usuarios por Empresa, o ABM de Productos con isolation por empresa).
