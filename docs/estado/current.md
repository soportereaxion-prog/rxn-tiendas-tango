# ESTADO ACTUAL

## módulos tocados

* módulo: auth (Inyección de Banderas de Rol Perfiladas `requireRxnAdmin`)
* módulo: dashboard (Blindaje Visual frente a Operadores limpios)
* módulo: empresas (Backoffice protegido en el íntegro de su Controlador)

## decisiones

* Se segmentó el acceso en dos universos disociados valiéndose de un único switch: `es_rxn_admin`.
* El Backoffice central (Módulo Empresas originario) exige explícitamente bandera en Alta (1).
* El usuario `admin@empresa.test` se categorizó como Máster RXN.
* Se concibió a `operador@empresa.test` ($pwd:123) como molde raso sin privilegios para realizar End-to-End Tests.

## riesgos

* Ocultar botones no blinda endpoints API. Queda estrictamente estipulado aplicar `requireRxnAdmin()` u homólogos a nivel Controlador ante cada nueva adición al Sistema Master RXN frente a accesos directos por URI.
* Eventualmente, cuando el ABM de Backoffice pretenda gobernar Usuarios, deberá establecerse quién puede conceder perfiles `RXN`.

## próximo paso

* Avanzar sobre la granularidad de los Permisos `es_admin` que separan la Jerarquía Interna Operativa.
* Cimentar dependencias orgánicas multiempresa: Módulo Productos o Categorías.
