# Artículos — Corrección Sync Stock, Buscador y Orden

## Contexto
Iteración sobre el módulo Artículos para dar cierre definitivo a la sincronización de Stock de Tango Connect, además de reparar el buscador roto por un error PDO y agregar capacidades de ordenamiento en la grilla.

## Problema
1. El proceso de Sincronización de Stock reportaba 0 Actualizados.
2. Al buscar en el catálogo, la aplicación explotaba con `SQLSTATE[HY093]: Invalid parameter number`.
3. La grilla principal carecía de funcionalidad de orden iterativo ascendente y descendente que combinara con filtros y paginación.

## Decisión
Se auditaron las capas (Controller, Repository, SyncService).
Se comprobó interactuando de forma directa con la base y el Connect vía PHP que:
- El `ID_STA22` filtraba correctamente si el usuario configuraba el ID real del depósito.
- MySQL devolvía `rowCount() = 0` cuando un update idéntico (mismo saldo) ocurría. Esto engañaba al condicional local y reportaba `sin_match`, asustando a los operadores.  Adicionalmente se comprobó que conjuntos paginados de Artículos y Stocks iniciales diferían, lo cual limitaba los overlaps de las primeras transferencias. 
- En el buscador, se repetía mal el parámetro `:search` por limitaciones de variables nominales en PDO.

## Archivos afectados
- `app/modules/Articulos/ArticuloRepository.php`
- `app/modules/Articulos/ArticuloController.php`
- `app/modules/Articulos/views/index.php`

## Implementación
- Se introdujo `fecha_ultima_sync = NOW()` a los `UPDATE` parciales de Stock y Lista de Precios en el Repository, para forzar un `rowCount() >= 1` devolviendo certidumbre real al contador de *Actualizados*.
- Se resolvió el `HY093` reemplazando los placeholders por `:search1`, `:search2` y `:search3`.
- Se integró una serie de links en los `<th>` con variables dinámicas `sort` y `dir` preservadas por la vista, la query string de paginado, el Controller y embebidas por Whitelist en PDO.

## Impacto
- El sync_stock refleja verdaderamente lo que procesa (se inyectó el SKU puro de Tango '01050    BCO090' para forzar una lectura con el proceso 17668 en dev test, logrando el update exacto del registro local al saldo `-41.00`).
- El catálogo vuelve a ser robustamente navegable sin quiebres.

## Riesgos
Ninguno residual importante. Ahora cada que se corran peticiones idénticas de Sync, se renovará la métrica `fecha_ultima_sync` sin mutar artificialmente el stock contable, lo que mantiene el estado fresco.

## Validación
- Comprobación manual PDO de conectividad vía sql y php script a `EmpreB`.
- Parseo binario exacto de espacios (15 char length) del Process 17668 `COD_ARTICULO` verificado.
- Verificación del Order By inyectado sin vulnerabilidades SQL Injection.
