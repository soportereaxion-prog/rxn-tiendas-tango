# [CONFIGURACIÓN] — Resolución de Colisión de Rutas URI

## Causa Raíz
Gracias a la evidencia forense recopilada por la sonda `ApiClient` HTTP Dump, se constató que la URL preconfigurada en DB era la canónica Axoft `https://000357-017.connect.axoft.com/Api`.
Al invocar a los Mappers (proceso 984 y 2941), `TangoApiClient` estaba forjando el endpoint con la nomenclatura `/Api/Get`.
Esto resultaba en una recombinación fatal dictada por el constructor interno:
`https://000357-017.connect.axoft.com/Api/Api/Get`

Milagrosamente este endpoint de Axoft respondía `200 OK` silente en lugar de un `404 Not Found`, lo cual arrojaba falsos positivos en los chequeos de conexión iniciales, pero naturalmente acompañaba de un `data: null`, rompiendo la trazabilidad del catálogo de Listas y Depósitos.

## Corrección
1. Se refactorizaron estrictamente `testConnection`, `getMaestroListasPrecio` y `getMaestroDepositos` dentro de `TangoApiClient.php`.
2. Ahora llaman limpios al segmento final: `Get`.
3. El `ApiClient.php` núcleo realiza un string concatenation que arroja ineludiblemente `https://.../Api/Get?process=...` lo cual cuadra quirúrgicamente con el contrato probado manualmente en Postman por Jefatura.

Las bases de integración Tango Connect de todo el framework están ahora completamente sincronizadas mediante un pathing algorítmico libre de duplicaciones, paginado y ruteado correctamente al nodo `resultData.list`.
