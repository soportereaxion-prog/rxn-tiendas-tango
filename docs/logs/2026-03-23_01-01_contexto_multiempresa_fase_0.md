# Base Contexto Multiempresa — Fase 0

## Contexto
El sistema es multiempresa desde su concepción, pero a nivel código operaba todo globalmente. Empresas se usa como panel de control central (Backoffice RXN) para administrarlas. Se requiere separar este backoffice de los hipotéticos frontends de cada empresa ("contexto de empresa"). 

## Problema
Faltaba una forma estándar, accesible y no destructiva de determinar **"en qué empresa estoy operando"** a nivel código, para que los modelos venideros (productos, permisos, etc.) sepan de manera nativa dónde guardar o leer información.

## Solución Propuesta y Diseño
Se diseñó el patrón "Context Object" mediante una clase estática sencilla `App\Core\Context`. Esta clase se inyecta en el arranque del microframework (`App::run()`) para inicializarse.
Brinda un getter universal `Context::getEmpresaId()` que no acopla a la capa de base de datos ni a frameworks pesados.
Temporalmente recaba la información analizando `$_GET['empresa_id']` o cayendo en `1` por defecto, permitiendo que el desarrollo del sistema avance simulando contextos mediante la URL hasta que exista el panel de Login real.

## Impacto en Arquitectura
* **Casi Nulo en Código Previo**: Backoffice Empresas sigue funcionando idéntico porque no se le exigió filtrar por `empresa_id`.
* **Guía a Futuro**: Para nuevas entidades, se requerirá incluir la columna `empresa_id INT` en BD (FK), y en los Repositories usar `Context::getEmpresaId()` para limitar las sub-consultas (ej. `WHERE empresa_id = ...`).

## Riesgos
* Al no usar Login o Sesión todavía, la lectura por `$_GET` es insegura en un entorno de paso a producción; debe ser reemplazada estrictamente antes de desplegar nada real.

## Validaciones
* Test CLI manual llamando a `Context::init()` en un stack limpio comprobó la toma de variables correctas.
* Arquitectura intacta. Ningún warning o fatal emitido post inyección.
