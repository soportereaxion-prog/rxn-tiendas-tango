# [Tango Connect] — [Alineación Sync Artículos a Endpoint Real]

## Contexto
El flujo original de Sync utilizaba conceptualmente un Endpoint errante (`GetByFilter?process=Articulos`) bajo presunción de integraciones estandartizadas previas y fallaba devolviendo Redirecciones Web 302 que desencadenaban contingencias Mock. Analizada la estructura real de Connect, se descubrió el desfasaje y se ejecutó un recableado contundente sobre los parámetros exactos dictados desde instancias superiores.

## Readecuación del Cliente y Parámetros
* Se reemplazó el uso de `GetByFilter` y se estableció `GET /Api/Get`
* Se inyectaron dinámicamente sobre la Query String los parámetros mandatorios extraídos en laboratorio:
  * `process=87` (En lugar del literal abstracto 'Articulos').
  * `pageSize=...` (En lugar de 'limit').
  * `pageIndex=...` (En lugar de 'page').
* La Paginación se protegió respetando la firma interfacil preexistente (`page=1`) pero matemáticamente transformada de forma silenciosa para el HTTP Request como `pageIndex = 0`. El sistema tolerante permite fluidez sin reescribir la Base Controladora.

## Estructura de Decodificación del Request
* Frente a estructuras envolventes misteriosas dictadas por la paginación C# subyacente de Axoft, reescribimos `TangoSyncService::syncArticulos()` con un extractor elástico capaz de resolver las recámaras `resultData.list`, `Data`, o arrays planos.
* El Mapper `ArticuloMapper` fue severamente reescrito abandonando el idioma agnóstico (Name, Description) y abrazando las llaves exactas nativas de Tango para el process 87 (`COD_STA11` para Códigos Externos, `DESCRIPCIO` para Nombre, `SINONIMO` para la Descripción opcional y `ACTIVO`/`HABILITADO` para el flag de vigencia).
* Las entidades de Base de Datos fueron reforzadas y tipadas rígidamente (Floats para precio y Smallint para Activo).

## Pruebas Realizadas y Cierre
* Los vestigios de información estática (Mocks `ART-001` previas) fueron arrasados explícitos mediante Script CLI `DELETE FROM articulos...`.
* Al no poseer el equipo Backend la Sub-Llave Completa para acceder a la terminal remota de Tango, la garantía del sistema depende ahora plenamente de la arquitectura configurada, la cual aguarda el `Hit` interactivo desde el usuario del Administrador en Pantalla `mi-empresa/sync/articulos` con la Base URL correcta de Connect inyectada en su Menú de Configuración para recolectar, al momento, el número genuino de Entradas Sincronizadas directas en Grilla.
* La solución preservó al 100% los Módulos Ui y Orquestaciones de RXN Originales sin introducir deuda técnica.
