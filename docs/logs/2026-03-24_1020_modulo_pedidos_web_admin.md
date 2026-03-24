# Módulo Admin — Pedidos Web

## Contexto
El checkout y la integración Tango ya están en la base de datos de manera estable (transaccionalmente aislados por Empresa y trazados exhaustivamente por Estado, Cabecera, Renglones, Logs). 
A los administradores del licenciatario les faltaba la posibilidad de visualizar los pedidos desde el panel central.

## Problema
Se requería brindar "Visibilidad Operativa" real (Lectura de Pedidos, Detalle, Estado API externa) para auditar ventas, sin desarrollar un ERP completo, reusando el scope mínimo y componentes UI crudos y livianos en el actual Dashboard de \`rxnTiendasIA\`.

## Decisión
Se decidió implementar:
1. Vista Index (con grilla y buscador) para Pedidos Web.
2. Vista Show (detalle aislado por tarjetas) exponiendo al Cliente, Dirección, Observaciones y Payload Técnico Tango.
3. Actualización de capa de consultas (Repositorios) utilizando un Join prefabricado a \`clientes_web\`.

## Archivos afectados
- \`app/config/routes.php\` (Agregado de GET routes del Admin).
- \`app/modules/Dashboard/views/home.php\` (Inyección del botón "Pedidos Web" en panel operativo central).
- \`app/modules/Pedidos/PedidoWebRepository.php\` (Inyección de los métodos \`findAllPaginated\`, \`countAll\`, \`findByIdWithDetails\`).
- \`app/modules/Pedidos/Controllers/PedidoWebController.php\` (Nuevo componente orquestador).
- \`app/modules/Pedidos/views/index.php\` (Listado grilla paginable y con sorter/filtros).
- \`app/modules/Pedidos/views/show.php\` (Master detail read-only UI).

## Implementación
Se codificó utilizando MySQL PDO de forma aséptica y usando \`json_decode\` inline en las vistas para preformatear los logs de los payloads de Integration API de Tango en un bloque \`<pre>\` agradable.
No se instalaron dependencias ni rediseñó arquitectura. 100% Bootstrap 5 nativo.

## Impacto
Visibilidad inmediata de la operación para todos los vendedores licenciatarios con nivel \`es_rxn_admin=0\`, pero respetando \`empresa_id\`. 

## Riesgos
- Cero riesgo de quiebre en tienda ya que las rutas son completamente disyuntas (\`/mi-empresa/*\` vs \`/{slug}/*\`).
- Riesgo de lectura cruzada mitigado por la asignación dura de la cláusula \`p.empresa_id = :empresa_id\` desde la inyección de la sesión (\`Context::getEmpresaId()\`).

## Validación
- Comprobación de que cada Query contenga \`:empresa_id\`.
- Comprobación de existencia de Controller, View path.
- Inclusión bajo \`AuthService::requireLogin()\`.
- Compilación del bloque JSON de payload para tolerar nulos/vacíos sin romper.

## Notas
Queda para futuras iteraciones habilitar la capacidad transaccional de "Reenviar a Tango process" o "Someter corrección de payload" desde el Admin.
